<?php

/**
 * A/B Test Admin Interface - Page Rendering Methods
 * 
 * This trait contains all the page rendering methods for the admin interface.
 * Include this in your main Pronto_AB_Admin class.
 */

trait Pronto_AB_Admin_Pages
{
    /**
     * Campaigns list page with enhanced functionality
     */
    public function campaigns_list_page()
    {
        // Handle bulk actions if submitted
        if (isset($_POST['action']) && $_POST['action'] !== '-1' && !empty($_POST['campaign_ids'])) {
            check_admin_referer('pronto_ab_bulk_action');
            $this->handle_bulk_action($_POST['action'], $_POST['campaign_ids']);
        }

        $campaigns = Pronto_AB_Campaign::get_campaigns(array(
            'limit' => 50,
            'orderby' => 'updated_at',
            'order' => 'DESC'
        ));

        // Get campaign counts for status filter
        $status_counts = $this->get_campaign_status_counts();

?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('A/B Test Campaigns', 'pronto-ab'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-new')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'pronto-ab'); ?>
            </a>

            <?php $this->render_admin_notices(); ?>

            <!-- Enhanced filters and search -->
            <div class="campaign-filters">
                <div class="alignleft actions bulkactions">
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php esc_html_e('Bulk Actions', 'pronto-ab'); ?></option>
                        <option value="activate"><?php esc_html_e('Activate', 'pronto-ab'); ?></option>
                        <option value="pause"><?php esc_html_e('Pause', 'pronto-ab'); ?></option>
                        <option value="complete"><?php esc_html_e('Complete', 'pronto-ab'); ?></option>
                        <option value="duplicate"><?php esc_html_e('Duplicate', 'pronto-ab'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', 'pronto-ab'); ?></option>
                    </select>
                    <button type="button" id="doaction" class="button action"><?php esc_html_e('Apply', 'pronto-ab'); ?></button>
                </div>

                <div class="alignleft actions">
                    <select name="status_filter" id="status-filter">
                        <option value=""><?php esc_html_e('All statuses', 'pronto-ab'); ?></option>
                        <option value="draft"><?php printf(__('Draft (%d)', 'pronto-ab'), $status_counts['draft']); ?></option>
                        <option value="active"><?php printf(__('Active (%d)', 'pronto-ab'), $status_counts['active']); ?></option>
                        <option value="paused"><?php printf(__('Paused (%d)', 'pronto-ab'), $status_counts['paused']); ?></option>
                        <option value="completed"><?php printf(__('Completed (%d)', 'pronto-ab'), $status_counts['completed']); ?></option>
                    </select>
                    <input type="search" id="campaign-search" placeholder="<?php esc_attr_e('Search campaigns...', 'pronto-ab'); ?>" />
                </div>
            </div>

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
                    <form method="post">
                        <?php wp_nonce_field('pronto_ab_bulk_action'); ?>
                        <table class="wp-list-table widefat fixed striped campaigns-table">
                            <thead>
                                <tr>
                                    <td class="manage-column column-cb check-column">
                                        <input id="cb-select-all-1" type="checkbox" />
                                    </td>
                                    <th class="manage-column column-primary sortable">
                                        <a href="#"><span><?php esc_html_e('Campaign', 'pronto-ab'); ?></span></a>
                                    </th>
                                    <th class="manage-column"><?php esc_html_e('Target Content', 'pronto-ab'); ?></th>
                                    <th class="manage-column"><?php esc_html_e('Status', 'pronto-ab'); ?></th>
                                    <th class="manage-column"><?php esc_html_e('Variations', 'pronto-ab'); ?></th>
                                    <th class="manage-column"><?php esc_html_e('Traffic Split', 'pronto-ab'); ?></th>
                                    <th class="manage-column sortable">
                                        <a href="#"><span><?php esc_html_e('Performance', 'pronto-ab'); ?></span></a>
                                    </th>
                                    <th class="manage-column"><?php esc_html_e('Actions', 'pronto-ab'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <?php $this->render_campaign_row($campaign); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Campaign edit/create page with enhanced UI
     */
    public function campaign_edit_page()
    {
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $campaign = $campaign_id ? Pronto_AB_Campaign::find($campaign_id) : new Pronto_AB_Campaign();
        $variations = $campaign_id ? $campaign->get_variations() : array();

        // Handle form submission
        if (isset($_POST['save_campaign']) && wp_verify_nonce($_POST['_wpnonce'], 'pronto_ab_save_campaign')) {
            $result = $this->save_campaign_form($campaign);
            if ($result['success']) {
                wp_redirect($result['redirect_url']);
                exit;
            } else {
                $this->add_admin_notice('error', $result['message']);
            }
        }

    ?>
        <div class="wrap">
            <h1>
                <?php echo $campaign_id ? esc_html__('Edit Campaign', 'pronto-ab') : esc_html__('Add New Campaign', 'pronto-ab'); ?>
                <?php if ($campaign_id): ?>
                    <span class="campaign-id">(ID: <?php echo esc_html($campaign_id); ?>)</span>
                <?php endif; ?>
            </h1>

            <?php $this->render_admin_notices(); ?>

            <form method="post" action="" id="pronto-ab-campaign-form" novalidate>
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
                                    <?php $this->render_campaign_details_form($campaign); ?>
                                </div>
                            </div>

                            <!-- Variations -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2>
                                        <?php esc_html_e('Variations', 'pronto-ab'); ?>
                                        <span class="variation-count">(<span id="variation-count"><?php echo count($variations); ?></span>)</span>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <div id="pronto-ab-variations">
                                        <?php $this->render_variations_editor($variations); ?>
                                    </div>
                                    <div class="variation-controls">
                                        <button type="button" id="add-variation" class="button">
                                            <span class="dashicons dashicons-plus-alt"></span>
                                            <?php esc_html_e('Add Variation', 'pronto-ab'); ?>
                                        </button>
                                        <button type="button" id="redistribute-weights" class="button">
                                            <?php esc_html_e('Redistribute Weights Evenly', 'pronto-ab'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="postbox-container-1" class="postbox-container">
                            <!-- Enhanced Publish Box -->
                            <?php $this->render_publish_box($campaign); ?>

                            <!-- Campaign Statistics -->
                            <?php if ($campaign_id): ?>
                                <?php $this->render_campaign_stats_box($campaign); ?>
                            <?php endif; ?>

                            <!-- Quick Actions Box -->
                            <?php if ($campaign_id): ?>
                                <?php $this->render_quick_actions_box($campaign); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    <?php
    }

    /**
     * Enhanced campaign row rendering with checkbox support
     */
    private function render_campaign_row($campaign)
    {
        $variations = $campaign->get_variations();
        $stats = $campaign->get_stats();
        $target_content = $this->get_target_content_display($campaign);

        // Status styling
        $status_colors = array(
            'draft' => '#999',
            'active' => '#46b450',
            'paused' => '#ffb900',
            'completed' => '#dc3232'
        );
        $status_color = isset($status_colors[$campaign->status]) ? $status_colors[$campaign->status] : '#999';

    ?>
        <tr data-campaign-id="<?php echo esc_attr($campaign->id); ?>" data-status="<?php echo esc_attr($campaign->status); ?>">
            <th scope="row" class="check-column">
                <input id="cb-select-<?php echo esc_attr($campaign->id); ?>" type="checkbox" name="campaign_ids[]" value="<?php echo esc_attr($campaign->id); ?>" class="cb-select" />
            </th>
            <td class="campaign-title column-primary">
                <strong>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-new&campaign_id=' . $campaign->id)); ?>" class="row-title">
                        <?php echo esc_html($campaign->name); ?>
                    </a>
                </strong>
                <?php if ($campaign->description): ?>
                    <br><small class="campaign-description"><?php echo esc_html(wp_trim_words($campaign->description, 10)); ?></small>
                <?php endif; ?>
                <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e('Show more details', 'pronto-ab'); ?></span></button>
            </td>
            <td class="campaign-target"><?php echo $target_content; ?></td>
            <td class="campaign-status">
                <span style="color: <?php echo esc_attr($status_color); ?>; font-weight: bold;" class="status-<?php echo esc_attr($campaign->status); ?>">
                    <?php echo esc_html(ucfirst($campaign->status)); ?>
                </span>
                <?php if ($campaign->start_date): ?>
                    <br><small><?php echo esc_html(date_i18n('M j, Y', strtotime($campaign->start_date))); ?></small>
                <?php endif; ?>
            </td>
            <td class="campaign-variations">
                <strong><?php echo count($variations); ?></strong> <?php esc_html_e('variations', 'pronto-ab'); ?>
                <?php if (!empty($variations)): ?>
                    <br><small class="variations-list">
                        <?php foreach ($variations as $i => $variation): ?>
                            <?php if ($i > 0) echo ', '; ?>
                            <span class="variation-name<?php echo $variation->is_control ? ' control-variation' : ''; ?>">
                                <?php echo esc_html($variation->name); ?>
                                <?php if ($variation->is_control) echo ' (Control)'; ?>
                            </span>
                        <?php endforeach; ?>
                    </small>
                <?php endif; ?>
            </td>
            <td class="campaign-traffic"><?php echo esc_html($campaign->traffic_split); ?></td>
            <td class="campaign-performance">
                <?php if ($stats['impressions'] > 0): ?>
                    <div class="performance-stats">
                        <strong><?php echo number_format($stats['impressions']); ?></strong> impressions<br>
                        <strong><?php echo number_format($stats['conversions']); ?></strong> conversions<br>
                        <small class="conversion-rate">
                            <?php echo round(($stats['conversions'] / $stats['impressions']) * 100, 2); ?>% conversion rate
                        </small>
                    </div>
                <?php else: ?>
                    <span style="color: #999;" class="no-data"><?php esc_html_e('No data yet', 'pronto-ab'); ?></span>
                <?php endif; ?>
            </td>
            <td class="campaign-actions">
                <?php $this->render_campaign_actions($campaign); ?>
            </td>
        </tr>
<?php
    }

    /**
     * Get target content display with enhanced formatting
     */
    private function get_target_content_display($campaign)
    {
        if (!$campaign->target_post_id) {
            return '<span style="color: #999;">—</span>';
        }

        $post = get_post($campaign->target_post_id);
        if (!$post) {
            return '<span style="color: #999; font-style: italic;">' . __('Post not found', 'pronto-ab') . '</span>';
        }

        return sprintf(
            '<a href="%s" title="%s">%s</a><br><small class="post-type">%s</small>',
            esc_url(get_edit_post_link($post->ID)),
            esc_attr(__('Edit post', 'pronto-ab')),
            esc_html($post->post_title),
            esc_html(get_post_type_object($post->post_type)->labels->singular_name)
        );
    }

    /**
     * Enhanced campaign actions with more options
     */
    private function render_campaign_actions($campaign)
    {
        $edit_url = admin_url('admin.php?page=pronto-abs-new&campaign_id=' . $campaign->id);
        $nonce = wp_create_nonce('pronto_ab_action');

        echo '<div class="row-actions">';

        // Edit
        echo '<span class="edit">';
        echo '<a href="' . esc_url($edit_url) . '" title="' . esc_attr__('Edit campaign', 'pronto-ab') . '">' . esc_html__('Edit', 'pronto-ab') . '</a>';
        echo '</span>';

        // Status actions
        if ($campaign->status === 'draft' || $campaign->status === 'paused') {
            echo ' | <span class="activate">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=activate&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '" title="' . esc_attr__('Activate campaign', 'pronto-ab') . '">';
            echo esc_html__('Activate', 'pronto-ab');
            echo '</a></span>';
        }

        if ($campaign->status === 'active') {
            echo ' | <span class="pause">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=pause&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '" title="' . esc_attr__('Pause campaign', 'pronto-ab') . '">';
            echo esc_html__('Pause', 'pronto-ab');
            echo '</a></span>';

            echo ' | <span class="complete">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=complete&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '" title="' . esc_attr__('Complete campaign', 'pronto-ab') . '">';
            echo esc_html__('Complete', 'pronto-ab');
            echo '</a></span>';
        }

        // Duplicate
        echo ' | <span class="duplicate">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=duplicate&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '" title="' . esc_attr__('Duplicate campaign', 'pronto-ab') . '">';
        echo esc_html__('Duplicate', 'pronto-ab');
        echo '</a></span>';

        // View results (if has data)
        $stats = $campaign->get_stats();
        if ($stats['impressions'] > 0) {
            echo ' | <span class="view-results">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs-analytics&campaign_id=' . $campaign->id)) . '" title="' . esc_attr__('View detailed results', 'pronto-ab') . '">';
            echo esc_html__('Results', 'pronto-ab');
            echo '</a></span>';
        }

        // Delete
        echo ' | <span class="delete">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pronto-abs&action=delete&campaign_id=' . $campaign->id . '&_wpnonce=' . $nonce)) . '" ';
        echo 'class="delete-campaign" data-campaign-name="' . esc_attr($campaign->name) . '" title="' . esc_attr__('Delete campaign', 'pronto-ab') . '">';
        echo esc_html__('Delete', 'pronto-ab');
        echo '</a></span>';

        echo '</div>';
    }

    /**
     * Get campaign status counts for filters
     */
    private function get_campaign_status_counts()
    {
        global $wpdb;

        $table = Pronto_AB_Database::get_campaigns_table();
        $results = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM $table 
            GROUP BY status
        ");

        $counts = array(
            'draft' => 0,
            'active' => 0,
            'paused' => 0,
            'completed' => 0
        );

        foreach ($results as $result) {
            $counts[$result->status] = $result->count;
        }

        return $counts;
    }
}
