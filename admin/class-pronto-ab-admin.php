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

class Pronto_AB_Admin
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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('plugin_action_links_' . PAB_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_options_page(
            __('Generic Plugin Settings', 'pronto-ab'),
            __('Generic Plugin', 'pronto-ab'),
            'manage_options',
            'pronto-ab',
            array($this, 'settings_page')
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings()
    {
        register_setting('PAB_settings', 'PAB_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'PAB_main_section',
            __('Main Settings', 'pronto-ab'),
            array($this, 'section_callback'),
            'pronto-ab'
        );

        add_settings_field(
            'enable_plugin',
            __('Enable Plugin', 'pronto-ab'),
            array($this, 'checkbox_field'),
            'pronto-ab',
            'PAB_main_section',
            array('field' => 'enable_plugin', 'label' => __('Enable plugin functionality', 'pronto-ab'))
        );

        add_settings_field(
            'plugin_text',
            __('Plugin Text', 'pronto-ab'),
            array($this, 'text_field'),
            'pronto-ab',
            'PAB_main_section',
            array('field' => 'plugin_text', 'placeholder' => __('Enter text...', 'pronto-ab'))
        );
    }

    public function enqueue_assets($hook)
    {
        // Only load on plugin admin pages
        if (strpos($hook, 'pronto-ab') === false) {
            return;
        }

        wp_enqueue_style(
            'pronto-ab-admin',
            PAB_ASSETS_URL . 'css/admin.css',
            array(),
            PAB_VERSION
        );

        wp_enqueue_script(
            'pronto-ab-admin',
            PAB_ASSETS_URL . 'js/admin.js',
            array('jquery'),
            PAB_VERSION,
            true
        );

        // Localize script for admin AJAX if needed
        wp_localize_script('pronto-ab-admin', 'PAB_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('PAB_admin_nonce'),
        ));
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
                settings_fields('PAB_settings');
                do_settings_sections('pronto-ab');
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
        echo '<p>' . esc_html__('Configure the basic plugin settings.', 'pronto-ab') . '</p>';
    }

    /**
     * Checkbox field
     */
    public function checkbox_field($args)
    {
        $settings = get_option('PAB_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : false;

        printf(
            '<input type="checkbox" id="%1$s" name="PAB_settings[%1$s]" value="1" %2$s /> <label for="%1$s">%3$s</label>',
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
        $settings = get_option('PAB_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : '';

        printf(
            '<input type="text" id="%1$s" name="PAB_settings[%1$s]" value="%2$s" placeholder="%3$s" class="regular-text" />',
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
            admin_url('options-general.php?page=pronto-ab'),
            __('Settings', 'pronto-ab')
        );

        array_unshift($links, $settings_link);
        return $links;
    }
}
