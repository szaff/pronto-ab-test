<?php

/**
 * A/B Test Admin Interface - Core Class
 * 
 * Handles basic admin functionality, menu creation, and page routing
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-pages.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-forms.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-ajax.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-helpers.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-gutenberg.php';


class Pronto_AB_Admin
{

    use Pronto_AB_Admin_Pages;
    use Pronto_AB_Admin_Forms;
    use Pronto_AB_Admin_Ajax;
    use Pronto_AB_Admin_Helpers;
    use Pronto_AB_Admin_Gutenberg;

    /**
     * Constructor
     */
    public function __construct()
    {
        error_log("Pronto A/B Debug: Constructor called");
        error_log("Pronto A/B Debug: enqueue_gutenberg_assets method exists: " . (method_exists($this, 'enqueue_gutenberg_assets') ? 'YES' : 'NO'));
        error_log("Pronto A/B Debug: is_campaign_edit_page method exists: " . (method_exists($this, 'is_campaign_edit_page') ? 'YES' : 'NO'));
        error_log("Pronto A/B Debug: is_gutenberg_available method exists: " . (method_exists($this, 'is_gutenberg_available') ? 'YES' : 'NO'));

        $this->init_hooks();
    }

    /**
     * Initialize admin hooks
     */
    private function init_hooks()
    {
        // Core admin functionality
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Gutenberg hooks - WITH DEBUG
        $gutenberg_hook_added = add_action('admin_enqueue_scripts', array($this, 'enqueue_gutenberg_assets'));
        error_log("Pronto A/B Debug: Gutenberg hook added: " . ($gutenberg_hook_added ? 'YES' : 'NO'));

        add_action('rest_api_init', array($this, 'register_gutenberg_endpoints'));
        add_action('wp_ajax_pronto_ab_save_gutenberg_blocks', array($this, 'ajax_save_gutenberg_blocks'));

        // Enhanced AJAX handlers for dynamic admin interface
        add_action('wp_ajax_pronto_ab_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_pronto_ab_delete_campaign', array($this, 'ajax_delete_campaign'));
        add_action('wp_ajax_pronto_ab_get_posts', array($this, 'ajax_get_posts'));
        add_action('wp_ajax_pronto_ab_toggle_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_pronto_ab_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_pronto_ab_autosave', array($this, 'ajax_autosave_campaign'));
        add_action('wp_ajax_pronto_ab_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_pronto_ab_preview_variation', array($this, 'ajax_preview_variation'));

        // Debugging
        add_action('admin_enqueue_scripts', array($this, 'debug_wp_scripts'), 999);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        // Main menu page
        add_menu_page(
            __('A/B Tests', 'pronto-ab'),
            __('A/B Tests', 'pronto-ab'),
            'manage_options',
            'pronto-abs',
            array($this, 'campaigns_list_page'),
            'dashicons-chart-line',
            25
        );

        // Submenu pages
        add_submenu_page(
            'pronto-abs',
            __('All Campaigns', 'pronto-ab'),
            __('All Campaigns', 'pronto-ab'),
            'manage_options',
            'pronto-abs',
            array($this, 'campaigns_list_page')
        );

        add_submenu_page(
            'pronto-abs',
            __('Add New Campaign', 'pronto-ab'),
            __('Add New', 'pronto-ab'),
            'manage_options',
            'pronto-abs-new',
            array($this, 'campaign_edit_page')
        );

        add_submenu_page(
            'pronto-abs',
            __('Analytics', 'pronto-ab'),
            __('Analytics', 'pronto-ab'),
            'manage_options',
            'pronto-abs-analytics',
            array($this, 'analytics_page')
        );

        add_submenu_page(
            'pronto-abs',
            __('Settings', 'pronto-ab'),
            __('Settings', 'pronto-ab'),
            'manage_options',
            'pronto-abs-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Handle admin actions (form submissions, URL actions)
     */
    public function handle_admin_actions()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle campaign actions from URL parameters
        if (isset($_GET['action']) && isset($_GET['campaign_id']) && wp_verify_nonce($_GET['_wpnonce'], 'pronto_ab_action')) {
            $campaign_id = intval($_GET['campaign_id']);
            $action = sanitize_text_field($_GET['action']);

            switch ($action) {
                case 'delete':
                    $this->delete_campaign($campaign_id);
                    break;
                case 'activate':
                    $this->toggle_campaign_status($campaign_id, 'active');
                    break;
                case 'pause':
                    $this->toggle_campaign_status($campaign_id, 'paused');
                    break;
                case 'complete':
                    $this->toggle_campaign_status($campaign_id, 'completed');
                    break;
                case 'duplicate':
                    $this->duplicate_campaign($campaign_id);
                    break;
            }
        }

        // Handle bulk actions from form submission
        if (isset($_POST['bulk_action']) && isset($_POST['campaign_ids']) && wp_verify_nonce($_POST['_wpnonce'], 'pronto_ab_bulk_action')) {
            $this->handle_bulk_action($_POST['bulk_action'], $_POST['campaign_ids']);
        }
    }

    /**
     * Enqueue admin assets with enhanced localization
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on plugin admin pages
        if (strpos($hook, 'pronto-abs') === false) {
            return;
        }

        // Enqueue jQuery UI for sortable and slider functionality
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_style('wp-jquery-ui-dialog');

        wp_enqueue_style(
            'pronto-ab-admin',
            PAB_ASSETS_URL . 'css/pronto-ab-admin.css',
            array('wp-jquery-ui-dialog'),
            PAB_VERSION
        );

        wp_enqueue_script(
            'pronto-ab-admin',
            PAB_ASSETS_URL . 'js/pronto-ab-admin.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-slider'),
            PAB_VERSION,
            true
        );

        // Enhanced localization with more data
        wp_localize_script('pronto-ab-admin', 'abTestAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pronto_ab_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete "%s"?', 'pronto-ab'),
                'confirm_bulk_delete' => __('Are you sure you want to delete %d campaign(s)?', 'pronto-ab'),
                'saving' => __('Saving...', 'pronto-ab'),
                'saved' => __('Saved!', 'pronto-ab'),
                'auto_saved' => __('Auto-saved', 'pronto-ab'),
                'error' => __('Error occurred. Please try again.', 'pronto-ab'),
                'loading' => __('Loading...', 'pronto-ab'),
                'select_content' => __('Select content', 'pronto-ab'),
                'error_loading' => __('Error loading posts', 'pronto-ab'),
                'remove' => __('Remove', 'pronto-ab'),
                'unsaved_changes' => __('You have unsaved changes. Are you sure you want to leave?', 'pronto-ab'),
                'validation_errors' => __('Please fix the following errors:', 'pronto-ab')
            ),
            'settings' => array(
                'autosave_interval' => 30000, // 30 seconds
                'stats_refresh_interval' => 30000, // 30 seconds
                'max_variations' => 10
            )
        ));
    }

    /**
     * Settings page for global plugin configuration
     */
    public function settings_page()
    {
        // Handle settings form submission
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'pronto_ab_save_settings')) {
            $this->save_plugin_settings();
        }

        $settings = get_option('pronto_ab_settings', array());
