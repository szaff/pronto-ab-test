<?php

/**
 * A/B Test Goal Tracker
 * Handles goal tracking and statistics
 *
 * @package Pronto_AB
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Pronto_AB_Goal_Tracker
{
    /**
     * Track goal completion
     *
     * @param int $campaign_id Campaign ID
     * @param int $variation_id Variation ID
     * @param int $goal_id Goal ID
     * @param string $visitor_id Visitor ID
     * @param float $value Optional goal value (for revenue tracking)
     * @return bool Success status
     */
    public static function track_goal($campaign_id, $variation_id, $goal_id, $visitor_id, $value = null)
    {
        global $wpdb;

        // Validate inputs
        if (!$campaign_id || !$variation_id || !$goal_id || !$visitor_id) {
            error_log("Pronto A/B: Invalid goal tracking parameters");
            return false;
        }

        // Get goal to determine default value if not provided
        $goal = Pronto_AB_Goal::find($goal_id);
        if (!$goal) {
            error_log("Pronto A/B: Goal {$goal_id} not found");
            return false;
        }

        // Use provided value or default from goal
        $goal_value = $value !== null ? floatval($value) : $goal->default_value;

        // Prepare analytics data
        $data = array(
            'campaign_id' => $campaign_id,
            'variation_id' => $variation_id,
            'visitor_id' => $visitor_id,
            'session_id' => session_id(),
            'event_type' => 'goal',
            'event_value' => $goal->name,
            'goal_id' => $goal_id,
            'goal_value' => $goal_value,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => self::get_client_ip(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'additional_data' => json_encode(array(
                'goal_name' => $goal->name,
                'goal_type' => $goal->goal_type,
                'timestamp' => current_time('mysql')
            ))
        );

        // Insert into analytics table
        $result = $wpdb->insert(
            Pronto_AB_Database::get_analytics_table(),
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log("Pronto A/B: Failed to track goal {$goal_id}");
            error_log("MySQL Error: " . $wpdb->last_error);
            return false;
        }

        // Trigger action for external integrations
        do_action('pronto_ab_goal_tracked', $campaign_id, $variation_id, $goal_id, $visitor_id, $goal_value);

        return true;
    }

    /**
     * Get goal statistics for a campaign variation
     *
     * @param int $campaign_id Campaign ID
     * @param int $variation_id Variation ID (optional, null for all variations)
     * @param int $goal_id Goal ID (optional, null for all goals)
     * @return array Statistics
     */
    public static function get_goal_stats($campaign_id, $variation_id = null, $goal_id = null)
    {
        global $wpdb;

        $where = array('campaign_id = %d', "event_type = 'goal'");
        $values = array($campaign_id);

        if ($variation_id) {
            $where[] = 'variation_id = %d';
            $values[] = $variation_id;
        }

        if ($goal_id) {
            $where[] = 'goal_id = %d';
            $values[] = $goal_id;
        }

        $sql = "SELECT
                    goal_id,
                    COUNT(*) as conversions,
                    COUNT(DISTINCT visitor_id) as unique_conversions,
                    SUM(goal_value) as total_value,
                    AVG(goal_value) as average_value,
                    MIN(goal_value) as min_value,
                    MAX(goal_value) as max_value
                FROM " . Pronto_AB_Database::get_analytics_table() . "
                WHERE " . implode(' AND ', $where);

        if ($goal_id) {
            // Single goal stats
            $sql = $wpdb->prepare($sql, $values);
            return $wpdb->get_row($sql, ARRAY_A);
        } else {
            // Multiple goals stats
            $sql .= " GROUP BY goal_id";
            $sql = $wpdb->prepare($sql, $values);
            return $wpdb->get_results($sql, ARRAY_A);
        }
    }

    /**
     * Calculate goal conversion rate for a variation
     *
     * @param int $campaign_id Campaign ID
     * @param int $variation_id Variation ID
     * @param int $goal_id Goal ID
     * @return array Conversion rate data
     */
    public static function calculate_goal_conversion_rate($campaign_id, $variation_id, $goal_id)
    {
        global $wpdb;

        // Get impressions for this variation
        $impressions = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT visitor_id)
            FROM " . Pronto_AB_Database::get_analytics_table() . "
            WHERE campaign_id = %d
            AND variation_id = %d
            AND event_type = 'impression'
        ", $campaign_id, $variation_id));

        $impressions = (int)$impressions;

        // Get goal conversions
        $conversions = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM " . Pronto_AB_Database::get_analytics_table() . "
            WHERE campaign_id = %d
            AND variation_id = %d
            AND goal_id = %d
            AND event_type = 'goal'
        ", $campaign_id, $variation_id, $goal_id));

        $conversions = (int)$conversions;

        // Calculate conversion rate
        $conversion_rate = $impressions > 0 ? ($conversions / $impressions) * 100 : 0;

        return array(
            'impressions' => $impressions,
            'conversions' => $conversions,
            'conversion_rate' => round($conversion_rate, 2)
        );
    }

    /**
     * Get goal revenue totals
     *
     * @param int $campaign_id Campaign ID
     * @param int $goal_id Goal ID (optional)
     * @return array Revenue data
     */
    public static function get_goal_revenue($campaign_id, $goal_id = null)
    {
        global $wpdb;

        $where = array('campaign_id = %d', "event_type = 'goal'", "goal_value IS NOT NULL");
        $values = array($campaign_id);

        if ($goal_id) {
            $where[] = 'goal_id = %d';
            $values[] = $goal_id;
        }

        $sql = "SELECT
                    COUNT(*) as transactions,
                    SUM(goal_value) as total_revenue,
                    AVG(goal_value) as average_order_value,
                    MIN(goal_value) as min_value,
                    MAX(goal_value) as max_value
                FROM " . Pronto_AB_Database::get_analytics_table() . "
                WHERE " . implode(' AND ', $where);

        $sql = $wpdb->prepare($sql, $values);
        $revenue = $wpdb->get_row($sql, ARRAY_A);

        return array(
            'transactions' => (int)($revenue['transactions'] ?? 0),
            'total_revenue' => (float)($revenue['total_revenue'] ?? 0),
            'average_order_value' => (float)($revenue['average_order_value'] ?? 0),
            'min_value' => (float)($revenue['min_value'] ?? 0),
            'max_value' => (float)($revenue['max_value'] ?? 0)
        );
    }

    /**
     * Get goal performance comparison across variations
     *
     * @param int $campaign_id Campaign ID
     * @param int $goal_id Goal ID
     * @return array Comparison data for each variation
     */
    public static function get_goal_comparison($campaign_id, $goal_id)
    {
        global $wpdb;

        // Get all variations for this campaign
        $variations = Pronto_AB_Variation::get_by_campaign($campaign_id);

        $comparison = array();

        foreach ($variations as $variation) {
            $stats = self::calculate_goal_conversion_rate($campaign_id, $variation->id, $goal_id);
            $revenue = self::get_goal_revenue($campaign_id, $goal_id);

            $comparison[] = array(
                'variation_id' => $variation->id,
                'variation_name' => $variation->name,
                'is_control' => (bool)$variation->is_control,
                'impressions' => $stats['impressions'],
                'conversions' => $stats['conversions'],
                'conversion_rate' => $stats['conversion_rate'],
                'revenue' => $revenue
            );
        }

        return $comparison;
    }

    /**
     * Get goal funnel data (if multiple goals exist)
     *
     * @param int $campaign_id Campaign ID
     * @param int $variation_id Variation ID
     * @param array $goal_ids Array of goal IDs in funnel order
     * @return array Funnel data
     */
    public static function get_goal_funnel($campaign_id, $variation_id, $goal_ids)
    {
        global $wpdb;

        $funnel = array();

        // Get base impressions
        $total_visitors = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT visitor_id)
            FROM " . Pronto_AB_Database::get_analytics_table() . "
            WHERE campaign_id = %d
            AND variation_id = %d
            AND event_type = 'impression'
        ", $campaign_id, $variation_id));

        $previous_count = (int)$total_visitors;

        foreach ($goal_ids as $index => $goal_id) {
            $goal = Pronto_AB_Goal::find($goal_id);
            if (!$goal) {
                continue;
            }

            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT visitor_id)
                FROM " . Pronto_AB_Database::get_analytics_table() . "
                WHERE campaign_id = %d
                AND variation_id = %d
                AND goal_id = %d
                AND event_type = 'goal'
            ", $campaign_id, $variation_id, $goal_id));

            $count = (int)$count;
            $conversion_rate = $total_visitors > 0 ? ($count / $total_visitors) * 100 : 0;
            $drop_off_rate = $previous_count > 0 ? (($previous_count - $count) / $previous_count) * 100 : 0;

            $funnel[] = array(
                'step' => $index + 1,
                'goal_id' => $goal_id,
                'goal_name' => $goal->name,
                'visitors' => $count,
                'conversion_rate' => round($conversion_rate, 2),
                'drop_off' => $previous_count - $count,
                'drop_off_rate' => round($drop_off_rate, 2)
            );

            $previous_count = $count;
        }

        return array(
            'total_visitors' => $total_visitors,
            'funnel' => $funnel
        );
    }

    /**
     * Get top performing goals for a campaign
     *
     * @param int $campaign_id Campaign ID
     * @param int $limit Number of goals to return
     * @return array Top goals
     */
    public static function get_top_goals($campaign_id, $limit = 5)
    {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT
                g.id,
                g.name,
                g.goal_type,
                COUNT(*) as conversions,
                COUNT(DISTINCT a.visitor_id) as unique_conversions,
                SUM(a.goal_value) as total_value
            FROM " . Pronto_AB_Database::get_analytics_table() . " a
            INNER JOIN " . Pronto_AB_Database::get_goals_table() . " g
                ON a.goal_id = g.id
            WHERE a.campaign_id = %d
            AND a.event_type = 'goal'
            GROUP BY g.id
            ORDER BY conversions DESC
            LIMIT %d
        ", $campaign_id, $limit);

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get time series data for goal conversions
     *
     * @param int $campaign_id Campaign ID
     * @param int $goal_id Goal ID
     * @param string $date_from Start date (Y-m-d format)
     * @param string $date_to End date (Y-m-d format)
     * @return array Time series data
     */
    public static function get_goal_time_series($campaign_id, $goal_id, $date_from, $date_to)
    {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT
                DATE(timestamp) as date,
                variation_id,
                COUNT(*) as conversions,
                COUNT(DISTINCT visitor_id) as unique_conversions,
                SUM(goal_value) as revenue
            FROM " . Pronto_AB_Database::get_analytics_table() . "
            WHERE campaign_id = %d
            AND goal_id = %d
            AND event_type = 'goal'
            AND DATE(timestamp) >= %s
            AND DATE(timestamp) <= %s
            GROUP BY DATE(timestamp), variation_id
            ORDER BY date ASC, variation_id ASC
        ", $campaign_id, $goal_id, $date_from, $date_to);

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get client IP address (helper method)
     */
    private static function get_client_ip()
    {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if visitor has completed a goal
     *
     * @param int $campaign_id Campaign ID
     * @param int $goal_id Goal ID
     * @param string $visitor_id Visitor ID
     * @return bool True if goal completed
     */
    public static function has_completed_goal($campaign_id, $goal_id, $visitor_id)
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM " . Pronto_AB_Database::get_analytics_table() . "
            WHERE campaign_id = %d
            AND goal_id = %d
            AND visitor_id = %s
            AND event_type = 'goal'
        ", $campaign_id, $goal_id, $visitor_id));

        return (int)$count > 0;
    }

    /**
     * Get goal completion count for a visitor
     *
     * @param string $visitor_id Visitor ID
     * @param int $goal_id Goal ID (optional)
     * @return int Number of goal completions
     */
    public static function get_visitor_goal_count($visitor_id, $goal_id = null)
    {
        global $wpdb;

        $where = array("visitor_id = %s", "event_type = 'goal'");
        $values = array($visitor_id);

        if ($goal_id) {
            $where[] = 'goal_id = %d';
            $values[] = $goal_id;
        }

        $sql = "SELECT COUNT(*)
                FROM " . Pronto_AB_Database::get_analytics_table() . "
                WHERE " . implode(' AND ', $where);

        $sql = $wpdb->prepare($sql, $values);
        return (int)$wpdb->get_var($sql);
    }
}
