<?php

/**
 * Winner Declaration System
 * 
 * Handles winner declaration workflow including:
 * - Manual winner selection
 * - Automatic winner detection
 * - Traffic redirection
 * - Campaign archiving
 * - Notifications
 * 
 * @package Pronto_AB
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Pronto_AB_Winner_Declaration
{
    /**
     * Minimum conversions per variation for valid winner declaration
     */
    const MIN_CONVERSIONS = 100;

    /**
     * Minimum test duration in days
     */
    const MIN_DAYS = 7;

    /**
     * Default confidence level for auto-detection (95%)
     */
    const DEFAULT_CONFIDENCE = 95;

    /**
     * Declare a winner for a campaign
     * 
     * @param int $campaign_id Campaign ID
     * @param int $variation_id Winning variation ID
     * @param int $user_id User declaring the winner (0 for system)
     * @param array $options {
     *     Optional. Additional options.
     *     @type bool $auto_detected Whether winner was auto-detected
     *     @type bool $auto_apply Apply winner to all traffic automatically
     *     @type bool $archive Archive campaign after declaration
     *     @type bool $notify Send email notification
     * }
     * @return array|WP_Error Success data or error
     */
    public static function declare($campaign_id, $variation_id, $user_id = 0, $options = array())
    {
        // Set default options
        $options = wp_parse_args($options, array(
            'auto_detected' => false,
            'auto_apply' => false,
            'archive' => false,
            'notify' => true
        ));

        // Validate the declaration
        $validation = self::validate_declaration($campaign_id, $variation_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Load campaign
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error(
                'campaign_not_found',
                __('Campaign not found.', 'pronto-ab')
            );
        }

        // Load variation
        $variation = Pronto_AB_Variation::find($variation_id);
        if (!$variation || $variation->campaign_id != $campaign_id) {
            return new WP_Error(
                'variation_not_found',
                __('Variation not found or does not belong to this campaign.', 'pronto-ab')
            );
        }

        // Update campaign with winner information
        $campaign->winner_variation_id = $variation_id;
        $campaign->winner_declared_at = current_time('mysql');
        $campaign->winner_declared_by = $user_id;

        if (!$campaign->save()) {
            return new WP_Error(
                'save_failed',
                __('Failed to save winner declaration.', 'pronto-ab')
            );
        }

        // Log the declaration
        self::log_declaration($campaign_id, $variation_id, $user_id, $options['auto_detected']);

        // Apply winner to all traffic if requested
        if ($options['auto_apply']) {
            $apply_result = self::apply_winner($campaign_id);
            if (is_wp_error($apply_result)) {
                error_log("Pronto A/B: Failed to auto-apply winner for campaign {$campaign_id}: " . $apply_result->get_error_message());
            }
        }

        // Archive campaign if requested
        if ($options['archive']) {
            $archive_result = self::archive_campaign($campaign_id, $user_id);
            if (is_wp_error($archive_result)) {
                error_log("Pronto A/B: Failed to archive campaign {$campaign_id}: " . $archive_result->get_error_message());
            }
        }

        // Send notification if requested
        if ($options['notify']) {
            self::send_notification($campaign_id, 'winner_declared', array(
                'variation_id' => $variation_id,
                'variation_name' => $variation->name,
                'auto_detected' => $options['auto_detected']
            ));
        }

        // Return success with details
        return array(
            'success' => true,
            'campaign_id' => $campaign_id,
            'variation_id' => $variation_id,
            'variation_name' => $variation->name,
            'declared_at' => $campaign->winner_declared_at,
            'applied' => $options['auto_apply'],
            'archived' => $options['archive']
        );
    }

    /**
     * Automatically detect and declare winner based on statistical significance
     * 
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error|false Winner data on success, WP_Error on failure, false if no clear winner
     */
    public static function auto_detect_winner($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', 'Campaign not found');
        }

        // Check if winner already declared
        if ($campaign->winner_variation_id) {
            return new WP_Error('winner_exists', 'Winner already declared for this campaign');
        }

        // Get auto-winner settings
        $settings = get_option('pronto_ab_auto_winner_settings', array());
        $min_conversions = isset($settings['min_conversions']) ? intval($settings['min_conversions']) : self::MIN_CONVERSIONS;
        $min_days = isset($settings['min_days']) ? intval($settings['min_days']) : self::MIN_DAYS;
        $confidence_level = isset($settings['confidence_level']) ? intval($settings['confidence_level']) : self::DEFAULT_CONFIDENCE;

        // Check minimum time requirement
        $days_running = self::get_campaign_days_running($campaign);
        if ($days_running < $min_days) {
            return false; // Not ready yet
        }

        // Get variations
        $variations = $campaign->get_variations();
        if (count($variations) < 2) {
            return new WP_Error('insufficient_variations', 'Need at least 2 variations');
        }

        // Check all variations meet minimum conversions
        foreach ($variations as $variation) {
            if ($variation->conversions < $min_conversions) {
                return false; // Not enough data yet
            }
        }

        // Calculate statistical significance
        $stats = Pronto_AB_Statistics::calculate_campaign_metrics($campaign_id);

        if (isset($stats['error'])) {
            return new WP_Error('stats_error', $stats['error']);
        }

        // Find clear winner with required confidence level
        $winner = null;
        $winner_variation_id = null;

        foreach ($stats as $result) {
            if (!isset($result['stats']) || isset($result['stats']['error'])) {
                continue;
            }

            $stats_data = $result['stats'];

            // Check confidence level
            $is_significant = false;
            if ($confidence_level >= 99 && isset($stats_data['confidence_99']) && $stats_data['confidence_99']) {
                $is_significant = true;
            } elseif ($confidence_level >= 95 && isset($stats_data['confidence_95']) && $stats_data['confidence_95']) {
                $is_significant = true;
            } elseif ($confidence_level >= 90 && isset($stats_data['confidence_90']) && $stats_data['confidence_90']) {
                $is_significant = true;
            }

            if ($is_significant) {
                // Check if this is the best performer
                if (!$winner || (isset($result['conversion_rate']) && $result['conversion_rate'] > $winner['conversion_rate'])) {
                    $winner = $result;
                    $winner_variation_id = $result['variation_id'];
                }
            }
        }

        // If we found a clear winner, declare it
        if ($winner_variation_id) {
            $auto_apply = isset($settings['auto_apply']) ? (bool)$settings['auto_apply'] : false;
            $auto_archive = isset($settings['auto_archive']) ? (bool)$settings['auto_archive'] : false;

            return self::declare(
                $campaign_id,
                $winner_variation_id,
                0, // System user
                array(
                    'auto_detected' => true,
                    'auto_apply' => $auto_apply,
                    'archive' => $auto_archive,
                    'notify' => true
                )
            );
        }

        return false; // No clear winner yet
    }

    /**
     * Apply winner to all traffic (set to 100%)
     * 
     * @param int $campaign_id Campaign ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function apply_winner($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', 'Campaign not found');
        }

        if (!$campaign->winner_variation_id) {
            return new WP_Error('no_winner', 'No winner declared for this campaign');
        }

        $variations = $campaign->get_variations();
        $success = true;

        foreach ($variations as $variation) {
            if ($variation->id == $campaign->winner_variation_id) {
                // Set winner to 100%
                $variation->weight_percentage = 100.00;
            } else {
                // Set losers to 0%
                $variation->weight_percentage = 0.00;
            }

            if (!$variation->save()) {
                $success = false;
                error_log("Pronto A/B: Failed to update variation {$variation->id} weight");
            }
        }

        if (!$success) {
            return new WP_Error('update_failed', 'Failed to update variation weights');
        }

        // Update campaign status to completed
        $campaign->status = 'completed';
        $campaign->save();

        // Log the action
        self::log_action($campaign_id, 'winner_applied', array(
            'variation_id' => $campaign->winner_variation_id
        ));

        return true;
    }

    /**
     * Archive losing variations
     * 
     * @param int $campaign_id Campaign ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function archive_losers($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', 'Campaign not found');
        }

        if (!$campaign->winner_variation_id) {
            return new WP_Error('no_winner', 'No winner declared');
        }

        $variations = $campaign->get_variations();
        foreach ($variations as $variation) {
            if ($variation->id != $campaign->winner_variation_id) {
                // Mark as archived by setting weight to 0
                $variation->weight_percentage = 0.00;
                $variation->save();
            }
        }

        return true;
    }

    /**
     * Archive entire campaign
     * 
     * @param int $campaign_id Campaign ID
     * @param int $user_id User archiving the campaign
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function archive_campaign($campaign_id, $user_id = 0)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', 'Campaign not found');
        }

        $campaign->archived_at = current_time('mysql');
        $campaign->archived_by = $user_id;
        $campaign->status = 'archived';

        if (!$campaign->save()) {
            return new WP_Error('save_failed', 'Failed to archive campaign');
        }

        // Log the action
        self::log_action($campaign_id, 'campaign_archived', array(
            'user_id' => $user_id
        ));

        return true;
    }

    /**
     * Restore campaign from archive
     * 
     * @param int $campaign_id Campaign ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function restore_campaign($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', 'Campaign not found');
        }

        $campaign->archived_at = null;
        $campaign->archived_by = null;
        $campaign->status = 'active';

        if (!$campaign->save()) {
            return new WP_Error('save_failed', 'Failed to restore campaign');
        }

        return true;
    }

    /**
     * Validate winner declaration
     * 
     * @param int $campaign_id Campaign ID
     * @param int $variation_id Variation ID
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public static function validate_declaration($campaign_id, $variation_id)
    {
        // Check campaign exists
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error(
                'campaign_not_found',
                __('Campaign not found.', 'pronto-ab')
            );
        }

        // Check if winner already declared
        if ($campaign->winner_variation_id) {
            return new WP_Error(
                'winner_exists',
                __('A winner has already been declared for this campaign.', 'pronto-ab')
            );
        }

        // Check variation exists and belongs to campaign
        $variation = Pronto_AB_Variation::find($variation_id);
        if (!$variation) {
            return new WP_Error(
                'variation_not_found',
                __('Variation not found.', 'pronto-ab')
            );
        }

        if ($variation->campaign_id != $campaign_id) {
            return new WP_Error(
                'variation_mismatch',
                __('Variation does not belong to this campaign.', 'pronto-ab')
            );
        }

        // Check minimum data requirements
        $data_check = self::check_minimum_data($campaign_id);
        if (is_wp_error($data_check)) {
            return $data_check;
        }

        return true;
    }

    /**
     * Check if campaign has minimum required data
     * 
     * @param int $campaign_id Campaign ID
     * @return bool|WP_Error True if sufficient data, WP_Error otherwise
     */
    public static function check_minimum_data($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', 'Campaign not found');
        }

        $variations = $campaign->get_variations();

        // Need at least 2 variations
        if (count($variations) < 2) {
            return new WP_Error(
                'insufficient_variations',
                __('Campaign must have at least 2 variations to declare a winner.', 'pronto-ab')
            );
        }

        // Check each variation has minimum impressions
        $min_impressions = 30; // Bare minimum
        foreach ($variations as $variation) {
            if ($variation->impressions < $min_impressions) {
                return new WP_Error(
                    'insufficient_data',
                    sprintf(
                        __('Variation "%s" has insufficient data (minimum %d impressions required).', 'pronto-ab'),
                        $variation->name,
                        $min_impressions
                    )
                );
            }
        }

        return true;
    }

    /**
     * Get recommended winner based on current data
     * 
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error Winner recommendation or error
     */
    public static function get_recommendation($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', 'Campaign not found');
        }

        // First check if we have variations
        $variations = $campaign->get_variations();
        if (count($variations) < 2) {
            return new WP_Error('insufficient_variations', 'Campaign needs at least 2 variations to recommend a winner');
        }

        // Check if variations have any data
        $has_data = false;
        foreach ($variations as $variation) {
            if ($variation->impressions > 0) {
                $has_data = true;
                break;
            }
        }

        if (!$has_data) {
            return new WP_Error('no_impressions', 'No impression data available yet. The campaign needs to collect data first.');
        }

        // Get statistical analysis
        $stats = Pronto_AB_Statistics::calculate_campaign_metrics($campaign_id);

        if (isset($stats['error'])) {
            return new WP_Error('stats_error', $stats['error']);
        }

        if (empty($stats)) {
            return new WP_Error('no_stats', 'Unable to calculate statistics. Please ensure variations have data.');
        }

        // Find variation with highest conversion rate
        // The structure has conversion_rate_b for the variation being tested
        $best = null;
        $highest_rate = -1;

        foreach ($stats as $result) {
            // The conversion rate for the variation is in stats->conversion_rate_b
            if (!isset($result['stats']['conversion_rate_b'])) {
                continue;
            }

            $variation_rate = $result['stats']['conversion_rate_b'];

            if ($variation_rate > $highest_rate) {
                $highest_rate = $variation_rate;
                $best = $result;
            }
        }

        if (!$best) {
            return new WP_Error('no_data', 'Unable to determine a recommendation. Variations may not have enough data.');
        }

        // Extract data from the actual structure
        $variation_rate = $best['stats']['conversion_rate_b'];
        $control_rate = $best['stats']['conversion_rate_a'];
        $is_significant = !empty($best['stats']['is_significant']);

        return array(
            'variation_id' => $best['variation_id'],
            'variation_name' => $best['variation_name'],
            'conversion_rate' => $variation_rate,
            'control_name' => $best['control_name'],
            'control_rate' => $control_rate,
            'lift' => isset($best['stats']['lift']) ? $best['stats']['lift'] : 0,
            'confidence' => $best['stats'],
            'data_check' => isset($best['data_check']) ? $best['data_check'] : array(),
            'interpretation' => isset($best['interpretation']) ? $best['interpretation'] : '',
            'recommendation' => self::get_recommendation_text($best),
            'is_significant' => $is_significant,
            'z_score' => isset($best['stats']['z_score']) ? $best['stats']['z_score'] : null,
            'p_value' => isset($best['stats']['p_value']) ? $best['stats']['p_value'] : null
        );
    }

    /**
     * Get recommendation text based on statistical confidence
     * 
     * @param array $result Statistical results from calculate_campaign_metrics
     * @return string Recommendation text
     */
    private static function get_recommendation_text($result)
    {
        if (!isset($result['stats'])) {
            return __('Insufficient data for recommendation.', 'pronto-ab');
        }

        $stats = $result['stats'];

        // Use the interpretation if available
        if (isset($result['interpretation']) && !empty($result['interpretation'])) {
            return $result['interpretation'];
        }

        // Fallback to confidence-based recommendations
        if (isset($stats['confidence_99']) && $stats['confidence_99']) {
            return __('Strong recommendation - 99% confidence level reached.', 'pronto-ab');
        }

        if (isset($stats['confidence_95']) && $stats['confidence_95']) {
            return __('Good recommendation - 95% confidence level reached.', 'pronto-ab');
        }

        if (isset($stats['confidence_90']) && $stats['confidence_90']) {
            return __('Moderate recommendation - 90% confidence level reached. Consider running test longer.', 'pronto-ab');
        }

        return __('Weak recommendation - Statistical significance not yet reached. Continue testing.', 'pronto-ab');
    }

    /**
     * Send notification about winner declaration
     * 
     * @param int $campaign_id Campaign ID
     * @param string $type Notification type
     * @param array $data Additional notification data
     * @return bool Success status
     */
    public static function send_notification($campaign_id, $type, $data = array())
    {
        // This will be implemented with the Notifications class
        // For now, just trigger an action hook
        do_action('pronto_ab_winner_notification', $campaign_id, $type, $data);

        return true;
    }

    /**
     * Log winner declaration action
     * 
     * @param int $campaign_id Campaign ID
     * @param int $variation_id Variation ID
     * @param int $user_id User ID
     * @param bool $auto_detected Whether auto-detected
     */
    private static function log_declaration($campaign_id, $variation_id, $user_id, $auto_detected)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        $variation = Pronto_AB_Variation::find($variation_id);

        $log_message = sprintf(
            'Winner declared for campaign "%s" (ID: %d): Variation "%s" (ID: %d) | Method: %s | User: %d',
            $campaign->name,
            $campaign_id,
            $variation->name,
            $variation_id,
            $auto_detected ? 'Auto-detected' : 'Manual',
            $user_id
        );

        error_log('Pronto A/B: ' . $log_message);

        // Trigger action for external logging
        do_action('pronto_ab_winner_declared', $campaign_id, $variation_id, $user_id, $auto_detected);
    }

    /**
     * Log general action
     * 
     * @param int $campaign_id Campaign ID
     * @param string $action Action type
     * @param array $data Additional data
     */
    private static function log_action($campaign_id, $action, $data = array())
    {
        error_log(sprintf(
            'Pronto A/B: Action "%s" for campaign %d - Data: %s',
            $action,
            $campaign_id,
            json_encode($data)
        ));

        do_action('pronto_ab_action_logged', $campaign_id, $action, $data);
    }

    /**
     * Get number of days campaign has been running
     * 
     * @param Pronto_AB_Campaign $campaign Campaign object
     * @return int Number of days
     */
    private static function get_campaign_days_running($campaign)
    {
        if (!$campaign->start_date) {
            // Use created_at if no start date
            $start = strtotime($campaign->created_at);
        } else {
            $start = strtotime($campaign->start_date);
        }

        $now = current_time('timestamp');
        $days = floor(($now - $start) / DAY_IN_SECONDS);

        return max(0, $days);
    }

    /**
     * Get winner declaration summary for a campaign
     * 
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error Summary data or error
     */
    public static function get_winner_summary($campaign_id)
    {
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', 'Campaign not found');
        }

        if (!$campaign->winner_variation_id) {
            return new WP_Error('no_winner', 'No winner declared');
        }

        $winner = Pronto_AB_Variation::find($campaign->winner_variation_id);
        $all_variations = $campaign->get_variations();
        $stats = Pronto_AB_Statistics::calculate_campaign_metrics($campaign_id);

        // Get winner stats
        $winner_stats = null;
        foreach ($stats as $result) {
            if ($result['variation_id'] == $campaign->winner_variation_id) {
                $winner_stats = $result;
                break;
            }
        }

        return array(
            'campaign_id' => $campaign_id,
            'campaign_name' => $campaign->name,
            'winner_id' => $winner->id,
            'winner_name' => $winner->name,
            'declared_at' => $campaign->winner_declared_at,
            'declared_by' => $campaign->winner_declared_by,
            'is_applied' => $winner->weight_percentage >= 100,
            'is_archived' => !empty($campaign->archived_at),
            'stats' => $winner_stats,
            'total_variations' => count($all_variations)
        );
    }
}
