<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit;
}

class TotemPWAClass {
    
    private const PWA_PASSWORD = 'totem2025';
    private $allowed_url_prefix;
    
    public function __construct() {
        $this->allowed_url_prefix = trailingslashit(get_site_url()) . 'monitor/display/';
        
        add_action('init', array($this, 'register_pwa_endpoint'));
        add_action('template_redirect', array($this, 'handle_pwa_request'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_pwa_assets'));
        add_action('wp_ajax_totem_pwa_validate', array($this, 'ajax_validate_setup'));
        add_action('wp_ajax_nopriv_totem_pwa_validate', array($this, 'ajax_validate_setup'));
    }
    
    public function register_pwa_endpoint() {
        add_rewrite_rule('^totem-pwa/?$', 'index.php?totem_pwa=1', 'top');
        add_rewrite_tag('%totem_pwa%', '([^&]+)');
        
        // Flush rewrite rules if our endpoint doesn't exist
        $rules = get_option('rewrite_rules');
        if (!isset($rules['^totem-pwa/?$'])) {
            flush_rewrite_rules();
        }
    }
    
    public function handle_pwa_request() {
        global $wp_query;
        
        if (!isset($wp_query->query_vars['totem_pwa'])) {
            return;
        }
        
        // Prevent caching
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        
        // Output PWA HTML
        $this->render_pwa_page();
        exit;
    }
    
    public function render_pwa_page() {
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <meta name="mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
            <meta name="theme-color" content="#000000">
            <title>Totem Monitor</title>
            <link rel="manifest" href="<?php echo DOKAN_SELECT_PRODUCTS_PLUGIN_URL; ?>pwa-totem/manifest.json">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body, html {
                    width: 100%;
                    height: 100%;
                    overflow: hidden;
                    background: #000;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                }
                
                #setup-container {
                    display: none;
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: #fff;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    width: 90%;
                    max-width: 400px;
                    z-index: 10000;
                }
                
                #setup-container h2 {
                    margin-bottom: 20px;
                    color: #333;
                    text-align: center;
                }
                
                #setup-container input {
                    width: 100%;
                    padding: 12px;
                    margin-bottom: 15px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    font-size: 16px;
                }
                
                #setup-container button {
                    width: 100%;
                    padding: 12px;
                    background: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    font-size: 16px;
                    cursor: pointer;
                }
                
