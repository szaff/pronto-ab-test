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
                                    <th class="manage-column sortable">
                                        <a href="#"><span><?php esc_html_e('Significance', 'pronto-ab'); ?></span></a>
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
        error_log("Pronto A/B Debug: campaign_edit_page() called");

        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $campaign = $campaign_id ? Pronto_AB_Campaign::find($campaign_id) : new Pronto_AB_Campaign();
        $variations = $campaign_id ? $campaign->get_variations() : array();

        error_log("Pronto A/B Debug: Campaign ID: " . $campaign_id);
        error_log("Pronto A/B Debug: POST data: " . print_r($_POST, true));

        // Handle form submission
        if (isset($_POST['save_campaign'])) {
            error_log("Pronto A/B Debug: Form submitted with save_campaign");

            // Check nonce
            if (!wp_verify_nonce($_POST['_wpnonce'], 'pronto_ab_save_campaign')) {
                error_log("Pronto A/B Debug: Nonce verification failed");
                $this->add_admin_notice('error', __('Security check failed. Please try again.', 'pronto-ab'));
            } else {
                error_log("Pronto A/B Debug: Nonce verified, processing form");

                // Process the form
                $result = $this->save_campaign_form($campaign);

                error_log("Pronto A/B Debug: Save result: " . print_r($result, true));

                if ($result['success']) {
                    error_log("Pronto A/B Debug: Save successful, redirecting to: " . $result['redirect_url']);
                    wp_redirect($result['redirect_url']);
                    exit;
                } else {
                    error_log("Pronto A/B Debug: Save failed: " . $result['message']);
                    $this->add_admin_notice('error', $result['message']);
                }
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
                                    <h2><?php esc_html_e('Variations', 'pronto-ab'); ?></h2>
                                </div>
                                <div class="inside">
                                    <?php $this->render_variations_list($campaign); ?>
                                </div>
                            </div>


                            <!-- Campaign Statistics -->
                            <?php if ($campaign_id): ?>
                                <?php $this->render_campaign_stats_box($campaign); ?>
                            <?php endif; ?>

                            <!-- Statistical Significance -->
                            <?php if ($campaign_id): ?>
                                <?php $this->render_statistics_box($campaign_id); ?>
                            <?php endif; ?>

                        </div>

                        <div id="postbox-container-1" class="postbox-container">
                            <!-- Enhanced Publish Box -->
                            <?php $this->render_publish_box($campaign); ?>

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

        // Ensure stats are never null
        $stats = array_merge(array(
            'impressions' => 0,
            'conversions' => 0,
            'unique_visitors' => 0
        ), $stats ?: array());

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
                <strong><?php echo count($variations); ?></strong> <?php echo count($variations) === 1 ? __('variation', 'pronto-ab') : __('variations', 'pronto-ab'); ?>
                <?php if (!empty($variations)): ?>
                    <?php $winning_variation_id = $this->get_winning_variation_id($campaign->id); ?>
                    <div class="variations-list-compact">
                        <?php foreach ($variations as $variation): ?>
                            <?php
                            $is_winner = ($winning_variation_id && $winning_variation_id === $variation->id);
                            $winner_class = $is_winner ? ' variation-winner' : '';
                            ?>
                            <div class="variation-badge<?php echo $winner_class; ?>">
                                <?php if ($is_winner): ?>
                                    <span class="winner-icon">🏆</span>
                                <?php endif; ?>
                                <?php echo esc_html($variation->name); ?>
                                <?php if ($variation->is_control): ?>
                                    <span class="badge-control"><?php esc_html_e('Control', 'pronto-ab'); ?></span>
                                <?php endif; ?>
                                <?php if ($is_winner): ?>
                                    <span class="badge-winner"><?php esc_html_e('Winner', 'pronto-ab'); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </td>
            <td class="campaign-traffic"><?php echo esc_html($campaign->traffic_split); ?></td>
            <td class="campaign-performance">
                <?php if ((int)$stats['impressions'] > 0): ?>
                    <div class="performance-stats">
                        <strong><?php echo number_format((int)$stats['impressions']); ?></strong> impressions<br>
                        <strong><?php echo number_format((int)$stats['conversions']); ?></strong> conversions<br>
                        <small class="conversion-rate">
                            <?php
                            $conversion_rate = 0;
                            if ((int)$stats['impressions'] > 0) {
                                $conversion_rate = round(((int)$stats['conversions'] / (int)$stats['impressions']) * 100, 2);
                            }
                            echo $conversion_rate;
                            ?>% conversion rate
                        </small>
                    </div>
                <?php else: ?>
                    <span style="color: #999;" class="no-data"><?php esc_html_e('No data yet', 'pronto-ab'); ?></span>
                <?php endif; ?>
            </td>
            <td class="campaign-significance">
                <?php $this->render_statistics_badge($campaign->id); ?>
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

    /**
     * Get winning variation ID for a campaign if statistically significant
     * 
     * @param int $campaign_id Campaign ID
     * @return int|null Winning variation ID or null
     */
    private function get_winning_variation_id($campaign_id)
    {
        $metrics = Pronto_AB_Statistics::calculate_campaign_metrics($campaign_id);

        if (isset($metrics['error']) || empty($metrics)) {
            return null;
        }

        // Find a variation with significant winner status
        foreach ($metrics as $result) {
            if (
                isset($result['stats']['is_significant']) &&
                $result['stats']['is_significant'] &&
                isset($result['stats']['winner'])
            ) {

                // If winner is 'b', return the variation_id
                // If winner is 'a', the control is winning
                if ($result['stats']['winner'] === 'b') {
                    return $result['variation_id'];
                }
            }
        }

        return null;
    }

    /**
     * Render variations list for campaign edit page
     */
    private function render_variations_list($campaign)
    {
        $campaign_id = $campaign->id ?? 0;

        // Get variations from custom post type
        $variation_posts = get_posts(array(
            'post_type' => 'ab_variation',
            'meta_key' => '_ab_campaign_id',
            'meta_value' => $campaign_id,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'ASC'
        ));

    ?>
        <div class="variations-overview">
            <?php if (empty($variation_posts)): ?>
                <div class="pronto-ab-empty-variations">
                    <h4><?php esc_html_e('No Variations Created Yet', 'pronto-ab'); ?></h4>
                    <p><?php esc_html_e('Create your first variation to start A/B testing.', 'pronto-ab'); ?></p>

                    <?php if ($campaign_id): ?>
                        <a href="<?php echo esc_url($this->get_new_variation_url($campaign_id)); ?>"
                            class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php esc_html_e('Create First Variation', 'pronto-ab'); ?>
                        </a>
                    <?php else: ?>
                        <p><em><?php esc_html_e('Save the campaign first to create variations.', 'pronto-ab'); ?></em></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="variations-list">
                    <?php foreach ($variation_posts as $variation_post): ?>
                        <?php $this->render_variation_card($variation_post); ?>
                    <?php endforeach; ?>
                </div>

                <div class="variation-controls">
                    <a href="<?php echo esc_url($this->get_new_variation_url($campaign_id)); ?>"
                        class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add New Variation', 'pronto-ab'); ?>
                    </a>

                    <button type="button" id="redistribute-weights" class="button">
                        <?php esc_html_e('Redistribute Weights Evenly', 'pronto-ab'); ?>
                    </button>

                    <button type="button" id="sync-variations" class="button">
                        <?php esc_html_e('Sync All Variations', 'pronto-ab'); ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="variations-help">
                <h4><?php esc_html_e('How Variations Work', 'pronto-ab'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Each variation is created as a separate post using WordPress\'s block editor', 'pronto-ab'); ?></li>
                    <li><?php esc_html_e('One variation should be marked as the "Control" (original content)', 'pronto-ab'); ?></li>
                    <li><?php esc_html_e('Traffic weight determines what percentage of visitors see each variation', 'pronto-ab'); ?></li>
                    <li><?php esc_html_e('All variation weights should add up to 100%', 'pronto-ab'); ?></li>
                    <li><?php esc_html_e('Click "Edit" on any variation to modify its content using the WordPress editor', 'pronto-ab'); ?></li>
                </ul>
            </div>
        </div>
    <?php
    }

    /**
     * Render individual variation card (simplified for custom post type)
     */
    private function render_variation_card($variation_post)
    {
        $is_control = get_post_meta($variation_post->ID, '_ab_is_control', true);
        $weight = get_post_meta($variation_post->ID, '_ab_weight_percentage', true) ?: 50;
        $campaign_id = get_post_meta($variation_post->ID, '_ab_campaign_id', true);

        // Get stats from database if they exist
        $stats = $this->get_variation_stats_by_post($variation_post->ID, $campaign_id);

        $edit_url = get_edit_post_link($variation_post->ID);
        $preview_url = get_preview_post_link($variation_post->ID);
    ?>
        <div class="variation-card" data-variation-id="<?php echo esc_attr($variation_post->ID); ?>">
            <div class="variation-card-header">
                <h4 class="variation-title">
                    <a href="<?php echo esc_url($edit_url); ?>">
                        <?php echo esc_html($variation_post->post_title); ?>
                    </a>
                    <?php if ($is_control): ?>
                        <span class="control-badge"><?php esc_html_e('Control', 'pronto-ab'); ?></span>
                    <?php endif; ?>
                </h4>
                <div class="variation-actions">
                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Edit', 'pronto-ab'); ?>
                    </a>

                    <?php if ($preview_url): ?>
                        <a href="<?php echo esc_url($preview_url); ?>" class="button button-small" target="_blank">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e('Preview', 'pronto-ab'); ?>
                        </a>
                    <?php endif; ?>

                    <button type="button" class="button button-small duplicate-variation"
                        data-variation-id="<?php echo esc_attr($variation_post->ID); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php esc_html_e('Duplicate', 'pronto-ab'); ?>
                    </button>

                    <?php if (!$is_control): ?>
                        <button type="button" class="button button-small button-link-delete delete-variation"
                            data-variation-id="<?php echo esc_attr($variation_post->ID); ?>"
                            data-variation-name="<?php echo esc_attr($variation_post->post_title); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Delete', 'pronto-ab'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="variation-card-content">
                <div class="variation-meta">
                    <div class="meta-item">
                        <label><?php esc_html_e('Traffic Weight:', 'pronto-ab'); ?></label>
                        <span class="weight-value"><?php echo esc_html($weight); ?>%</span>
                        <input type="range" class="weight-slider"
                            min="0" max="100" step="0.1"
                            value="<?php echo esc_attr($weight); ?>"
                            data-variation-id="<?php echo esc_attr($variation_post->ID); ?>">
                    </div>

                    <div class="meta-item">
                        <label><?php esc_html_e('Status:', 'pronto-ab'); ?></label>
                        <span class="status-<?php echo esc_attr($variation_post->post_status); ?>">
                            <?php echo esc_html(ucfirst($variation_post->post_status)); ?>
                        </span>
                    </div>

                    <div class="meta-item">
                        <label><?php esc_html_e('Last Modified:', 'pronto-ab'); ?></label>
                        <span><?php echo esc_html(get_the_modified_date('M j, Y', $variation_post)); ?></span>
                    </div>
                </div>

                <?php if ($stats && $stats['impressions'] > 0): ?>
                    <div class="variation-stats">
                        <div class="stat-item">
                            <strong><?php echo number_format((int)$stats['impressions']); ?></strong>
                            <span><?php esc_html_e('Impressions', 'pronto-ab'); ?></span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo number_format((int)$stats['conversions']); ?></strong>
                            <span><?php esc_html_e('Conversions', 'pronto-ab'); ?></span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo number_format($stats['conversion_rate'], 2); ?>%</strong>
                            <span><?php esc_html_e('Rate', 'pronto-ab'); ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="variation-stats-empty">
                        <p><?php esc_html_e('No performance data yet. Activate campaign to start collecting data.', 'pronto-ab'); ?></p>
                    </div>
                <?php endif; ?>

                <div class="variation-content-preview">
                    <label><?php esc_html_e('Content Preview:', 'pronto-ab'); ?></label>
                    <div class="content-preview">
                        <?php
                        $content = wp_trim_words(wp_strip_all_tags($variation_post->post_content), 20);
                        echo esc_html($content ?: 'No content yet...');
                        ?>
                    </div>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Get URL for creating new variation
     */
    private function get_new_variation_url($campaign_id)
    {
        $url = admin_url('post-new.php?post_type=ab_variation');
        if ($campaign_id) {
            $url = add_query_arg('campaign_id', $campaign_id, $url);
        }
        return $url;
    }

    /**
     * Get variation statistics by post ID
     */
    private function get_variation_stats_by_post($post_id, $campaign_id)
    {
        if (!$campaign_id) {
            return null;
        }

        global $wpdb;
        $table = Pronto_AB_Database::get_variations_table();

        // Try to get stats by post title (name in variations table)
        $post_title = get_the_title($post_id);
        $variation = $wpdb->get_row($wpdb->prepare(
            "SELECT impressions, conversions FROM $table WHERE campaign_id = %d AND name = %s",
            $campaign_id,
            $post_title
        ));

        if (!$variation) {
            return null;
        }

        // Ensure values are never null
        $impressions = (int)($variation->impressions ?? 0);
        $conversions = (int)($variation->conversions ?? 0);

        $conversion_rate = 0;
        if ($impressions > 0) {
            $conversion_rate = round(($conversions / $impressions) * 100, 2);
        }

        return array(
            'impressions' => $impressions,
            'conversions' => $conversions,
            'conversion_rate' => $conversion_rate
        );
    }
}
