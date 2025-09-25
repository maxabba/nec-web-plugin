<?php

use Dokan_Mods\UtilsAMClass;
use Dokan_Mods\PurchaseDeadlineManager;

$settings = $this->get_settings_for_display();
$post_id = get_the_ID(); // Get the current post ID

// Initialize variables with proper validation
$button_disable_time = isset($settings['button_disable_time']) ? sanitize_text_field($settings['button_disable_time']) : null;
$product_id = isset($settings['product_id']) && is_numeric($settings['product_id']) ? intval($settings['product_id']) : 0;
$button_link = isset($settings['button_link']) ? sanitize_text_field($settings['button_link']) : '';
$url = !empty($button_link) ? home_url($button_link . '?post_id=' . intval($post_id) . '&product_id=' . intval($product_id)) : '';

// Initialize disabled state
$disabled = '';
$hide= false;
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

// Check purchase availability using the new manager
$availability = PurchaseDeadlineManager::check_purchase_availability($post_id, $button_link);

// If purchase is not allowed, disable the button
if (!$availability['allowed'] && !empty($button_link)) {
    $disabled = 'disabled';
    $url = '';
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
        $hide = true;
    }
    $product_description = (new UtilsAMClass())->get_product_description($settings['product_id'], $post_id);
} else {
    $text_before = '';
    $product_price = '';
    $product_description = null;
}


if(!$hide){
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

<?php
}