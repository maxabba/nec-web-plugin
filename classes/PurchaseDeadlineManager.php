<?php
namespace Dokan_Mods;

class PurchaseDeadlineManager {
    
    const OPTION_PREFIX = 'dokan_deadline_city_';
    const DEFAULTS_OPTION = 'dokan_deadline_defaults';
    const CACHE_GROUP = 'dokan_deadlines';
    
    // Product categories mapping
    const MANIFESTO_PRODUCTS = ['manifesto-top', 'manifesto-silver'];
    const FLORAL_PRODUCTS = ['bouquet', 'composizione-floreale', 'cuscino'];
    
    // Default deadlines in seconds
    const DEFAULT_FIORI_DEADLINE = 14400;     // 4 ore
    const DEFAULT_MANIFESTI_DEADLINE = 10800;  // 3 ore
    
    /**
     * Get deadline for a specific city and product type
     * 
     * @param string $city_slug City slug
     * @param string $product_type 'fiori' or 'manifesti'
     * @return int Deadline in seconds
     */
    public static function get_deadline($city_slug, $product_type) {
        // Check cache first
        $cache_key = $city_slug . '_' . $product_type;
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get city-specific deadlines
        $city_deadlines = get_option(self::OPTION_PREFIX . sanitize_key($city_slug));
        
        if (!empty($city_deadlines) && isset($city_deadlines[$product_type])) {
            $deadline = intval($city_deadlines[$product_type]);
        } else {
            // Fallback to defaults
            $defaults = self::get_defaults();
            $deadline = $defaults[$product_type] ?? self::get_system_default($product_type);
        }
        
        // Cache the result
        wp_cache_set($cache_key, $deadline, self::CACHE_GROUP, HOUR_IN_SECONDS);
        
        return $deadline;
    }
    
    /**
     * Check if purchase is allowed based on funeral time and product category
     * 
     * @param int $post_id Post ID
     * @param string $product_category Product category slug
     * @return array ['allowed' => bool, 'deadline' => int, 'message' => string]
     */
    public static function check_purchase_availability($post_id, $product_category) {
        $result = [
            'allowed' => true,
            'deadline' => 0,
            'message' => ''
        ];
        
        // Get funeral timestamp (funerale_data now contains datetime)
        $funerale_datetime = get_field('funerale_data', $post_id);
        
        if (!$funerale_datetime) {
            return $result; // Allow purchase if no funeral time set
        }
        
        // Handle different datetime formats
        if (is_string($funerale_datetime)) {
            $timestamp_funerale = strtotime($funerale_datetime);
        } elseif (is_object($funerale_datetime) && method_exists($funerale_datetime, 'getTimestamp')) {
            // DateTime object
            $timestamp_funerale = $funerale_datetime->getTimestamp();
        } elseif (is_array($funerale_datetime) && isset($funerale_datetime['date'])) {
            // ACF datetime array format
            $timestamp_funerale = strtotime($funerale_datetime['date']);
        } else {
            // Fallback - try to convert to string and parse
            $timestamp_funerale = strtotime((string)$funerale_datetime);
        }
        
        if ($timestamp_funerale === false) {
            return $result; // Allow purchase if timestamp invalid
        }
        
        // Get city from post
        $city = get_field('citta', $post_id);
        if (!$city) {
            $city = 'default';
        }
        $city_slug = sanitize_title($city);
        
        // Determine product type and get deadline
        $deadline = 0;
        $product_type = '';
        
        if (in_array($product_category, self::MANIFESTO_PRODUCTS)) {
            $product_type = 'manifesti';
            $deadline = self::get_deadline($city_slug, 'manifesti');
        } elseif (in_array($product_category, self::FLORAL_PRODUCTS)) {
            $product_type = 'fiori';
            $deadline = self::get_deadline($city_slug, 'fiori');
        } else {
            // Unknown product type, allow purchase
            return $result;
        }
        
        // Calculate cutoff time
        $cutoff_time = $timestamp_funerale - $deadline;
        $current_time = current_time('timestamp');
        
        // Check if purchase is still allowed
        if ($current_time >= $cutoff_time) {
            $result['allowed'] = false;
            $result['deadline'] = $deadline;
            $result['message'] = 'Spiacenti, non è più possibile ordinare questo prodotto per questo defunto';
            
        }
        
        return $result;
    }
    
    /**
     * Save deadline for a specific city
     * 
     * @param string $city_slug City slug
     * @param array $deadlines ['fiori' => seconds, 'manifesti' => seconds]
     * @return bool
     */
    public static function save_city_deadline($city_slug, $deadlines) {
        $city_slug = sanitize_key($city_slug);
        
        // Validate and sanitize input
        $clean_deadlines = [
            'fiori' => max(0, intval($deadlines['fiori'] ?? self::DEFAULT_FIORI_DEADLINE)),
            'manifesti' => max(0, intval($deadlines['manifesti'] ?? self::DEFAULT_MANIFESTI_DEADLINE))
        ];
        
        $option_name = self::OPTION_PREFIX . $city_slug;
        
        // Check if option already exists
        $existing_value = get_option($option_name, 'NOT_FOUND');
        
        $result = update_option($option_name, $clean_deadlines);
        
        // If update_option failed, check why
        if (!$result) {
            // Check if the values are identical (update_option returns false if no change)
            if ($existing_value !== 'NOT_FOUND' && $existing_value === $clean_deadlines) {
                $result = true;
            } else {
                // Try add_option if update_option failed
                $add_result = add_option($option_name, $clean_deadlines);
                
                if ($add_result) {
                    $result = true;
                } else {
                    // Last resort: try direct database insert
                    global $wpdb;
                    
                    $serialized_value = maybe_serialize($clean_deadlines);
                    $sql = $wpdb->prepare(
                        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'yes') 
                         ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
                        $option_name,
                        $serialized_value
                    );
                    
                    $db_result = $wpdb->query($sql);
                    
                    if ($db_result !== false) {
                        $result = true;
                    }
                }
            }
        }
        