                #setup-container button:hover {
                    background: #45a049;
                }
                
                #error-message {
                    color: red;
                    margin-bottom: 15px;
                    text-align: center;
                    display: none;
                }
                
                #install-button {
                    display: none;
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 25px;
                    background: #2196F3;
                    color: white;
                    border: none;
                    border-radius: 50px;
                    font-size: 16px;
                    font-weight: bold;
                    cursor: pointer;
                    box-shadow: 0 4px 15px rgba(33,150,243,0.4);
                    z-index: 10001;
                    animation: pulse 2s infinite;
                }
                
                #install-button:hover {
                    background: #1976D2;
                    transform: scale(1.05);
                }
                
                @keyframes pulse {
                    0% { box-shadow: 0 4px 15px rgba(33,150,243,0.4); }
                    50% { box-shadow: 0 4px 25px rgba(33,150,243,0.8); }
                    100% { box-shadow: 0 4px 15px rgba(33,150,243,0.4); }
                }
                
                #offline-message {
                    display: none;
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    text-align: center;
                    z-index: 10000;
                }
                
                #offline-message h2 {
                    color: #ff5722;
                    margin-bottom: 15px;
                }
                
                #content-frame {
                    width: 100%;
                    height: 100%;
                    border: none;
                    display: none;
                }
                
                .loading {
                    display: none;
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    color: white;
                    font-size: 24px;
                    z-index: 9999;
                }
                
                @media screen and (orientation: landscape) {
                    body {
                        transform: rotate(0deg);
                    }
                }
                
                @media screen and (orientation: portrait) {
                    body {
                        transform: rotate(0deg);
                    }
                }
            </style>
        </head>
        <body>
            <div id="setup-container">
                <h2>Configurazione Totem</h2>
                <div id="error-message"></div>
                <input type="password" id="password-input" placeholder="Inserisci password" autocomplete="off">
                <input type="text" id="url-input" placeholder="Inserisci URL monitor" autocomplete="off">
                <button onclick="saveConfiguration()">Salva Configurazione</button>
            </div>
            
            <div id="offline-message">
                <h2>⚠️ Connessione Assente</h2>
                <p>Collegarsi alla rete per utilizzare il totem</p>
            </div>
            
            <div class="loading">Caricamento...</div>
            
            <button id="install-button" onclick="installPWA()">📱 Installa App</button>
            
            <iframe id="content-frame"></iframe>
            
            <script>
                const AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';
                const ALLOWED_PREFIX = '<?php echo esc_js($this->allowed_url_prefix); ?>';
                const STORAGE_KEY = 'totem_monitor_url';
                const STORAGE_LOCK = 'totem_monitor_locked';
                
                let deferredPrompt;
                let isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                                   window.navigator.standalone || 
                                   document.referrer.includes('android-app://');
                
                // Capture install prompt
                window.addEventListener('beforeinstallprompt', function(e) {
                    e.preventDefault();
                    deferredPrompt = e;
                    if (!isStandalone) {
                        document.getElementById('install-button').style.display = 'block';
                    }
                });
                
                // Install PWA
                async function installPWA() {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        if (outcome === 'accepted') {
                            document.getElementById('install-button').style.display = 'none';
                        }
                        deferredPrompt = null;
                    }
                }
                
                // Check if app was installed
                window.addEventListener('appinstalled', function() {
                    document.getElementById('install-button').style.display = 'none';
                    isStandalone = true;
                });
                
                // Kiosk mode features - only if installed as app
                if (isStandalone) {
                    // Prevent context menu and text selection
                    document.addEventListener('contextmenu', e => e.preventDefault());
                    document.addEventListener('selectstart', e => e.preventDefault());
                    
                    // Prevent zoom
                    document.addEventListener('touchstart', function(e) {
                        if (e.touches.length > 1) {
                            e.preventDefault();
                        }
                    }, {passive: false});
                    
                    // Block navigation keys
                    document.addEventListener('keydown', function(e) {
                        // Block F5, Ctrl+R, Alt+Tab, etc.
                        if (e.key === 'F5' || 
                            (e.ctrlKey && e.key === 'r') ||
                            (e.altKey && e.key === 'Tab') ||
                            e.key === 'Escape') {
                            e.preventDefault();
                            return false;
                        }
                    });
                }
                
                // Check online status
                function checkConnection() {
                    if (!navigator.onLine) {
                        document.getElementById('offline-message').style.display = 'block';
                        document.getElementById('setup-container').style.display = 'none';
                        document.getElementById('content-frame').style.display = 'none';
                        return false;
                    } else {
                        document.getElementById('offline-message').style.display = 'none';
                        return true;
                    }
                }
                
                // Monitor connection status
                window.addEventListener('online', function() {
                    if (localStorage.getItem(STORAGE_LOCK) === 'true') {
                        loadMonitorContent();
                    } else {
                        checkConnection();
                    }
                });
                
                window.addEventListener('offline', checkConnection);
                
                // Initialize PWA
                function initPWA() {
                    if (!checkConnection()) {
                        return;
                    }
                    
                    const isLocked = localStorage.getItem(STORAGE_LOCK) === 'true';
                    const savedUrl = localStorage.getItem(STORAGE_KEY);
                    
                    if (isLocked && savedUrl) {
                        loadMonitorContent();
                    } else {
                        document.getElementById('setup-container').style.display = 'block';
                    }
                }
                
                // Save configuration
                async function saveConfiguration() {
                    const password = document.getElementById('password-input').value;
                    const url = document.getElementById('url-input').value.trim();
                    const errorDiv = document.getElementById('error-message');
                    
                    errorDiv.style.display = 'none';
                    
                    if (!password || !url) {
                        errorDiv.textContent = 'Compilare tutti i campi';
                        errorDiv.style.display = 'block';
                        return;
                    }
                    
                    if (!url.startsWith(ALLOWED_PREFIX)) {
                        errorDiv.textContent = 'URL non valido. Deve iniziare con: ' + ALLOWED_PREFIX;
                        errorDiv.style.display = 'block';
                        return;
                    }
                    
                    // Validate password via AJAX
                    try {
                        const formData = new FormData();
                        formData.append('action', 'totem_pwa_validate');
                        formData.append('password', password);
                        formData.append('url', url);
                        
                        const response = await fetch(AJAX_URL, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            localStorage.setItem(STORAGE_KEY, url);
                            localStorage.setItem(STORAGE_LOCK, 'true');
                            loadMonitorContent();
                        } else {
                            errorDiv.textContent = data.data || 'Password non corretta';
                            errorDiv.style.display = 'block';
                        }
                    } catch (error) {
                        errorDiv.textContent = 'Errore di connessione';
                        errorDiv.style.display = 'block';
                    }
                }
                
                // Load monitor content
                function loadMonitorContent() {
                    const savedUrl = localStorage.getItem(STORAGE_KEY);
                    
                    if (!savedUrl || !checkConnection()) {
                        return;
                    }
                    
                    document.getElementById('setup-container').style.display = 'none';
                    document.querySelector('.loading').style.display = 'block';
                    
                    const iframe = document.getElementById('content-frame');
                    iframe.onload = function() {
                        document.querySelector('.loading').style.display = 'none';
                        iframe.style.display = 'block';
                    };
                    
                    iframe.onerror = function() {
                        if (checkConnection()) {
                            document.querySelector('.loading').innerHTML = 'Errore caricamento contenuto';
                        }
                    };
                    
                    // Add timestamp to prevent caching
                    const separator = savedUrl.includes('?') ? '&' : '?';
                    iframe.src = savedUrl + separator + 't=' + Date.now();
                }
                
                // Service Worker registration
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('<?php echo DOKAN_SELECT_PRODUCTS_PLUGIN_URL; ?>pwa-totem/service-worker.js', {
                        scope: '/totem-pwa/'
                    }).then(function(registration) {
                        console.log('ServiceWorker registered');
                    }).catch(function(error) {
                        console.log('ServiceWorker registration failed:', error);
                    });
                }
                
                // Initialize on load
                document.addEventListener('DOMContentLoaded', initPWA);
                
                // Kiosk behaviors - only if installed as app
                if (isStandalone) {
                    // Prevent back navigation
                    history.pushState(null, null, location.href);
                    window.onpopstate = function() {
                        history.go(1);
                    };
                    
                    // Enter fullscreen when possible
                    document.addEventListener('click', function() {
                        if (document.documentElement.requestFullscreen) {
                            document.documentElement.requestFullscreen().catch(e => {});
                        } else if (document.documentElement.webkitRequestFullscreen) {
                            document.documentElement.webkitRequestFullscreen();
                        }
                    });
                }
            </script>
        </body>
        </html>
        <?php
    }
    
    public function ajax_validate_setup() {
        $password = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '';
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if ($password !== self::PWA_PASSWORD) {
            wp_send_json_error('Password non corretta');
            return;
        }
        
        if (strpos($url, $this->allowed_url_prefix) !== 0) {
            wp_send_json_error('URL non valido');
            return;
        }
        
        wp_send_json_success('Configurazione salvata');
    }
    
    public function enqueue_pwa_assets() {
        // Not needed for this implementation
    }
}