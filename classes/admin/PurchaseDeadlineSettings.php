<?php
namespace Dokan_Mods\Admin;

use Dokan_Mods\PurchaseDeadlineManager;

class PurchaseDeadlineSettings {
    
    private $page_slug = 'dokan-purchase-deadlines';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_save_purchase_deadline', [$this, 'ajax_save_deadline']);
        add_action('wp_ajax_delete_purchase_deadline', [$this, 'ajax_delete_deadline']);
        add_action('wp_ajax_save_default_deadlines', [$this, 'ajax_save_defaults']);
        add_action('wp_ajax_search_cities', [$this, 'ajax_search_cities']);
    }
    
    /**
     * Add submenu page under dokan-mod
     */
    public function add_menu_page() {
        add_submenu_page(
            'dokan-mod',
            'Gestione Tempi di Acquisto',
            'Tempi di Acquisto',
            'manage_options',
            $this->page_slug,
            [$this, 'render_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        // Correct hook check based on MonitorTotemClass pattern
        if ($hook !== 'dokan-mods_page_' . $this->page_slug) {
            return;
        }
        
        // Use the same constant as other classes
        wp_enqueue_script(
            'dokan-purchase-deadline-admin',
            DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/admin/purchase-deadline.js',
            array('jquery'),
            '1.0.2',
            true
        );
        
        wp_localize_script('dokan-purchase-deadline-admin', 'dokanDeadlineAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dokan_deadline_nonce')
        ));
        
        wp_enqueue_style(
            'dokan-purchase-deadline-admin',
            DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/admin/purchase-deadline.css',
            array(),
            '1.0.4'
        );
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        $cities = PurchaseDeadlineManager::get_all_cities();
        $defaults = PurchaseDeadlineManager::get_defaults();
        ?>
        <div class="wrap dokan-purchase-deadlines-page">
            <!-- AJAX object -->
            <script>
                // Create AJAX object inline in case localize_script doesn't work
                window.dokanDeadlineAjax = {
                    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    nonce: '<?php echo wp_create_nonce('dokan_deadline_nonce'); ?>'
                };
            </script>
            <h1>Gestione Tempi di Acquisto</h1>
            
            <div class="notice notice-info">
                <p>
                    <strong>Nota:</strong> I tempi indicano quanto prima del funerale deve essere disabilitato l'acquisto.
                    <br>Es: 4 ore = il prodotto non sarà acquistabile nelle ultime 4 ore prima del funerale.
                </p>
            </div>
            
            <!-- Default Values Section -->
            <div class="card">
                <h2>Valori di Default</h2>
                <p class="description">Questi valori verranno applicati alle città che non hanno configurazioni specifiche.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default-fiori">Fiori (ore prima)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="default-fiori" 
                                   value="<?php echo PurchaseDeadlineManager::seconds_to_hours($defaults['fiori']); ?>" 
                                   min="0" 
                                   step="0.5" 
                                   style="width: 100px">
                            <span class="description">ore</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default-manifesti">Manifesti Top/Silver (ore prima)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="default-manifesti" 
                                   value="<?php echo PurchaseDeadlineManager::seconds_to_hours($defaults['manifesti']); ?>" 
                                   min="0" 
                                   step="0.5" 
                                   style="width: 100px">
                            <span class="description">ore</span>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button class="button button-primary" id="save-defaults">Salva Valori Default</button>
                </p>
            </div>
            
            <!-- Cities Table -->
            <div class="card" style="margin-top: 20px">
                <h2>Configurazioni per Città</h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%">Città</th>
                            <th style="width: 25%">Fiori (ore prima)</th>
                            <th style="width: 25%">Manifesti (ore prima)</th>
                            <th style="width: 20%">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="cities-table">
                        <?php foreach ($cities as $city): ?>
                        <tr data-city="<?php echo esc_attr($city['slug']); ?>">
                            <td>
                                <strong><?php echo esc_html(PurchaseDeadlineManager::get_city_name($city['slug'])); ?></strong>
                                <br><small><?php echo esc_html($city['slug']); ?></small>
                            </td>
                            <td>
                                <input type="number" 
                                       class="deadline-input fiori" 
                                       value="<?php echo PurchaseDeadlineManager::seconds_to_hours($city['fiori']); ?>" 
                                       min="0" 
                                       step="0.5" 
                                       style="width: 100px">
                                <span class="description">ore</span>
                            </td>
                            <td>
                                <input type="number" 
                                       class="deadline-input manifesti" 
                                       value="<?php echo PurchaseDeadlineManager::seconds_to_hours($city['manifesti']); ?>" 
                                       min="0" 
                                       step="0.5" 
                                       style="width: 100px">
                                <span class="description">ore</span>
                            </td>
                            <td>
                                <button class="button save-city">Salva</button>
                                <button class="button delete-city">Elimina</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($cities)): ?>
                        <tr>
                            <td colspan="4">Nessuna città configurata. Aggiungi la prima città usando il form sottostante.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Add New City Form -->
                <h3>Aggiungi Nuova Città</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="new-city-search">Nome Città</label>
                        </th>
                        <td>
                            <div class="city-search-container" style="position: relative; width: 250px;">
                                <input type="text" 
                                       id="new-city-search" 
                                       placeholder="Digita il nome della città..." 
                                       style="width: 100%; padding-right: 30px;"
                                       autocomplete="off">
                                <span class="search-spinner" style="display: none; position: absolute; right: 8px; top: 8px;">
                                    <span class="spinner is-active" style="float: none; margin: 0;"></span>
                                </span>
                                <ul id="city-search-results" class="city-autocomplete-results" style="display: none;"></ul>
                            </div>
                            <input type="hidden" id="new-city-slug" value="">
                            <p class="description">Inizia a digitare per cercare una città (minimo 2 caratteri)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="new-city-fiori">Fiori (ore prima)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="new-city-fiori" 
                                   value="4" 
                                   min="0" 
                                   step="0.5" 
                                   style="width: 100px">
                            <span class="description">ore</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="new-city-manifesti">Manifesti (ore prima)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="new-city-manifesti" 
                                   value="3" 
                                   min="0" 
                                   step="0.5" 
                                   style="width: 100px">
                            <span class="description">ore</span>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button class="button button-primary" id="add-new-city">Aggiungi Città</button>
                </p>
            </div>
            
            <!-- Cache Notice -->
            <div class="card" style="margin-top: 20px">
                <h3>Cache Management</h3>
                <p>Le modifiche saranno immediatamente visibili. La cache viene automaticamente svuotata ad ogni salvataggio.</p>
                <p>
                    <button class="button" id="flush-all-cache">Svuota Tutta la Cache</button>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for saving city deadline
     */
    public function ajax_save_deadline() {
        check_ajax_referer('dokan_deadline_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $city_slug = sanitize_key($_POST['city'] ?? '');
        $fiori_hours = floatval($_POST['fiori'] ?? 4);
        $manifesti_hours = floatval($_POST['manifesti'] ?? 3);
        
        if (empty($city_slug)) {
            wp_send_json_error('City slug is required');
            return;
        }
        
        $deadlines = [
            'fiori' => PurchaseDeadlineManager::hours_to_seconds($fiori_hours),
            'manifesti' => PurchaseDeadlineManager::hours_to_seconds($manifesti_hours)
        ];
        
        $result = PurchaseDeadlineManager::save_city_deadline($city_slug, $deadlines);
        
        if ($result) {
            wp_send_json_success('Impostazioni salvate e cache aggiornata');
        } else {
            wp_send_json_error('Errore nel salvataggio');
        }
    }
    
    /**
     * AJAX handler for deleting city deadline
     */
    public function ajax_delete_deadline() {
        check_ajax_referer('dokan_deadline_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $city_slug = sanitize_key($_POST['city'] ?? '');
        
        if (empty($city_slug)) {
            wp_send_json_error('City slug is required');
        }
        
        $result = PurchaseDeadlineManager::delete_city_deadline($city_slug);
        
        if ($result) {
            wp_send_json_success('Città eliminata e cache aggiornata');
        } else {
            wp_send_json_error('Errore nella cancellazione');
        }
    }
    
    /**
     * AJAX handler for saving default deadlines
     */
    public function ajax_save_defaults() {
        check_ajax_referer('dokan_deadline_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $fiori_hours = floatval($_POST['fiori'] ?? 4);
        $manifesti_hours = floatval($_POST['manifesti'] ?? 3);
        
        $defaults = [
            'fiori' => PurchaseDeadlineManager::hours_to_seconds($fiori_hours),
            'manifesti' => PurchaseDeadlineManager::hours_to_seconds($manifesti_hours)
        ];
        
        $result = PurchaseDeadlineManager::save_defaults($defaults);
        
        if ($result) {
            wp_send_json_success('Valori default salvati e cache aggiornata');
        } else {
            wp_send_json_error('Errore nel salvataggio');
        }
    }
    
    /**
     * AJAX handler for city search
     */
    public function ajax_search_cities() {
        check_ajax_referer('dokan_deadline_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        if (strlen($search) < 2) {
            wp_send_json_success([]);
            return;
        }
        
        $cities = PurchaseDeadlineManager::search_cities($search);
        
        // Limit results to 10 for performance
        $cities = array_slice($cities, 0, 10);
        
        wp_send_json_success($cities);
    }
}