<?php

/**
 * A/B Test Admin Interface
 * 
 * Handles the admin interface for managing A/B test campaigns
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
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_pronto_ab_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_pronto_ab_delete_campaign', array($this, 'ajax_delete_campaign'));
        add_action('wp_ajax_pronto_ab_toggle_status', array($this, 'ajax_toggle_status'));
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
    }

    /**
     * Handle admin actions
     */
    public function handle_admin_actions()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle campaign actions
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
            }
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'pronto-abs') === false) {
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

        wp_localize_script('pronto-ab-admin', 'abTestAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pronto_ab_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this campaign?', 'pronto-ab'),
                'saving' => __('Saving...', 'pronto-ab'),
                'saved' => __('Saved!', 'pronto-ab'),
                'error' => __('Error occurred. Please try again.', 'pronto-ab')
            )
        ));
    }

    /**
     * Campaigns list page
     */
    public function campaigns_list_page()
    {
        $campaigns = Pronto_AB_Campaign::get_campaigns(array(
            'limit' => 50,
            'orderby' => 'updated_at',
            'order' => 'DESC'
        ));

?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('A/B Test Campaigns', 'pronto-ab'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-new')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'pronto-ab'); ?>
            </a>

            <?php if (isset($_GET['message'])): ?>
                <div id="message" class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        switch ($_GET['message']) {
                            case 'deleted':
                                esc_html_e('Campaign deleted successfully.', 'pronto-ab');
                                break;
                            case 'updated':
                                esc_html_e('Campaign updated successfully.', 'pronto-ab');
                                break;
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="pronto-ab-campaigns-table">
                <?php if (empty($campaigns)): ?>
                    <div class="pronto-ab-empty-state">
                        <h2><?php esc_html_e('No A/B test campaigns yet', 'pronto-ab'); ?></h2>
                        <p><?php esc_html_e('Create your first A/B test campaign to start optimizing your content.', 'pronto-ab'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-new')); ?>" class="button button-primary">
                            <?php esc_html_e('Create Your First Campaign', 'pronto-ab'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Campaign', 'pronto-ab'); ?></th>
                                <th><?php esc_html_e('Target Content', 'pronto-ab'); ?></th>
                                <th><?php esc_html_e('Status', 'pronto-ab'); ?></th>
                                <th><?php esc_html_e('Variations', 'pronto-ab'); ?></th>
                                <th><?php esc_html_e('Traffic Split', 'pronto-ab'); ?></th>
                                <th><?php esc_html_e('Performance', 'pronto-ab'); ?></th>
                                <th><?php esc_html_e('Actions', 'pronto-ab'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign): ?>
                                <?php $this->render_campaign_row($campaign); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render a single campaign row
     */
    private function render_campaign_row($campaign)
    {
        $variations = $campaign->get_variations();
        $stats = $campaign->get_stats();

        // Get target content info
        $target_content = '';
        if ($campaign->target_post_id) {
            $post = get_post($campaign->target_post_id);
            if ($post) {
                $target_content = sprintf(
                    '<a href="%s">%s</a><br><small>(%s)</small>',
                    esc_url(get_edit_post_link($post->ID)),
                    esc_html($post->post_title),
                    esc_html($post->post_type)
                );
            }
        }

        // Status styling
        $status_colors = array(
            'draft' => '#999',
            'active' => '#46b450',
            'paused' => '#ffb900',
            'completed' => '#dc3232'
        );
        $status_color = isset($status_colors[$campaign->status]) ? $status_colors[$campaign->status] : '#999';

    ?>
        <tr>
            <td>
                <strong>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-new&campaign_id=' . $campaign->id)); ?>">
                        <?php echo esc_html($campaign->name); ?>
                    </a>
                </strong>
                <?php if ($campaign->description): ?>
                    <br><small><?php echo esc_html(wp_trim_words($campaign->description, 10)); ?></small>
                <?php endif; ?>
            </td>
            <td><?php echo $target_content ?: '<span style="color: #999;">—</span>'; ?></td>
            <td>
                <span style="color: <?php echo esc_attr($status_color); ?>; font-weight: bold;">
                    <?php echo esc_html(ucfirst($campaign->status)); ?>
                </span>
                <?php if ($campaign->start_date): ?>
                    <br><small><?php echo esc_html(date_i18n('M j, Y', strtotime($campaign->start_date))); ?></small>
                <?php endif; ?>
            </td>
            <td>
                <?php echo count($variations); ?> <?php esc_html_e('variations', 'pronto-ab'); ?>
                <?php if (!empty($variations)): ?>
                    <br><small>
                        <?php foreach ($variations as $variation): ?>
                            <?php echo esc_html($variation->name); ?><?php echo $variation->is_control ? ' (Control)' : ''; ?><br>
                        <?php endforeach; ?>
                    </small>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($campaign->traffic_split); ?></td>
            <td>
                <?php if ($stats['impressions'] > 0): ?>
                    <strong><?php echo number_format($stats['impressions']); ?></strong> impressions<br>
                    <strong><?php echo number_format($stats['conversions']); ?></strong> conversions<br>
                    <small><?php echo round(($stats['conversions'] / $stats['impressions']) * 100, 2); ?>% conversion rate</small>
                <?php else: ?>
                    <span style="color: #999;"><?php esc_html_e('No data yet', 'pronto-ab'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php $this->render_campaign_actions($campaign); ?>
            </td>
        </tr>
    <?php
    }

    /**
     * Render campaign action buttons
     */
    private function render_campaign_actions($campaign)
    {
        $edit_url = admin_url('admin.php?page=pronto-abs-new&campaign_id=' . $campaign->id);
        $nonce = wp_create_nonce('pronto_ab_action');

        echo '<div class="row-actions">';

        // Edit
        echo '<span class="edit">';
        echo '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'pronto-ab') . '</a>';
        echo '</span>';

        // Status actions
        if ($campaign->status === 'draft' || $campaign->status === 'paused') {
            echo ' | <span class="activate">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=activate&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '">';
            echo esc_html__('Activate', 'pronto-ab');
            echo '</a></span>';
        }

        if ($campaign->status === 'active') {
            echo ' | <span class="pause">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=pause&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '">';
            echo esc_html__('Pause', 'pronto-ab');
            echo '</a></span>';

            echo ' | <span class="complete">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=complete&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '">';
            echo esc_html__('Complete', 'pronto-ab');
            echo '</a></span>';
        }

        // Delete
        echo ' | <span class="delete">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=delete&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '" ';
        echo 'onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this campaign?', 'pronto-ab')) . '\')">';
        echo esc_html__('Delete', 'pronto-ab');
        echo '</a></span>';

        echo '</div>';
    }

    /**
     * Campaign edit/create page
     */
    public function campaign_edit_page()
    {
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $campaign = $campaign_id ? Pronto_AB_Campaign::find($campaign_id) : new Pronto_AB_Campaign();
        $variations = $campaign_id ? $campaign->get_variations() : array();

        // Handle form submission
        if (isset($_POST['save_campaign']) && wp_verify_nonce($_POST['_wpnonce'], 'pronto_ab_save_campaign')) {
            $this->save_campaign_form($campaign);
        }

    ?>
        <div class="wrap">
            <h1>
                <?php echo $campaign_id ? esc_html__('Edit Campaign', 'pronto-ab') : esc_html__('Add New Campaign', 'pronto-ab'); ?>
            </h1>

            <form method="post" action="" id="pronto-ab-campaign-form">
                <?php wp_nonce_field('pronto_ab_save_campaign'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <!-- Campaign Details -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Campaign Details', 'pronto-ab'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="campaign_name"><?php esc_html_e('Campaign Name', 'pronto-ab'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="campaign_name" name="campaign_name"
                                                    value="<?php echo esc_attr($campaign->name); ?>"
                                                    class="regular-text" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="campaign_description"><?php esc_html_e('Description', 'pronto-ab'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="campaign_description" name="campaign_description"
                                                    rows="3" class="large-text"><?php echo esc_textarea($campaign->description); ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="target_content"><?php esc_html_e('Target Content', 'pronto-ab'); ?></label>
                                            </th>
                                            <td>
                                                <?php $this->render_target_content_selector($campaign); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="traffic_split"><?php esc_html_e('Traffic Split', 'pronto-ab'); ?></label>
                                            </th>
                                            <td>
                                                <select id="traffic_split" name="traffic_split">
                                                    <option value="50/50" <?php selected($campaign->traffic_split, '50/50'); ?>>50/50</option>
                                                    <option value="60/40" <?php selected($campaign->traffic_split, '60/40'); ?>>60/40</option>
                                                    <option value="70/30" <?php selected($campaign->traffic_split, '70/30'); ?>>70/30</option>
                                                    <option value="80/20" <?php selected($campaign->traffic_split, '80/20'); ?>>80/20</option>
                                                    <option value="90/10" <?php selected($campaign->traffic_split, '90/10'); ?>>90/10</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="start_date"><?php esc_html_e('Start Date', 'pronto-ab'); ?></label>
                                            </th>
                                            <td>
                                                <input type="datetime-local" id="start_date" name="start_date"
                                                    value="<?php echo $campaign->start_date ? esc_attr(date('Y-m-d\TH:i', strtotime($campaign->start_date))) : ''; ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="end_date"><?php esc_html_e('End Date', 'pronto-ab'); ?></label>
                                            </th>
                                            <td>
                                                <input type="datetime-local" id="end_date" name="end_date"
                                                    value="<?php echo $campaign->end_date ? esc_attr(date('Y-m-d\TH:i', strtotime($campaign->end_date))) : ''; ?>">
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Variations -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Variations', 'pronto-ab'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div id="pronto-ab-variations">
                                        <?php $this->render_variations_editor($variations); ?>
                                    </div>
                                    <button type="button" id="add-variation" class="button">
                                        <?php esc_html_e('Add Variation', 'pronto-ab'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="postbox-container-1" class="postbox-container">
                            <!-- Publish Box -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Publish', 'pronto-ab'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div class="submitbox">
                                        <div id="minor-publishing">
                                            <div id="minor-publishing-actions">
                                                <div id="save-action">
                                                    <input type="submit" name="save_campaign" value="<?php esc_attr_e('Save Draft', 'pronto-ab'); ?>" class="button">
                                                </div>
                                            </div>
                                        </div>

                                        <div id="major-publishing-actions">
                                            <div id="publishing-action">
                                                <select name="campaign_status">
                                                    <option value="draft" <?php selected($campaign->status, 'draft'); ?>><?php esc_html_e('Draft', 'pronto-ab'); ?></option>
                                                    <option value="active" <?php selected($campaign->status, 'active'); ?>><?php esc_html_e('Active', 'pronto-ab'); ?></option>
                                                    <option value="paused" <?php selected($campaign->status, 'paused'); ?>><?php esc_html_e('Paused', 'pronto-ab'); ?></option>
                                                    <option value="completed" <?php selected($campaign->status, 'completed'); ?>><?php esc_html_e('Completed', 'pronto-ab'); ?></option>
                                                </select>
                                                <input type="submit" name="save_campaign" value="<?php esc_attr_e('Save Campaign', 'pronto-ab'); ?>" class="button button-primary button-large">
                                            </div>
                                            <div class="clear"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campaign Stats -->
                            <?php if ($campaign_id): ?>
                                <div class="postbox">
                                    <div class="postbox-header">
                                        <h2><?php esc_html_e('Campaign Statistics', 'pronto-ab'); ?></h2>
                                    </div>
                                    <div class="inside">
                                        <?php $this->render_campaign_stats($campaign); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    <?php
    }

    /**
     * Render target content selector
     */
    private function render_target_content_selector($campaign)
    {
        $post_types = get_post_types(array('public' => true), 'objects');
    ?>
        <div class="pronto-ab-target-selector">
            <select id="target_post_type" name="target_post_type">
                <option value=""><?php esc_html_e('Select post type', 'pronto-ab'); ?></option>
                <?php foreach ($post_types as $post_type): ?>
                    <option value="<?php echo esc_attr($post_type->name); ?>"
                        <?php selected($campaign->target_post_type, $post_type->name); ?>>
                        <?php echo esc_html($post_type->labels->singular_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="target_post_id" name="target_post_id" style="<?php echo $campaign->target_post_type ? '' : 'display:none;'; ?>">
                <option value=""><?php esc_html_e('Select content', 'pronto-ab'); ?></option>
                <?php if ($campaign->target_post_type): ?>
                    <?php
                    $posts = get_posts(array(
                        'post_type' => $campaign->target_post_type,
                        'post_status' => 'publish',
                        'numberposts' => 100,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ));
                    foreach ($posts as $post): ?>
                        <option value="<?php echo esc_attr($post->ID); ?>"
                            <?php selected($campaign->target_post_id, $post->ID); ?>>
                            <?php echo esc_html($post->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#target_post_type').change(function() {
                    var postType = $(this).val();
                    var $postSelect = $('#target_post_id');

                    if (!postType) {
                        $postSelect.hide().html('<option value=""><?php esc_html_e("Select content", "pronto-ab"); ?></option>');
                        return;
                    }

                    $.post(ajaxurl, {
                        action: 'pronto_ab_get_posts',
                        post_type: postType,
                        nonce: '<?php echo wp_create_nonce("pronto_ab_get_posts"); ?>'
                    }, function(response) {
                        if (response.success) {
                            var options = '<option value=""><?php esc_html_e("Select content", "pronto-ab"); ?></option>';
                            $.each(response.data, function(i, post) {
                                options += '<option value="' + post.ID + '">' + post.post_title + '</option>';
                            });
                            $postSelect.html(options).show();
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Render variations editor
     */
    private function render_variations_editor($variations)
    {
        if (empty($variations)) {
            // Add default control variation
            $variations = array(
                (object) array(
                    'id' => '',
                    'name' => 'Control',
                    'content' => '',
                    'is_control' => true,
                    'weight_percentage' => 50
                ),
                (object) array(
                    'id' => '',
                    'name' => 'Variation A',
                    'content' => '',
                    'is_control' => false,
                    'weight_percentage' => 50
                )
            );
        }

        foreach ($variations as $index => $variation): ?>
            <div class="pronto-ab-variation" data-index="<?php echo esc_attr($index); ?>">
                <div class="variation-header">
                    <h4>
                        <?php echo esc_html($variation->name); ?>
                        <?php if ($variation->is_control): ?>
                            <span class="control-badge"><?php esc_html_e('Control', 'pronto-ab'); ?></span>
                        <?php endif; ?>
                    </h4>
                    <?php if (!$variation->is_control): ?>
                        <button type="button" class="remove-variation button-link-delete">
                            <?php esc_html_e('Remove', 'pronto-ab'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="variation-content">
                    <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][id]"
                        value="<?php echo esc_attr($variation->id ?? ''); ?>">
                    <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][is_control]"
                        value="<?php echo $variation->is_control ? '1' : '0'; ?>">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="variation_name_<?php echo esc_attr($index); ?>"><?php esc_html_e('Name', 'pronto-ab'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="variation_name_<?php echo esc_attr($index); ?>"
                                    name="variations[<?php echo esc_attr($index); ?>][name]"
                                    value="<?php echo esc_attr($variation->name); ?>"
                                    class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="variation_content_<?php echo esc_attr($index); ?>"><?php esc_html_e('Content', 'pronto-ab'); ?></label>
                            </th>
                            <td>
                                <textarea id="variation_content_<?php echo esc_attr($index); ?>"
                                    name="variations[<?php echo esc_attr($index); ?>][content]"
                                    rows="8" class="large-text code"><?php echo esc_textarea($variation->content ?? ''); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('HTML content for this variation. This will replace the target content when this variation is shown.', 'pronto-ab'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="variation_weight_<?php echo esc_attr($index); ?>"><?php esc_html_e('Weight %', 'pronto-ab'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="variation_weight_<?php echo esc_attr($index); ?>"
                                    name="variations[<?php echo esc_attr($index); ?>][weight_percentage]"
                                    value="<?php echo esc_attr($variation->weight_percentage ?? 50); ?>"
                                    min="0" max="100" step="0.01" class="small-text">%
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endforeach;
    }

    /**
     * Render campaign statistics
     */
    private function render_campaign_stats($campaign)
    {
        $stats = $campaign->get_stats();
        $variations = $campaign->get_variations();

        ?>
        <div class="pronto-ab-stats">
            <div class="stats-summary">
                <div class="stat-item">
                    <strong><?php echo number_format($stats['impressions']); ?></strong>
                    <span><?php esc_html_e('Total Impressions', 'pronto-ab'); ?></span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['conversions']); ?></strong>
                    <span><?php esc_html_e('Total Conversions', 'pronto-ab'); ?></span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['unique_visitors']); ?></strong>
                    <span><?php esc_html_e('Unique Visitors', 'pronto-ab'); ?></span>
                </div>
                <div class="stat-item">
                    <strong>
                        <?php echo $stats['impressions'] > 0 ? round(($stats['conversions'] / $stats['impressions']) * 100, 2) : 0; ?>%
                    </strong>
                    <span><?php esc_html_e('Conversion Rate', 'pronto-ab'); ?></span>
                </div>
            </div>

            <?php if (!empty($variations)): ?>
                <div class="variations-performance">
                    <h4><?php esc_html_e('Variation Performance', 'pronto-ab'); ?></h4>
                    <?php foreach ($variations as $variation): ?>
                        <div class="variation-stat">
                            <strong><?php echo esc_html($variation->name); ?></strong>
                            <?php if ($variation->is_control): ?>
                                <span class="control-badge"><?php esc_html_e('Control', 'pronto-ab'); ?></span>
                            <?php endif; ?>
                            <br>
                            <small>
                                <?php echo number_format($variation->impressions); ?> impressions,
                                <?php echo number_format($variation->conversions); ?> conversions
                                (<?php echo $variation->get_conversion_rate(); ?>%)
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Save campaign form data
     */
    private function save_campaign_form($campaign)
    {
        // Save campaign data
        $campaign->name = sanitize_text_field($_POST['campaign_name']);
        $campaign->description = sanitize_textarea_field($_POST['campaign_description']);
        $campaign->status = sanitize_text_field($_POST['campaign_status'] ?? 'draft');
        $campaign->target_post_id = intval($_POST['target_post_id']);
        $campaign->target_post_type = sanitize_text_field($_POST['target_post_type']);
        $campaign->traffic_split = sanitize_text_field($_POST['traffic_split']);
        $campaign->start_date = $_POST['start_date'] ? sanitize_text_field($_POST['start_date']) : null;
        $campaign->end_date = $_POST['end_date'] ? sanitize_text_field($_POST['end_date']) : null;

        if ($campaign->save()) {
            // Save variations
            if (isset($_POST['variations']) && is_array($_POST['variations'])) {
                $this->save_campaign_variations($campaign->id, $_POST['variations']);
            }

            $redirect_url = admin_url('admin.php?page=pronto-abs&message=updated');
            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Save campaign variations
     */
    private function save_campaign_variations($campaign_id, $variations_data)
    {
        // Get existing variations
        $existing_variations = Pronto_AB_Variation::get_by_campaign($campaign_id);
        $existing_ids = array_map(function ($v) {
            return $v->id;
        }, $existing_variations);
        $saved_ids = array();

        foreach ($variations_data as $variation_data) {
            $variation = new Pronto_AB_Variation();

            if (!empty($variation_data['id'])) {
                $variation = Pronto_AB_Variation::find($variation_data['id']) ?: new Pronto_AB_Variation();
            }

            $variation->campaign_id = $campaign_id;
            $variation->name = sanitize_text_field($variation_data['name']);
            $variation->content = wp_kses_post($variation_data['content']);
            $variation->is_control = !empty($variation_data['is_control']);
            $variation->weight_percentage = floatval($variation_data['weight_percentage']);

            if ($variation->save()) {
                $saved_ids[] = $variation->id;
            }
        }

        // Delete removed variations
        $to_delete = array_diff($existing_ids, $saved_ids);
        foreach ($to_delete as $variation_id) {
            $variation = Pronto_AB_Variation::find($variation_id);
            if ($variation) {
                $variation->delete();
            }
        }
    }

    /**
     * Delete campaign
     */
    private function delete_campaign($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if ($campaign && $campaign->delete()) {
            $redirect_url = admin_url('admin.php?page=pronto-abs&message=deleted');
            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Toggle campaign status
     */
    private function toggle_campaign_status($campaign_id, $status)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if ($campaign) {
            $campaign->status = $status;
            $campaign->save();

            $redirect_url = admin_url('admin.php?page=pronto-abs&message=updated');
            wp_redirect($redirect_url);
            exit;
        }
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
     * AJAX handlers
     */
    public function ajax_get_posts()
    {
        check_ajax_referer('pronto_ab_get_posts', 'nonce');

        $post_type = sanitize_text_field($_POST['post_type']);
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => 100,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        wp_send_json_success($posts);
    }
}
