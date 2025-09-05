<?php

/**
 * Plugin Name: Generic Plugin Template
 * Plugin URI: https://yourwebsite.com/plugins/generic-plugin
 * Description: A generic, reusable plugin template for WordPress development.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: generic-plugin
 * Domain Path: /languages
 * Network: false
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants - Change prefix for your plugin
define('GP_VERSION', '1.0.0');
define('GP_PLUGIN_FILE', __FILE__);
define('GP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('GP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GP_INCLUDES_DIR', GP_PLUGIN_DIR . 'includes/');
define('GP_ADMIN_DIR', GP_PLUGIN_DIR . 'admin/');
define('GP_PUBLIC_DIR', GP_PLUGIN_DIR . 'public/');
define('GP_TEMPLATES_DIR', GP_PLUGIN_DIR . 'templates/');
define('GP_ASSETS_URL', GP_PLUGIN_URL . 'assets/');

/**
 * Main Plugin Class
 * 
 * Handles plugin initialization, hooks, and core functionality
 */
class Generic_Plugin
{

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Plugin version
     */
    public $version = GP_VERSION;

    /**
     * Plugin options
     */
    public $options = array();

    /**
     * Get single instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Private to prevent multiple instances
     */
    private function __construct()
    {
        $this->load_options();
        $this->define_hooks();
        $this->init();
    }

    /**
     * Load plugin options
     */
    private function load_options()
    {
        $this->options = get_option('gp_options', array());
    }

