<?php

/**
 * A/B Test Admin Interface - Helper Methods
 * 
 * This trait contains all helper methods for campaign management.
 * Include this in your main Pronto_AB_Admin class.
 */

trait Pronto_AB_Admin_Helpers
{
    /**
     * Save campaign form data with enhanced validation
     */
    private function save_campaign_form($campaign)
    {
        $errors = array();

        // Validate required fields
        $campaign_name = sanitize_text_field($_POST['campaign_name'] ?? '');
        if (empty($campaign_name)) {
            $errors[] = __('Campaign name is required', 'pronto-ab');
        }

        // Validate variations
        $variations_data = $_POST['variations'] ?? array();
        if (empty($variations_data)) {
            $errors[] = __('At least one variation is required', 'pronto-ab');
        }

        // Validate weights
        $total_weight = 0;
        foreach ($variations_data as $variation_data) {
            $total_weight += floatval($variation_data['weight_percentage'] ?? 0);
        }

        if (abs($total_weight - 100) > 1) {
            $errors[] = __('Total variation weights should equal 100%', 'pronto-ab');
        }

        // Validate dates
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        if ($start_date && $end_date && strtotime($start_date) >= strtotime($end_date)) {
            $errors[] = __('End date must be after start date', 'pronto-ab');
        }

        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => implode('<br>', $errors)
            );
        }

        // Save campaign data
        $campaign->name = $campaign_name;
        $campaign->description = sanitize_textarea_field($_POST['campaign_description'] ?? '');
        $campaign->status = sanitize_text_field($_POST['campaign_status'] ?? 'draft');
        $campaign->target_post_id = intval($_POST['target_post_id'] ?? 0);
        $campaign->target_post_type = sanitize_text_field($_POST['target_post_type'] ?? '');
        $campaign->traffic_split = sanitize_text_field($_POST['traffic_split'] ?? '50/50');
        $campaign->start_date = $start_date ? date('Y-m-d H:i:s', strtotime($start_date)) : null;
        $campaign->end_date = $end_date ? date('Y-m-d H:i:s', strtotime($end_date)) : null;

        if ($campaign->save()) {
            // Save variations
            $this->save_campaign_variations($campaign->id, $variations_data);

            // Log campaign save
            $this->log_campaign_action($campaign->id, 'saved', array(
                'status' => $campaign->status
            ));

            return array(
                'success' => true,
                'redirect_url' => admin_url('admin.php?page=pronto-abs&message=saved'),
                'campaign_id' => $campaign->id
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Failed to save campaign', 'pronto-ab')
            );
        }
    }

    /**
     * Save campaign variations with enhanced handling
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
                $existing = Pronto_AB_Variation::find($variation_data['id']);
                if ($existing) {
                    $variation = $existing;
                }
            }

            $variation->campaign_id = $campaign_id;
            $variation->name = sanitize_text_field($variation_data['name'] ?? '');
            $variation->content = wp_kses_post($variation_data['content'] ?? '');
            $variation->is_control = !empty($variation_data['is_control']);
            $variation->weight_percentage = floatval($variation_data['weight_percentage'] ?? 50);

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

        return true;
    }

    /**
     * Delete campaign and cleanup related data
     */
    private function delete_campaign($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if ($campaign) {
            // Log before deletion
            $this->log_campaign_action($campaign_id, 'deleted', array(
                'name' => $campaign->name
            ));

            if ($campaign->delete()) {
                $this->add_admin_notice('success', __('Campaign deleted successfully.', 'pronto-ab'));
                wp_redirect(admin_url('admin.php?page=pronto-abs&message=deleted'));
                exit;
            }
        }
        $this->add_admin_notice('error', __('Failed to delete campaign.', 'pronto-ab'));
    }

    /**
     * Toggle campaign status with validation
     */
    private function toggle_campaign_status($campaign_id, $status)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if ($campaign && $this->can_transition_status($campaign->status, $status)) {
            $old_status = $campaign->status;
            $campaign->status = $status;

            if ($campaign->save()) {
                $this->log_campaign_action($campaign_id, 'status_changed', array(
                    'old_status' => $old_status,
                    'new_status' => $status
                ));

                $this->add_admin_notice('success', sprintf(__('Campaign status changed to %s.', 'pronto-ab'), $status));
            } else {
                $this->add_admin_notice('error', __('Failed to update campaign status.', 'pronto-ab'));
            }
        } else {
            $this->add_admin_notice('error', __('Invalid status transition.', 'pronto-ab'));
        }

        wp_redirect(admin_url('admin.php?page=pronto-abs&message=updated'));
        exit;
    }

    /**
     * Duplicate campaign with all variations
     */
    private function duplicate_campaign($campaign_id)
    {
        $original = Pronto_AB_Campaign::find($campaign_id);
        if (!$original) {
            return false;
        }

        // Create new campaign
        $duplicate = new Pronto_AB_Campaign();
        $duplicate->name = $original->name . ' (Copy)';
        $duplicate->description = $original->description;
        $duplicate->status = 'draft'; // Always start as draft
        $duplicate->target_post_id = $original->target_post_id;
        $duplicate->target_post_type = $original->target_post_type;
        $duplicate->traffic_split = $original->traffic_split;
        // Don't copy dates - let user set new dates

        if ($duplicate->save()) {
            // Duplicate variations
            $original_variations = $original->get_variations();
            foreach ($original_variations as $original_variation) {
                $duplicate_variation = new Pronto_AB_Variation();
                $duplicate_variation->campaign_id = $duplicate->id;
                $duplicate_variation->name = $original_variation->name;
                $duplicate_variation->content = $original_variation->content;
                $duplicate_variation->is_control = $original_variation->is_control;
                $duplicate_variation->weight_percentage = $original_variation->weight_percentage;
                $duplicate_variation->save();
            }

            $this->log_campaign_action($duplicate->id, 'duplicated', array(
                'original_id' => $campaign_id
            ));

            return $duplicate->id;
        }

        return false;
    }

    /**
     * Handle bulk actions from form submission
     */
    private function handle_bulk_action($action, $campaign_ids)
    {
        $campaign_ids = array_map('intval', $campaign_ids);
        $success_count = 0;

        foreach ($campaign_ids as $campaign_id) {
            $campaign = Pronto_AB_Campaign::find($campaign_id);
            if (!$campaign) {
                continue;
            }

            switch ($action) {
                case 'activate':
                    if ($this->can_transition_status($campaign->status, 'active')) {
                        $campaign->status = 'active';
                        if ($campaign->save()) $success_count++;
                    }
                    break;
                case 'pause':
                    if ($this->can_transition_status($campaign->status, 'paused')) {
                        $campaign->status = 'paused';
                        if ($campaign->save()) $success_count++;
                    }
                    break;
                case 'complete':
                    if ($this->can_transition_status($campaign->status, 'completed')) {
                        $campaign->status = 'completed';
                        if ($campaign->save()) $success_count++;
                    }
                    break;
                case 'duplicate':
                    if ($this->duplicate_campaign($campaign_id)) {
                        $success_count++;
                    }
                    break;
                case 'delete':
                    if ($campaign->delete()) {
                        $success_count++;
                    }
                    break;
            }
        }

        if ($success_count > 0) {
            $this->add_admin_notice('success', sprintf(
                __('%d campaigns processed successfully.', 'pronto-ab'),
                $success_count
            ));
        } else {
            $this->add_admin_notice('error', __('No campaigns were processed.', 'pronto-ab'));
        }

        wp_redirect(admin_url('admin.php?page=pronto-abs&message=bulk_action_completed'));
        exit;
    }

    /**
     * Check if status transition is valid
     */
    private function can_transition_status($from_status, $to_status)
    {
        $valid_transitions = array(
            'draft' => array('active'),
            'active' => array('paused', 'completed'),
            'paused' => array('active', 'completed'),
            'completed' => array() // No transitions from completed
        );

        return isset($valid_transitions[$from_status]) &&
            in_array($to_status, $valid_transitions[$from_status]);
    }

    /**
     * Log campaign actions for audit trail
     */
    private function log_campaign_action($campaign_id, $action, $data = array())
    {
        $log_entry = array(
            'campaign_id' => $campaign_id,
            'action' => $action,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'data' => $data
        );

        // Store in WordPress options for simple implementation
        $logs = get_option('pronto_ab_action_logs', array());
        array_unshift($logs, $log_entry); // Add to beginning

        // Keep only last 1000 entries
        $logs = array_slice($logs, 0, 1000);

        update_option('pronto_ab_action_logs', $logs);
    }

    /**
     * Get campaign by ID with error handling
     */
    private function get_campaign_or_404($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            wp_die(__('Campaign not found.', 'pronto-ab'), 404);
        }
        return $campaign;
    }

    /**
     * Validate campaign permissions
     */
    private function validate_campaign_permissions($action = 'manage')
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'pronto-ab'));
        }

        // Add more granular permissions here if needed
        switch ($action) {
            case 'edit':
                // Could check for edit_campaign capability
                break;
            case 'delete':
                // Could check for delete_campaign capability
                break;
        }

        return true;
    }

    /**
     * Get formatted date for display
     */
    private function format_campaign_date($date, $format = 'M j, Y')
    {
        if (!$date) {
            return '—';
        }
        return date_i18n($format, strtotime($date));
    }

    /**
     * Generate campaign permalink for frontend viewing
     */
    private function get_campaign_preview_url($campaign)
    {
        if (!$campaign->target_post_id) {
            return '';
        }

        $post_url = get_permalink($campaign->target_post_id);
        if (!$post_url) {
            return '';
        }

        // Add campaign parameter for preview
        return add_query_arg(array(
            'ab_preview' => $campaign->id,
            'ab_variation' => 'all'
        ), $post_url);
    }

    /**
     * Get campaign export data
     */
    private function get_campaign_export_data($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return false;
        }

        $variations = $campaign->get_variations();
        $stats = $campaign->get_stats();

        return array(
            'campaign' => array(
                'id' => $campaign->id,
                'name' => $campaign->name,
                'description' => $campaign->description,
                'status' => $campaign->status,
                'traffic_split' => $campaign->traffic_split,
                'start_date' => $campaign->start_date,
                'end_date' => $campaign->end_date,
                'created_at' => $campaign->created_at
            ),
            'variations' => array_map(function ($variation) {
                return array(
                    'id' => $variation->id,
                    'name' => $variation->name,
                    'is_control' => $variation->is_control,
                    'weight_percentage' => $variation->weight_percentage,
                    'impressions' => $variation->impressions,
                    'conversions' => $variation->conversions,
                    'conversion_rate' => $variation->get_conversion_rate()
                );
            }, $variations),
            'statistics' => $stats,
            'export_date' => current_time('mysql')
        );
    }

    /**
     * Check if campaign has sufficient data for statistical significance
     */
    private function has_statistical_significance($campaign)
    {
        $stats = $campaign->get_stats();
        $variations = $campaign->get_variations();

        // Basic check: at least 100 impressions per variation
        $min_impressions_per_variation = 100;
        $total_variations = count($variations);

        if ($total_variations < 2) {
            return false;
        }

        $required_total_impressions = $min_impressions_per_variation * $total_variations;

        return $stats['impressions'] >= $required_total_impressions;
    }

    /**
     * Get winner variation based on conversion rate
     */
    private function get_winning_variation($campaign)
    {
        $variations = $campaign->get_variations();
        if (count($variations) < 2) {
            return null;
        }

        // Filter out variations with no impressions
        $valid_variations = array_filter($variations, function ($variation) {
            return $variation->impressions > 0;
        });

        if (empty($valid_variations)) {
            return null;
        }

        // Sort by conversion rate
        usort($valid_variations, function ($a, $b) {
            return $b->get_conversion_rate() <=> $a->get_conversion_rate();
        });

        return $valid_variations[0];
    }

    /**
     * Clean up old campaign data
     */
    public function cleanup_old_data()
    {
        $settings = get_option('pronto_ab_settings', array());
        $retention_days = intval($settings['data_retention_days'] ?? 365);

        if ($retention_days <= 0) {
            return; // No cleanup if retention is 0 or negative
        }

        global $wpdb;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        // Clean up old analytics data
        $analytics_table = Pronto_AB_Database::get_analytics_table();
        $deleted_analytics = $wpdb->query($wpdb->prepare(
            "DELETE FROM $analytics_table WHERE timestamp < %s",
            $cutoff_date
        ));

        // Clean up completed campaigns older than retention period
        $campaigns_table = Pronto_AB_Database::get_campaigns_table();
        $deleted_campaigns = $wpdb->query($wpdb->prepare(
            "DELETE FROM $campaigns_table WHERE status = 'completed' AND updated_at < %s",
            $cutoff_date
        ));

        // Log cleanup activity
        if ($deleted_analytics || $deleted_campaigns) {
            error_log(sprintf(
                'Pronto A/B: Cleanup completed - %d analytics records, %d campaigns removed',
                $deleted_analytics ?: 0,
                $deleted_campaigns ?: 0
            ));
        }
    }

    /**
     * Schedule cleanup cron job
     */
    public function schedule_cleanup()
    {
        if (!wp_next_scheduled('pronto_ab_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pronto_ab_daily_cleanup');
        }
    }

    /**
     * Unschedule cleanup cron job
     */
    public function unschedule_cleanup()
    {
        wp_clear_scheduled_hook('pronto_ab_daily_cleanup');
    }
}
