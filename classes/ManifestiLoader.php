<?php

namespace Dokan_Mods;

use WP_Query;
use Dokan_Mods\UtilsAMClass;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\ManifestiLoader')) {

    class ManifestiLoader
    {
        private $post_id;
        private $offset;
        private $tipo_manifesto;
        private $limit;
        private $original_author_id;

        public function __construct($post_id, $offset, $tipo_manifesto, $limit = 20)
        {
            $this->post_id = $post_id;
            $this->offset = $offset;
            $this->tipo_manifesto = $tipo_manifesto;
            $this->limit = $limit;
            $this->original_author_id = get_post_field('post_author', $post_id);
        }

        public function load_manifesti()
        {
            if ($this->tipo_manifesto === 'top') {
                return $this->load_top_manifesti();
            }
            return $this->load_grouped_manifesti();
        }

        /**
         * Load manifesti for monitor display (all types, no pagination)
         */
        public function load_manifesti_for_monitor()
        {
            $query = new WP_Query([
                'post_type' => 'manifesto',
                'posts_per_page' => -1, // Load all manifesti
                'orderby' => 'date',
                'order' => 'DESC',
                'post_status' => 'publish',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'annuncio_di_morte_relativo',
                        'value' => $this->post_id,
                        'compare' => '='
                    ],
                    [
                        'key' => 'tipo_manifesto',
                        'value' => ['top', 'silver', 'online'],
                        'compare' => 'IN'
                    ]
                ]
            ]);

            return $this->process_monitor_results($query);
        }

        /**
         * Process query results for monitor display
         */
        private function process_monitor_results($query)
        {
            $response = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $response[] = $this->render_manifesto_for_monitor();
                }
            }

            wp_reset_postdata();
            return $response;
        }

        /**
         * Render single manifesto for monitor display
         */
        private function render_manifesto_for_monitor()
        {
            $current_post_id = get_the_ID();
            $vendor_id = get_the_author_meta('ID');
            $vendor_data = (new UtilsAMClass())->get_vendor_data_by_id($vendor_id);
            $testo_manifesto = get_field('testo_manifesto');
            $tipo_manifesto = get_field('tipo_manifesto');

            // Clean up the manifesto text for display
            $clean_text = $this->clean_manifesto_text($testo_manifesto);
            
            // Build HTML structure compatible with manifesto.js rendering
            $html = '<div class="manifesto-wrapper" data-post-id="' . $current_post_id . '">';
            $html .= '<div class="text-editor-background">';
            $html .= '<div class="custom-text-editor">';
            $html .= $clean_text;
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            return [
                'id' => $current_post_id,
                'html' => $html,
                'tipo' => $tipo_manifesto,
                'vendor_data' => $vendor_data,
                'date' => get_the_date('c') // ISO format for JS
            ];
        }

        /**
         * Clean manifesto text for monitor display
         */
        private function clean_manifesto_text($text)
        {
            if (empty($text)) {
                return '<p>Testo manifesto non disponibile</p>';
            }

            // Remove any script tags for security
            $text = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $text);
            
            // Ensure text is properly formatted
            $text = wpautop($text);
            
            // Add basic styling classes
            $text = str_replace('<p>', '<p class="manifesto-paragraph">', $text);
            
            return $text;
        }

        private function get_meta_query()
        {
            $compare = '=';
            $value = $this->tipo_manifesto;

            if (str_contains($this->tipo_manifesto, ',')) {
                $value = explode(',', $this->tipo_manifesto);
                $compare = 'IN';
            }

            return [
                'relation' => 'AND',
                [
                    'key' => 'annuncio_di_morte_relativo',
                    'value' => $this->post_id,
                    'compare' => '='
                ],
                [
                    'key' => 'tipo_manifesto',
                    'value' => $value,
                    'compare' => $compare
                ]
            ];
        }

        private function load_top_manifesti()
        {
            $query = new WP_Query([
                'post_type' => 'manifesto',
                'post_status' => 'publish',
                'posts_per_page' => $this->limit,
                'offset' => $this->offset,
                'orderby' => 'date',
                'order' => 'ASC',
                'meta_query' => $this->get_meta_query()
            ]);

            return $this->process_query_results($query);
        }

        private function load_grouped_manifesti()
        {
            $author_order = $this->get_ordered_authors();
            $current_author_data = $this->calculate_current_author($author_order);

            if (!$current_author_data) {
                return [];
            }

            $query = new WP_Query([
                'post_type' => 'manifesto',
                'post_status' => 'publish',
                'posts_per_page' => $this->limit,
                'offset' => $current_author_data['offset'],
                'author' => $current_author_data['author_id'],
                'orderby' => 'date',
                'order' => 'ASC',
                'meta_query' => $this->get_meta_query()
            ]);

            $response = [];

            // Se non è il primo autore e stiamo caricando i suoi primi post
            if ($current_author_data['author_id'] !== $this->original_author_id &&
                $current_author_data['offset'] === 0) {
                $response[] = $this->get_divider();
            }

            // Se siamo all'ultimo post di questo autore e ci sono altri autori dopo
            $total_author_posts = $this->get_author_total_posts($current_author_data['author_id']);
            $current_position = $current_author_data['offset'] + $query->post_count;

            if ($current_position >= $total_author_posts) {
                $next_author_exists = $this->check_next_author_exists($author_order, $current_author_data['author_id']);
                if ($next_author_exists) {
                    $results = $this->process_query_results($query);
                    $results[] = $this->get_divider();
                    return array_merge($response, $results);
                }
            }

            return array_merge($response, $this->process_query_results($query));
        }


        private function get_author_total_posts($author_id)
        {
            return count(get_posts([
                'post_type' => 'manifesto',
                'author' => $author_id,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => $this->get_meta_query()
            ]));
        }

        private function check_next_author_exists($author_order, $current_author_id)
        {
            $current_index = array_search($current_author_id, $author_order);
            return isset($author_order[$current_index + 1]);
        }

        private function get_divider()
        {
            ob_start();
            ?>
            <div class="col-12" style="width: 90%;">
                <hr class="manifesto_divider" style="margin: 30px 0;">
            </div>
            <?php
            return [
                'html' => ob_get_clean(),
                'vendor_data' => null
            ];
        }

        private function get_ordered_authors()
        {
            // Usa l'ID del post come seed per mantenere lo stesso ordine casuale
            $seed = $this->post_id;

            $other_authors_query = new WP_Query([
                'post_type' => 'manifesto',
                'fields' => 'id=>post_author',
                'posts_per_page' => -1,
                'meta_query' => $this->get_meta_query(),
                'author__not_in' => [$this->original_author_id, 1]
            ]);

            $other_authors = array_unique(wp_list_pluck($other_authors_query->posts, 'post_author'));

            // Ordina gli altri autori in modo deterministico basato sul seed
            usort($other_authors, function ($a, $b) use ($seed) {
                return (($a * $seed) % 100) - (($b * $seed) % 100);
            });

            return array_merge([$this->original_author_id], $other_authors);
        }

        private function calculate_current_author($author_order)
        {
            $current_batch_start = 0;

            foreach ($author_order as $index => $author_id) {
                $author_posts_count = count(get_posts([
                    'post_type' => 'manifesto',
                    'author' => $author_id,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'meta_query' => $this->get_meta_query()
                ]));

                if ($current_batch_start + $author_posts_count > $this->offset) {
                    return [
                        'author_id' => $author_id,
                        'offset' => $this->offset - $current_batch_start
                    ];
                }

                $current_batch_start += $author_posts_count;
            }

            return null;
        }


        private function process_query_results($query)
        {
            $response = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();

                    if (!get_field('immagine_manifesto_old') && !(get_field("testo_manifesto")))
                    {
                        continue; // Skip this manifesto if both fields are empty
                    }
                    $response[] = $this->render_manifesto();
                }
            }

            wp_reset_postdata();
            return $response;
        }

        private function render_manifesto()
        {
            $current_post_id = get_the_ID();
            $vendor_id = get_the_author_meta('ID');

            $vendor_data = (new UtilsAMClass())->get_vendor_data_by_id($vendor_id);

            if(get_field('immagine_manifesto_old') && !(get_field("testo_manifesto")))
            {
                $vendor_data['manifesto_background'] = "https://necrologi.sciame.it/necrologi/".get_field('immagine_manifesto_old');
            }

            ob_start();
            ?>
            <div class="flex-item" style="margin-bottom: 25px;">
                <div class="text-editor-background" style="background-image: none"
                     data-postid="<?php echo $current_post_id; ?>"
                     data-vendorid="<?php echo $vendor_id; ?>">
                    <div class="custom-text-editor">
                        <?php the_field('testo_manifesto'); ?>
                    </div>
                </div>
            </div>
            <?php
            return [
                'html' => ob_get_clean(),
                'vendor_data' => $vendor_data
            ];
        }
    }
}