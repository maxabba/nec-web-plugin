<?php
namespace Dokan_Mods;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Annunci_Widget extends Widget_Base
{

    private UtilsAMClass $UtilsAMClass;

    public function __construct($data = [], $args = null)
    {
        parent::__construct($data, $args);
        $this->UtilsAMClass = new UtilsAMClass();

    }
    public function get_name()
    {
        return 'annunci_widget';
    }

    public function get_title()
    {
        return __('Annunci Widget', 'Dokan_mod');
    }

    public function get_icon()
    {
        return 'fa fa-code';
    }

    public function get_categories()
    {
        return ['dokan-mods-category'];
    }


    protected function _register_controls()
    {
        // Sezione contenuto
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'Dokan_mod'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'icon',
            [
                'label' => __('Icon', 'Dokan_mod'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-bell',
                    'library' => 'fa-solid',
                ],
            ]
        );

        //add control for icon width
        $this->add_control(
            'icon_width',
            [
                'label' => __('Icon Width', 'Dokan_mod'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', '%'],
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 100,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 0.1,
                        'max' => 3,
                        'step' => 0.1,
                    ],
                    '%' => [
                        'min' => 1,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-icon' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        //add control for icon margin
        $this->add_control(
            'icon_margin',
            [
                'label' => __('Icon Margin', 'Dokan_mod'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => [
                    'top' => '0',
                    'right' => '10',
                    'bottom' => '15',
                    'left' => '0',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );



        $this->add_control(
            'text',
            [
                'label' => __('Text', 'Dokan_mod'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Default text', 'Dokan_mod'),
                'placeholder' => __('Type your text here', 'Dokan_mod'),
            ]
        );

        $this->add_control(
            'tooltip',
            [
                'label' => __('Tooltip', 'Dokan_mod'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Default tooltip text', 'Dokan_mod'),
                'placeholder' => __('Type your tooltip text here', 'Dokan_mod'),
            ]
        );



        $this->end_controls_section();

        $this->start_controls_section(
            'button_section',
            [
                'label' => __('Button', 'Dokan_mod'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'Dokan_mod'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Click Me', 'Dokan_mod'),
                'placeholder' => __('Type your button text here', 'Dokan_mod'),
            ]
        );

        $this->add_control(
            'button_link',
            [
                'label' => __('Button Link', 'Dokan_mod'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->UtilsAMClass->get_pages_slug(),
                'default' => 'link1',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'product_section',
            [
                'label' => __('Product', 'Dokan_mod'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'product_id',
            [
                'label' => __('Correlated Product', 'Dokan_mod'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->UtilsAMClass->get_products_name($this->UtilsAMClass->get_default_products()),
                'default' => esc_html__('Select Product', 'Dokan_mod'),
            ]
        );

        $this->end_controls_section();

        // Sezione stile icona
        $this->start_controls_section(
            'icon_style_section',
            [
                'label' => __('Icon', 'Dokan_mod'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );


        $this->add_control(
            'icon_svg_color',
            [
                'label' => __('SVG Icon Color', 'Dokan_mod'),
                'type' => Controls_Manager::COLOR,
                'default' => '#dcbe52',
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-icon svg' => 'fill: {{VALUE}}',
                ],
            ]
        );



        $this->end_controls_section();

        //create a controls section, in this section i have to select e timeout disable the button based on the post date published
        $this->start_controls_section(
            'button_disable_section',
            [
                'label' => __('Time to Disable', 'Dokan_mod'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        //add a label of the current post date time as info for the user using Controls_Manager::HEADING
        $this->add_control(
            'info_label',
            [
                'label' => __('Post pubblicato il: '.get_the_date('Y-m-d H:i:s', get_the_ID()), 'Dokan_mod'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        //add a select control to select 1h 3h 5h 12h 24h 48h 72h
        $this->add_control(
            'button_disable_time',
            [
                'label' => __('Disable after', 'Dokan_mod'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                        'null' => 'Non disabilitare',
                        '1h' => '1h',
                        '3h' => '3h',
                        '5h' => '5h',
                        '12h' => '12h',
                        '24h' => '24h',
                        '48h' => '48h',
                        '72h' => '72h',
                ],
                'default' => 'null',
            ]
        );

        $this->add_control(
            'button_disable_text',
            [
                'label' => __('Button Disable Text', 'Dokan_mod'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Default text', 'Dokan_mod'),
                'placeholder' => __('Type your text here', 'Dokan_mod'),
            ]
        );

        $this->add_control(
            'button_disable_color',
            [
                'label' => __('Button Disable Color', 'Dokan_mod'),
                'type' => Controls_Manager::COLOR,
                'default' => '#000000',
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-button[disabled]' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_disable_background_color',
            [
                'label' => __('Button Disable Background Color', 'Dokan_mod'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f1f1f1',
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-button[disabled]' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();

        // Sezione stile testo
        $this->start_controls_section(
            'text_style_section',
            [
                'label' => __('Text', 'Dokan_mod'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => __('Text Color', 'Dokan_mod'),
                'type' => Controls_Manager::COLOR,
                'default' => '#000000',
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-text' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'text_typography',
                'label' => __('Text Typography', 'Dokan_mod'),
                'selector' => '{{WRAPPER}} .custom-widget-text',
            ]
        );

        $this->end_controls_section();

        // Sezione stile bottone
        // Sezione stile bottone
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Button', 'Dokan_mod'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Button Color', 'Dokan_mod'),
                'type' => Controls_Manager::COLOR,
                'default' => '#FFFFFF',
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-button' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Button Text Color', 'Dokan_mod'),
                'type' => Controls_Manager::COLOR,
                'default' => '#000000',
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-button' => 'color: {{VALUE}}',
                ],
            ]
        );

//add border options
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'label' => __('Border', 'Dokan_mod'),
                'selector' => '{{WRAPPER}} .custom-widget-button',
            ]
        );

//radius
        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'Dokan_mod'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'label' => __('Button Typography', 'Dokan_mod'),
                'selector' => '{{WRAPPER}} .custom-widget-button',
            ]
        );


        $this->end_controls_section();
        $this->start_controls_section(
            'custom_widget_price_style_section',
            [
                'label' => __('Custom Widget Price Style', 'Dokan_mod'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'custom_widget_price_style',
            [
                'label' => __('Color', 'Dokan_mod'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-price' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'custom_widget_price_typography',
                'label' => __('Typography', 'Dokan_mod'),
                'selector' => '{{WRAPPER}} .custom-widget-price',
            ]
        );

        $this->add_control(
            'price_text_style',
            [
                'label' => __('Price Text Color', 'Dokan_mod'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .custom-widget-price .price-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_text_typography',
                'label' => __('Price Text Typography', 'Dokan_mod'),
                'selector' => '{{WRAPPER}} .custom-widget-price .price-text',
            ]
        );

        $this->end_controls_section();

    }

    protected function render()
    {
        require DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'includes/WidgetBuyElementor/render-frontend.php';
    }


}

