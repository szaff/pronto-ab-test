<?php

/**
 * Public functionality for Generic Plugin
 * 
 * Minimal frontend functionality - just the essentials.
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class Generic_Plugin_Public
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('init', array($this, 'register_shortcodes'));
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets()
    {
        // Only load if shortcode is present
        if (! $this->should_load_assets()) {
            return;
        }

        wp_enqueue_style(
            'generic-plugin',
            GP_ASSETS_URL . 'css/public.css',
            array(),
            GP_VERSION
        );

        wp_enqueue_script(
            'generic-plugin',
            GP_ASSETS_URL . 'js/public.js',
            array('jquery'),
            GP_VERSION,
            true
        );
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes()
    {
        add_shortcode('generic_plugin', array($this, 'shortcode_handler'));
    }

    /**
     * Shortcode handler
     */
    public function shortcode_handler($atts)
    {
        $atts = shortcode_atts(array(
            'class' => 'generic-plugin'
        ), $atts);

        return '<div class="' . esc_attr($atts['class']) . '">' .
            esc_html__('Generic Plugin Output', 'generic-plugin') .
            '</div>';
    }

    /**
     * Check if assets should load
     */
    private function should_load_assets()
    {
        global $post;
        return is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'generic_plugin');
    }
}
