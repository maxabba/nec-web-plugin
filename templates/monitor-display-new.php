<?php
/**
 * Monitor Display Template - Enhanced for Multiple Monitors and Layouts
 * Template for displaying content on digital monitors/totems with layout support
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Get monitor parameters from new URL structure
$vendor_id = intval(get_query_var('vendor_id'));
$monitor_id = intval(get_query_var('monitor_id'));
$monitor_slug = get_query_var('monitor_slug');

// Legacy support for old URL format
if (!$vendor_id && !$monitor_id) {
    $legacy_vendor_id = intval(get_query_var('legacy_vendor_id'));
    $legacy_slug = get_query_var('monitor_slug');
    
    if ($legacy_vendor_id && $legacy_slug) {
        // Try to find monitor by legacy data
        $db_manager = Dokan_Mods\MonitorDatabaseManager::get_instance();
        $legacy_monitor = $db_manager->get_monitor_by_slug($legacy_vendor_id, $legacy_slug);
        
        if ($legacy_monitor) {
            $vendor_id = $legacy_vendor_id;
            $monitor_id = $legacy_monitor['id'];
            $monitor_slug = $legacy_slug;
        }
    }
}

// Validate required parameters
if (!$vendor_id || !$monitor_id || !$monitor_slug) {
    wp_die('Parametri monitor non validi.', 'Monitor Error', array('response' => 400));
}

// Get monitor configuration
$monitor_class = new Dokan_Mods\MonitorTotemClass();
$db_manager = Dokan_Mods\MonitorDatabaseManager::get_instance();

$monitor_config = $db_manager->get_monitor($monitor_id);
if (!$monitor_config) {
    wp_die('Monitor non trovato.', 'Monitor Error', array('response' => 404));
}

// Verify monitor belongs to vendor and is enabled
if ($monitor_config['vendor_id'] != $vendor_id || !$monitor_config['is_enabled']) {
    wp_die('Monitor non disponibile.', 'Monitor Disabled', array('response' => 403));
}

// Get vendor info
$vendor = get_user_by('ID', $vendor_id);
if (!$vendor) {
    wp_die('Vendor non trovato.', 'Vendor Error', array('response' => 404));
}

$vendor_obj = dokan()->vendor->get($vendor_id);
$shop_name = $vendor_obj->get_shop_name();
$shop_banner = $vendor_obj->get_banner();

// Prepare vendor data for templates
$vendor_data = [
    'id' => $vendor_id,
    'shop_name' => $shop_name,
    'banner' => $shop_banner,
    'user' => $vendor
];

// Get layout type and configuration
$layout_type = $monitor_config['layout_type'];
$layout_config = $monitor_config['layout_config'];
$associated_post_id = $monitor_config['associated_post_id'];

// Prepare monitor data for JavaScript
$js_monitor_data = [
    'vendorId' => $vendor_id,
    'monitorId' => $monitor_id,
    'monitorSlug' => $monitor_slug,
    'layoutType' => $layout_type,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('monitor_nonce'),
    'pollingInterval' => 15000,
    'slideInterval' => 10000
];

// Handle different layout types
switch ($layout_type) {
    case 'solo_annuncio':
        if (!$associated_post_id) {
            include_once 'monitor-waiting-screen.php';
            return;
        }
        
        $post = get_post($associated_post_id);
        if (!$post || $post->post_type !== 'annuncio-di-morte') {
            include_once 'monitor-waiting-screen.php';
            return;
        }
        
        // Get defunto data for solo annuncio
        $defunto_data = [
            'id' => $associated_post_id,
            'title' => get_the_title($associated_post_id),
            'nome' => get_field('nome', $associated_post_id),
            'cognome' => get_field('cognome', $associated_post_id),
            'info' => get_field('info', $associated_post_id),
            'eta' => get_field('eta', $associated_post_id),
            'fotografia' => get_field('fotografia', $associated_post_id),
            'immagine_annuncio_di_morte' => get_field('immagine_annuncio_di_morte', $associated_post_id),
            'data_di_morte' => get_field('data_di_morte', $associated_post_id)
        ];
        
        $GLOBALS['monitor_data'] = [
            'defunto_data' => $defunto_data,
            'vendor_data' => $vendor_data,
            'layout_config' => $layout_config
        ];
        
        $js_monitor_data['defuntoTitle'] = $defunto_data['title'];
        break;
        
    case 'citta_multi':
        $GLOBALS['monitor_data'] = [
            'vendor_data' => $vendor_data,
            'layout_config' => $layout_config
        ];
        break;
        
    case 'manifesti':
    default:
        if (!$associated_post_id) {
            include_once 'monitor-waiting-screen.php';
            return;
        }
        
        $post = get_post($associated_post_id);
        if (!$post || $post->post_type !== 'annuncio-di-morte') {
            include_once 'monitor-waiting-screen.php';
            return;
        }
        
        // Traditional manifesti layout - use existing logic but with new structure
        $defunto_title = get_the_title($associated_post_id);
        $foto_defunto = get_field('fotografia', $associated_post_id);
        $data_di_morte = get_field('data_di_morte', $associated_post_id);
        $data_pubblicazione = get_the_date('d/m/Y', $associated_post_id);
        $display_date = $data_di_morte ? date('d/m/Y', strtotime($data_di_morte)) : $data_pubblicazione;
        
        $js_monitor_data['defuntoTitle'] = $defunto_title;
        $js_monitor_data['postId'] = $associated_post_id;
        break;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(($js_monitor_data['defuntoTitle'] ?? 'Monitor') . ' - ' . $shop_name); ?></title>
    
    <!-- Prevent caching for real-time updates -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Load essential scripts without theme interference -->
    <script src="<?php echo includes_url('js/jquery/jquery.min.js'); ?>"></script>
    
    <?php if ($layout_type === 'manifesti'): ?>
        <!-- Traditional manifesti layout scripts -->
        <script src="<?php echo plugins_url('assets/js/monitor-display.js', dirname(__FILE__)); ?>"></script>
        <link rel="stylesheet" href="<?php echo plugins_url('assets/css/manifesto.css', dirname(__FILE__)); ?>">
        <link rel="stylesheet" href="<?php echo plugins_url('assets/css/monitor-display.css', dirname(__FILE__)); ?>">
    <?php endif; ?>
    
    <!-- Base reset styles -->
    <style>
        /* CSS Variables per Consistenza - Monitor Design Standards */
        :root {
            /* Colors */
            --monitor-bg-primary: rgb(55, 55, 55);
            --monitor-text-primary: #ffffff;
            --monitor-text-secondary: rgba(255, 255, 255, 0.85);
            --monitor-text-muted: rgba(255, 255, 255, 0.7);
            --monitor-status-active: #4CAF50;
            
            /* Typography */
            --monitor-font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --monitor-font-manifesti: "PlayFair Display Mine", serif;
            
            /* Spacing */
            --monitor-padding-small: 20px;
            --monitor-padding-medium: 40px;
            --monitor-gap-small: 10px;
            --monitor-gap-medium: 20px;
            --monitor-gap-large: 30px;
            
            /* Layout Heights - Standard per layout tradizionali */
            --monitor-header-height: 20vh;
            --monitor-body-height: 75vh;
            --monitor-footer-height: 5vh;
            
            /* Transitions */
            --monitor-transition-fast: 0.3s ease;
            --monitor-transition-slow: 0.5s ease;
            
            /* Shadows */
            --monitor-shadow-light: 0 2px 4px rgba(0, 0, 0, 0.3);
            --monitor-shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.5);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
            font-family: var(--monitor-font-family);
            background: var(--monitor-bg-primary);
            color: var(--monitor-text-primary);
        }
        

    </style>
</head>

<body>
    <?php
    // Load appropriate layout template
    switch ($layout_type) {
        case 'solo_annuncio':
            include_once 'monitor-layout-solo-annuncio.php';
            break;
            
        case 'citta_multi':
            include_once 'monitor-layout-citta-multi.php';
            break;
            
        case 'manifesti':
        default:
            // Include traditional manifesti layout (existing functionality)
            include_once 'monitor-layout-manifesti.php';
            break;
    }
    ?>

    <!-- Pass data to JavaScript -->
    <script>
        window.MonitorData = <?php echo wp_json_encode($js_monitor_data); ?>;
        console.log('Monitor Data:', window.MonitorData);
    </script>
    
    <!-- Layout-specific initialization -->
    <?php if ($layout_type === 'manifesti'): ?>
    <script>
        // Initialize traditional monitor display
        document.addEventListener('DOMContentLoaded', function() {
            if (window.MonitorData && typeof MonitorDisplay !== 'undefined') {
                window.monitorDisplay = new MonitorDisplay();
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>