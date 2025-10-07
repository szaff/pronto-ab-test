<?php

/**
 * Plugin Name: Pronto A/B Testing
 * Plugin URI: https://studiozaffines.ca/pronto-suite
 * Description: A tool for running A/B testing on your WordPress site to help optimize your marketing.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Studio Zaffines
 * Author URI: https://studiozaffines.ca
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pronto-ab
 * Domain Path: /languages
 * Network: false
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants - Change prefix for your plugin
define('PAB_VERSION', '1.0.0');
define('PAB_PLUGIN_FILE', __FILE__);
define('PAB_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('PAB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PAB_INCLUDES_DIR', PAB_PLUGIN_DIR . 'includes/');
define('PAB_ADMIN_DIR', PAB_PLUGIN_DIR . 'admin/');
define('PAB_PUBLIC_DIR', PAB_PLUGIN_DIR . 'public/');
define('PAB_TEMPLATES_DIR', PAB_PLUGIN_DIR . 'templates/');
define('PAB_ASSETS_URL', PAB_PLUGIN_URL . 'assets/');

require_once PAB_INCLUDES_DIR . 'class-pronto-ab-variation-cpt.php';

/**
 * Main Plugin Class
 * 
 * Handles plugin initialization, hooks, and core functionality
 */
class Pronto_AB
{

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Plugin version
     */
    public $version = PAB_VERSION;

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
        $this->options = get_option('PAB_options', array());
    }

    /**
     * Define WordPress hooks
     */
    private function define_hooks()
    {
        // Plugin lifecycle hooks
        register_activation_hook(PAB_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(PAB_PLUGIN_FILE, array($this, 'deactivate'));
        register_uninstall_hook(PAB_PLUGIN_FILE, array('Pronto_AB', 'uninstall'));

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

        // Database migration check
        add_action('admin_init', array($this, 'check_database_version'));

        // A/B Test AJAX handlers
        add_action('wp_ajax_pronto_ab_get_posts', array('Pronto_AB_Admin', 'ajax_get_posts'));
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
        if (strpos($class_name, 'Pronto_AB') !== 0) {
            return;
        }

        // Convert class name to filename
        $class_file = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';

        // Directories to search in order
        $directories = array(
            PAB_INCLUDES_DIR,
            PAB_ADMIN_DIR,
            PAB_PUBLIC_DIR,
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
            deactivate_plugins(PAB_PLUGIN_BASENAME);
            wp_die(esc_html__('Plugin activation failed due to unmet requirements.', 'pronto-ab'));
        }

        // Set default options
        $default_options = array(
            'version' => PAB_VERSION,
            'first_activation' => current_time('timestamp'),
            'settings' => array(),
        );

        add_option('PAB_options', $default_options);

        // Create database tables if needed
        $this->create_tables();

        // Schedule cron events if needed
        $this->schedule_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag for admin notice
        set_transient('PAB_activation_notice', true, 60);
    }

    /**
     * Check and run database migrations on plugin load
     */
    public function check_database_version()
    {
        // Only check in admin
        if (!is_admin()) {
            return;
        }

        // Check if database needs update
        if (Pronto_AB_Database::needs_update()) {
            $installed_version = get_option('pronto_ab_db_version', '1.0.0');
            Pronto_AB_Database::migrate_database($installed_version);
        }
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
        set_transient('PAB_deactivation_notice', true, 60);
    }

    /**
     * Plugin uninstallation
     */
    public static function uninstall()
    {
        // Remove options
        delete_option('PAB_options');

        // Remove database tables if needed
        Pronto_AB_Database::drop_tables();

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

        // Check if database needs update
        if (Pronto_AB_Database::needs_update()) {
            Pronto_AB_Database::create_tables();
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
        if (class_exists('Pronto_AB_Admin')) {
            $admin_instance = new Pronto_AB_Admin();
        } else {

            // Try to manually load the admin class
            $admin_file = PAB_ADMIN_DIR . 'class-pronto-ab-admin.php';
            if (file_exists($admin_file)) {
                require_once $admin_file;

                if (class_exists('Pronto_AB_Admin')) {
                    $admin_instance = new Pronto_AB_Admin();
                }
            } else {
                error_log("Pronto A/B Debug: Admin file not found at: " . $admin_file);
            }
        }
    }

    /**
     * Initialize public functionality
     */
    private function init_public()
    {
        // Load public classes
        if (class_exists('Pronto_AB_Public')) {
            new Pronto_AB_Public();
        }
    }

    /**
     * Initialize core functionality
     */
    private function init_core()
    {
        // Load core classes that work in both admin and public
        if (class_exists('Pronto_AB_Core')) {
            new Pronto_AB_Core();
        }

        if (class_exists('Pronto_AB_Variation_CPT')) {
            Pronto_AB_Variation_CPT::init();
        }
    }

    /**
     * Register post types
     */
    private function register_post_types()
    {
        // Register the A/B Variation custom post type
        if (class_exists('Pronto_AB_Variation_CPT')) {
            // Post type registration is handled in the class
            // But we can add the submenu here
            add_action('admin_menu', array($this, 'add_variations_submenu'), 20);
        }
    }

    /**
     * Add variations submenu to the A/B Tests menu
     */
    public function add_variations_submenu()
    {
        add_submenu_page(
            'pronto-abs',
            __('Variations', 'pronto-ab'),
            __('Variations', 'pronto-ab'),
            'manage_options',
            'edit.php?post_type=ab_variation'
        );
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
            'pronto-ab-public',
            PAB_ASSETS_URL . 'css/public.css',
            array(),
            PAB_VERSION
        );

        wp_enqueue_script(
            'pronto-ab-public',
            PAB_ASSETS_URL . 'js/public.js',
            array('jquery'),
            PAB_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('pronto-ab-public', 'PAB_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('PAB_ajax_nonce'),
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on plugin admin pages
        if (strpos($hook, 'pronto-ab') === false) {
            return;
        }

        wp_enqueue_style(
            'pronto-ab-admin',
            PAB_ASSETS_URL . 'css/pronto-ab-admin.css',
            array(),
            PAB_VERSION
        );

        wp_enqueue_script(
            'pronto-ab-admin',
            PAB_ASSETS_URL . 'js/pronto-ab-admin.js',
            array('jquery'),
            PAB_VERSION,
            true
        );
    }


    /**
     * Ajax Handler Method
     */
    public function ajax_get_posts()
    {
        check_ajax_referer('ab_test_get_posts', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => 100,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => array('ID', 'post_title')
        ));

        wp_send_json_success($posts);
    }

    /**
     * Create database tables
     */
    private function create_tables()
    {
        // Override in specific plugin implementation
        Pronto_AB_Database::create_tables();
    }

    /**
     * Schedule cron events
     */
    private function schedule_events()
    {
        // Schedule recurring events if needed
        if (! wp_next_scheduled('PAB_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'PAB_daily_cleanup');
        }
    }

    /**
     * Clear scheduled events
     */
    private function clear_scheduled_events()
    {
        wp_clear_scheduled_hook('PAB_daily_cleanup');
    }

    /**
     * Handle admin notices
     */
    private function handle_admin_notices()
    {
        if (get_transient('PAB_activation_notice')) {
            add_action('admin_notices', array($this, 'activation_notice'));
            delete_transient('PAB_activation_notice');
        }
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'pronto-ab',
            false,
            dirname(PAB_PLUGIN_BASENAME) . '/languages/'
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
        update_option('PAB_options', $this->options);
    }

    /**
     * Admin notices
     */
    public function activation_notice()
    {
        echo '<div class="notice notice-success is-dismissible"><p>';
        esc_html_e('Generic Plugin has been activated successfully!', 'pronto-ab');
        echo '</p></div>';
    }

    public function wp_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__('Generic Plugin requires WordPress 5.0 or higher. You are running version %s. Please upgrade WordPress.', 'pronto-ab'),
            esc_html(get_bloginfo('version'))
        );
        echo '</p></div>';
    }

    public function php_version_notice()
    {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__('Generic Plugin requires PHP 7.4 or higher. You are running version %s. Please upgrade PHP.', 'pronto-ab'),
            esc_html(PHP_VERSION)
        );
        echo '</p></div>';
    }
}

/**
 * Initialize the plugin
 * Returns the main plugin instance
 */
function pronto_ab()
{
    return Pronto_AB::get_instance();
}

// Start the plugin
pronto_ab();
