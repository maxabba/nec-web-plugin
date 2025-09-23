<?php

use Dokan_Mods\UtilsAMClass;

$settings = $this->get_settings_for_display();
$post_id = get_the_ID(); // Get the current post ID

// Constants for time calculations (in seconds)
define('MINUTES_30', 30 * 60);
define('HOURS_3', 3 * 60 * 60);

// Initialize variables with proper validation
$button_disable_time = isset($settings['button_disable_time']) ? sanitize_text_field($settings['button_disable_time']) : null;
$product_id = isset($settings['product_id']) && is_numeric($settings['product_id']) ? intval($settings['product_id']) : 0;
$button_link = isset($settings['button_link']) ? sanitize_text_field($settings['button_link']) : '';
$url = !empty($button_link) ? home_url($button_link . '?post_id=' . intval($post_id) . '&product_id=' . intval($product_id)) : '';

// Initialize disabled state
$disabled = '';

//get the wp current time with the utc offset
$current_time = current_time('timestamp');

// Check button disable time based on post publish date
if (!empty($button_disable_time) && $button_disable_time !== 'null' && is_numeric($button_disable_time)) {
    $post_publish_date = get_the_date('Y-m-d H:i:s', $post_id);
    if ($post_publish_date) {
        $disable_timestamp = strtotime($post_publish_date . ' + ' . intval($button_disable_time) . ' days');
        if ($disable_timestamp !== false && $current_time > $disable_timestamp) {
            $disabled = 'disabled';
            $url = '';
        }
    }
}

// Get funeral date/time with fallback to publication date
$funerale_ora = get_field('funerale_data', $post_id);
$timestamp_funerale = false;

if (!empty($funerale_ora)) {
    // First, remove the day name from the string
    $date_without_day = preg_replace('/^[a-zàèéìòù]+ /iu', '', $funerale_ora);
    // Convert the cleaned string to a timestamp
    $timestamp_funerale = strtotime($date_without_day);

    if ($timestamp_funerale === false) {
        // Fallback method if the above doesn't work
        // Parse the date manually
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{1,2})/', $date_without_day, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $hour = $matches[4];
            $minute = $matches[5];

            $timestamp_funerale = mktime($hour, $minute, 0, $month, $day, $year);
        }
    }
}

// Fallback: if no funeral date/time is set, use publication date/time
if ($timestamp_funerale === false) {
    $post_publish_date = get_the_date('Y-m-d H:i:s', $post_id);
    if ($post_publish_date) {
        $timestamp_funerale = strtotime($post_publish_date);
    }
}

// Check funeral-based disable conditions with proper validation
if ($timestamp_funerale !== false && !empty($button_link)) {
    // Define product categories with their time limits
    $manifesto_products = ['manifesto-top', 'manifesto-silver'];
    $floral_products = ['bouquet', 'composizione-floreale', 'cuscino'];
    
    // Check manifesto products (30 minutes before funeral)
    if (in_array($button_link, $manifesto_products) &&
        ($timestamp_funerale + MINUTES_30 <= $current_time)) {
        $disabled = 'disabled';
        $url = '';
    }
    // Check floral products (3 hours before funeral)
    elseif (in_array($button_link, $floral_products) &&
            ($timestamp_funerale + HOURS_3 <= $current_time)) {
        $disabled = 'disabled';
        $url = '';
    }
}

//if $settings['product_id'] is a number
if (is_numeric($settings['product_id'])) {
    $text_before = "Costo: ";
    if($button_link == 'pensierini'){
        $product_wc = wc_get_product($settings['product_id']);
        $product_price = wc_price($product_wc->get_price());
    }else{
        $product_price = (new UtilsAMClass())->get_product_price($settings['product_id'], $post_id);
    }

    //if product price is empty set remove text before
    if(empty($product_price)){
        $text_before = '';
        //force the disabled state
        $disabled = 'disabled';
    }
    $product_description = (new UtilsAMClass())->get_product_description($settings['product_id'], $post_id);
} else {
    $text_before = '';
    $product_price = '';
    $product_description = null;
}



?>
<div class="custom-widget">
    <div class="display-flex-custom-widget">
        <div class="left-content">
            <div class="custom-widget-icon">
                <?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']); ?>
            </div>
            <p class="custom-widget-text">
                <?php echo esc_html($settings['text']); ?>
                <i class="fa fa-info-circle custom-widget-info-icon"></i>
            </p>
        </div>
        <?php if ($disabled == 'disabled') : ?>
        <?php else : ?>
        <a class="custom-widget-button" href="<?php echo esc_html($url) ?>" <?php echo esc_html($disabled) ?>>
            <?php echo esc_html($settings['button_text']); ?>
        </a>
        <?php endif; ?>
    </div>
    <?php if ($disabled == 'disabled') : ?>
        <span class="custom-widget-disabled-text">Spiacenti, non è più possibile ordinare questo prodotto per questo defunto</span>
    <?php endif; ?>
        <span class="custom-widget-price">
            <?php echo esc_html($text_before); ?><span class="price-text"><?php echo $product_price; ?></span>
        </span>
    <span class="custom-widget-tooltip">
        <?php echo wp_kses_post($product_description ? $product_description : $settings['tooltip']); ?>
    </span>
</div>