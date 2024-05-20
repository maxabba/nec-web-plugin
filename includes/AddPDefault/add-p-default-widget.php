<?php

namespace Elementor;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Add_P_Default_Widget extends Widget_Base
{

    public function get_name()
    {
        return 'add_p_default_widget';
    }

    public function get_title()
    {
        return __('Aggiungi Pensierini Predefiniti', 'custom-elementor-widget');
    }

    public function get_icon()
    {
        return 'eicon-editor';
    }

    public function get_categories()
    {
        return ['basic'];
    }

    protected function _register_controls()
    {

        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'custom-elementor-widget'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'editor_content',
            [
                'label' => __('Content', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::WYSIWYG,
                'default' => __('Default content', 'custom-elementor-widget'),
            ]
        );

        $this->end_controls_section();

        //add button control
        $this->start_controls_section(
            'button_section',
            [
                'label' => __('Button', 'custom-elementor-widget'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Seleziona', 'custom-elementor-widget'),
            ]
        );

        //margin control
        $this->add_control(
            'button_margin',
            [
                'label' => __('Button Margin', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .add-p-default-widget .add-p-default-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();


        // Text style controls
        $this->start_controls_section(
            'text_style_section',
            [
                'label' => __('Text Style', 'custom-elementor-widget'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => __('Text Color', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .add-p-default-widget .editable-content' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'text_typography',
                'label' => __('Typography', 'custom-elementor-widget'),
                'selector' => '{{WRAPPER}} .add-p-default-widget .editable-content',
            ]
        );

        $this->end_controls_section();

        // Button style controls
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Button Style', 'custom-elementor-widget'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Button Color', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .add-p-default-widget .add-p-default-button' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_bg_color',
            [
                'label' => __('Button Background Color', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .add-p-default-widget .add-p-default-button' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'label' => __('Typography', 'custom-elementor-widget'),
                'selector' => '{{WRAPPER}} .add-p-default-widget .add-p-default-button',
            ]
        );

        $this->end_controls_section();


        //main div card style controls
        $this->start_controls_section(
            'main_div_style_section',
            [
                'label' => __('Main Div Style', 'custom-elementor-widget'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'main_div_bg_color',
            [
                'label' => __('Main Div Background Color', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#FFFFFF', // Default to white background
                'selectors' => [
                    '{{WRAPPER}} .add-p-default-widget' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'main_div_border',
                'label' => __('Border', 'custom-elementor-widget'),
                'selector' => '{{WRAPPER}} .add-p-default-widget',
            ]
        );

        $this->add_control(
            'main_div_border_radius',
            [
                'label' => __('Border Radius', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => [
                    'unit' => 'px',
                    'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', // Default to 2px border radius
                ],
                'selectors' => [
                    '{{WRAPPER}} .add-p-default-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'main_div_box_shadow',
                'label' => __('Box Shadow', 'custom-elementor-widget'),
                'default' => [
                    'horizontal' => '0',
                    'vertical' => '2',
                    'blur' => '6',
                    'spread' => '0',
                    'color' => 'rgba(0,0,0,0.2)', // Default to Material Design-like box shadow
                ],
                'selector' => '{{WRAPPER}} .add-p-default-widget',
            ]
        );

        $this->add_control(
            'main_div_padding',
            [
                'label' => __('Padding', 'custom-elementor-widget'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => [
                    'unit' => 'px',
                    'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', // Default to 16px padding
                ],
                'selectors' => [
                    '{{WRAPPER}} .add-p-default-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();



    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        ?>
        <div class="add-p-default-widget" data-widget-id="<?php echo $this->get_id(); ?>">
            <div class="editable-content" contenteditable="true">
                <?php echo $settings['editor_content']; ?>
            </div>
            <button class="add-p-default-button"><?php echo $settings['button_text'] ?></button>
        </div>
        <?php
    }

    protected function content_template()
    {
    }

    public function get_script_depends()
    {
        return ['add-p-default-widget-js'];
    }
}