?>
        <div class="wrap">
            <h1><?php esc_html_e('A/B Testing Settings', 'pronto-ab'); ?></h1>

            <?php $this->render_admin_notices(); ?>

            <form method="post" action="">
                <?php wp_nonce_field('pronto_ab_save_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Default Traffic Split', 'pronto-ab'); ?></th>
                        <td>
                            <select name="default_traffic_split">
                                <option value="50/50" <?php selected($settings['default_traffic_split'] ?? '50/50', '50/50'); ?>>50/50</option>
                                <option value="60/40" <?php selected($settings['default_traffic_split'] ?? '50/50', '60/40'); ?>>60/40</option>
                                <option value="70/30" <?php selected($settings['default_traffic_split'] ?? '50/50', '70/30'); ?>>70/30</option>
                                <option value="80/20" <?php selected($settings['default_traffic_split'] ?? '50/50', '80/20'); ?>>80/20</option>
                                <option value="90/10" <?php selected($settings['default_traffic_split'] ?? '50/50', '90/10'); ?>>90/10</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-save Interval', 'pronto-ab'); ?></th>
                        <td>
                            <input type="number" name="autosave_interval" value="<?php echo esc_attr($settings['autosave_interval'] ?? 30); ?>" min="10" max="300" class="small-text"> seconds
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Statistics Refresh', 'pronto-ab'); ?></th>
                        <td>
                            <input type="number" name="stats_refresh" value="<?php echo esc_attr($settings['stats_refresh'] ?? 30); ?>" min="10" max="300" class="small-text"> seconds
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Data Retention', 'pronto-ab'); ?></th>
                        <td>
                            <input type="number" name="data_retention_days" value="<?php echo esc_attr($settings['data_retention_days'] ?? 365); ?>" min="30" max="9999" class="small-text"> days
                            <p class="description"><?php esc_html_e('How long to keep analytics data before automatic cleanup.', 'pronto-ab'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'pronto-ab'), 'primary', 'save_settings'); ?>
            </form>
        </div>
    <?php
    }

    /**
     * Analytics page
     */
    public function analytics_page()
    {
    ?>
        <div class="wrap">
            <h1><?php esc_html_e('A/B Test Analytics', 'pronto-ab'); ?></h1>
            <p><?php esc_html_e('Detailed analytics and reporting will be implemented here.', 'pronto-ab'); ?></p>
        </div>
<?php
    }

    /**
     * Utility Methods
     */

    /**
     * Save plugin settings
     */
    private function save_plugin_settings()
    {
        $settings = array(
            'default_traffic_split' => sanitize_text_field($_POST['default_traffic_split'] ?? '50/50'),
            'autosave_interval' => intval($_POST['autosave_interval'] ?? 30),
            'stats_refresh' => intval($_POST['stats_refresh'] ?? 30),
            'data_retention_days' => intval($_POST['data_retention_days'] ?? 365)
        );

        update_option('pronto_ab_settings', $settings);
        $this->add_admin_notice('success', __('Settings saved successfully.', 'pronto-ab'));
    }

    /**
     * Add admin notice
     */
    private function add_admin_notice($type, $message)
    {
        $notices = get_option('pronto_ab_admin_notices', array());
        $notices[] = array(
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        );
        update_option('pronto_ab_admin_notices', $notices);
    }

    /**
     * Render admin notices
     */
    private function render_admin_notices()
    {
        $notices = get_option('pronto_ab_admin_notices', array());

        foreach ($notices as $notice) {
            // Only show notices from the last 5 minutes
            if (time() - $notice['timestamp'] < 300) {
                echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
                echo '<p>' . esc_html($notice['message']) . '</p>';
                echo '</div>';
            }
        }

        // Clear old notices
        delete_option('pronto_ab_admin_notices');

        // Handle URL-based messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            switch ($message) {
                case 'saved':
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                        esc_html__('Campaign saved successfully.', 'pronto-ab') . '</p></div>';
                    break;
                case 'deleted':
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                        esc_html__('Campaign deleted successfully.', 'pronto-ab') . '</p></div>';
                    break;
                case 'updated':
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                        esc_html__('Campaign updated successfully.', 'pronto-ab') . '</p></div>';
                    break;
                case 'bulk_action_completed':
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                        esc_html__('Bulk action completed.', 'pronto-ab') . '</p></div>';
                    break;
            }
        }
    }

    /**
     * Initialize the admin class - call this from your main plugin file
     */
    public static function init()
    {
        $instance = new self();
        return $instance;
    }
}
