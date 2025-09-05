<?php

/**
 * Simplified Admin functionality for Generic Plugin
 * 
 * Minimal admin interface - just the essentials for a starting point.
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class Generic_Plugin_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize admin hooks
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_filter('plugin_action_links_' . GP_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_options_page(
            __('Generic Plugin Settings', 'generic-plugin'),
            __('Generic Plugin', 'generic-plugin'),
            'manage_options',
            'generic-plugin',
            array($this, 'settings_page')
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings()
    {
        register_setting('gp_settings', 'gp_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'gp_main_section',
            __('Main Settings', 'generic-plugin'),
            array($this, 'section_callback'),
            'generic-plugin'
        );

        add_settings_field(
            'enable_plugin',
            __('Enable Plugin', 'generic-plugin'),
            array($this, 'checkbox_field'),
            'generic-plugin',
            'gp_main_section',
            array('field' => 'enable_plugin', 'label' => __('Enable plugin functionality', 'generic-plugin'))
        );

        add_settings_field(
            'plugin_text',
            __('Plugin Text', 'generic-plugin'),
            array($this, 'text_field'),
            'generic-plugin',
            'gp_main_section',
            array('field' => 'plugin_text', 'placeholder' => __('Enter text...', 'generic-plugin'))
        );
    }

    /**
     * Settings page
     */
    public function settings_page()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('gp_settings');
                do_settings_sections('generic-plugin');
                submit_button();
                ?>
            </form>
        </div>
<?php
    }

    /**
     * Section callback
     */
    public function section_callback()
    {
        echo '<p>' . esc_html__('Configure the basic plugin settings.', 'generic-plugin') . '</p>';
    }

    /**
     * Checkbox field
     */
    public function checkbox_field($args)
    {
        $settings = get_option('gp_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : false;

        printf(
            '<input type="checkbox" id="%1$s" name="gp_settings[%1$s]" value="1" %2$s /> <label for="%1$s">%3$s</label>',
            esc_attr($args['field']),
            checked(1, $value, false),
            esc_html($args['label'])
        );
    }

    /**
     * Text field
     */
    public function text_field($args)
    {
        $settings = get_option('gp_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : '';

        printf(
            '<input type="text" id="%1$s" name="gp_settings[%1$s]" value="%2$s" placeholder="%3$s" class="regular-text" />',
            esc_attr($args['field']),
            esc_attr($value),
            esc_attr($args['placeholder'] ?? '')
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        if (isset($input['enable_plugin'])) {
            $sanitized['enable_plugin'] = (bool) $input['enable_plugin'];
        }

        if (isset($input['plugin_text'])) {
            $sanitized['plugin_text'] = sanitize_text_field($input['plugin_text']);
        }

        return $sanitized;
    }

    /**
     * Add plugin action links
     */
    public function add_action_links($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=generic-plugin'),
            __('Settings', 'generic-plugin')
        );

        array_unshift($links, $settings_link);
        return $links;
    }
}
