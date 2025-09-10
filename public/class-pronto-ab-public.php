<?php

/**
 * Public functionality for A/B Testing
 * 
 * Handles frontend display and tracking
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
     * Initialize hooks
     */
    private function init_hooks()
    {
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
        // Only load if there are active campaigns
        if (!$this->has_active_campaigns()) {
            return;
        }

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

        return array_map(function ($campaign_data) {
            return new pronto_ab_Campaign($campaign_data);
        }, $campaigns);
    }

    /**
     * Apply campaign variation to content
     */
    private function apply_campaign_variation($content, $campaign)
    {
        $variation = $this->get_visitor_variation($campaign);

        if (!$variation || $variation->is_control) {
            // Track impression for control
            if ($variation) {
                $this->track_impression($campaign->id, $variation->id);
            }
            return $content;
        }

        // Track impression
        $this->track_impression($campaign->id, $variation->id);

        // Replace content with variation
        // This is a simple implementation - you might want more sophisticated content replacement
        return '<div class="pronto-ab-content" data-campaign="' . esc_attr($campaign->id) . '" data-variation="' . esc_attr($variation->id) . '">' .
            wp_kses_post($variation->content) .
            '</div>';
    }

    /**
     * Get the appropriate variation for the current visitor
     */
    private function get_visitor_variation($campaign)
    {
        $visitor_id = $this->get_visitor_id();
        $session_key = 'pronto_ab_campaign_' . $campaign->id;

        // Check if visitor already has an assigned variation
        if (isset($_SESSION[$session_key])) {
            $variation_id = $_SESSION[$session_key];
            return pronto_ab_Variation::find($variation_id);
        }

        // Get all variations for this campaign
        $variations = $campaign->get_variations();
        if (empty($variations)) {
            return null;
        }

        // Simple random assignment based on weights
        $variation = $this->assign_random_variation($variations);

        // Store assignment in session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[$session_key] = $variation->id;

        return $variation;
    }

    /**
     * Assign random variation based on weights
     */
    private function assign_random_variation($variations)
    {
        $total_weight = array_sum(array_map(function ($v) {
            return $v->weight_percentage;
        }, $variations));

        if ($total_weight <= 0) {
            return $variations[0]; // Return first variation if no weights
        }

        $random = mt_rand(1, $total_weight * 100) / 100;
        $cumulative = 0;

        foreach ($variations as $variation) {
            $cumulative += $variation->weight_percentage;
            if ($random <= $cumulative) {
                return $variation;
            }
        }

        return $variations[0]; // Fallback
    }

    /**
     * Get unique visitor ID
     */
    private function get_visitor_id()
    {
        $cookie_name = 'pronto_ab_visitor_id';

        if (isset($_COOKIE[$cookie_name])) {
            return sanitize_text_field($_COOKIE[$cookie_name]);
        }

        $visitor_id = wp_generate_uuid4();
        setcookie($cookie_name, $visitor_id, time() + (86400 * 365), '/'); // 1 year

        return $visitor_id;
    }

    /**
     * Track impression
     */
    private function track_impression($campaign_id, $variation_id)
    {
        $visitor_id = $this->get_visitor_id();

        // Avoid duplicate impressions in the same session
        $session_key = 'pronto_ab_impression_' . $campaign_id . '_' . $variation_id;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION[$session_key])) {
            return; // Already tracked this impression
        }

        pronto_ab_Analytics::track_event($campaign_id, $variation_id, 'impression', $visitor_id);
        $_SESSION[$session_key] = true;
    }

    /**
     * Check if there are active campaigns
     */
    private function has_active_campaigns()
    {
        global $wpdb;

        $table = Pronto_AB_Database::get_campaigns_table();
        $count = $wpdb->get_var("
            SELECT COUNT(*) FROM $table 
            WHERE status = 'active'
            AND (start_date IS NULL OR start_date <= NOW())
            AND (end_date IS NULL OR end_date >= NOW())
        ");

        return $count > 0;
    }

    /**
     * Render tracking script in head
     */
    public function render_tracking_script()
    {
        if (!$this->has_active_campaigns()) {
            return;
        }

?>
        <script>
            // A/B Test tracking initialization
            window.abTestTracking = {
                visitorId: '<?php echo esc_js($this->get_visitor_id()); ?>',
                trackConversion: function(campaignId, variationId, value) {
                    if (typeof jQuery !== 'undefined') {
                        jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', {
                            action: 'pronto_ab_track',
                            campaign_id: campaignId,
                            variation_id: variationId,
                            event_type: 'conversion',
                            event_value: value || '',
                            visitor_id: this.visitorId,
                            nonce: '<?php echo wp_create_nonce("pronto_ab_track"); ?>'
                        });
                    }
                }
            };
        </script>
<?php
    }

    /**
     * AJAX handler for tracking events
     */
    public function ajax_track_event()
    {
        check_ajax_referer('pronto_ab_track', 'nonce');

        $campaign_id = intval($_POST['campaign_id']);
        $variation_id = intval($_POST['variation_id']);
        $event_type = sanitize_text_field($_POST['event_type']);
        $visitor_id = sanitize_text_field($_POST['visitor_id']);
        $event_value = sanitize_text_field($_POST['event_value'] ?? '');

        $additional_data = array();
        if (!empty($event_value)) {
            $additional_data['value'] = $event_value;
        }

        $result = Pronto_AB_Analytics::track_event(
            $campaign_id,
            $variation_id,
            $event_type,
            $visitor_id,
            $additional_data
        );

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to track event');
        }
    }
}
