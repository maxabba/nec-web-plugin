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
                    $message = __('Non hai i permessi per visualizzare questa pagina. ', 'dokan');
                    $message .= '<a href="' . esc_url($myaccount_url) . '">' . __('Login', 'dokan') . '</a>';
                    echo $message;
                    exit;
                } else {
                    //redirect to home page
                    wp_redirect(home_url());
                    exit;
                }
            }
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

                    <p class="card-text">Cambia lo stato del triggesimo</p>
                    <form id="post-status-form" method="POST" action="">
                        <input type="hidden" name="post_id" value="<?php echo $post_id ?>">
                        <label for="post_status">Stato del Post:</label>
                        <select id="post_status" name="post_status">
                            <?php
                            $current_status = get_post_status($post_id);
                            $statuses = array('publish' => 'Pubblicato', 'draft' => 'Bozza', 'pending' => 'Attesa Revisione');
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