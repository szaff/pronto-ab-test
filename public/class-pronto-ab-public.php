<?php

/**
 * Public functionality for A/B Testing
 * 
 * Handles frontend display and tracking with hybrid session/transient approach
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class Pronto_AB_Public
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks - only if there are active campaigns
     */
    private function init_hooks()
    {
        // Check for active campaigns first to avoid unnecessary overhead
        if (!$this->has_active_campaigns()) {
            return;
        }

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_head', array($this, 'render_tracking_script'));
        add_action('the_content', array($this, 'filter_content'));
        add_shortcode('pronto_ab', array($this, 'shortcode_handler'));

        // AJAX handlers for tracking
        add_action('wp_ajax_pronto_ab_track', array($this, 'ajax_track_event'));
        add_action('wp_ajax_nopriv_pronto_ab_track', array($this, 'ajax_track_event'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets()
    {
        wp_enqueue_script(
            'pronto-ab-public',
            PAB_ASSETS_URL . 'js/pronto-ab-public.js',
            array('jquery'),
            PAB_VERSION,
            true
        );

        wp_localize_script('pronto-ab-public', 'abTestData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pronto_ab_track'),
            'visitor_id' => $this->get_visitor_id(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));

        wp_enqueue_style(
            'pronto-ab-public',
            PAB_ASSETS_URL . 'css/pronto-ab-public.css',
            array(),
            PAB_VERSION
        );
    }

    /**
     * Filter content to inject A/B test variations
     */
    public function filter_content($content)
    {
        global $post;

        if (!is_singular() || !$post) {
            return $content;
        }

        // Get active campaigns for this post
        $campaigns = $this->get_active_campaigns_for_post($post->ID, $post->post_type);

        if (empty($campaigns)) {
            return $content;
        }

        foreach ($campaigns as $campaign) {
            $content = $this->apply_campaign_variation($content, $campaign);
        }

        return $content;
    }

    /**
     * Shortcode handler for manual A/B test insertion
     */
    public function shortcode_handler($atts)
    {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'element' => 'content'
        ), $atts);

        if (empty($atts['campaign_id'])) {
            return '';
        }

        $campaign = Pronto_AB_Campaign::find($atts['campaign_id']);
        if (!$campaign || !$campaign->is_active()) {
            return '';
        }

        $variation = $this->get_visitor_variation($campaign);
        if (!$variation) {
            return '';
        }

        // Track impression
        $this->track_impression($campaign->id, $variation->id);

        return '<div class="pronto-ab-content" data-campaign="' . esc_attr($campaign->id) . '" data-variation="' . esc_attr($variation->id) . '">' .
            wp_kses_post($variation->content) .
            '</div>';
    }

    /**
     * HYBRID SESSION/TRANSIENT METHODS
     */

    /**
     * Get stored value with transient priority, session fallback
     */
    private function get_visitor_assignment($key, $default = null)
    {
        $visitor_id = $this->get_visitor_id();
        $transient_key = 'pronto_ab_' . $visitor_id . '_' . $key;

        // Try transient first (survives page loads, cache-friendly)
        $value = get_transient($transient_key);
        if ($value !== false) {
            return $value;
        }

        // Fallback to session (for immediate consistency)
        if (session_status() !== PHP_SESSION_NONE && isset($_SESSION[$key])) {
            $value = $_SESSION[$key];
            // Upgrade to transient for future requests
            set_transient($transient_key, $value, 30 * DAY_IN_SECONDS);
            return $value;
        }

        return $default;
    }

    /**
     * Store visitor assignment in both transient and session
     */
    private function set_visitor_assignment($key, $value, $expire_seconds = null)
    {
        $visitor_id = $this->get_visitor_id();
        $transient_key = 'pronto_ab_' . $visitor_id . '_' . $key;

        // Store in transient (persistent across requests)
        $expire = $expire_seconds ?: (30 * DAY_IN_SECONDS);
        set_transient($transient_key, $value, $expire);

        // Also store in session for immediate access (if session available)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start(); // Use @ to suppress warnings
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Get active campaigns for a specific post
     */
    private function get_active_campaigns_for_post($post_id, $post_type)
    {
        global $wpdb;

        $table = Pronto_AB_Database::get_campaigns_table();
        $campaigns = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            WHERE status = 'active' 
            AND target_post_id = %d 
            AND target_post_type = %s
            AND (start_date IS NULL OR start_date <= NOW())
            AND (end_date IS NULL OR end_date >= NOW())
        ", $post_id, $post_type));

        if (empty($campaigns)) {
            return array();
        }

        return array_map(function ($campaign_data) {
            return new Pronto_AB_Campaign((array) $campaign_data);
        }, $campaigns);
    }

    /**
     * Apply campaign variation to content
     */
    private function apply_campaign_variation($content, $campaign)
    {
        $variation = $this->get_visitor_variation($campaign);

        if (!$variation) {
            return $content;
        }

        // Track impression for all variations (including control)
        $this->track_impression($campaign->id, $variation->id);

        // If it's the control variation, return original content
        if ($variation->is_control) {
            return $content;
        }

        // Replace content with variation
        return '<div class="pronto-ab-content" data-campaign="' . esc_attr($campaign->id) . '" data-variation="' . esc_attr($variation->id) . '">' .
            wp_kses_post($variation->content) .
            '</div>';
    }

    /**
     * Get the appropriate variation for the current visitor using hybrid approach
     */
    private function get_visitor_variation($campaign)
    {
        $session_key = 'campaign_' . $campaign->id;

        // Check if visitor already has an assigned variation
        $variation_id = $this->get_visitor_assignment($session_key);
        if ($variation_id) {
            $variation = Pronto_AB_Variation::find($variation_id);
            if ($variation) {
                return $variation;
            }
        }

        // Get all variations for this campaign
        $variations = $campaign->get_variations();
        if (empty($variations)) {
            return null;
        }

        // Assign random variation based on weights
        $variation = $this->assign_random_variation($variations);
        if (!$variation) {
            return null;
        }

        // Store assignment using hybrid approach
        $this->set_visitor_assignment($session_key, $variation->id);

        return $variation;
    }

    /**
     * Assign random variation based on weights
     */
    private function assign_random_variation($variations)
    {
        if (empty($variations)) {
            return null;
        }

        $total_weight = array_sum(array_map(function ($v) {
            return floatval($v->weight_percentage);
        }, $variations));

        if ($total_weight <= 0) {
            return $variations[0]; // Return first variation if no weights
        }

        $random = mt_rand(1, intval($total_weight * 100)) / 100;
        $cumulative = 0;

        foreach ($variations as $variation) {
            $cumulative += floatval($variation->weight_percentage);
            if ($random <= $cumulative) {
                return $variation;
            }
        }

        return $variations[0]; // Fallback to first variation
    }

    /**
     * Get unique visitor ID with enhanced persistence
     */
    private function get_visitor_id()
    {
        $cookie_name = 'pronto_ab_visitor_id';

        // Try to get from cookie first
        if (isset($_COOKIE[$cookie_name]) && !empty($_COOKIE[$cookie_name])) {
            return sanitize_text_field($_COOKIE[$cookie_name]);
        }

        // Try to get from transient (fallback for cookie-disabled users)
        $ip_hash = md5(
            ($_SERVER['REMOTE_ADDR'] ?? '') .
                ($_SERVER['HTTP_USER_AGENT'] ?? '') .
                ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
        );
        $transient_key = 'pronto_ab_visitor_ip_' . $ip_hash;
        $visitor_id = get_transient($transient_key);

        if (!$visitor_id) {
            $visitor_id = wp_generate_uuid4();
            // Store in transient as backup (30 days)
            set_transient($transient_key, $visitor_id, 30 * DAY_IN_SECONDS);
        }

        // Set secure cookie (1 year)
        $secure = is_ssl();
        $httponly = true;
        setcookie($cookie_name, $visitor_id, time() + (365 * DAY_IN_SECONDS), '/', '', $secure, $httponly);

        return $visitor_id;
    }

    /**
     * Track impression using hybrid approach
     */
    private function track_impression($campaign_id, $variation_id)
    {
        $impression_key = 'impression_' . $campaign_id . '_' . $variation_id;

        // Check if already tracked (avoid duplicates)
        if ($this->get_visitor_assignment($impression_key)) {
            return;
        }

        // Track the event
        $visitor_id = $this->get_visitor_id();
        $result = Pronto_AB_Analytics::track_event($campaign_id, $variation_id, 'impression', $visitor_id);

        if ($result) {
            // Mark as tracked (1 hour expiry to avoid session-long duplicates)
            $this->set_visitor_assignment($impression_key, true, HOUR_IN_SECONDS);
        }
    }

    /**
     * Check if there are active campaigns (with caching)
     */
    private function has_active_campaigns()
    {
        $cache_key = 'pronto_ab_has_active_campaigns';
        $has_campaigns = wp_cache_get($cache_key, 'pronto_ab');

        if ($has_campaigns === false) {
            global $wpdb;
            $table = Pronto_AB_Database::get_campaigns_table();
            $count = $wpdb->get_var("
                SELECT COUNT(*) FROM $table 
                WHERE status = 'active'
                AND (start_date IS NULL OR start_date <= NOW())
                AND (end_date IS NULL OR end_date >= NOW())
            ");

            $has_campaigns = $count > 0 ? 'yes' : 'no';
            wp_cache_set($cache_key, $has_campaigns, 'pronto_ab', 300); // 5 minutes cache
        }

        return $has_campaigns === 'yes';
    }

    /**
     * Render tracking script in head (moved to external JS file)
     */
    public function render_tracking_script()
    {
        // The tracking functionality is now handled by pronto-ab-public.js
        // This method can be used for any additional inline tracking setup if needed

        echo '<script>/* A/B Test tracking initialized via pronto-ab-public.js */</script>' . "\n";
    }

    /**
     * AJAX handler for tracking events
     */
    public function ajax_track_event()
    {
        // Verify nonce
        if (!check_ajax_referer('pronto_ab_track', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Sanitize input
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $variation_id = intval($_POST['variation_id'] ?? 0);
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $visitor_id = sanitize_text_field($_POST['visitor_id'] ?? '');
        $event_value = sanitize_text_field($_POST['event_value'] ?? '');

        // Validate required fields
        if (!$campaign_id || !$variation_id || !$event_type || !$visitor_id) {
            wp_send_json_error('Missing required fields');
            return;
        }

        // Validate visitor ID matches
        if ($visitor_id !== $this->get_visitor_id()) {
            wp_send_json_error('Invalid visitor ID');
            return;
        }

        // Prepare additional data
        $additional_data = array();
        if (!empty($event_value)) {
            $additional_data['value'] = $event_value;
        }

        // Add referrer and user agent
        $additional_data['referrer'] = $_SERVER['HTTP_REFERER'] ?? '';
        $additional_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $additional_data['timestamp'] = current_time('mysql');

        // Track the event
        $result = Pronto_AB_Analytics::track_event(
            $campaign_id,
            $variation_id,
            $event_type,
            $visitor_id,
            $additional_data
        );

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Event tracked successfully',
                'event_type' => $event_type,
                'campaign_id' => $campaign_id,
                'variation_id' => $variation_id
            ));
        } else {
            wp_send_json_error('Failed to track event');
        }
    }
}
