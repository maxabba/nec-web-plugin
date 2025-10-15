<?php
/**
 * Monitor Display Template - Enhanced for Multiple Monitors and Layouts
 * Template for displaying content on digital monitors/totems with layout support
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Set aggressive no-cache headers to prevent browser caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

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
    'slideInterval' => 10000,  // Base time per slide (milliseconds)
    'pauseInterval' => 30000   // Base pause time after user interaction (milliseconds)
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
        $js_monitor_data['postId'] = $associated_post_id;
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
        
        // Set global data for manifesti template
        $GLOBALS['manifesti_data'] = [
            'defunto_title' => $defunto_title,
            'foto_defunto' => $foto_defunto,
            'display_date' => $display_date,
            'associated_post_id' => $associated_post_id
        ];
        
        $GLOBALS['monitor_data'] = [
            'vendor_data' => $vendor_data,
            'layout_config' => $layout_config
        ];
        
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

    <?php
    // Cache busting version based on file modification time
    $cache_buster = time(); // or use filemtime() for specific files

    if ($layout_type === 'manifesti'):
        $js_file = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'assets/js/monitor-display.js';
        $css_manifesto_file = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'assets/css/manifesto.css';
        $css_display_file = DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'assets/css/monitor-display.css';

        $js_version = file_exists($js_file) ? filemtime($js_file) : $cache_buster;
        $css_manifesto_version = file_exists($css_manifesto_file) ? filemtime($css_manifesto_file) : $cache_buster;
        $css_display_version = file_exists($css_display_file) ? filemtime($css_display_file) : $cache_buster;
    ?>
        <!-- Traditional manifesti layout scripts with cache busting -->
        <script src="<?php echo plugins_url('assets/js/monitor-display.js', dirname(__FILE__)) . '?v=' . $js_version; ?>"></script>
        <link rel="stylesheet" href="<?php echo plugins_url('assets/css/manifesto.css', dirname(__FILE__)) . '?v=' . $css_manifesto_version; ?>">
        <link rel="stylesheet" href="<?php echo plugins_url('assets/css/monitor-display.css', dirname(__FILE__)) . '?v=' . $css_display_version; ?>">
    <?php endif; ?>
    
    <!-- Base reset styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: rgb(55, 55, 55);
            color: #fff;
        }
        
        /* Hide any theme elements that might interfere */
        #wpadminbar,
        .admin-bar,
        header:not(.solo-annuncio-header):not(.citta-multi-header):not(.monitor-header),
        footer:not(.solo-annuncio-footer):not(.citta-multi-footer):not(.monitor-footer),
        nav,
        .site-header,
        .site-footer {
            display: none !important;
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
            // Re-define variables for manifesti layout since they were defined earlier in switch
            $defunto_title = get_the_title($associated_post_id);
            $foto_defunto = get_field('fotografia', $associated_post_id);
            $data_di_morte = get_field('data_di_morte', $associated_post_id);
            $data_pubblicazione = get_the_date('d/m/Y', $associated_post_id);
            $display_date = $data_di_morte ? date('d/m/Y', strtotime($data_di_morte)) : $data_pubblicazione;
            
            // Include traditional manifesti layout (existing functionality)
            include_once 'monitor-layout-manifesti.php';
            break;
    }
    ?>

    <!-- Pass data to JavaScript -->
    <script>
        window.MonitorData = <?php echo wp_json_encode($js_monitor_data); ?>;
        console.log('Monitor Data:', window.MonitorData);

        // Monitor configuration change detector
        (function() {
            let changeCheckInterval = null;
            const CHECK_INTERVAL = 10000; // Check every 10 seconds

            function checkMonitorChanges() {
                jQuery.ajax({
                    url: window.MonitorData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'monitor_check_changes',
                        monitor_id: window.MonitorData.monitorId,
                        vendor_id: window.MonitorData.vendorId,
                        current_layout_type: window.MonitorData.layoutType,
                        current_post_id: window.MonitorData.postId || 0
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            if (response.data.changed && response.data.reload) {
                                console.log('Monitor configuration changed:', response.data.reasons || response.data.reason);
                                console.log('Reloading page...');

                                // Clear interval before reload
                                if (changeCheckInterval) {
                                    clearInterval(changeCheckInterval);
                                }

                                // Show brief message before reload
                                if (response.data.message) {
                                    const messageDiv = document.createElement('div');
                                    messageDiv.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,0.9);color:white;padding:30px 50px;border-radius:10px;font-size:20px;z-index:99999;text-align:center;';
                                    messageDiv.textContent = response.data.message;
                                    document.body.appendChild(messageDiv);
                                }

                                // Reload after brief delay with aggressive cache clearing
                                setTimeout(function() {
                                    /**
                                     * Multi-level cache clearing strategy:
                                     *
                                     * 1. Hard Reload: location.reload(true) - Forces browser to bypass cache
                                     *    - Reloads HTML, CSS, JS from server
                                     *    - Similar to Ctrl+Shift+R / Cmd+Shift+R
                                     *
                                     * 2. Cache Busting URL: Adds timestamp parameter
                                     *    - Makes browser treat as new URL
                                     *    - Fallback if hard reload doesn't work
                                     *    - Works with server-side cache-control headers
                                     *
                                     * Combined with PHP headers (Cache-Control: no-store) and
                                     * asset versioning (?v=timestamp), this ensures fresh content.
                                     */

                                    // Try hard reload first
                                    try {
                                        if (window.location.reload) {
                                            window.location.reload(true); // true = hard reload (bypass cache)
                                        }
                                    } catch (e) {
                                        console.log('Hard reload not supported, using cache-bust URL');
                                    }

                                    // Fallback: Add timestamp to URL to force fresh load
                                    const url = new URL(window.location.href);
                                    url.searchParams.set('_refresh', Date.now());
                                    window.location.href = url.toString();
                                }, 1500);
                            } else {
                                console.log('No changes detected at', response.data.last_check);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.warn('Change check failed:', error);
                    }
                });
            }

            // Start monitoring on page load
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Starting monitor configuration change detection (every ' + (CHECK_INTERVAL/1000) + 's)');

                // Initial check after 5 seconds
                setTimeout(checkMonitorChanges, 5000);

                // Then check periodically
                changeCheckInterval = setInterval(checkMonitorChanges, CHECK_INTERVAL);
            });

            // Clear interval on page unload
            window.addEventListener('beforeunload', function() {
                if (changeCheckInterval) {
                    clearInterval(changeCheckInterval);
                }
            });
        })();
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