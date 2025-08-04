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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }
        
        .waiting-content {
            max-width: 600px;
            animation: fadeInUp 1s ease-out;
        }
        
        .monitor-icon {
            font-size: 6rem;
            margin-bottom: 30px;
            opacity: 0.8;
            animation: pulse 2s infinite;
        }
        
        .waiting-title {
            font-size: 3rem;
            font-weight: 300;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .waiting-message {
            font-size: 1.4rem;
            opacity: 0.9;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .shop-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 40px;
        }
        
        .shop-logo {
            height: 80px;
            width: auto;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .shop-name {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 10px;
        }
        
        .shop-subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
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
        
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        
        .floating-element {
            position: absolute;
            font-size: 2rem;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            top: 30%;
            right: 15%;
            animation-delay: 2s;
        }
        
        .floating-element:nth-child(3) {
            bottom: 25%;
            left: 20%;
            animation-delay: 4s;
        }
        
        .floating-element:nth-child(4) {
            bottom: 35%;
            right: 25%;
            animation-delay: 1s;
        }
        
        .last-update {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 0.9rem;
            opacity: 0.6;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.8;
            }
            50% {
                transform: scale(1.05);
                opacity: 1;
            }
        }
        
        @keyframes blink {
            0%, 50% {
                opacity: 1;
            }
            51%, 100% {
                opacity: 0.3;
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }
        
        @media (max-width: 768px) {
            .waiting-title {
                font-size: 2.2rem;
            }
            
            .waiting-message {
                font-size: 1.2rem;
            }
            
            .shop-name {
                font-size: 1.6rem;
            }
            
            .monitor-icon {
                font-size: 4rem;
            }
        }
        
        @media (orientation: portrait) {
            .waiting-title {
                font-size: 2.5rem;
            }
            
            .monitor-icon {
                font-size: 5rem;
            }
        }
    </style>
</head>

<body>
    <div class="waiting-container">
        <!-- Floating Background Elements -->
        <div class="floating-elements">
            <div class="floating-element">🖥️</div>
            <div class="floating-element">📱</div>
            <div class="floating-element">💻</div>
            <div class="floating-element">🖨️</div>
        </div>
        
        <div class="waiting-content">
            <div class="monitor-icon">🖥️</div>
            
            <h1 class="waiting-title">Monitor in Attesa</h1>
            
            <p class="waiting-message">
                Il monitor è attivo e in attesa che venga associato un annuncio di morte.<br>
                L'agenzia può selezionare un defunto dal proprio pannello di controllo.
            </p>
            
            <?php if ($vendor_obj): ?>
            <div class="shop-info">
                <?php if ($shop_banner): ?>
                    <img src="<?php echo esc_url($shop_banner); ?>" 
                         alt="<?php echo esc_attr($shop_name); ?>" 
                         class="shop-logo">
                <?php endif; ?>
                
                <div class="shop-name"><?php echo esc_html($shop_name); ?></div>
                <div class="shop-subtitle">Monitor Digitale</div>
            </div>
            <?php endif; ?>
            
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span>Sistema Attivo - In attesa di contenuti</span>
            </div>
        </div>
        
        <div class="last-update">
            Ultimo controllo: <?php echo current_time('H:i:s'); ?>
        </div>
    </div>
    
    <!-- No theme interference -->
</body>
</html>