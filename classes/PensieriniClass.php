<?php

namespace Dokan_Mods;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\PensieriniClass')) {
    class PensieriniClass
    {

        private $UtilsAMClass;

        public function __construct()
        {
            $this->UtilsAMClass = new UtilsAMClass();
            add_action('admin_post_pensierino_form', array($this, 'handle_form_submission'));
            add_action('admin_post_nopriv_pensierino_form', array($this, 'handle_form_submission'));

            add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'));
            add_filter('woocommerce_get_item_data', array($this, 'display_comment_and_post_title_in_cart'), 10, 2);
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'display_comment_and_post_title_in_order_checkout'), 10, 4);

            add_action('wp_set_comment_status', array($this, 'handle_comment_approval'), 10, 2);
            add_action('delete_comment', array($this, 'handle_comment_deletion'));

            add_action('wp_ajax_load_more_comments', array($this, 'load_more_comments'));
            add_action('wp_ajax_nopriv_load_more_comments', array($this, 'load_more_comments'));

            add_action('init', array($this, 'register_shortcodes'));

        }

        public function register_shortcodes()
        {
            add_shortcode('pensierino_form', array($this, 'generate_pensierino_form'));
            add_shortcode('pensierino_comments', array($this, 'generate_comments_shortcode'));
        }





        public function generate_pensierino_form()
        {
            ob_start(); // Start output buffering

            ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="full-width-form">
                <input type="hidden" name="action" value="pensierino_form">
                <input type="hidden" name="post_id" value="<?php echo $_POST['post_id'] ?? get_the_ID(); ?>">
                <textarea name="pensierino" id="pensierino_comment_id" maxlength="200" required
                          class="styled-textarea"></textarea>
                <input type="submit" value="Continua">
            </form>
            <style>
                .full-width-form input[type="submit"] {
                    display: block;
                    margin: 0 auto;
                }

                .full-width-form {
                    width: 100%;
                }

                .styled-textarea {
                    width: 100%;
                    margin-bottom: 10px;
                    resize: none;
                    height: 150px;
                    border: none; /* Remove all borders */
                    border-bottom: 1px solid #000; /* Add only bottom border */
                    border-radius: 0; /* Remove border radius */
                    font-family: var(--e-global-typography-text-font-family), Sans-serif;
                    font-weight: var(--e-global-typography-text-font-weight);
                }
            </style>
            <?php

            return ob_get_clean(); // End output buffering and return the form HTML
        }

        public function handle_form_submission()
        {
            // Check if the form is submitted and the textarea text is set
            if (isset($_POST['pensierino'])) {
                // Get the textarea text and sanitize it
                $comment_text = sanitize_text_field($_POST['pensierino']);

                // Get the post ID and validate it
                $post_id = intval($_POST['post_id']);
                if (!$post_id) {
                    wp_die('Invalid post ID');
                }

                // Get the product ID of Pensierini
                $product_id = $this->UtilsAMClass->get_product_id_by_slug('pensierini');
                if (!$product_id) {
                    wp_die('Product not found');
                }

                // Check if WooCommerce is available
                if (!class_exists('WooCommerce')) {
                    // Redirect back to the page with error message
                    wp_redirect(get_permalink($post_id));
                    exit;
                }

                $product = wc_get_product($product_id);
                if (!$product) {
                    wp_die('Product not found');
                }

                // Ensure WooCommerce cart is loaded
                if (!WC()->cart) {
                    wc_load_cart();
                }

                if ($product->is_purchasable()) {
                    $cart_item_data = array(
                        'pensierino_comment_text' => $comment_text,
                        'pensierino_comment_post_id' => $post_id,
                    );

                    WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
                    wp_redirect(wc_get_cart_url());
                    exit;
                } else {
                    wp_die('Product not purchasable');
                }
            } else {
                wp_die('Form submission error');
            }
        }

        public function handle_comment_approval($comment_id, $comment_status)
        {
            if ($comment_status == 'approve') {
                // Get the comment object
                $comment = get_comment($comment_id);

                // Get the order ID from the comment meta
                $order_id = get_comment_meta($comment_id, 'order_id', true);

                // Check if the order ID exists
                if ($order_id) {
                    // Get the order object
                    $order = wc_get_order($order_id);

                    // Check if the order exists and is not already completed
                    if ($order && $order->get_status() != 'completed') {
                        // Update the order status to completed
                        $order->update_status('completed');
                    }
                }
            }
        }

        public function handle_comment_deletion($comment_id)
        {
            // Get the order ID from the comment meta
            $order_id = get_comment_meta($comment_id, 'order_id', true);

            // Check if the order ID exists
            if ($order_id) {
                // Get the order object
                $order = wc_get_order($order_id);

                // Check if the order exists and is not already cancelled
                if ($order && $order->get_status() != 'cancelled') {
                    // Update the order status to cancelled
                    $order->update_status('cancelled');
                }
            }
        }

        public function handle_payment_complete($order_id)
        {
            // Get the order
            $order = wc_get_order($order_id);

            // Loop through the order items
            foreach ($order->get_items() as $item_id => $item) {
                // Get the product ID
                $product_id = $item->get_product_id();

                // Check if the product is Pensierini
                if ($product_id == $this->UtilsAMClass->get_product_id_by_slug('pensierini')) {
                    // Get the comment text and post ID from the cart item data
                    $comment_text = $item->get_meta('_pensierino_comment_text');
                    $post_id = $item->get_meta('_pensierino_comment_post_id');

                    // Get the order
                    $order = wc_get_order($order_id);

                    // Get the billing first name and last name
                    $first_name = $order->get_billing_first_name();
                    $last_name = $order->get_billing_last_name();

                    // Check if the comment text and post ID are set
                    if ($comment_text && $post_id) {
                        // Prepare the comment data
                        $comment_data = array(
                            'comment_post_ID' => $post_id,
                            'comment_author' => $first_name . ' ' . $last_name,
                            'comment_content' => $comment_text,
                            'comment_type' => 'comment',
                            'comment_approved' => 0, // Set to 0 to make the comment unapproved
                        );

                        // Insert the comment
                        $comment_id = wp_insert_comment($comment_data);

                        if ($comment_id) {
                            add_comment_meta($comment_id, 'order_id', $order_id, true);
                            add_comment_meta($comment_id, 'product_id', $product_id, true);
                        }
                    }

                }
            }
        }

        public function display_comment_and_post_title_in_cart($item_data, $cart_item)
        {
            // Get the product ID
            $product_id = $cart_item['product_id'];

            // Check if the product is Pensierini
            if ($product_id == $this->UtilsAMClass->get_product_id_by_slug('pensierini')) {
                // Get the comment text and post ID from the cart item data
                $comment_text = $cart_item['pensierino_comment_text'];
                $post_id = $cart_item['pensierino_comment_post_id'];


                // Get the post title
                $post_title = get_the_title($post_id);

                // Append the comment and post title to the product name
                $item_data[] = array(
                    'key' => 'Pensierino',
                    'value' => sprintf(
                        '%s',
                        esc_html($comment_text),
                    ),
                );
                $item_data[] = array(
                    'key' => 'Dedicato a',
                    'value' => sprintf(
                        '%s',
                        esc_html($post_title),
                    ),
                );
            }

            return $item_data;
        }

        public function display_comment_and_post_title_in_order_checkout($item, $cart_item_key, $values, $order)
        {
            // Get the product ID
            $product_id = $item->get_product_id();

            // Check if the product is Pensierini
            if ($product_id == $this->UtilsAMClass->get_product_id_by_slug('pensierini')) {
                // Get the comment text and post ID from the cart item data
                $comment_text = $values['pensierino_comment_text'];
                $post_id = $values['pensierino_comment_post_id'];

                // Get the post title
                $post_title = get_the_title($post_id);
                $item->add_meta_data('_pensierino_comment_text', $comment_text, true);
                $item->add_meta_data('_pensierino_comment_post_id', $post_id, true);
                // Append the comment and post title to the product name
                $item->add_meta_data('Pensierino', $comment_text, true);
                $item->add_meta_data('Dedicato a', $post_title, true);
            }
        }


        function load_more_comments()
        {
            $post_id = intval($_POST['post_id']);
            $offset = intval($_POST['offset']);
            $limit = 4; // Numero di commenti da caricare per volta

            $comments = get_comments(array(
                'post_id' => $post_id,
                'status' => 'approve',
                'number' => $limit,
                'offset' => $offset,
            ));

            if ($comments) {
                foreach ($comments as $comment) {
                    ?>
                    <div class="col-12 col-lg-3" >
                        <div class="p-3" style="display: inline-block;">
                            <div class="nrc-user d-flex justify-content-between">
                                <p><?php echo (empty($comment->comment_author) || $comment->comment_author == '') ? 'Anonimo' : esc_html($comment->comment_author); ?></p>
                                <span>ha scritto il <?php echo get_comment_date('j F', $comment->comment_ID); ?>:</span>
                            </div>
                            <div class="card border-0 speech-bubble-card mt-3">
                                <div class="card-body">
                                    <p><?php echo esc_html($comment->comment_content); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '0';
            }

            wp_die();
        }


        public function generate_comments_shortcode($atts)
        {
            $post_id = get_the_ID();
            ob_start();
            ?>
            <style>
                .nrc-user {
                    font-size: 12px;
                }

                .nrc-user p {
                    color: #dcbe52;
                    font-weight: 700;
                }

                .nrc-user span {
                    color: #565656;
                }

                .card-body p {
                    font-size: 15px;
                    font-style: italic;
                }

                .card {
                    background-color: #e9efee;
                    height: 100%;
                }

                .loader {
                    border: 8px solid #f3f3f3; /* Light grey */
                    border-top: 8px solid #3498db; /* Blue */
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 2s linear infinite;
                    display: none; /* Nascosto inizialmente */
                    margin: 20px auto;
                }

                @keyframes spin {
                    0% {
                        transform: rotate(0deg);
                    }
                    100% {
                        transform: rotate(360deg);
                    }
                }
            </style>
            <div id="comments-container" class="row g-2"></div>
            <div class="loader" id="comments-loader"></div>
            <script>
                (function ($) {
                    var offset = 0;
                    var post_id = <?php echo $post_id; ?>;
                    var loading = false;
                    var $loader = $('#comments-loader');

                    function loadComments() {
                        if (loading) return;
                        loading = true;
                        $loader.show();

                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'post',
                            data: {
                                action: 'load_more_comments',
                                post_id: post_id,
                                offset: offset
                            },
                            success: function (response) {
                                if (response === '0') {
                                    $(window).off('scroll');
                                    $loader.hide();
                                    return;
                                }
                                $('#divisore_pensierini').show();
                                $('#comments-container').append(response);
                                offset += 4; // Incrementa l'offset per il prossimo caricamento
                                loading = false;
                                $loader.hide();
                            },
                            error: function () {
                                loading = false;
                                $loader.hide();
                            }
                        });
                    }

                    function isElementInViewport(el) {
                        var rect = el.getBoundingClientRect();
                        return (
                            rect.top <= (window.innerHeight || document.documentElement.clientHeight)
                        );
                    }

                    $(window).on('scroll.pensierini', function () {
                        if (isElementInViewport(document.getElementById('comments-container'))) {
                            loadComments();
                        }
                    });

                    // Carica i primi commenti all'inizio
                    loadComments();
                })(jQuery);
            </script>
            <?php
            return ob_get_clean();
        }


    }
}