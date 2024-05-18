<?php
$settings = $this->get_settings_for_display();
$post_id = get_the_ID(); // Get the current post ID
//get the settings button_disable_time
$button_disable_time = $settings['button_disable_time'];
$url = home_url($settings['button_link'] . '?post_id=' . $post_id); // Construct the URL

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
            <a class="custom-widget-button"  <?php echo esc_html($disabled) ?>>
                <?php echo esc_html($settings['button_text']); ?>
            </a>
        <?php else : ?>
        <a class="custom-widget-button" href="<?php echo esc_html($url) ?>" <?php echo esc_html($disabled) ?>>
            <?php echo esc_html($settings['button_text']); ?>
        </a>
        <?php endif; ?>
    </div>
    <span class="custom-widget-price">

    </span>
    <span class="custom-widget-tooltip">
        <?php echo esc_html($settings['tooltip']); ?>
    </span>
</div>