    /**
     * Define WordPress hooks
     */
    private function define_hooks()
    {
        // Plugin lifecycle hooks
        register_activation_hook(GP_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(GP_PLUGIN_FILE, array($this, 'deactivate'));
        register_uninstall_hook(GP_PLUGIN_FILE, array('Generic_Plugin', 'uninstall'));

        // WordPress initialization hooks
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
        add_action('init', array($this, 'wp_init'));
        add_action('wp_loaded', array($this, 'wp_loaded'));

        // Admin hooks
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'));

        // Asset hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Initialize the plugin
     */
    private function init()
    {
        // Setup autoloader
        spl_autoload_register(array($this, 'autoload'));

        // Load text domain for translations
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Class autoloader
     * Automatically loads classes from includes/, admin/, and public/ directories
     */
    public function autoload($class_name)
    {
        // Only autoload classes that start with our prefix (adjust as needed)
        if (strpos($class_name, 'Generic_Plugin') !== 0) {
            return;
        }

        // Convert class name to filename
        $class_file = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';

        // Directories to search in order
        $directories = array(
            GP_INCLUDES_DIR,
            GP_ADMIN_DIR,
            GP_PUBLIC_DIR,
        );

        foreach ($directories as $directory) {
            $file_path = $directory . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Check requirements before activation
        if (! $this->check_requirements()) {
            deactivate_plugins(GP_PLUGIN_BASENAME);
            wp_die(esc_html__('Plugin activation failed due to unmet requirements.', 'generic-plugin'));
        }

        // Set default options
        $default_options = array(
            'version' => GP_VERSION,
            'first_activation' => current_time('timestamp'),
            'settings' => array(),
        );

        add_option('gp_options', $default_options);

        // Create database tables if needed
        $this->create_tables();

        // Schedule cron events if needed
        $this->schedule_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag for admin notice
        set_transient('gp_activation_notice', true, 60);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clear scheduled events
        $this->clear_scheduled_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set deactivation flag if needed
        set_transient('gp_deactivation_notice', true, 60);
    }

    /**
     * Plugin uninstallation
     */
    public static function uninstall()
    {
        // Remove options
        delete_option('gp_options');

        // Remove database tables if needed
        // self::drop_tables();

        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Plugins loaded hook
     */
    public function plugins_loaded()
    {
        // Check requirements
        if (! $this->check_requirements()) {
            return;
        }

        // Load components based on context
        if (is_admin()) {
            $this->init_admin();
        }

        if (! is_admin() || wp_doing_ajax()) {
            $this->init_public();
        }

        // Initialize core functionality
        $this->init_core();
    }

    /**
     * WordPress init hook
     */
    public function wp_init()
    {
        // Register post types, taxonomies, shortcodes, etc.
        $this->register_post_types();
        $this->register_taxonomies();
        $this->register_shortcodes();
    }

    /**
     * WordPress loaded hook
     */
    public function wp_loaded()
    {
        // Everything is loaded, final initialization
    }

    /**
     * Admin init hook
     */
    public function admin_init()
    {
        // Admin-specific initialization
        $this->handle_admin_notices();
    }

    /**
     * Admin menu hook
     */
    public function admin_menu()
    {
        // Add admin menu items - override in child implementation
    }

    /**
     * Check plugin requirements
     */
    private function check_requirements()
    {
        $requirements_met = true;

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            $requirements_met = false;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            $requirements_met = false;
        }

        // Check required PHP extensions, plugins, etc.
        // Add more checks as needed

        return $requirements_met;
    }

    /**
     * Initialize admin functionality
     */
    private function init_admin()
    {
        // Load admin classes
        if (class_exists('Generic_Plugin_Admin')) {
            new Generic_Plugin_Admin();
        }
    }

    /**
     * Initialize public functionality
     */
    private function init_public()
    {
        // Load public classes
        if (class_exists('Generic_Plugin_Public')) {
            new Generic_Plugin_Public();
        }
    }

    /**
     * Initialize core functionality
     */
    private function init_core()
    {
        // Load core classes that work in both admin and public
        if (class_exists('Generic_Plugin_Core')) {
            new Generic_Plugin_Core();
        }
    }

    /**
     * Register post types
     */
    private function register_post_types()
    {
        // Override in specific plugin implementation
    }

    /**
     * Register taxonomies
     */
    private function register_taxonomies()
    {
        // Override in specific plugin implementation
    }

    /**
     * Register shortcodes
     */
    private function register_shortcodes()
    {
        // Override in specific plugin implementation
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets()
    {
        // Enqueue frontend CSS and JS
        wp_enqueue_style(
            'generic-plugin-public',
            GP_ASSETS_URL . 'css/public.css',
            array(),
            GP_VERSION
        );

        wp_enqueue_script(
            'generic-plugin-public',
            GP_ASSETS_URL . 'js/public.js',
            array('jquery'),
            GP_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('generic-plugin-public', 'gp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gp_ajax_nonce'),
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on plugin admin pages
        if (strpos($hook, 'generic-plugin') === false) {
            return;
        }

        wp_enqueue_style(
            'generic-plugin-admin',
            GP_ASSETS_URL . 'css/admin.css',
            array(),
            GP_VERSION
        );

        wp_enqueue_script(
            'generic-plugin-admin',
            GP_ASSETS_URL . 'js/admin.js',
            array('jquery'),
            GP_VERSION,
            true
        );
    }

    /**
     * Create database tables
     */
    private function create_tables()
    {
        // Override in specific plugin implementation
        // Example table creation code would go here
    }

    /**
     * Schedule cron events
     */
    private function schedule_events()
    {
        // Schedule recurring events if needed
        if (! wp_next_scheduled('gp_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'gp_daily_cleanup');
        }
    }

    /**
     * Clear scheduled events
     */
    private function clear_scheduled_events()
    {
        wp_clear_scheduled_hook('gp_daily_cleanup');
    }

    /**
     * Handle admin notices
     */
    private function handle_admin_notices()
    {
        if (get_transient('gp_activation_notice')) {
            add_action('admin_notices', array($this, 'activation_notice'));
            delete_transient('gp_activation_notice');
        }
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'generic-plugin',
            false,
            dirname(GP_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Get plugin option
     */
    public function get_option($key, $default = null)
    {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    /**
     * Update plugin option
     */
    public function update_option($key, $value)
    {
        $this->options[$key] = $value;
        update_option('gp_options', $this->options);
    }

    /**
     * Admin notices
     */
    public function activation_notice()
    {
        echo '<div class="notice notice-success is-dismissible"><p>';
        esc_html_e('Generic Plugin has been activated successfully!', 'generic-plugin');
        echo '</p></div>';
    }

    public function wp_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__('Generic Plugin requires WordPress 5.0 or higher. You are running version %s. Please upgrade WordPress.', 'generic-plugin'),
            esc_html(get_bloginfo('version'))
        );
        echo '</p></div>';
    }

    public function php_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__('Generic Plugin requires PHP 7.4 or higher. You are running version %s. Please upgrade PHP.', 'generic-plugin'),
            esc_html(PHP_VERSION)
        );
        echo '</p></div>';
    }
}

/**
 * Initialize the plugin
 * Returns the main plugin instance
 */
function generic_plugin()
{
    return Generic_Plugin::get_instance();
}

// Start the plugin
generic_plugin();
