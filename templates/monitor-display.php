<?php
/**
 * Monitor Display Template
 * Template for displaying defunto and manifesti on digital monitors/totems
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

error_log("Monitor Display Template: Starting execution");

// Get monitor parameters
$monitor_type = get_query_var('monitor_type', 'display');
$monitor_id = intval(get_query_var('monitor_id'));
$monitor_slug = get_query_var('monitor_slug');

// Find vendor by monitor_url
$vendors = get_users(array(
    'role' => 'seller',
    'meta_query' => array(
        array(
            'key' => 'monitor_url',
            'value' => $monitor_slug,
            'compare' => '='
        )
    )
));

if (empty($vendors)) {
    error_log("Monitor Display Debug - No vendor found for slug: $monitor_slug");
    wp_die('Monitor non trovato o non configurato.', 'Monitor Error', array('response' => 404));
}

$vendor = $vendors[0];
$vendor_id = $vendor->ID;
error_log("Monitor Display Debug - Found vendor: ID=$vendor_id, Slug=$monitor_slug");

// Check if vendor is enabled for monitor
$monitor_class = new Dokan_Mods\MonitorTotemClass();
if (!$monitor_class->is_vendor_enabled($vendor_id)) {
    wp_die('Monitor non abilitato per questo vendor.', 'Monitor Disabled', array('response' => 403));
}

// Get associated post
$associated_post_id = $monitor_class->get_associated_post($vendor_id);
error_log("Monitor Display Debug - Vendor ID: $vendor_id, Associated Post: " . ($associated_post_id ? $associated_post_id : 'none'));

// Temporary: Show monitor even without associated post for debugging
if (!$associated_post_id) {
    error_log("No associated post found, but continuing for debug purposes");
    // For debugging, use a dummy post ID or show a test message
    $associated_post_id = null; // We'll handle this case below
}

// Verify post exists and is valid (temporarily disabled for debug)
$post = null;
if ($associated_post_id) {
    $post = get_post($associated_post_id);
    if (!$post || $post->post_type !== 'annuncio-di-morte') {
        error_log("Invalid post found, ID: $associated_post_id");
        $post = null;
    }
}

// Get post data (handle null case for debugging)
if ($post) {
    $defunto_title = get_the_title($associated_post_id);
    $foto_defunto = get_field('foto_defunto', $associated_post_id);
    $data_di_morte = get_field('data_di_morte', $associated_post_id);
    $data_pubblicazione = get_the_date('d/m/Y', $associated_post_id);
} else {
    // Debug values when no post is associated
    $defunto_title = 'Test Monitor Display';
    $foto_defunto = null;
    $data_di_morte = null;
    $data_pubblicazione = date('d/m/Y');
}

// Get vendor info
$vendor_obj = dokan()->vendor->get($vendor_id);
$shop_name = $vendor_obj->get_shop_name();
$shop_banner = $vendor_obj->get_banner();

// Display date - prefer death date, fallback to publication date
$display_date = $data_di_morte ? date('d/m/Y', strtotime($data_di_morte)) : $data_pubblicazione;

error_log("Monitor Display Template: About to render HTML. Title: $defunto_title, Shop: $shop_name");

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($defunto_title . ' - ' . $shop_name); ?></title>
    
    <!-- Prevent caching for real-time updates -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Load essential scripts without theme interference -->
    <script src="<?php echo includes_url('js/jquery/jquery.min.js'); ?>"></script>
    <script src="<?php echo plugins_url('assets/js/monitor-display.js', dirname(__FILE__)); ?>"></script>
    
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #000;
            color: #fff;
        }
        
        .monitor-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        /* Header Section - 20% */
        .monitor-header {
            height: 20vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 20px;
            position: relative;
        }
        
        .defunto-info {
            display: flex;
            align-items: center;
            gap: 30px;
            max-width: 90%;
        }
        
        .defunto-foto {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .defunto-foto-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: rgba(255, 255, 255, 0.5);
            border: 4px solid rgba(255, 255, 255, 0.3);
        }
        
        .defunto-details h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .defunto-details p {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
        }
        
        /* Body Section - 70% */
        .monitor-body {
            height: 70vh;
            position: relative;
            overflow: hidden;
            background: #111;
        }
        
        .manifesti-slideshow {
            height: 100%;
            position: relative;
        }
        
        .manifesto-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .manifesto-slide.active {
            opacity: 1;
            z-index: 1;
        }
        
        .manifesto-content {
            background: #fff;
            color: #333;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 1.8rem;
            line-height: 1.6;
            padding: 60px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow-y: auto;
        }
        
        .manifesto-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .manifesto-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .manifesto-content::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }
        
        /* Slideshow Controls */
        .slideshow-controls {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
            opacity: 0.7;
        }
        
        .slideshow-controls:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.8);
            transform: translateY(-50%) scale(1.1);
        }
        
        .prev-btn {
            left: 20px;
        }
        
        .next-btn {
            right: 20px;
        }
        
        /* Slide Indicators */
        .slide-indicators {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 10;
        }
        
        .slide-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .slide-indicator.active {
            background: rgba(255, 255, 255, 0.9);
            transform: scale(1.2);
        }
        
        /* Footer Section - 10% */
        .monitor-footer {
            height: 10vh;
            background: #222;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            border-top: 2px solid #333;
        }
        
        .shop-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .shop-logo {
            height: 50px;
            width: auto;
        }
        
        .shop-name {
            font-size: 1.4rem;
            font-weight: 300;
        }
        
        .monitor-status {
            font-size: 0.9rem;
            opacity: 0.7;
        }
        
        /* Loading State */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .loading-overlay.hidden {
            display: none;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive Design */
        @media (max-width: 1366px) {
            .defunto-details h1 {
                font-size: 2rem;
            }
            
            .defunto-foto, .defunto-foto-placeholder {
                width: 100px;
                height: 100px;
            }
            
            .manifesto-content {
                font-size: 1.5rem;
                padding: 40px;
            }
        }
        
        @media (max-height: 800px) {
            .defunto-details h1 {
                font-size: 1.8rem;
            }
            
            .manifesto-content {
                font-size: 1.4rem;
                padding: 30px;
            }
        }
        
        /* Portrait Orientation (Totem) */
        @media (orientation: portrait) {
            .defunto-info {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .defunto-details h1 {
                font-size: 2rem;
            }
            
            .manifesto-content {
                font-size: 1.6rem;
            }
        }
        
        /* No Manifesti State */
        .no-manifesti {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.6);
            text-align: center;
            padding: 40px;
        }
        
        .no-manifesti-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .no-manifesti h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .no-manifesti p {
            font-size: 1rem;
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <div class="monitor-container">
        <!-- Header Section -->
        <header class="monitor-header">
            <div class="defunto-info">
                <?php if ($foto_defunto && isset($foto_defunto['sizes']['medium'])): ?>
                    <img src="<?php echo esc_url($foto_defunto['sizes']['medium']); ?>" 
                         alt="<?php echo esc_attr($defunto_title); ?>" 
                         class="defunto-foto">
                <?php else: ?>
                    <div class="defunto-foto-placeholder">
                        <i class="dashicons dashicons-admin-users"></i>
                    </div>
                <?php endif; ?>
                
                <div class="defunto-details">
                    <h1><?php echo esc_html($defunto_title); ?></h1>
                    <p><?php echo esc_html($display_date); ?></p>
                </div>
            </div>
        </header>

        <!-- Body Section -->
        <main class="monitor-body">
            <div class="manifesti-slideshow" id="manifesti-slideshow">
                <!-- Loading Overlay -->
                <div class="loading-overlay" id="loading-overlay">
                    <div class="spinner"></div>
                    <p>Caricamento manifesti...</p>
                </div>

                <!-- Manifesti slides will be loaded here -->
                <div id="manifesti-container"></div>

                <!-- No manifesti state -->
                <div class="no-manifesti" id="no-manifesti" style="display: none;">
                    <div class="no-manifesti-icon">📝</div>
                    <h3>Nessun manifesto disponibile</h3>
                    <p>I manifesti verranno visualizzati non appena saranno pubblicati</p>
                </div>

                <!-- Slideshow Controls -->
                <button class="slideshow-controls prev-btn" id="prev-btn" style="display: none;">
                    <span>‹</span>
                </button>
                <button class="slideshow-controls next-btn" id="next-btn" style="display: none;">
                    <span>›</span>
                </button>

                <!-- Slide Indicators -->
                <div class="slide-indicators" id="slide-indicators" style="display: none;"></div>
            </div>
        </main>

        <!-- Footer Section -->
        <footer class="monitor-footer">
            <div class="shop-info">
                <?php if ($shop_banner): ?>
                    <img src="<?php echo esc_url($shop_banner); ?>" 
                         alt="<?php echo esc_attr($shop_name); ?>" 
                         class="shop-logo">
                <?php endif; ?>
                <div class="shop-name"><?php echo esc_html($shop_name); ?></div>
            </div>
            <div class="monitor-status">
                <small>Ultimo aggiornamento: <span id="last-update"><?php echo current_time('H:i'); ?></span></small>
            </div>
        </footer>
    </div>

    <!-- No theme footer to prevent interference -->

    <script>
        // Pass data to JavaScript
        window.MonitorData = {
            vendorId: <?php echo $vendor_id; ?>,
            postId: <?php echo $associated_post_id; ?>,
            monitorSlug: '<?php echo esc_js($monitor_slug); ?>',
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('monitor_display_nonce'); ?>',
            pollingInterval: 15000, // 15 seconds
            slideInterval: 5000, // 5 seconds
            defuntoTitle: '<?php echo esc_js($defunto_title); ?>'
        };
    </script>
</body>
</html>