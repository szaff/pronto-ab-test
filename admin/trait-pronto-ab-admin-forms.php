<?php

/**
 * A/B Test Admin Interface - Form Rendering Methods
 * 
 * This trait contains all the form rendering methods for campaign editing.
 * Include this in your main Pronto_AB_Admin class.
 */

trait Pronto_AB_Admin_Forms
{
    /**
     * Render campaign details form section
     */
    private function render_campaign_details_form($campaign)
    {
?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="campaign_name"><?php esc_html_e('Campaign Name', 'pronto-ab'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="campaign_name" name="campaign_name"
                        value="<?php echo esc_attr($campaign->name ?? ''); ?>"
                        class="regular-text" required aria-describedby="campaign-name-description">
                    <p class="description" id="campaign-name-description">
                        <?php esc_html_e('Give your campaign a descriptive name.', 'pronto-ab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="campaign_description"><?php esc_html_e('Description', 'pronto-ab'); ?></label>
                </th>
                <td>
                    <textarea id="campaign_description" name="campaign_description"
                        rows="3" class="large-text" aria-describedby="campaign-description-description"><?php echo esc_textarea($campaign->description ?? ''); ?></textarea>
                    <p class="description" id="campaign-description-description">
                        <?php esc_html_e('Optional description of what this campaign is testing.', 'pronto-ab'); ?>
                    </p>
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
                    <select id="traffic_split" name="traffic_split" aria-describedby="traffic-split-description">
                        <option value="50/50" <?php selected($campaign->traffic_split ?? '50/50', '50/50'); ?>>50/50</option>
                        <option value="60/40" <?php selected($campaign->traffic_split ?? '50/50', '60/40'); ?>>60/40</option>
                        <option value="70/30" <?php selected($campaign->traffic_split ?? '50/50', '70/30'); ?>>70/30</option>
                        <option value="80/20" <?php selected($campaign->traffic_split ?? '50/50', '80/20'); ?>>80/20</option>
                        <option value="90/10" <?php selected($campaign->traffic_split ?? '50/50', '90/10'); ?>>90/10</option>
                        <option value="custom" <?php selected($campaign->traffic_split ?? '50/50', 'custom'); ?>><?php esc_html_e('Custom (set in variations)', 'pronto-ab'); ?></option>
                    </select>
                    <div id="traffic-split-visualizer"></div>
                    <p class="description" id="traffic-split-description">
                        <?php esc_html_e('How to distribute traffic between variations.', 'pronto-ab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="start_date"><?php esc_html_e('Start Date', 'pronto-ab'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" id="start_date" name="start_date"
                        value="<?php echo $campaign->start_date ? esc_attr(date('Y-m-d\TH:i', strtotime($campaign->start_date))) : ''; ?>"
                        aria-describedby="start-date-description">
                    <p class="description" id="start-date-description">
                        <?php esc_html_e('Leave empty to start immediately when activated.', 'pronto-ab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="end_date"><?php esc_html_e('End Date', 'pronto-ab'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" id="end_date" name="end_date"
                        value="<?php echo $campaign->end_date ? esc_attr(date('Y-m-d\TH:i', strtotime($campaign->end_date))) : ''; ?>"
                        aria-describedby="end-date-description">
                    <p class="description" id="end-date-description">
                        <?php esc_html_e('Leave empty to run indefinitely until manually stopped.', 'pronto-ab'); ?>
                    </p>
                </td>
            </tr>
        </table>
    <?php
    }

    /**
     * Enhanced target content selector with AJAX loading
     */
    private function render_target_content_selector($campaign)
    {
        $post_types = get_post_types(array('public' => true), 'objects');
        $target_post_type = $campaign->target_post_type ?? '';
        $target_post_id = $campaign->target_post_id ?? 0;
    ?>
        <div class="pronto-ab-target-selector">
            <select id="target_post_type" name="target_post_type" aria-describedby="target-content-description">
                <option value=""><?php esc_html_e('Select post type', 'pronto-ab'); ?></option>
                <?php foreach ($post_types as $post_type): ?>
                    <option value="<?php echo esc_attr($post_type->name); ?>"
                        <?php selected($target_post_type, $post_type->name); ?>>
                        <?php echo esc_html($post_type->labels->singular_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="target_post_id" name="target_post_id" style="<?php echo $target_post_type ? '' : 'display:none;'; ?>" <?php echo $target_post_type ? '' : 'disabled'; ?>>
                <option value=""><?php esc_html_e('Select content', 'pronto-ab'); ?></option>
                <?php if ($target_post_type): ?>
                    <?php
                    $posts = get_posts(array(
                        'post_type' => $target_post_type,
                        'post_status' => 'publish',
                        'numberposts' => 100,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ));
                    foreach ($posts as $post): ?>
                        <option value="<?php echo esc_attr($post->ID); ?>"
                            <?php selected($target_post_id, $post->ID); ?>>
                            <?php echo esc_html($post->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <p class="description" id="target-content-description">
                <?php esc_html_e('Select the post or page where this A/B test will run. Leave empty for manual placement via shortcode.', 'pronto-ab'); ?>
            </p>
        </div>
    <?php
    }

    private function render_variations_editor($variations)
    {
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

        // Simply call the method from the Pages trait
        $this->render_variations_list((object)['id' => $campaign_id]);
    }

    /**
     * Get variation preview URL
     */
    private function get_variation_preview_url($variation_id)
    {
        $preview_url = get_preview_post_link($variation_id);
        return $preview_url;
    }

    /**
     * Get variation statistics
     */
    private function get_variation_stats($campaign_id, $variation_name)
    {
        if (!$campaign_id || !$variation_name) {
            return null;
        }

        global $wpdb;
        $table = Pronto_AB_Database::get_variations_table();
        $variation = $wpdb->get_row($wpdb->prepare(
            "SELECT impressions, conversions FROM $table WHERE campaign_id = %d AND name = %s",
            $campaign_id,
            $variation_name
        ));

        if (!$variation) {
            return null;
        }

        $conversion_rate = $variation->impressions > 0 ?
            round(($variation->conversions / $variation->impressions) * 100, 2) : 0;

        return array(
            'impressions' => $variation->impressions,
            'conversions' => $variation->conversions,
            'conversion_rate' => $conversion_rate
        );
    }

    /**
     * Render a single variation with enhanced UI
     */
    private function render_single_variation($variation, $index)
    {
    ?>
        <div class="pronto-ab-variation" data-index="<?php echo esc_attr($index); ?>">
            <div class="variation-header">
                <h4 class="variation-title-display">
                    <span class="variation-name-display"><?php echo esc_html($variation->name); ?></span>
                    <?php if ($variation->is_control): ?>
                        <span class="control-badge"><?php esc_html_e('Control', 'pronto-ab'); ?></span>
                    <?php endif; ?>
                    <span class="variation-stats" style="display: none;">
                        <small class="stats-display">0 impressions, 0 conversions (0%)</small>
                    </span>
                </h4>
                <div class="variation-actions">
                    <button type="button" class="button-link preview-variation" title="<?php esc_attr_e('Preview this variation', 'pronto-ab'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <?php if (!$variation->is_control): ?>
                        <button type="button" class="button-link-delete remove-variation" title="<?php esc_attr_e('Remove this variation', 'pronto-ab'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Remove', 'pronto-ab'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="variation-content">
                <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][id]"
                    value="<?php echo esc_attr($variation->id ?? ''); ?>">
                <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][is_control]"
                    value="<?php echo $variation->is_control ? '1' : '0'; ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="variation_name_<?php echo esc_attr($index); ?>"><?php esc_html_e('Name', 'pronto-ab'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="variation_name_<?php echo esc_attr($index); ?>"
                                name="variations[<?php echo esc_attr($index); ?>][name]"
                                value="<?php echo esc_attr($variation->name); ?>"
                                class="regular-text variation-name" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="variation_content_<?php echo esc_attr($index); ?>"><?php esc_html_e('Content', 'pronto-ab'); ?></label>
                        </th>
                        <td>
                            <div class="variation-content-editor">
                                <div class="editor-tabs">
                                    <button type="button" class="editor-tab active" data-tab="html">
                                        <?php esc_html_e('HTML', 'pronto-ab'); ?>
                                    </button>
                                    <button type="button" class="editor-tab" data-tab="preview">
                                        <?php esc_html_e('Preview', 'pronto-ab'); ?>
                                    </button>
                                </div>
                                <div class="editor-content">
                                    <div class="editor-panel active" data-panel="html">
                                        <textarea id="variation_content_<?php echo esc_attr($index); ?>"
                                            name="variations[<?php echo esc_attr($index); ?>][content]"
                                            rows="8" class="large-text code variation-content"><?php echo esc_textarea($variation->content ?? ''); ?></textarea>
                                    </div>
                                    <div class="editor-panel" data-panel="preview">
                                        <div class="variation-preview" id="preview_<?php echo esc_attr($index); ?>">
                                            <?php if (!empty($variation->content)): ?>
                                                <?php echo wp_kses_post($variation->content); ?>
                                            <?php else: ?>
                                                <em><?php esc_html_e('Enter content above to see preview', 'pronto-ab'); ?></em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                                min="0" max="100" step="0.01" class="small-text variation-weight">%
                            <div class="weight-slider" style="margin-top: 8px; width: 200px;"></div>
                            <p class="description">
                                <?php esc_html_e('Percentage of traffic that should see this variation.', 'pronto-ab'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    <?php
    }

    /**
     * Enhanced publish box with more options
     */
    private function render_publish_box($campaign)
    {
    ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php esc_html_e('Publish', 'pronto-ab'); ?></h2>
            </div>
            <div class="inside">
                <div class="submitbox">
                    <div id="minor-publishing">
                        <div id="minor-publishing-actions">
                            <div id="save-action">
                                <input type="submit" name="save_campaign" value="<?php esc_attr_e('Save Draft', 'pronto-ab'); ?>"
                                    class="button" id="save-draft">
                            </div>
                            <div id="preview-action">
                                <button type="button" class="button" id="preview-campaign"
                                    <?php echo !$campaign->id ? 'disabled' : ''; ?>>
                                    <?php esc_html_e('Preview', 'pronto-ab'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="clear"></div>
                    </div>

                    <div id="major-publishing-actions">
                        <div id="publishing-action">
                            <select name="campaign_status" id="campaign-status-select">
                                <option value="draft" <?php selected($campaign->status, 'draft'); ?>><?php esc_html_e('Draft', 'pronto-ab'); ?></option>
                                <option value="active" <?php selected($campaign->status, 'active'); ?>><?php esc_html_e('Active', 'pronto-ab'); ?></option>
                                <option value="paused" <?php selected($campaign->status, 'paused'); ?>><?php esc_html_e('Paused', 'pronto-ab'); ?></option>
                                <option value="completed" <?php selected($campaign->status, 'completed'); ?>><?php esc_html_e('Completed', 'pronto-ab'); ?></option>
                            </select>
                            <input type="submit" name="save_campaign" value="<?php esc_attr_e('Save Campaign', 'pronto-ab'); ?>"
                                class="button button-primary button-large" id="publish">
                        </div>
                        <div class="clear"></div>
                    </div>

                    <!-- Auto-save status -->
                    <div id="autosave-status" style="display: none;">
                        <span class="spinner"></span>
                        <span class="autosave-message"><?php esc_html_e('Auto-saving...', 'pronto-ab'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Enhanced campaign statistics box with real-time updates
     */
    private function render_campaign_stats_box($campaign)
    {
        $stats = $campaign->get_stats();
        $variations = $campaign->get_variations();

        // Ensure stats are never null - provide defaults
        $stats = array_merge(array(
            'total_events' => 0,
            'unique_visitors' => 0,
            'impressions' => 0,
            'conversions' => 0
        ), $stats ?: array());

    ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2>
                    <?php esc_html_e('Campaign Statistics', 'pronto-ab'); ?>
                    <button type="button" class="button-link refresh-stats" id="refresh-stats"
                        title="<?php esc_attr_e('Refresh statistics', 'pronto-ab'); ?>">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                </h2>
            </div>
            <div class="inside">
                <div class="pronto-ab-stats" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                    <div class="stats-summary">
                        <div class="stat-item">
                            <strong class="stat-impressions"><?php echo number_format((int)$stats['impressions']); ?></strong>
                            <span><?php esc_html_e('Total Impressions', 'pronto-ab'); ?></span>
                        </div>
                        <div class="stat-item">
                            <strong class="stat-conversions"><?php echo number_format((int)$stats['conversions']); ?></strong>
                            <span><?php esc_html_e('Total Conversions', 'pronto-ab'); ?></span>
                        </div>
                        <div class="stat-item">
                            <strong class="stat-visitors"><?php echo number_format((int)$stats['unique_visitors']); ?></strong>
                            <span><?php esc_html_e('Unique Visitors', 'pronto-ab'); ?></span>
                        </div>
                        <div class="stat-item">
                            <strong class="stat-rate">
                                <?php
                                $conversion_rate = 0;
                                if ((int)$stats['impressions'] > 0) {
                                    $conversion_rate = round(((int)$stats['conversions'] / (int)$stats['impressions']) * 100, 2);
                                }
                                echo $conversion_rate;
                                ?>%
                            </strong>
                            <span><?php esc_html_e('Conversion Rate', 'pronto-ab'); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($variations) && (int)$stats['impressions'] > 0): ?>
                        <div class="variations-performance">
                            <h4><?php esc_html_e('Variation Performance', 'pronto-ab'); ?></h4>
                            <div class="variation-stat-grid">
                                <?php foreach ($variations as $variation): ?>
                                    <div class="variation-stat" data-variation="<?php echo esc_attr($variation->id); ?>">
                                        <strong><?php echo esc_html($variation->name); ?></strong>
                                        <?php if ($variation->is_control): ?>
                                            <span class="control-badge"><?php esc_html_e('Control', 'pronto-ab'); ?></span>
                                        <?php endif; ?>
                                        <br>
                                        <small>
                                            <span class="variation-impressions"><?php echo number_format((int)$variation->impressions); ?></span> impressions,
                                            <span class="variation-conversions"><?php echo number_format((int)$variation->conversions); ?></span> conversions
                                            (<span class="variation-rate"><?php echo esc_html($variation->get_conversion_rate()); ?></span>%)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>


                    <?php elseif (empty($variations)): ?>
                        <p class="no-variations"><?php esc_html_e('No variations created yet.', 'pronto-ab'); ?></p>
                    <?php else: ?>
                        <p class="no-data"><?php esc_html_e('No data collected yet. Activate the campaign to start collecting data.', 'pronto-ab'); ?></p>
                    <?php endif; ?>

                    <div class="stats-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-analytics&campaign_id=' . $campaign->id)); ?>"
                            class="button button-secondary">
                            <?php esc_html_e('View Detailed Analytics', 'pronto-ab'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Quick actions box for campaign management
     */
    private function render_quick_actions_box($campaign)
    {
    ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php esc_html_e('Quick Actions', 'pronto-ab'); ?></h2>
            </div>
            <div class="inside">
                <div class="quick-actions">
                    <?php if ($campaign->status !== 'active'): ?>
                        <button type="button" class="button button-primary quick-activate"
                            data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Activate Campaign', 'pronto-ab'); ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="button quick-pause"
                            data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                            <span class="dashicons dashicons-controls-pause"></span>
                            <?php esc_html_e('Pause Campaign', 'pronto-ab'); ?>
                        </button>
                    <?php endif; ?>

                    <button type="button" class="button quick-duplicate"
                        data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php esc_html_e('Duplicate Campaign', 'pronto-ab'); ?>
                    </button>

                    <button type="button" class="button quick-export"
                        data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export Data', 'pronto-ab'); ?>
                    </button>

                    <hr>

                    <button type="button" class="button button-link-delete quick-delete"
                        data-campaign-id="<?php echo esc_attr($campaign->id); ?>"
                        data-campaign-name="<?php echo esc_attr($campaign->name); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Delete Campaign', 'pronto-ab'); ?>
                    </button>
                </div>
            </div>
        </div>
<?php
    }
}
