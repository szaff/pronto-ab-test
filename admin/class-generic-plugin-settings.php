<?php

/**
 * Settings functionality for Generic Plugin
 * 
 * Handles plugin settings registration, validation, and display.
 * Can be extended for more complex settings management.
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class Generic_Plugin_Settings
{

    /**
     * Get setting value
     */
    public function get($key, $default = null)
    {
        $settings = get_option('gp_settings', array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Update setting value
     */
    public function update($key, $value)
    {
        $settings = get_option('gp_settings', array());
        $settings[$key] = $value;
        return update_option('gp_settings', $settings);
    }

    /**
     * Get all settings
     */
    public function get_all()
    {
        return get_option('gp_settings', array());
    }
}
