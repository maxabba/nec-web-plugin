<?php
namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . 'Templates_MiscClass')) {
    class Templates_MiscClass
    {

        public function __construct()
        {
        }

        public function check_dokan_can_and_message_login(): void
        {
            if (!current_user_can('dokan_view_product_menu')) {
                $myaccount_page_id = get_option('woocommerce_myaccount_page_id');
                if ($myaccount_page_id) {
                    $myaccount_url = get_permalink($myaccount_page_id);
                    $message = __('Non hai i permessi per visualizzare questa pagina. ', 'dokan-mod');
                    $message .= '<a href="' . esc_url($myaccount_url) . '">' . __('Login', 'dokan-mod') . '</a>';
                    echo $message;
                    exit;
                } else {
                    //redirect to home page
                    wp_redirect(home_url());
                    exit;
                }
            }else if(!current_user_can('sell_manifesto') && !current_user_can('sell_fiori')){
                //get the dokan dashboard url
                $dashboard_url = dokan_get_navigation_url('dashboard');
                wp_redirect($dashboard_url);
            }
        }

        public function schedule_post_and_update_status($post_id)
        {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_status']) && isset($_POST['post_date']) && isset($_POST['post_id'])) {
                $post_status = sanitize_text_field($_POST['post_status']);
                $post_id = intval($_POST['post_id']);
                $post_date = sanitize_text_field($_POST['post_date']);

                // Update the post status and scheduled date
                $post_data = array(
                    'ID' => $post_id,
                    'post_status' => $post_status,
                );

                if (!empty($post_date)) {
                    $post_data['post_date'] = $post_date;
                }

                wp_update_post($post_data);
            }


            $post_title = get_the_title($post_id);
            ob_start();
            ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Programma/Cambia Stato: <?php echo $post_title ?></h5>

                    <p class="card-text">Programma la pubblicazione o cambia lo stato del post</p>
                    <form id="post-schedule-form" method="POST" action="">
                        <input type="hidden" name="post_id" value="<?php echo $post_id ?>">
                        <label for="post_status">Stato del Post:</label>
                        <select id="post_status" name="post_status">
                            <?php
                            $current_status = get_post_status($post_id) ?: 'draft';
                            $statuses = array('publish' => 'Pubblicato', 'draft' => 'Bozza', 'pending' => 'Attesa Revisione');
                            foreach ($statuses as $status => $label) {
                                echo '<option value="' . $status . '"' . selected($current_status, $status, false) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                        <label for="post_date">Data di Pubblicazione:</label>
                        <input type="datetime-local" id="post_date" name="post_date"
                               value="<?php echo get_post_time('Y-m-d\TH:i', false, $post_id); ?>">
                        <hr>
                        <button type="submit">Update Status</button>
                    </form>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }


        public function render_post_state_form_and_handle($post_id){
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_status'])) {
                $post_status = sanitize_text_field($_POST['post_status']);

                $post_id = intval($_POST['post_id']);

                // Fix: Handle delete properly
                if ($post_status === 'delete') {
                    wp_delete_post($post_id, true);
                    wp_redirect(add_query_arg('deleted', '1', wp_get_referer()));
                    exit;
                } else {
                    // Update the post status
                    $post_data = array(
                        'ID' => $post_id,
                        'post_status' => $post_status,
                    );
                    wp_update_post($post_data);
                }
            }


            if ($post_id === 'new_post') {
                return '';
            }
            $post_title = get_the_title($post_id);
            ob_start();
            ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Pubblica: <?php echo $post_title ?></h5>

                    <p class="card-text">Cambia lo stato del post</p>
                    <form id="post-status-form" method="POST" action="">
                        <input type="hidden" name="post_id" value="<?php echo $post_id ?>">
                        <label for="post_status">Stato del Post:</label>
                        <select id="post_status" name="post_status">
                            <?php
                            $current_status = get_post_status($post_id);
                            $statuses = array('publish' => 'Pubblicato', 'draft' => 'Bozza', 'pending' => 'Attesa Revisione','delete' => 'Elimina');
                            foreach ($statuses as $status => $label) {
                                echo '<option value="' . $status . '"' . selected($current_status, $status, false) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                        <hr>
                        <button type="submit">Update Status</button>
                    </form>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Renders an inline post status control for integration with ACF forms
         * Provides a seamless user experience without duplicate forms
         */
        public function render_post_state_inline_control($post_id) {

            
            $current_status = get_post_status($post_id);
            $post_title = get_the_title($post_id);
            
            ob_start(); 
            ?>
            <div class="acf-field post-state-control" style="margin-bottom: 20px;">
                <div class="acf-label">
                    <label for="post_status_selector">Stato del Post:</label>
                </div>
                <div class="acf-input">
                    <select id="post_status_selector" class="acf-input" style="width: 100%;">
                        <option value="draft" <?php selected($current_status, 'draft'); ?>>📝 Bozza</option>
                        <option value="publish" <?php selected($current_status, 'publish'); ?>>✅ Pubblicato</option>
                        <option value="pending" <?php selected($current_status, 'pending'); ?>>⏳ In Revisione</option>
                        <option value="delete" style="color: #d63384;">🗑️ Elimina</option>
                    </select>
                    <p class="description">Seleziona lo stato desiderato per questo post.</p>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selector = document.getElementById('post_status_selector');
                const hiddenField = document.getElementById('acf_post_status_control');
                
                if (selector && hiddenField) {
                    selector.addEventListener('change', function() {
                        const value = this.value;
                        
                        if (value === 'delete') {
                            const postTitle = '<?php echo esc_js($post_title); ?>';
                            if (!confirm('⚠️ Sei sicuro di voler eliminare "' + postTitle + '"?\n\nQuesta azione è irreversibile e potrebbe influenzare altri contenuti collegati.')) {
                                this.value = hiddenField.getAttribute('data-original');
                                return;
                            }
                        }
                        
                        hiddenField.value = value;
                        
                        // Visual feedback for different states
                        selector.style.borderColor = value === 'delete' ? '#d63384' : 
                                                   value === 'publish' ? '#28a745' : 
                                                   value === 'pending' ? '#ffc107' : '#6c757d';
                    });
                    
                    // Set initial border color and sync hidden field
                    const initialValue = selector.value;
                    selector.style.borderColor = initialValue === 'publish' ? '#28a745' : 
                                                initialValue === 'pending' ? '#ffc107' : '#6c757d';
                    
                    // Initialize hidden field with current select value
                    hiddenField.value = initialValue;
                    console.log('Initialized hidden field with select value:', initialValue);
                }
            });
            </script>
            
            <style>
            .post-state-control .acf-label {
                margin-bottom: 5px;
            }
            .post-state-control .acf-input select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                transition: border-color 0.3s ease;
            }
            .post-state-control .acf-input select:focus {
                outline: none;
                border-color: #007cba;
                box-shadow: 0 0 0 1px #007cba;
            }
            .post-state-control .description {
                margin-top: 5px;
                font-size: 12px;
                color: #666;
                font-style: italic;
            }
            </style>
            <?php 
            return ob_get_clean();
        }

        /**
         * Returns formatted post status with icon and color
         * @param string $status The post status
         * @return string HTML formatted status
         */
        public function get_formatted_post_status($status) {
            $statuses = [
                'publish' => ['icon' => '✅', 'label' => 'Pubblicato', 'color' => '#28a745'],
                'draft' => ['icon' => '📝', 'label' => 'Bozza', 'color' => '#6c757d'],
                'pending' => ['icon' => '⏳', 'label' => 'In Revisione', 'color' => '#ffc107'],
                'future' => ['icon' => '🕐', 'label' => 'Programmato', 'color' => '#17a2b8'],
                'private' => ['icon' => '🔒', 'label' => 'Privato', 'color' => '#6f42c1'],
                'trash' => ['icon' => '🗑️', 'label' => 'Cestino', 'color' => '#dc3545']
            ];
            
            $status_info = $statuses[$status] ?? ['icon' => '❓', 'label' => ucfirst($status), 'color' => '#6c757d'];
            
            return sprintf(
                '<span style="color: %s; font-weight: 500;">%s %s</span>',
                esc_attr($status_info['color']),
                $status_info['icon'],
                esc_html($status_info['label'])
            );
        }

        /**
         * Include common dashboard CSS styles
         * Centralizza gli stili CSS comuni per tutti i template dashboard
         * 
         * @return void
         */
        public function enqueue_dashboard_common_styles(): void
        {
            $css_url = plugin_dir_url(dirname(__FILE__)) . 'assets/css/dokan-dashboard-common.css';
            $css_version = filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/dokan-dashboard-common.css');
            
            wp_enqueue_style(
                'dokan-dashboard-common',
                $css_url,
                array(),
                $css_version,
                'all'
            );
        }

        /**
         * Include common dashboard JavaScript for fade effects
         * 
         * @return string
         */
        public function get_dashboard_common_scripts(): string
        {
            return '<script>
                window.onload = function () {
                    var alerts = document.querySelectorAll(\'.alert\');
                    setTimeout(function () {
                        for (var i = 0; i < alerts.length; i++) {
                            fadeOut(alerts[i]);
                        }
                    }, 5000);
                }

                function fadeOut(element) {
                    var op = 1;  // initial opacity
                    var timer = setInterval(function () {
                        if (op <= 0.1) {
                            clearInterval(timer);
                            element.style.display = \'none\';
                        }
                        element.style.opacity = op;
                        op -= op * 0.1;
                    }, 50);
                }
            </script>';
        }
    }
}