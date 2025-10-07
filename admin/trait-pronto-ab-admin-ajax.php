<?php

/**
 * A/B Test Admin Interface - AJAX Handlers
 * 
 * This trait contains all AJAX handler methods for the admin interface.
 * Include this in your main Pronto_AB_Admin class.
 */

trait Pronto_AB_Admin_Ajax
{
    /**
     * AJAX: Get posts by post type (for target content selector)
     */
    public function ajax_get_posts()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        if (empty($post_type)) {
            wp_send_json_error(__('Post type is required', 'pronto-ab'));
        }

        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => 100,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_pronto_ab_excluded',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        $formatted_posts = array();
        foreach ($posts as $post) {
            $formatted_posts[] = array(
                'ID' => $post->ID,
                'post_title' => $post->post_title,
                'post_status' => $post->post_status,
                'edit_link' => get_edit_post_link($post->ID)
            );
        }

        wp_send_json_success($formatted_posts);
    }

    /**
     * AJAX: Toggle campaign status
     */
    public function ajax_toggle_status()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if (!$campaign_id || !$status) {
            wp_send_json_error(__('Missing required parameters', 'pronto-ab'));
        }

        $valid_statuses = array('draft', 'active', 'paused', 'completed');
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(__('Invalid status', 'pronto-ab'));
        }

        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            wp_send_json_error(__('Campaign not found', 'pronto-ab'));
        }

        // Validate status transition
        if (!$this->can_transition_status($campaign->status, $status)) {
            wp_send_json_error(__('Invalid status transition', 'pronto-ab'));
        }

        $old_status = $campaign->status;
        $campaign->status = $status;

        if ($campaign->save()) {
            // Log status change
            $this->log_campaign_action($campaign_id, 'status_changed', array(
                'old_status' => $old_status,
                'new_status' => $status
            ));

            wp_send_json_success(array(
                'message' => sprintf(__('Campaign status changed to %s', 'pronto-ab'), $status),
                'new_status' => $status
            ));
        } else {
            wp_send_json_error(__('Failed to update campaign', 'pronto-ab'));
        }
    }

    /**
     * AJAX: Get real-time campaign statistics
     */
    public function ajax_get_stats()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        if (!$campaign_id) {
            wp_send_json_error(__('Campaign ID is required', 'pronto-ab'));
        }

        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            wp_send_json_error(__('Campaign not found', 'pronto-ab'));
        }

        $stats = $campaign->get_stats();
        $variations = $campaign->get_variations();

        // Get detailed variation statistics
        $variation_stats = array();
        foreach ($variations as $variation) {
            $variation_stats[] = array(
                'id' => $variation->id,
                'name' => $variation->name,
                'is_control' => $variation->is_control,
                'impressions' => $variation->impressions,
                'conversions' => $variation->conversions,
                'conversion_rate' => $variation->get_conversion_rate()
            );
        }

        // Calculate conversion rate
        $conversion_rate = $stats['impressions'] > 0 ? round(($stats['conversions'] / $stats['impressions']) * 100, 2) : 0;

        wp_send_json_success(array(
            'impressions' => $stats['impressions'],
            'conversions' => $stats['conversions'],
            'unique_visitors' => $stats['unique_visitors'],
            'conversion_rate' => $conversion_rate,
            'variations' => $variation_stats,
            'last_updated' => current_time('mysql')
        ));
    }

    /**
     * AJAX: Auto-save campaign (draft save without validation)
     */
    public function ajax_autosave_campaign()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $campaign = $campaign_id ? Pronto_AB_Campaign::find($campaign_id) : new Pronto_AB_Campaign();

        // Only save basic fields for auto-save (don't validate)
        $campaign->name = sanitize_text_field($_POST['campaign_name'] ?? '');
        $campaign->description = sanitize_textarea_field($_POST['campaign_description'] ?? '');
        $campaign->target_post_id = intval($_POST['target_post_id'] ?? 0);
        $campaign->target_post_type = sanitize_text_field($_POST['target_post_type'] ?? '');
        $campaign->traffic_split = sanitize_text_field($_POST['traffic_split'] ?? '50/50');
        $campaign->start_date = !empty($_POST['start_date']) ? date('Y-m-d H:i:s', strtotime($_POST['start_date'])) : null;
        $campaign->end_date = !empty($_POST['end_date']) ? date('Y-m-d H:i:s', strtotime($_POST['end_date'])) : null;

        // Keep status as draft for auto-saves, don't change existing status
        if (!$campaign->status) {
            $campaign->status = 'draft';
        }

        if ($campaign->save()) {
            // Also auto-save variations if they exist
            if (!empty($_POST['variations']) && is_array($_POST['variations'])) {
                $this->save_campaign_variations($campaign->id, $_POST['variations']);
            }

            wp_send_json_success(array(
                'message' => __('Auto-saved', 'pronto-ab'),
                'campaign_id' => $campaign->id,
                'timestamp' => current_time('mysql'),
                'status' => $campaign->status
            ));
        } else {
            wp_send_json_error(__('Auto-save failed', 'pronto-ab'));
        }
    }

    /**
     * AJAX: Handle bulk actions on multiple campaigns
     */
    public function ajax_bulk_action()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $campaign_ids = array_map('intval', $_POST['campaign_ids'] ?? array());

        if (empty($action) || empty($campaign_ids)) {
            wp_send_json_error(__('Missing required parameters', 'pronto-ab'));
        }

        $valid_actions = array('activate', 'pause', 'complete', 'duplicate', 'delete');
        if (!in_array($action, $valid_actions)) {
            wp_send_json_error(__('Invalid bulk action', 'pronto-ab'));
        }

        $success_count = 0;
        $error_count = 0;
        $errors = array();

        foreach ($campaign_ids as $campaign_id) {
            $campaign = Pronto_AB_Campaign::find($campaign_id);
            if (!$campaign) {
                $error_count++;
                $errors[] = sprintf(__('Campaign ID %d not found', 'pronto-ab'), $campaign_id);
                continue;
            }

            $result = false;
            switch ($action) {
                case 'activate':
                    if ($this->can_transition_status($campaign->status, 'active')) {
                        $campaign->status = 'active';
                        $result = $campaign->save();
                    }
                    break;
                case 'pause':
                    if ($this->can_transition_status($campaign->status, 'paused')) {
                        $campaign->status = 'paused';
                        $result = $campaign->save();
                    }
                    break;
                case 'complete':
                    if ($this->can_transition_status($campaign->status, 'completed')) {
                        $campaign->status = 'completed';
                        $result = $campaign->save();
                    }
                    break;
                case 'duplicate':
                    $result = $this->duplicate_campaign($campaign_id);
                    break;
                case 'delete':
                    $result = $campaign->delete();
                    break;
            }

            if ($result) {
                $success_count++;
                $this->log_campaign_action($campaign_id, 'bulk_' . $action);
            } else {
                $error_count++;
                $errors[] = sprintf(__('Failed to %s campaign "%s"', 'pronto-ab'), $action, $campaign->name);
            }
        }

        if ($success_count > 0 && $error_count === 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d campaigns processed successfully', 'pronto-ab'), $success_count)
            ));
        } elseif ($success_count > 0 && $error_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d campaigns processed, %d errors occurred', 'pronto-ab'), $success_count, $error_count),
                'errors' => $errors
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Bulk action failed', 'pronto-ab'),
                'errors' => $errors
            ));
        }
    }

    /**
     * AJAX: Preview variation content
     */
    public function ajax_preview_variation()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $content = wp_kses_post($_POST['content'] ?? '');
        $campaign_id = intval($_POST['campaign_id'] ?? 0);

        if (empty($content)) {
            wp_send_json_error(__('No content to preview', 'pronto-ab'));
        }

        // Process shortcodes and apply filters like the frontend would
        $processed_content = do_shortcode($content);
        $processed_content = wpautop($processed_content);
        $processed_content = apply_filters('the_content', $processed_content);

        wp_send_json_success(array(
            'preview_html' => $processed_content,
            'raw_content' => $content
        ));
    }

    /**
     * AJAX: Save campaign (legacy handler)
     */
    public function ajax_save_campaign()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $campaign = $campaign_id ? Pronto_AB_Campaign::find($campaign_id) : new Pronto_AB_Campaign();

        // Use the same save logic as the form handler
        $result = $this->save_campaign_form($campaign);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Campaign saved successfully', 'pronto-ab'),
                'campaign_id' => $result['campaign_id'],
                'redirect_url' => $result['redirect_url']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Delete campaign (legacy handler)
     */
    public function ajax_delete_campaign()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        if (!$campaign_id) {
            wp_send_json_error(__('Campaign ID is required', 'pronto-ab'));
        }

        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            wp_send_json_error(__('Campaign not found', 'pronto-ab'));
        }

        // Log before deletion
        $this->log_campaign_action($campaign_id, 'deleted', array(
            'name' => $campaign->name
        ));

        if ($campaign->delete()) {
            wp_send_json_success(array(
                'message' => __('Campaign deleted successfully', 'pronto-ab')
            ));
        } else {
            wp_send_json_error(__('Failed to delete campaign', 'pronto-ab'));
        }
    }

    /**
     * Handle AJAX request for statistics refresh
     */
    /**
     * AJAX: Refresh statistics for a campaign
     */
    public function ajax_refresh_statistics()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);

        if (!$campaign_id) {
            wp_send_json_error(__('Campaign ID is required', 'pronto-ab'));
        }

        // Clear any cached statistics
        delete_transient('pab_stats_' . $campaign_id);

        // Get fresh statistics
        $metrics = Pronto_AB_Statistics::calculate_campaign_metrics($campaign_id);

        // Render HTML
        ob_start();
        $this->render_statistics_box($campaign_id);
        $html = ob_get_clean();

        wp_send_json_success(array(
            'metrics' => $metrics,
            'html' => $html,
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX: Get all goals
     */
    public function ajax_get_goals()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $args = array(
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'goal_type' => sanitize_text_field($_POST['goal_type'] ?? ''),
            'limit' => intval($_POST['limit'] ?? 100),
            'offset' => intval($_POST['offset'] ?? 0)
        );

        $goals = Pronto_AB_Goal::get_all($args);

        $formatted_goals = array();
        foreach ($goals as $goal) {
            $formatted_goals[] = array(
                'id' => $goal->id,
                'name' => $goal->name,
                'description' => $goal->description,
                'goal_type' => $goal->goal_type,
                'tracking_method' => $goal->tracking_method,
                'tracking_value' => $goal->tracking_value,
                'default_value' => $goal->default_value,
                'status' => $goal->status,
                'total_conversions' => $goal->get_total_conversions(),
                'campaigns' => count($goal->get_campaigns())
            );
        }

        wp_send_json_success($formatted_goals);
    }

    /**
     * AJAX: Get goals for a specific campaign
     */
    public function ajax_get_campaign_goals()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        if (!$campaign_id) {
            wp_send_json_error(__('Campaign ID is required', 'pronto-ab'));
        }

        $goals = Pronto_AB_Goal::get_by_campaign($campaign_id);

        $formatted_goals = array();
        foreach ($goals as $goal) {
            $formatted_goals[] = array(
                'id' => $goal->id,
                'name' => $goal->name,
                'description' => $goal->description,
                'goal_type' => $goal->goal_type,
                'tracking_method' => $goal->tracking_method,
                'is_primary' => $goal->is_primary ?? false,
                'campaign_goal_value' => $goal->campaign_goal_value
            );
        }

        wp_send_json_success($formatted_goals);
    }

    /**
     * AJAX: Assign goal to campaign
     */
    public function ajax_assign_goal()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $goal_id = intval($_POST['goal_id'] ?? 0);
        $is_primary = isset($_POST['is_primary']) && $_POST['is_primary'] === 'true';
        $goal_value = isset($_POST['goal_value']) ? floatval($_POST['goal_value']) : null;

        if (!$campaign_id || !$goal_id) {
            wp_send_json_error(__('Campaign ID and Goal ID are required', 'pronto-ab'));
        }

        // Verify goal exists
        $goal = Pronto_AB_Goal::find($goal_id);
        if (!$goal) {
            wp_send_json_error(__('Goal not found', 'pronto-ab'));
        }

        // Verify campaign exists
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            wp_send_json_error(__('Campaign not found', 'pronto-ab'));
        }

        $result = Pronto_AB_Goal::assign_to_campaign($goal_id, $campaign_id, $is_primary, $goal_value);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Goal assigned successfully', 'pronto-ab'),
                'goal' => array(
                    'id' => $goal->id,
                    'name' => $goal->name,
                    'is_primary' => $is_primary
                )
            ));
        } else {
            wp_send_json_error(__('Failed to assign goal', 'pronto-ab'));
        }
    }

    /**
     * AJAX: Remove goal from campaign
     */
    public function ajax_remove_goal()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $goal_id = intval($_POST['goal_id'] ?? 0);

        if (!$campaign_id || !$goal_id) {
            wp_send_json_error(__('Campaign ID and Goal ID are required', 'pronto-ab'));
        }

        $result = Pronto_AB_Goal::remove_from_campaign($goal_id, $campaign_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Goal removed successfully', 'pronto-ab')
            ));
        } else {
            wp_send_json_error(__('Failed to remove goal', 'pronto-ab'));
        }
    }

    /**
     * AJAX: Delete goal
     */
    public function ajax_delete_goal()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $goal_id = intval($_POST['goal_id'] ?? 0);
        if (!$goal_id) {
            wp_send_json_error(__('Goal ID is required', 'pronto-ab'));
        }

        $goal = Pronto_AB_Goal::find($goal_id);
        if (!$goal) {
            wp_send_json_error(__('Goal not found', 'pronto-ab'));
        }

        $result = $goal->delete();

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Goal deleted successfully', 'pronto-ab')
            ));
        } else {
            wp_send_json_error(__('Failed to delete goal', 'pronto-ab'));
        }
    }

    /**
     * AJAX: Get goal statistics
     */
    public function ajax_get_goal_stats()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $goal_id = intval($_POST['goal_id'] ?? 0);

        if (!$campaign_id || !$goal_id) {
            wp_send_json_error(__('Campaign ID and Goal ID are required', 'pronto-ab'));
        }

        $comparison = Pronto_AB_Goal_Tracker::get_goal_comparison($campaign_id, $goal_id);
        $revenue = Pronto_AB_Goal_Tracker::get_goal_revenue($campaign_id, $goal_id);

        wp_send_json_success(array(
            'comparison' => $comparison,
            'revenue' => $revenue
        ));
    }
}
