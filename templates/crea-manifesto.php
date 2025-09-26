<?php
/**
 * Template per la selezione dei prodotti predefiniti in Dokan.
 */


$template_class = (new \Dokan_Mods\Templates_MiscClass());
$template_class->check_dokan_can_and_message_login();


$user_id = get_current_user_id();
$manifesto_background = get_user_meta($user_id, 'manifesto_background', true) !== '' ? get_user_meta($user_id, 'manifesto_background', true) : DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/default.jpg';
$manifesto_orientation = get_user_meta($user_id, 'manifesto_orientation', true) !== '' ? get_user_meta($user_id, 'manifesto_orientation', true) : 'vertical';
$margin_top = get_user_meta($user_id, 'manifesto_margin_top', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_top', true) : '3.9188837174992';
$margin_right = get_user_meta($user_id, 'manifesto_margin_right', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_right', true) : '5.8620083240518';
$margin_bottom = get_user_meta($user_id, 'manifesto_margin_bottom', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_bottom', true) : '3.9188837174992';
$margin_left = get_user_meta($user_id, 'manifesto_margin_left', true) !== '' ? get_user_meta($user_id, 'manifesto_margin_left', true) : '5.8620083240518';
$alignment = get_user_meta($user_id, 'manifesto_alignment', true) !== '' ? get_user_meta($user_id, 'manifesto_alignment', true) : 'center';

$post_id_annuncio = isset($_GET['post_id_annuncio']) ? intval($_GET['post_id_annuncio']) : null;
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 'new_post';


$form = $template_class->render_post_state_form_and_handle($post_id);
$redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : null;

//se redirect non è null il link di redirect è il redirect_to
if ($redirect_to !== null) {
    $redirect_to = $redirect_to;
}else{
    $redirect_to = home_url('/dashboard/lista-manifesti/?post_id_annuncio=' . $post_id_annuncio . '&operation_result=success');
}


$add_edit_text = $post_id === 'new_post' ? 'Crea' : 'Modifica';

//get the title
$post_title = get_the_title($post_id_annuncio);
//check if vendor status is enabled
$disable_form = false;
if (dokan_is_user_seller($user_id) && !dokan_is_seller_enabled($user_id)) {
    $disable_form = true;
}

// Get existing manifesto content if editing
$existing_content = '';
if ($post_id !== 'new_post') {
    $existing_content = get_field('testo_manifesto', $post_id);
}

get_header();

$active_menu = 'add-annuncio';

// Enqueue the required CSS and JS
wp_enqueue_script('text-editor-manifesto-script', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/js/manifesto-text-editor-acf.js', array('jquery'), null, true);
wp_localize_script('text-editor-manifesto-script', 'acf_ajax_object', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('save_manifesto_nonce'),
    'user_data' => array(
        'manifesto_background' => $manifesto_background,
        'margin_top' => $margin_top,
        'margin_right' => $margin_right,
        'margin_bottom' => $margin_bottom,
        'margin_left' => $margin_left,
        'alignment' => $alignment
    ),
    'post_id' => $post_id,
    'post_id_annuncio' => $post_id_annuncio,
    'redirect_to' => $redirect_to,
    'existing_content' => $existing_content
));
wp_enqueue_style('manifesto-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/manifesto.css');
wp_enqueue_style('manifesto-text-editor-style', DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/css/manifesto-text-editor.css');
?>

<main id="content" class="site-main post-58 page type-page status-publish hentry">
    <header class="page-header"></header>
    
    <div class="page-content">
        <div class="dokan-dashboard-wrap">
            <?php do_action('dokan_dashboard_content_before'); ?>
            
            <div class="dokan-dashboard-content dokan-product-edit">
                <?php
                do_action('dokan_dashboard_content_inside_before');
                do_action('dokan_before_listing_product');
                ?>
                
                <header class="dokan-dashboard-header dokan-clearfix">
                    <h1 class="entry-title">
                        <?php _e($add_edit_text . ' Partecipazione per: ' . $post_title, 'dokan-mod'); ?>
                        <span class="dokan-label dokan-product-status-label"></span>
                    </h1>
                    <?php
                    if (isset($_GET['operation_result'])) {
                        $operation_result = wp_kses($_GET['operation_result'], array());
                        if ($operation_result == 'success') {
                            echo '<div class="alert alert-success">Operazione eseguita con successo.</div>';
                        } else if ($operation_result == 'error') {
                            echo '<div class="alert alert-danger">Si è verificato un errore durante l\'operazione.</div>';
                        }
                    }
                    ?>
                </header>

                <div class="product-edit-new-container product-edit-container" style="margin-bottom: 100px">
                    <?php if (!$disable_form) { ?>
                        <?php if (is_user_logged_in()) { ?>
                            
                            <!-- Post state control inline -->
                            <?php echo $template_class->render_post_state_inline_control($post_id); ?>
                            
                            <!-- Custom Text Editor Form -->
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('save_manifesto_nonce', 'manifesto_nonce'); ?>
                                <input type="hidden" name="action" value="save_manifesto">
                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                <input type="hidden" name="post_id_annuncio" value="<?php echo $post_id_annuncio; ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo $redirect_to; ?>">
                                <input type="hidden" name="testo_manifesto" id="testo_manifesto_hidden">
                                <?php 
                                // Add the hidden field that the post state control expects
                                $current_status = ($post_id !== 'new_post') ? get_post_status($post_id) : 'publish';
                                ?>
                                <input type="hidden" id="acf_post_status_control" name="acf_post_status_control" value="<?php echo esc_attr($current_status); ?>" data-original="<?php echo esc_attr($current_status); ?>">
                                
                                <div class="text-editor-container" style="width: 70%;">
                                    <div class="manifesto-container">
                                        <div class="editor-toolbar">
                                            <button type="button" data-command="bold"><b>B</b></button>
                                            <button type="button" data-command="italic"><i>I</i></button>
                                            <button type="button" data-command="underline"><u>U</u></button>
                                            <select id="font-size-selector" class="font-size-selector">
                                                <option value="small">Piccolo</option>
                                                <option value="medium" selected>Medio</option>
                                                <option value="large">Grande</option>
                                            </select>
                                        </div>
                                        <div id="text-editor-background" class="text-editor-background" style="background-image: none; container-type: unset;">
                                            <!-- Loading indicator -->
                                            <div id="editor-loading" class="editor-loading" style="display: none;">
                                                <div class="loading-spinner"></div>
                                                <p>Caricamento editor...</p>
                                            </div>
                                            <div id="text-editor" contenteditable="true" class="custom-text-editor"><?php 
                                                echo !empty($existing_content) ? $existing_content : '<p><br></p>'; 
                                            ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Save Button -->
                                    <div style="margin-top: 20px; text-align: center;">
                                        <button type="submit" class="button button-primary"><?php echo $add_edit_text; ?> Manifesto</button>
                                    </div>
                                </div>
                            </form>
                            
                            
                        <?php } else { ?>
                            <p><?php _e('Devi essere loggato per compilare questo form.', 'dokan-mod'); ?></p>
                            <?php wp_login_form(); ?>
                        <?php } ?>
                        
                    <?php } else { ?>
                        <div style="display: flex; justify-content: center; align-items: center; height: 250px">
                            <i class="fas fa-ban" style="font-size: 100px; color: red;"></i>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
/* Inherit styles from original template */
body.dokan-dashboard.theme-hello-elementor .site-main,
body.dokan-dashboard .site-main {
    max-width: none !important;
    width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

body.dokan-dashboard.theme-hello-elementor .page-content,
body.dokan-dashboard .page-content {
    max-width: none !important;
    width: 100% !important;
}

body.dokan-dashboard.theme-hello-elementor .dokan-dashboard-wrap,
body.dokan-dashboard .dokan-dashboard-wrap {
    width: 100% !important;
    max-width: 1140px !important;
    margin: 0 auto !important;
}

.alert {
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 5px;
    box-shadow: 0 2px 1px -1px rgba(0, 0, 0, 0.2), 0 1px 1px 0 rgba(0, 0, 0, 0.14), 0 1px 3px 0 rgba(0, 0, 0, 0.12);
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.manifesto-container {
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    flex-direction: column;
    align-items: center;
}



/* Font size selector styling - match toolbar buttons */
.font-size-selector {
    --button-size: clamp(24px, 3vw, 32px);
    display: inline-block;
    font-weight: 400;
    color: #c36;
    text-align: center;
    white-space: nowrap;
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
    background-color: transparent;
    border: 1px solid #c36;
    padding: .5rem 1rem;
    font-size: 1rem;
    border-radius: 3px;
    transition: all .3s;
    cursor: pointer;
    margin: 0 2px;
    min-width: 140px;
}

.font-size-selector:hover {
    background: #f0f0f0;
}

.font-size-selector:focus {
    outline: none;
    background: #f0f0f0;
}

/* Loading indicator styles */
.editor-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    z-index: 10;
    background: rgba(255, 255, 255, 0.9);
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #c36;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.text-editor-background {
    position: relative;
}

</style>

<?php get_footer(); ?>