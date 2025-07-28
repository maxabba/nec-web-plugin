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

                // Update the post status
                $post_data = array(
                    'ID' => $post_id,
                    'post_status' => $post_status,
                );

                wp_update_post($post_data);
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
    }
}