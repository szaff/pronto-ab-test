<?php

/**
 * FIXED A/B Test Admin Interface - Core Class
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Load all traits
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-pages.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-forms.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-ajax.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-helpers.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-statistics.php';
require_once PAB_ADMIN_DIR . 'trait-pronto-ab-admin-analytics.php';

class Pronto_AB_Admin
{
    use Pronto_AB_Admin_Pages;
    use Pronto_AB_Admin_Forms;
    use Pronto_AB_Admin_Ajax;
    use Pronto_AB_Admin_Helpers;
    use Pronto_AB_Admin_Statistics;
    use Pronto_AB_Admin_Analytics;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * FIXED: Initialize admin hooks with proper order
     */
    private function init_hooks()
    {
        // Core admin functionality
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));

        // CRITICAL FIX: Load admin assets early to ensure dependencies
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'), 5);


        // AJAX handlers - Enhanced set
        $this->register_ajax_handlers();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {

        // Main menu page
        $main_page = add_menu_page(
            __('A/B Tests', 'pronto-ab'),
            __('A/B Tests', 'pronto-ab'),
            'manage_options',
            'pronto-abs',
            array($this, 'campaigns_list_page'),
            'dashicons-chart-line',
            25
        );


        // Submenu pages
        $submenu_1 = add_submenu_page(
            'pronto-abs',
            __('All Campaigns', 'pronto-ab'),
            __('All Campaigns', 'pronto-ab'),
            'manage_options',
            'pronto-abs',
            array($this, 'campaigns_list_page')
        );

        $submenu_2 = add_submenu_page(
            'pronto-abs',
            __('Add New Campaign', 'pronto-ab'),
            __('Add New', 'pronto-ab'),
            'manage_options',
            'pronto-abs-new',
            array($this, 'campaign_edit_page')
        );

        $submenu_3 = add_submenu_page(
            'pronto-abs',
            __('Analytics', 'pronto-ab'),
            __('Analytics', 'pronto-ab'),
            'manage_options',
            'pronto-abs-analytics',
            array($this, 'analytics_page')
        );

        $submenu_4 = add_submenu_page(
            'pronto-abs',
            __('Settings', 'pronto-ab'),
            __('Settings', 'pronto-ab'),
            'manage_options',
            'pronto-abs-settings',
            array($this, 'settings_page')
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                'pronto-abs',
                __('Test Data Generator', 'pronto-ab'),
                __('Test Data', 'pronto-ab'),
                'manage_options',
                'pronto-abs-test-data',
                array('Pronto_AB_Test_Data_Generator', 'render_admin_page')
            );

            add_submenu_page(
                'pronto-abs',
                __('Test Winner Declaration', 'pronto-ab'),
                __('🔧 Test Winner', 'pronto-ab'),
                'manage_options',
                'pronto-ab-test-winner',
                array($this, 'render_test_winner_page')
            );
        }
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
     * FIXED: Enqueue admin assets with enhanced dependency management
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on plugin admin pages
        if (strpos($hook, 'pronto-abs') === false) {
            return;
        }


        // Core WordPress UI components
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_style('wp-jquery-ui-dialog');

        // Plugin admin styles
        wp_enqueue_style(
            'pronto-ab-admin',
            PAB_ASSETS_URL . 'css/pronto-ab-admin.css',
            array('wp-jquery-ui-dialog'),
            PAB_VERSION
        );

        if ($hook === 'a-b-tests_page_pronto-abs-analytics') {
            wp_enqueue_style(
                'pronto-ab-analytics',
                PAB_ASSETS_URL . 'css/pronto-ab-analytics.css',
                array(),
                PAB_VERSION
            );
        }

        // Plugin admin scripts  
        wp_enqueue_script(
            'pronto-ab-admin',
            PAB_ASSETS_URL . 'js/pronto-ab-admin.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-slider'),
            PAB_VERSION,
            true
        );

        if ($hook === 'a-b-tests_page_pronto-abs-analytics') {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );

            wp_enqueue_script(
                'chartjs-adapter-date-fns',
                'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
                array('chartjs'),
                '3.0.0',
                true
            );

            wp_enqueue_script(
                'pronto-ab-analytics',
                PAB_ASSETS_URL . 'js/pronto-ab-analytics.js',
                array('jquery', 'chartjs'),
                PAB_VERSION,
                true
            );
        }

        // Enhanced localization
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
                'validation_errors' => __('Please fix the following errors:', 'pronto-ab'),
                'refreshing' => __('Refreshing...', 'pronto-ab'),
                'stats_refreshed' => __('Statistics refreshed!', 'pronto-ab'),
                'auto_refresh_stats' => true, // Set to false to disable auto-refresh
            ),
            'settings' => array(
                'autosave_interval' => 30000,
                'stats_refresh_interval' => 30000,
                'max_variations' => 10
            ),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }

    /**
     * Settings page
     */
    public function settings_page()
    {
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
                        <th scope="row"><?php esc_html_e('Editor Type', 'pronto-ab'); ?></th>
                        <td>
                            <select name="preferred_editor">
                                <option value="gutenberg" <?php selected($settings['preferred_editor'] ?? 'gutenberg', 'gutenberg'); ?>>
                                    <?php esc_html_e('Block Editor (Gutenberg)', 'pronto-ab'); ?>
                                </option>
                                <option value="classic" <?php selected($settings['preferred_editor'] ?? 'gutenberg', 'classic'); ?>>
                                    <?php esc_html_e('Classic Editor', 'pronto-ab'); ?>
                                </option>
                                <option value="html" <?php selected($settings['preferred_editor'] ?? 'gutenberg', 'html'); ?>>
                                    <?php esc_html_e('HTML Editor', 'pronto-ab'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose your preferred editor for variation content. Block Editor provides the richest editing experience.', 'pronto-ab'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-save Interval', 'pronto-ab'); ?></th>
                        <td>
                            <input type="number" name="autosave_interval" value="<?php echo esc_attr($settings['autosave_interval'] ?? 30); ?>" min="10" max="300" class="small-text"> seconds
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Data Retention', 'pronto-ab'); ?></th>
                        <td>
                            <input type="number" name="data_retention_days" value="<?php echo esc_attr($settings['data_retention_days'] ?? 365); ?>" min="30" max="9999" class="small-text"> days
                            <p class="description"><?php esc_html_e('How long to keep analytics data before automatic cleanup.', 'pronto-ab'); ?></p>
                        </td>
                    </tr>
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Debug Mode', 'pronto-ab'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_debug" value="1" <?php checked($settings['enable_debug'] ?? false); ?>>
                                    <?php esc_html_e('Enable enhanced debugging', 'pronto-ab'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Only available when WP_DEBUG is enabled.', 'pronto-ab'); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>

                <?php submit_button(__('Save Settings', 'pronto-ab'), 'primary', 'save_settings'); ?>
            </form>
        </div>
<?php
    }


    /**
     * AJAX: Save variation weights
     */
    public function ajax_save_variation_weights()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $weights = $_POST['weights'] ?? array();

        if (empty($weights)) {
            wp_send_json_error(__('No weights provided', 'pronto-ab'));
        }

        $updated = 0;
        foreach ($weights as $variation_id => $weight) {
            $variation_id = intval($variation_id);
            $weight = floatval($weight);

            if ($variation_id && $weight >= 0 && $weight <= 100) {
                update_post_meta($variation_id, '_ab_weight_percentage', $weight);
                $updated++;
            }
        }

        if ($updated > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d variation weights updated', 'pronto-ab'), $updated),
                'updated_count' => $updated
            ));
        } else {
            wp_send_json_error(__('No weights were updated', 'pronto-ab'));
        }
    }

    /**
     * AJAX: Sync variations between posts and database
     */
    public function ajax_sync_variations()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);

        if (!$campaign_id) {
            wp_send_json_error(__('Campaign ID is required', 'pronto-ab'));
        }

        // Get all variation posts for this campaign
        $variation_posts = get_posts(array(
            'post_type' => 'ab_variation',
            'meta_key' => '_ab_campaign_id',
            'meta_value' => $campaign_id,
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));

        $synced = 0;
        foreach ($variation_posts as $post) {
            if (Pronto_AB_Variation_CPT::sync_variation_to_database($post->ID, $campaign_id)) {
                $synced++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d variations synced successfully', 'pronto-ab'), $synced),
            'synced_count' => $synced
        ));
    }

    /**
     * AJAX: Duplicate variation
     */
    public function ajax_duplicate_variation()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $variation_id = intval($_POST['variation_id'] ?? 0);

        if (!$variation_id) {
            wp_send_json_error(__('Variation ID is required', 'pronto-ab'));
        }

        $original_post = get_post($variation_id);
        if (!$original_post || $original_post->post_type !== 'ab_variation') {
            wp_send_json_error(__('Variation not found', 'pronto-ab'));
        }

        // Create duplicate post
        $new_post_args = array(
            'post_title' => $original_post->post_title . ' (Copy)',
            'post_content' => $original_post->post_content,
            'post_status' => 'draft',
            'post_type' => 'ab_variation'
        );

        $new_post_id = wp_insert_post($new_post_args);

        if (is_wp_error($new_post_id)) {
            wp_send_json_error(__('Failed to create duplicate post', 'pronto-ab'));
        }

        // Copy meta data
        $campaign_id = get_post_meta($variation_id, '_ab_campaign_id', true);
        $weight = get_post_meta($variation_id, '_ab_weight_percentage', true);

        update_post_meta($new_post_id, '_ab_campaign_id', $campaign_id);
        update_post_meta($new_post_id, '_ab_is_control', '0'); // Never duplicate as control
        update_post_meta($new_post_id, '_ab_weight_percentage', $weight ?: 50);

        wp_send_json_success(array(
            'message' => __('Variation duplicated successfully', 'pronto-ab'),
            'new_variation_id' => $new_post_id,
            'edit_url' => get_edit_post_link($new_post_id)
        ));
    }

    /**
     * AJAX: Delete variation
     */
    public function ajax_delete_variation()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $variation_id = intval($_POST['variation_id'] ?? 0);

        if (!$variation_id) {
            wp_send_json_error(__('Variation ID is required', 'pronto-ab'));
        }

        $post = get_post($variation_id);
        if (!$post || $post->post_type !== 'ab_variation') {
            wp_send_json_error(__('Variation not found', 'pronto-ab'));
        }

        // Check if it's a control variation
        $is_control = get_post_meta($variation_id, '_ab_is_control', true);
        if ($is_control) {
            wp_send_json_error(__('Cannot delete control variation', 'pronto-ab'));
        }

        // Delete the post
        $deleted = wp_delete_post($variation_id, true); // Force delete, skip trash

        if (!$deleted) {
            wp_send_json_error(__('Failed to delete variation', 'pronto-ab'));
        }

        // Also remove from database table if it exists
        $campaign_id = get_post_meta($variation_id, '_ab_campaign_id', true);
        if ($campaign_id) {
            global $wpdb;
            $table = Pronto_AB_Database::get_variations_table();
            $wpdb->delete(
                $table,
                array(
                    'campaign_id' => $campaign_id,
                    'name' => $post->post_title
                ),
                array('%d', '%s')
            );
        }

        wp_send_json_success(array(
            'message' => __('Variation deleted successfully', 'pronto-ab')
        ));
    }

    /**
     * Update the register_ajax_handlers method to include new handlers
     */
    private function register_ajax_handlers()
    {
        $ajax_handlers = array(
            // Existing handlers
            'pronto_ab_save_campaign',
            'pronto_ab_delete_campaign',
            'pronto_ab_get_posts',
            'pronto_ab_toggle_status',
            'pronto_ab_get_stats',
            'pronto_ab_autosave',
            'pronto_ab_bulk_action',
            'pronto_ab_preview_variation',

            // NEW: Variation management handlers
            'pronto_ab_save_variation_weights',
            'pronto_ab_sync_variations',
            'pronto_ab_duplicate_variation',
            'pronto_ab_delete_variation',

            // Enhanced features
            'pronto_ab_validate_campaign',
            'pronto_ab_export_data',

            // Statistics feature
            'pronto_ab_refresh_statistics'
        );

        foreach ($ajax_handlers as $handler) {
            $method_name = 'ajax_' . str_replace('pronto_ab_', '', $handler);
            if (method_exists($this, $method_name)) {
                add_action('wp_ajax_' . $handler, array($this, $method_name));
            }
        }
    }

    /**
     * ENHANCED: Save plugin settings
     */
    private function save_plugin_settings()
    {
        $settings = array(
            'default_traffic_split' => sanitize_text_field($_POST['default_traffic_split'] ?? '50/50'),
            'preferred_editor' => sanitize_text_field($_POST['preferred_editor'] ?? 'gutenberg'),
            'autosave_interval' => intval($_POST['autosave_interval'] ?? 30),
            'data_retention_days' => intval($_POST['data_retention_days'] ?? 365),
            'enable_debug' => !empty($_POST['enable_debug']) && defined('WP_DEBUG') && WP_DEBUG
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
     * ENHANCED: Render admin notices
     */
    private function render_admin_notices()
    {
        $notices = get_option('pronto_ab_admin_notices', array());

        foreach ($notices as $notice) {
            if (time() - $notice['timestamp'] < 300) { // 5 minutes
                echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
                echo '<p>' . esc_html($notice['message']) . '</p>';
                echo '</div>';
            }
        }

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
     * ENHANCED AJAX: Validate campaign before saving
     */
    public function ajax_validate_campaign()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_data = $_POST['campaign_data'] ?? array();
        $variations_data = $_POST['variations_data'] ?? array();

        $errors = array();
        $warnings = array();

        // Validate campaign name
        if (empty($campaign_data['name'])) {
            $errors[] = __('Campaign name is required', 'pronto-ab');
        }

        // Validate variations
        if (empty($variations_data)) {
            $errors[] = __('At least one variation is required', 'pronto-ab');
        } else {
            $total_weight = 0;
            $has_control = false;

            foreach ($variations_data as $i => $variation) {
                if (empty($variation['name'])) {
                    $errors[] = sprintf(__('Variation %d name is required', 'pronto-ab'), $i + 1);
                }

                $weight = floatval($variation['weight_percentage'] ?? 0);
                $total_weight += $weight;

                if ($variation['is_control']) {
                    $has_control = true;
                }
            }

            if (!$has_control) {
                $warnings[] = __('No control variation detected. Consider marking one variation as the control.', 'pronto-ab');
            }

            if (abs($total_weight - 100) > 1) {
                $errors[] = sprintf(__('Total variation weights should equal 100%% (currently %.1f%%)', 'pronto-ab'), $total_weight);
            }
        }

        // Validate dates
        if (!empty($campaign_data['start_date']) && !empty($campaign_data['end_date'])) {
            if (strtotime($campaign_data['start_date']) >= strtotime($campaign_data['end_date'])) {
                $errors[] = __('End date must be after start date', 'pronto-ab');
            }
        }

        wp_send_json_success(array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => array(
                'variations_count' => count($variations_data),
                'total_weight' => $total_weight,
                'has_control' => $has_control
            )
        ));
    }

    /**
     * ENHANCED AJAX: Export campaign data
     */
    public function ajax_export_data()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $export_format = sanitize_text_field($_POST['format'] ?? 'json');

        if (!$campaign_id) {
            wp_send_json_error(__('Campaign ID is required', 'pronto-ab'));
        }

        $export_data = $this->get_campaign_export_data($campaign_id);

        if (!$export_data) {
            wp_send_json_error(__('Campaign not found or export failed', 'pronto-ab'));
        }

        switch ($export_format) {
            case 'csv':
                $csv_data = $this->convert_to_csv($export_data);
                wp_send_json_success(array(
                    'format' => 'csv',
                    'data' => $csv_data,
                    'filename' => 'campaign-' . $campaign_id . '-export.csv'
                ));
                break;

            case 'json':
            default:
                wp_send_json_success(array(
                    'format' => 'json',
                    'data' => $export_data,
                    'filename' => 'campaign-' . $campaign_id . '-export.json'
                ));
                break;
        }
    }

    /**
     * Convert export data to CSV format
     */
    private function convert_to_csv($export_data)
    {
        $csv_lines = array();

        // Campaign header
        $csv_lines[] = 'Campaign Export';
        $csv_lines[] = 'Name,Description,Status,Created';
        $csv_lines[] = sprintf(
            '"%s","%s","%s","%s"',
            $export_data['campaign']['name'],
            $export_data['campaign']['description'],
            $export_data['campaign']['status'],
            $export_data['campaign']['created_at']
        );
        $csv_lines[] = '';

        // Variations header
        $csv_lines[] = 'Variations';
        $csv_lines[] = 'Name,Control,Weight %,Impressions,Conversions,Conversion Rate %';

        foreach ($export_data['variations'] as $variation) {
            $csv_lines[] = sprintf(
                '"%s","%s","%s","%s","%s","%s"',
                $variation['name'],
                $variation['is_control'] ? 'Yes' : 'No',
                $variation['weight_percentage'],
                $variation['impressions'],
                $variation['conversions'],
                $variation['conversion_rate']
            );
        }

        return implode("\n", $csv_lines);
    }

    /**
     * Render test winner declaration page
     */
    public function render_test_winner_page()
    {
        include PAB_ADMIN_DIR . 'views/page-test-winner-declaration.php';
    }

    /**
     * Initialize the admin class
     */
    public static function init()
    {
        $instance = new self();
        return $instance;
    }
}
