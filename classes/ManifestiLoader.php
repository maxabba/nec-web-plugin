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
        private $current_author_id;
        private $author_offset;

        public function __construct($post_id, $offset, $tipo_manifesto, $limit = 20)
        {
            $this->post_id = $post_id;
            $this->offset = $offset;
            $this->tipo_manifesto = $tipo_manifesto;
            $this->limit = $limit;
            $this->original_author_id = get_post_field('post_author', $post_id);
            $this->current_author_id = null;
            $this->author_offset = 0;
        }

        /**
         * Set author-specific pagination parameters
         */
        public function set_author_pagination($author_id, $author_offset)
        {
            $this->current_author_id = $author_id;
            $this->author_offset = $author_offset;
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

            $pagination_info['offset'] = $this->offset + $query->post_count;
            $pagination_info['is_finished_current_author'] = false; // Not relevant for 'top' type


            return [
                'manifesti' => $this->process_query_results($query),
                'pagination' => $pagination_info
            ];
        }

        private function load_grouped_manifesti()
        {
            $author_order = $this->get_ordered_authors();
            
            // Use author-specific pagination if provided
            if ($this->current_author_id !== null) {
                $current_author_data = [
                    'author_id' => $this->current_author_id,
                    'offset' => $this->author_offset
                ];
            } else {
                $current_author_data = $this->calculate_current_author($author_order);
            }

            if (!$current_author_data) {
                $pagination_info['offset'] = -1; // No more content
                $pagination_info['is_finished_current_author'] = true;
                return $this->build_response_with_meta([], $pagination_info);
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

            $is_finished = false;
            //if post count is less than limit, we are at the end of this author's posts
            if ($query->post_count < $this->limit) {
                $is_finished = true;
            }

            // Process query results first
            $results = $this->process_query_results($query);
            
            // Calculate pagination metadata
            $total_author_posts = $this->get_author_total_posts($current_author_data['author_id']);
            $current_position = $current_author_data['offset'] + $query->post_count;
            $next_author_info = null;
            
            // Check if there's a next author for pagination info, but don't add divider here
            if (!empty($results)) {
                if ($current_position >= $total_author_posts) {
                    $next_author_info = $this->get_next_author_info($author_order, $current_author_data['author_id']);
                    // Remove the ending divider - it will be added at the beginning of next author's batch
                }
            }

            $manifesti = $results;

            // Calculate next pagination info
/*            $pagination_info = $this->calculate_next_pagination_info(
                $current_author_data,
                $author_order,
                $total_author_posts,
                $current_position,
                $next_author_info
            );*/



            $pagination_info['offset'] = $this->offset + $query->post_count;
            $pagination_info['is_finished_current_author'] = $is_finished;
            return $this->build_response_with_meta($manifesti, $pagination_info);
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

            // Get ALL authors who have manifestos for this annuncio with the specific tipo_manifesto
            $all_authors_query = new WP_Query([
                'post_type' => 'manifesto',
                'fields' => 'id=>post_author',
                'posts_per_page' => -1,
                'meta_query' => $this->get_meta_query(),
                'author__not_in' => [1] // Exclude only admin user (ID 1)
            ]);

            $all_authors = array_unique(wp_list_pluck($all_authors_query->posts, 'post_author'));
            
            // Filter out authors with zero posts (consistency check)
            $valid_authors = array_filter($all_authors, function($author_id) {
                $count = count(get_posts([
                    'post_type' => 'manifesto',
                    'post_status' => 'publish',
                    'author' => $author_id,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'meta_query' => $this->get_meta_query()
                ]));
                return $count > 0;
            });
            
            // Separate original author from others, only if they have valid posts
            $other_authors = array_filter($valid_authors, function($author_id) {
                return $author_id != $this->original_author_id;
            });

            // Ordina gli altri autori in modo deterministico basato sul seed
            usort($other_authors, function ($a, $b) use ($seed) {
                return (($a * $seed) % 100) - (($b * $seed) % 100);
            });

            // Only include original author if they have posts for this tipo_manifesto
            $ordered_authors = [];
            if (in_array($this->original_author_id, $valid_authors)) {
                $ordered_authors[] = $this->original_author_id;
            }
            
            return array_merge($ordered_authors, $other_authors);
        }

        private function calculate_current_author($author_order)
        {
            $current_batch_start = 0;

            foreach ($author_order as $index => $author_id) {
                $author_posts_count = count(get_posts([
                    'post_type' => 'manifesto',
                    'author' => $author_id,
                    'post_status' => 'publish',
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

                    if (!(get_field("testo_manifesto")))
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

        /**
         * Get information about the next author in sequence
         */
        private function get_next_author_info($author_order, $current_author_id)
        {
            $current_index = array_search($current_author_id, $author_order);
            
            if ($current_index !== false && isset($author_order[$current_index + 1])) {
                return [
                    'author_id' => $author_order[$current_index + 1],
                    'author_offset' => 0
                ];
            }
            
            return null;
        }

        /**
         * Calculate next pagination information for JS
         */
        private function calculate_next_pagination_info($current_author_data, $author_order, $total_author_posts, $current_position, $next_author_info)
        {
            // If we haven't reached the end of current author's posts
            if ($current_position < $total_author_posts) {
                return [
                    'has_more' => true,
                    'current_author_id' => $current_author_data['author_id'],
                    'author_offset' => $current_position,
                    'next_author' => null
                ];
            }
            
            // If we're at the end of current author but there's a next author
            if ($next_author_info) {
                return [
                    'has_more' => true,
                    'current_author_id' => $next_author_info['author_id'],
                    'author_offset' => 0,
                    'next_author' => $next_author_info
                ];
            }
            
            // No more content available
            return [
                'has_more' => false,
                'current_author_id' => null,
                'author_offset' => 0,
                'next_author' => null
            ];
        }

        /**
         * Build response with pagination metadata for JS tracking
         */
        private function build_response_with_meta($manifesti, $pagination_info)
        {
            if ($this->tipo_manifesto === 'top' || $this->current_author_id !== null) {
                // For 'top' type or when using author-specific pagination, return manifesti with metadata
                return [
                    'manifesti' => $manifesti,
                    'pagination' => $pagination_info
                ];
            }
            
            // Fallback for backward compatibility
            return [
                'manifesti' => $manifesti,
                'pagination' => $pagination_info
            ];
        }
    }
}