        // Flush cache for this city
        self::flush_city_cache($city_slug);
        
        return $result;
    }
    
    /**
     * Get all cities with their deadlines
     * 
     * @return array
     */
    public static function get_all_cities() {
        global $wpdb;
        
        $cities = [];
        $option_name = self::OPTION_PREFIX . '%';
        
        // Get all deadline options
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $option_name
        ));
        
        foreach ($results as $row) {
            $city_slug = str_replace(self::OPTION_PREFIX, '', $row->option_name);
            $deadlines = maybe_unserialize($row->option_value);
            
            $cities[$city_slug] = [
                'slug' => $city_slug,
                'name' => ucfirst(str_replace('-', ' ', $city_slug)),
                'fiori' => $deadlines['fiori'] ?? self::DEFAULT_FIORI_DEADLINE,
                'manifesti' => $deadlines['manifesti'] ?? self::DEFAULT_MANIFESTI_DEADLINE
            ];
        }
        
        return $cities;
    }
    
    /**
     * Delete deadline for a specific city
     * 
     * @param string $city_slug
     * @return bool
     */
    public static function delete_city_deadline($city_slug) {
        $city_slug = sanitize_key($city_slug);
        $result = delete_option(self::OPTION_PREFIX . $city_slug);
        
        // Flush cache
        self::flush_city_cache($city_slug);
        
        return $result;
    }
    
    /**
     * Get default deadlines
     * 
     * @return array
     */
    public static function get_defaults() {
        $defaults = get_option(self::DEFAULTS_OPTION);
        
        if (empty($defaults)) {
            $defaults = [
                'fiori' => self::DEFAULT_FIORI_DEADLINE,
                'manifesti' => self::DEFAULT_MANIFESTI_DEADLINE
            ];
            update_option(self::DEFAULTS_OPTION, $defaults);
        }
        
        return $defaults;
    }
    
    /**
     * Save default deadlines
     * 
     * @param array $defaults
     * @return bool
     */
    public static function save_defaults($defaults) {
        $clean_defaults = [
            'fiori' => max(0, intval($defaults['fiori'] ?? self::DEFAULT_FIORI_DEADLINE)),
            'manifesti' => max(0, intval($defaults['manifesti'] ?? self::DEFAULT_MANIFESTI_DEADLINE))
        ];
        
        $result = update_option(self::DEFAULTS_OPTION, $clean_defaults);
        
        // Flush all cache
        self::flush_all_cache();
        
        return $result;
    }
    
    /**
     * Get system default for product type
     * 
     * @param string $product_type
     * @return int
     */
    private static function get_system_default($product_type) {
        return $product_type === 'fiori' 
            ? self::DEFAULT_FIORI_DEADLINE 
            : self::DEFAULT_MANIFESTI_DEADLINE;
    }
    
    /**
     * Flush cache for specific city
     * 
     * @param string $city_slug
     */
    public static function flush_city_cache($city_slug) {
        wp_cache_delete($city_slug . '_fiori', self::CACHE_GROUP);
        wp_cache_delete($city_slug . '_manifesti', self::CACHE_GROUP);
        
        // Also flush WP object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }
    
    /**
     * Flush all deadline cache
     */
    public static function flush_all_cache() {
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        } else {
            // Fallback: flush entire cache
            wp_cache_flush();
        }
    }
    
    /**
     * Convert hours to seconds
     * 
     * @param float $hours
     * @return int
     */
    public static function hours_to_seconds($hours) {
        return intval($hours * 3600);
    }
    
    /**
     * Convert seconds to hours
     * 
     * @param int $seconds
     * @return float
     */
    public static function seconds_to_hours($seconds) {
        return round($seconds / 3600, 2);
    }
    
    /**
     * Search cities by name (for autocomplete)
     * 
     * @param string $search Search term (minimum 2 characters)
     * @return array Array of cities with id and text
     */
    public static function search_cities($search) {
        if (strlen(trim($search)) < 2) {
            return [];
        }
        
        // Use existing DbClass method
        if (class_exists('Dokan_Mods\DbClass')) {
            global $dbClassInstance;
            
            if ($dbClassInstance) {
                $results = $dbClassInstance->get_comune_by_typing($search);
                
                // The function already returns the correct format, just use it directly
                if (!empty($results)) {
                    return $results;
                }
            }
        }
        
        // Fallback: search in existing configured cities
        $cities = self::get_all_cities();
        $results = [];
        
        foreach ($cities as $city) {
            if (stripos($city['name'], $search) !== false) {
                $results[] = [
                    'id' => $city['slug'],
                    'text' => $city['name']
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get city name from slug
     * 
     * @param string $city_slug
     * @return string
     */
    public static function get_city_name($city_slug) {
        // Try to get from existing configured cities first
        $cities = self::get_all_cities();
        if (isset($cities[$city_slug])) {
            return $cities[$city_slug]['name'];
        }
        
        // Try to get from database
        if (class_exists('Dokan_Mods\DbClass')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'dkm_comuni';
            
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT nome FROM $table_name WHERE nome = %s OR nome = %s LIMIT 1",
                $city_slug,
                ucfirst(str_replace('-', ' ', $city_slug))
            ));
            
            if ($result) {
                return $result;
            }
        }
        
        // Fallback: convert slug to readable name
        return ucfirst(str_replace('-', ' ', $city_slug));
    }
}