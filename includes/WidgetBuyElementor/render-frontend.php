<?php

use Dokan_Mods\UtilsAMClass;

$settings = $this->get_settings_for_display();
$post_id = get_the_ID(); // Get the current post ID
//get the settings button_disable_time
$button_disable_time = $settings['button_disable_time'];
$product_id = is_numeric($settings['product_id']) ? $settings['product_id'] : 0;
$url = home_url($settings['button_link'] . '?post_id=' . $post_id . '&product_id=' . $product_id ); // Construct the URL

$disabled = '';
if ($button_disable_time != 'null') {
    $post_publish_date = get_the_date('Y-m-d H:i:s', $post_id);
//summ the publish date and time with the button_disable_time
    $button_disable_time = strtotime($post_publish_date . ' + ' . $button_disable_time . ' days');
//chek if the current time is greater than the button_disable_time
    if (time() > $button_disable_time) {
        $disabled = 'disabled';
        $url = '';
    }}

$funerale_ora = get_field('funerale_data', $post_id);
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

//if the $timestamp_funerale +30 minutes is equal or greater than the current time and $settings['button_link'] is one of manifesto-top or manifesto-silver the button is disabled
if ($timestamp_funerale + 1800 >= time() && ($settings['button_link'] == 'manifesto-top' || $settings['button_link'] == 'manifesto-silver')) {
    $disabled = 'disabled';
    $url = '';
}
//else if the button_link is one of bouquet composizione-floreale or cuscino and the $timestamp_funerale + 3h is greater than the current time the button is disabled
elseif (($settings['button_link'] == 'bouquet' || $settings['button_link'] == 'composizione-floreale' || $settings['button_link'] == 'cuscino') && $timestamp_funerale + 10800 >= time()) {
    $disabled = 'disabled';
    $url = '';
}

//if $settings['product_id'] is a number
if (is_numeric($settings['product_id'])) {
    $text_before = "Costo: ";
    $product_price = (new UtilsAMClass())->get_product_price($settings['product_id']);
} else {
    $text_before = '';
    $product_price = '';
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
        <?php echo esc_html($settings['tooltip']); ?>
    </span>
</div>