<?php
namespace Elementor;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Elementor_Dynamic_Input_Slider_Widget extends Widget_Base
{

    public function get_name()
    {
        return 'dynamic_input_slider';
    }

    public function get_title()
    {
        return __('Dynamic Input Slider', 'plugin-name');
    }

    public function get_icon()
    {
        return 'eicon-slider-album';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function _register_controls()
    {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'plugin-name'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'inputs',
            [
                'label' => __('Text Inputs', 'plugin-name'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => [
                    [
                        'name' => 'text',
                        'label' => __('Text', 'plugin-name'),
                        'type' => \Elementor\Controls_Manager::WYSIWYG,
                        'default' => __('Testo Manifesto', 'plugin-name'),
                    ],
                ],
                'default' => [],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (!empty($settings['inputs'])) {
            ?>
            <div id="main_swiper_div" style="display: none">
                <div class="swiper-container" style="height: 350px; width: 248px; overflow: hidden;">
                    <div class="swiper-wrapper">
                        <?php foreach ($settings['inputs'] as $input) : ?>
                            <div class="swiper-slide"
                                 style="font-size: 14px; font-family: var(--e-global-typography-text-font-family), Sans-serif; font-weight: var(--e-global-typography-text-font-weight);">
                                <div class="slide-content"><?php echo wp_kses_post($input['text']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="swiper-pagination"></div>

                <div class="swiper-navigation">
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
                <div style="display: flex; justify-content: center; margin-top: 50px;">
                    <button id="copy-button">Seleziona</button>
                </div>
            </div>
            <style>
                .swiper-container{
                    border: 1px solid #000;
                }
                .swiper-button-prev, .swiper-rtl .swiper-button-next {
                    left: -40px;
                    right: auto;
                }

                .swiper-button-next, .swiper-rtl .swiper-button-prev{
                    right: -40px;
                    left: auto;
                }
            </style>
            <?php
        }
    }

    public function get_script_depends()
    {
        return ['swiper', 'custom-slider'];
    }

    public function get_style_depends()
    {
        return ['swiper'];
    }
}
