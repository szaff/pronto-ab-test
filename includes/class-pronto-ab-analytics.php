<?php

/**
 * A/B Test Analytics Model
 */
class Pronto_AB_Analytics
{

    /**
     * Track an event (impression, conversion, etc.)
     */
    public static function track_event($campaign_id, $variation_id, $event_type, $visitor_id, $additional_data = array())
    {
        global $wpdb;

        $data = array(
            'campaign_id' => $campaign_id,
            'variation_id' => $variation_id,
            'visitor_id' => $visitor_id,
            'session_id' => session_id(),
            'event_type' => $event_type,
            'event_value' => isset($additional_data['value']) ? $additional_data['value'] : '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => self::get_client_ip(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'additional_data' => json_encode($additional_data)
        );

        $result = $wpdb->insert(
            Pronto_AB_Database::get_analytics_table(),
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        // Update variation counters
        if ($result && in_array($event_type, array('impression', 'conversion'))) {
            $counter_field = $event_type === 'impression' ? 'impressions' : 'conversions';
            $wpdb->query($wpdb->prepare(
                "UPDATE " . Pronto_AB_Database::get_variations_table() .
                    " SET $counter_field = $counter_field + 1 WHERE id = %d",
                $variation_id
            ));
        }

        return $result !== false;
    }

    /**
     * Get client IP address
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
     * Get campaign analytics summary
     */
    public static function get_campaign_summary($campaign_id, $date_from = null, $date_to = null)
    {
        global $wpdb;

        $where = array('campaign_id = %d');
        $values = array($campaign_id);

        if ($date_from) {
            $where[] = 'timestamp >= %s';
            $values[] = $date_from;
        }

        if ($date_to) {
            $where[] = 'timestamp <= %s';
            $values[] = $date_to;
        }

        $sql = "SELECT 
                    variation_id,
                    COUNT(*) as total_events,
                    COUNT(DISTINCT visitor_id) as unique_visitors,
                    SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                    SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions
                FROM " . Pronto_AB_Database::get_analytics_table() . "
                WHERE " . implode(' AND ', $where) . "
                GROUP BY variation_id";

        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }
}
