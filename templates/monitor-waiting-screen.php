<?php
/**
 * Monitor Waiting Screen Template
 * Displayed when no defunto is associated to the monitor
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Get vendor info if available
$vendor_obj = dokan()->vendor->get($vendor_id);
$shop_name = $vendor_obj ? $vendor_obj->get_shop_name() : 'Monitor Digitale';
$shop_banner = $vendor_obj ? $vendor_obj->get_banner() : '';

// Get monitor info from database
$db_manager = new \Dokan_Mods\MonitorDatabaseManager();
$monitors = $db_manager->get_vendor_monitors($vendor_id);
$monitor_name = '';

// Since we have limited monitors per vendor, usually just one
// We can get the first enabled monitor as the current one
if (!empty($monitors)) {
    foreach ($monitors as $monitor) {
        if ($monitor['is_enabled'] == 1) {
            $monitor_name = isset($monitor['monitor_name']) ? $monitor['monitor_name'] : 'Monitor ' . $monitor['id'];
            break;
        }
    }
}

// If no enabled monitor found, try to get the first one
if (empty($monitor_name) && !empty($monitors)) {
    $monitor = $monitors[0];
    $monitor_name = isset($monitor['monitor_name']) ? $monitor['monitor_name'] : 'Monitor ' . $monitor['id'];
}

// If still no monitor name found, use default
if (empty($monitor_name)) {
    $monitor_name = 'Monitor 1';
}

// Get logo image URL from plugin assets - no dependency on site media library
$logo_url = plugin_dir_url(__FILE__) . '../assets/images/Necrologi-oro.png';

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($shop_name . ' - Monitor in Attesa'); ?></title>
    
    <!-- Auto refresh every 30 seconds to check for new associations -->
    <meta http-equiv="refresh" content="30">
    
    <!-- No theme interference -->
    
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
        
        .waiting-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            position: relative;
            background: rgb(55, 55, 55);
        }
        
        /* Layout Orizzontale: Logo a sinistra, contenuto al centro */
        @media (orientation: landscape) {
            .waiting-container {
                flex-direction: row;
                text-align: left;
                align-items: center;
                gap: 40px;
                padding: 40px 60px;
            }
            
            .waiting-content {
                flex-direction: row;
                align-items: center;
                gap: 60px;
            }
            
            .waiting-left {
                flex-shrink: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .waiting-right {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                gap: 30px;
            }
        }
        
        .waiting-content {
            max-width: 800px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .logo-image {
            width: auto;
            height: 30vh;
            max-width: 90%;
            margin-bottom: 40px;
            opacity: 0.9;
            object-fit: contain;
        }
        
        .waiting-title {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .waiting-message {
            font-size: 1.3rem;
            opacity: 0.85;
            margin-bottom: 40px;
            line-height: 1.6;
            max-width: 600px;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-size: 1.1rem;
            opacity: 0.7;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4CAF50;
            animation: blink 1.5s infinite;
        }
        
        .footer-info {
            position: absolute;
            bottom: 20px;
            left: 40px;
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 0.9rem;
            opacity: 0.7;
        }
        
        .footer-separator {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .last-update {
            position: absolute;
            bottom: 20px;
            right: 40px;
            font-size: 0.9rem;
            opacity: 0.6;
        }
        
        @keyframes blink {
            0%, 50% {
                opacity: 1;
            }
            51%, 100% {
                opacity: 0.3;
            }
        }
        
        /* Portrait orientation - disposizione verticale tradizionale */
        @media (orientation: portrait) {
            .waiting-content {
                flex-direction: column;
            }
            
            .waiting-left, .waiting-right {
                display: contents; /* Gli elementi appaiono come se fossero parte del parent */
            }
            
            .logo-image {
                width: 80vw;
                height: auto;
                max-height: 40vh;
            }
            
            .waiting-title {
                font-size: 2rem;
            }
            
            .waiting-message {
                font-size: 1.2rem;
            }
        }
        
        /* Landscape orientation - already set to 30vh in base styles */
        @media (orientation: landscape) {
            .logo-image {
                height: 30vh;
                width: auto;
                max-width: 90%;
            }
        }
        
        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .waiting-title {
                font-size: 1.8rem;
            }
            
            .waiting-message {
                font-size: 1.1rem;
            }
            
            .footer-info {
                left: 20px;
                font-size: 0.8rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .footer-separator {
                display: none;
            }
            
            .last-update {
                right: 20px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .waiting-container {
                padding: 20px;
            }
            
            .logo-image {
                margin-bottom: 30px;
            }
            
            .waiting-title {
                font-size: 1.6rem;
                margin-bottom: 15px;
            }
            
            .waiting-message {
                font-size: 1rem;
                margin-bottom: 30px;
            }
            
            .status-indicator {
                font-size: 0.9rem;
            }
        }
        
        /* Very small portrait screens */
        @media (max-width: 480px) and (orientation: portrait) {
            .logo-image {
                width: 70vw;
                max-height: 30vh;
            }
        }
    </style>
</head>

<body>
    <div class="waiting-container">
        <div class="waiting-content">
            <!-- Sezione sinistra: Logo (visible in landscape) -->
            <div class="waiting-left">
                <!-- Logo Image -->
                <img src="<?php echo esc_url($logo_url); ?>" 
                     alt="Logo" 
                     class="logo-image">
            </div>
            
            <!-- Sezione destra: Contenuto testuale (center in landscape, below in portrait) -->
            <div class="waiting-right">
                <h1 class="waiting-title">Monitor in attesa di associazione</h1>
                
                <p class="waiting-message">
                    Il monitor è attivo e in attesa che venga associato,<br>
                    accedi alla dashboard dell'agenzia per l'associazione.
                </p>
                
                <div class="status-indicator">
                    <div class="status-dot"></div>
                    <span>Sistema Attivo</span>
                </div>
            </div>
        </div>
        
        <!-- Footer with agency and monitor info -->
        <div class="footer-info">
            <span class="agency-name"><?php echo esc_html($shop_name); ?></span>
            <span class="footer-separator">|</span>
            <span class="monitor-reference"><?php echo esc_html($monitor_name); ?></span>
        </div>
        
        <div class="last-update">
            Ultimo controllo: <?php echo current_time('H:i:s'); ?>
        </div>
    </div>
    
    <!-- No theme interference -->
</body>
</html>