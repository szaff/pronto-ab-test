<?php

/**
 * Enhanced Public functionality for A/B Testing
 * 
 * Handles frontend display and tracking with comprehensive shortcode support
 * This file replaces the existing public/class-pronto-ab-public.php
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class Pronto_AB_Public
{
    /**
     * Visitor assignment cache
     */
    private $visitor_assignments = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Core shortcode registration
        add_shortcode('ab_test', array($this, 'ab_test_shortcode'));
        add_shortcode('pronto_ab', array($this, 'ab_test_shortcode')); // Alias for compatibility

        // Asset enqueuing
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Content filtering for automatic placement
        add_filter('the_content', array($this, 'auto_inject_tests'), 20);

        // AJAX handlers for tracking
        add_action('wp_ajax_pronto_ab_track', array($this, 'ajax_track_event'));
        add_action('wp_ajax_nopriv_pronto_ab_track', array($this, 'ajax_track_event'));
        add_action('wp_ajax_pronto_ab_track_goal', array($this, 'ajax_track_goal'));
        add_action('wp_ajax_nopriv_pronto_ab_track_goal', array($this, 'ajax_track_goal'));

        // Initialize visitor session
        add_action('wp', array($this, 'initialize_visitor_session'));

        // Add body classes for targeting
        add_filter('body_class', array($this, 'add_body_classes'));
    }

    /**
     * Main A/B Test shortcode handler
     * 
     * Usage Examples:
     * [ab_test campaign="123"]
     * [ab_test campaign="123" element="headline"]
     * [ab_test campaign="123" default="Original content"]Original content[/ab_test]
     */
    public function ab_test_shortcode($atts, $content = '')
    {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'campaign'    => '',           // Required: Campaign ID
            'element'     => 'content',    // Element type for tracking
            'default'     => $content,     // Default content if no variations
            'class'       => '',           // Additional CSS classes
            'wrapper'     => 'div',        // HTML wrapper element
            'debug'       => false,        // Show debug info
            'force'       => '',           // Force specific variation (for testing)
        ), $atts, 'ab_test');

        // Validate required parameters
        if (empty($atts['campaign'])) {
            return $this->render_error('Campaign ID is required', $atts['debug']);
        }

        $campaign_id = intval($atts['campaign']);

        // Get campaign
        $campaign = Pronto_AB_Campaign::find($campaign_id);
        if (!$campaign) {
            return $this->render_error('Campaign not found', $atts['debug']);
        }

        // Check if campaign is active
        if (!$campaign->is_active()) {
            return $this->render_default_content($atts['default'], $atts['debug']);
        }

        // Get visitor's assigned variation
        $variation = $this->get_visitor_variation($campaign, $atts['force']);
        if (!$variation) {
            return $this->render_default_content($atts['default'], $atts['debug']);
        }

        // Track impression
        $this->track_impression($campaign_id, $variation->id);

        // Render the variation
        return $this->render_variation($variation, $campaign, $atts);
    }

    /**
     * Get the appropriate variation for the current visitor
     */
    private function get_visitor_variation($campaign, $force_variation = '')
    {
        // Force specific variation for testing
        if (!empty($force_variation)) {
            if (is_numeric($force_variation)) {
                return Pronto_AB_Variation::find($force_variation);
            }
            // Find by name
            $variations = $campaign->get_variations();
            foreach ($variations as $variation) {
                if (strtolower($variation->name) === strtolower($force_variation)) {
                    return $variation;
                }
            }
        }

        $visitor_id = $this->get_visitor_id();
        $assignment_key = 'campaign_' . $campaign->id;

        // Check cache first
        if (isset($this->visitor_assignments[$assignment_key])) {
            return $this->visitor_assignments[$assignment_key];
        }

        // Check stored assignment
        $stored_variation_id = $this->get_visitor_assignment($assignment_key);
        if ($stored_variation_id) {
            $variation = Pronto_AB_Variation::find($stored_variation_id);
            if ($variation) {
                $this->visitor_assignments[$assignment_key] = $variation;
                return $variation;
            }
        }

        // Assign new variation
        $variations = $campaign->get_variations();
        if (empty($variations)) {
            return null;
        }

        $assigned_variation = $this->assign_random_variation($variations);
        if ($assigned_variation) {
            // Store assignment
            $this->set_visitor_assignment($assignment_key, $assigned_variation->id);
            $this->visitor_assignments[$assignment_key] = $assigned_variation;
        }

        return $assigned_variation;
    }

    /**
     * Assign random variation based on weights
     */
    private function assign_random_variation($variations)
    {
        if (empty($variations)) {
            return null;
        }

        // Calculate total weight
        $total_weight = 0;
        foreach ($variations as $variation) {
            $total_weight += floatval($variation->weight_percentage ?: 0);
        }

        if ($total_weight <= 0) {
            // No weights set, distribute evenly
            $random_index = array_rand($variations);
            return $variations[$random_index];
        }

        // Generate random number and find matching variation
        $random = mt_rand(1, intval($total_weight * 100)) / 100;
        $cumulative = 0;

        foreach ($variations as $variation) {
            $cumulative += floatval($variation->weight_percentage ?: 0);
            if ($random <= $cumulative) {
                return $variation;
            }
        }

        // Fallback to first variation
        return $variations[0];
    }

    /**
     * Render variation content
     */
    private function render_variation($variation, $campaign, $atts)
    {
        $wrapper = sanitize_text_field($atts['wrapper']);
        $css_classes = array(
            'pronto-ab-content',
            'campaign-' . $campaign->id,
            'variation-' . $variation->id
        );

        if ($variation->is_control) {
            $css_classes[] = 'ab-control';
        } else {
            $css_classes[] = 'ab-variation';
        }

        if (!empty($atts['class'])) {
            $css_classes[] = sanitize_text_field($atts['class']);
        }

        if (!empty($atts['element'])) {
            $css_classes[] = 'ab-element-' . sanitize_text_field($atts['element']);
        }

        $css_class_string = implode(' ', $css_classes);

        // Process content
        $content = $this->process_variation_content($variation->content);

        // Debug output
        $debug_output = '';
        if ($atts['debug']) {
            $debug_output = $this->render_debug_info($campaign, $variation);
        }

        // Render with wrapper
        if ($wrapper && $wrapper !== 'none') {
            return sprintf(
                '<%1$s class="%2$s" data-campaign="%3$d" data-variation="%4$d" data-element="%5$s">%6$s%7$s</%1$s>',
                esc_attr($wrapper),
                esc_attr($css_class_string),
                esc_attr($campaign->id),
                esc_attr($variation->id),
                esc_attr($atts['element']),
                $content,
                $debug_output
            );
        } else {
            // No wrapper, just add data attributes via span
            return sprintf(
                '<span class="%1$s" data-campaign="%2$d" data-variation="%3$d" data-element="%4$s" style="display:contents;">%5$s</span>%6$s',
                esc_attr($css_class_string),
                esc_attr($campaign->id),
                esc_attr($variation->id),
                esc_attr($atts['element']),
                $content,
                $debug_output
            );
        }
    }

    /**
     * Process variation content (shortcodes, filters, etc.)
     */
    private function process_variation_content($content)
    {
        // Apply WordPress content filters
        $content = apply_filters('pronto_ab_variation_content', $content);

        // Process shortcodes
        $content = do_shortcode($content);

        // Apply wpautop if needed
        if (false === strpos($content, '<p>') && false === strpos($content, '<div>')) {
            $content = wpautop($content);
        }

        return $content;
    }

    /**
     * Render default content when no variation is available
     */
    private function render_default_content($default_content, $debug = false)
    {
        if (empty($default_content)) {
            return $debug ? '<!-- A/B Test: No content available -->' : '';
        }

        $processed_content = $this->process_variation_content($default_content);

        if ($debug) {
            $processed_content .= '<!-- A/B Test: Showing default content -->';
        }

        return $processed_content;
    }

    /**
     * Render error message
     */
    private function render_error($message, $debug = false)
    {
        if (!$debug) {
            return '';
        }

        return sprintf(
            '<div class="pronto-ab-error" style="background:#ffe6e6;border:1px solid #ff6b6b;padding:10px;margin:10px 0;color:#c0392b;">
                <strong>A/B Test Error:</strong> %s
            </div>',
            esc_html($message)
        );
    }

    /**
     * Render debug information
     */
    private function render_debug_info($campaign, $variation)
    {
        $visitor_id = $this->get_visitor_id();

        return sprintf(
            '<div class="pronto-ab-debug" style="background:#e8f4f8;border:1px solid #3498db;padding:8px;margin:8px 0;font-size:12px;color:#2c3e50;">
                <strong>A/B Test Debug:</strong><br>
                Campaign: %s (ID: %d, Status: %s)<br>
                Variation: %s (ID: %d, Weight: %s%%, Control: %s)<br>
                Visitor ID: %s
            </div>',
            esc_html($campaign->name),
            esc_html($campaign->id),
            esc_html($campaign->status),
            esc_html($variation->name),
            esc_html($variation->id),
            esc_html($variation->weight_percentage),
            $variation->is_control ? 'Yes' : 'No',
            esc_html(substr($visitor_id, 0, 8)) . '...'
        );
    }

    /**
     * Auto-inject tests based on campaign target settings
     */
    public function auto_inject_tests($content)
    {
        global $post;

        if (!is_singular() || !$post) {
            return $content;
        }

        // Get active campaigns targeting this post
        $campaigns = $this->get_campaigns_for_post($post->ID, $post->post_type);

        if (empty($campaigns)) {
            return $content;
        }

        foreach ($campaigns as $campaign) {
            // Add shortcode at the beginning of content
            $shortcode = sprintf('[ab_test campaign="%d" element="auto-inject"]', $campaign->id);
            $content = $shortcode . $content;
        }

        return $content;
    }

    /**
     * Get active campaigns targeting a specific post
     */
    private function get_campaigns_for_post($post_id, $post_type)
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
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets()
    {
        // Only enqueue if there are active campaigns or shortcodes on the page
        if (!$this->should_load_assets()) {
            return;
        }

        // Frontend CSS
        wp_enqueue_style(
            'pronto-ab-public',
            PAB_ASSETS_URL . 'css/pronto-ab-public.css',
            array(),
            PAB_VERSION
        );

        // Frontend JavaScript
        wp_enqueue_script(
            'pronto-ab-public',
            PAB_ASSETS_URL . 'js/pronto-ab-public.js',
            array('jquery'),
            PAB_VERSION,
            true
        );

        // Get active goals for this page
        $active_goals = Pronto_AB_Goal::get_active_for_page();

        // Localize script for AJAX and configuration
        wp_localize_script('pronto-ab-public', 'abTestData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pronto_ab_track'),
            'visitor_id' => $this->get_visitor_id(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'auto_track' => get_option('pronto_ab_auto_track', true),
            'goals' => $active_goals,
            'track_events' => array(
                'clicks' => true,
                'forms' => true,
                'time_on_page' => true,
                'scroll_depth' => false
            )
        ));
    }

    /**
     * Check if assets should be loaded
     */
    private function should_load_assets()
    {
        global $post;

        // Always load on pages with shortcodes
        if ($post && (
            has_shortcode($post->post_content, 'ab_test') ||
            has_shortcode($post->post_content, 'pronto_ab')
        )) {
            return true;
        }

        // Load if there are active campaigns targeting this post
        if ($post && !empty($this->get_campaigns_for_post($post->ID, $post->post_type))) {
            return true;
        }

        // Load if there are any active campaigns (conservative approach)
        return $this->has_active_campaigns();
    }

    /**
     * Check if there are any active campaigns
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
     * Initialize visitor session
     */
    public function initialize_visitor_session()
    {
        // Ensure visitor ID is set
        $this->get_visitor_id();

        // Start session if not already started (for assignment storage)
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * Get unique visitor ID with enhanced persistence
     */
    private function get_visitor_id()
    {
        $cookie_name = 'pronto_ab_visitor_id';
        $cookie_value = isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '';

        // Validate existing cookie
        if (!empty($cookie_value) && $this->is_valid_visitor_id($cookie_value)) {
            return $cookie_value;
        }

        // Generate new visitor ID
        $visitor_id = $this->generate_visitor_id();

        // Set secure cookie
        $this->set_visitor_cookie($cookie_name, $visitor_id);

        return $visitor_id;
    }

    /**
     * Generate unique visitor ID
     */
    private function generate_visitor_id()
    {
        // Use WordPress UUID function if available
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        // Fallback to custom generation
        return sprintf(
            'ab_%s_%s',
            uniqid(),
            wp_hash(
                ($_SERVER['REMOTE_ADDR'] ?? '') .
                    ($_SERVER['HTTP_USER_AGENT'] ?? '') .
                    time()
            )
        );
    }

    /**
     * Validate visitor ID format
     */
    private function is_valid_visitor_id($visitor_id)
    {
        return !empty($visitor_id) &&
            strlen($visitor_id) >= 10 &&
            strlen($visitor_id) <= 100 &&
            preg_match('/^[a-zA-Z0-9_-]+$/', $visitor_id);
    }

    /**
     * Set visitor cookie securely
     */
    private function set_visitor_cookie($name, $value)
    {
        $expire = time() + (365 * DAY_IN_SECONDS); // 1 year
        $secure = is_ssl();
        $httponly = true;
        $samesite = 'Lax';

        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $value, array(
                'expires' => $expire,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ));
        } else {
            setcookie($name, $value, $expire, '/', '', $secure, $httponly);
        }
    }

    /**
     * Get visitor assignment (persistent storage)
     */
    private function get_visitor_assignment($key, $default = null)
    {
        $visitor_id = $this->get_visitor_id();
        $transient_key = 'pronto_ab_' . md5($visitor_id . '_' . $key);

        // Try transient first
        $value = get_transient($transient_key);
        if ($value !== false) {
            return $value;
        }

        // Fallback to session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[$key])) {
            $value = $_SESSION[$key];
            // Upgrade to transient
            set_transient($transient_key, $value, 30 * DAY_IN_SECONDS);
            return $value;
        }

        return $default;
    }

    /**
     * Set visitor assignment (persistent storage)
     */
    private function set_visitor_assignment($key, $value, $expire_seconds = null)
    {
        $visitor_id = $this->get_visitor_id();
        $transient_key = 'pronto_ab_' . md5($visitor_id . '_' . $key);

        // Store in transient
        $expire = $expire_seconds ?: (30 * DAY_IN_SECONDS);
        set_transient($transient_key, $value, $expire);

        // Also store in session for immediate access
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Track impression
     */
    private function track_impression($campaign_id, $variation_id)
    {
        $impression_key = 'impression_' . $campaign_id . '_' . $variation_id;

        // Prevent duplicate impressions within a session
        if ($this->get_visitor_assignment($impression_key)) {
            return;
        }

        $visitor_id = $this->get_visitor_id();
        $result = Pronto_AB_Analytics::track_event(
            $campaign_id,
            $variation_id,
            'impression',
            $visitor_id,
            array(
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            )
        );

        if ($result) {
            // Mark as tracked for this session
            $this->set_visitor_assignment($impression_key, true, HOUR_IN_SECONDS);
        }
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

        // Sanitize and validate input
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $variation_id = intval($_POST['variation_id'] ?? 0);
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $visitor_id = sanitize_text_field($_POST['visitor_id'] ?? '');
        $event_data = $_POST['event_data'] ?? array();

        // Validate required fields
        if (!$campaign_id || !$variation_id || !$event_type || !$visitor_id) {
            wp_send_json_error('Missing required fields');
            return;
        }

        // Validate visitor ID matches current visitor
        if ($visitor_id !== $this->get_visitor_id()) {
            wp_send_json_error('Invalid visitor ID');
            return;
        }

        // Sanitize event data
        $additional_data = array();
        if (is_array($event_data)) {
            foreach ($event_data as $key => $value) {
                $additional_data[sanitize_key($key)] = sanitize_text_field($value);
            }
        }

        // Add standard tracking data
        $additional_data['timestamp'] = current_time('mysql');
        $additional_data['url'] = sanitize_url($_POST['page_url'] ?? '');
        $additional_data['referrer'] = sanitize_url($_POST['referrer'] ?? '');

        // Handle goal events - redirect to dedicated goal tracker
        if ($event_type === 'goal' && isset($additional_data['goalId'])) {
            $goal_id = intval($additional_data['goalId']);
            $goal_value = isset($additional_data['goalValue']) ? floatval($additional_data['goalValue']) : null;

            $result = Pronto_AB_Goal_Tracker::track_goal(
                $campaign_id,
                $variation_id,
                $goal_id,
                $visitor_id,
                $goal_value
            );
        } else {
            // Track regular event
            $result = Pronto_AB_Analytics::track_event(
                $campaign_id,
                $variation_id,
                $event_type,
                $visitor_id,
                $additional_data
            );
        }

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

    /**
     * AJAX handler for tracking goal completions
     */
    public function ajax_track_goal()
    {
        // Verify nonce
        if (!check_ajax_referer('pronto_ab_track', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Sanitize and validate input
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $variation_id = intval($_POST['variation_id'] ?? 0);
        $goal_id = intval($_POST['goal_id'] ?? 0);
        $visitor_id = sanitize_text_field($_POST['visitor_id'] ?? '');
        $goal_value = isset($_POST['goal_value']) ? floatval($_POST['goal_value']) : null;

        // Validate required fields
        if (!$campaign_id || !$variation_id || !$goal_id || !$visitor_id) {
            wp_send_json_error(array(
                'message' => 'Missing required fields',
                'debug' => array(
                    'campaign_id' => $campaign_id,
                    'variation_id' => $variation_id,
                    'goal_id' => $goal_id,
                    'visitor_id' => $visitor_id ? 'set' : 'missing'
                )
            ));
            return;
        }

        // Validate visitor ID matches current visitor
        if ($visitor_id !== $this->get_visitor_id()) {
            wp_send_json_error('Invalid visitor ID');
            return;
        }

        // Verify goal exists and is assigned to this campaign
        $goal = Pronto_AB_Goal::find($goal_id);
        if (!$goal) {
            wp_send_json_error('Goal not found');
            return;
        }

        if (!Pronto_AB_Goal::is_assigned_to_campaign($goal_id, $campaign_id)) {
            wp_send_json_error('Goal not assigned to this campaign');
            return;
        }

        // Check if goal already completed by this visitor (prevent duplicates)
        if (Pronto_AB_Goal_Tracker::has_completed_goal($campaign_id, $goal_id, $visitor_id)) {
            wp_send_json_success(array(
                'message' => 'Goal already completed by this visitor',
                'goal_id' => $goal_id,
                'goal_name' => $goal->name,
                'duplicate' => true
            ));
            return;
        }

        // Track the goal
        $result = Pronto_AB_Goal_Tracker::track_goal(
            $campaign_id,
            $variation_id,
            $goal_id,
            $visitor_id,
            $goal_value
        );

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Goal tracked successfully',
                'goal_id' => $goal_id,
                'goal_name' => $goal->name,
                'goal_value' => $goal_value,
                'campaign_id' => $campaign_id,
                'variation_id' => $variation_id
            ));
        } else {
            wp_send_json_error('Failed to track goal');
        }
    }

    /**
     * Add body classes for targeting and styling
     */
    public function add_body_classes($classes)
    {
        global $post;

        if (!$post) {
            return $classes;
        }

        // Check if this page has active A/B tests
        $campaigns = $this->get_campaigns_for_post($post->ID, $post->post_type);

        if (!empty($campaigns)) {
            $classes[] = 'has-ab-tests';

            foreach ($campaigns as $campaign) {
                $classes[] = 'ab-campaign-' . $campaign->id;

                $variation = $this->get_visitor_variation($campaign);
                if ($variation) {
                    $classes[] = 'ab-variation-' . $variation->id;
                    if ($variation->is_control) {
                        $classes[] = 'ab-control';
                    }
                }
            }
        }

        return $classes;
    }
}
