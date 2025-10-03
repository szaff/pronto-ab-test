<?php

/**
 * Test Data Generator for A/B Testing Statistics
 * 
 * This utility helps generate realistic test data for validating
 * the statistical significance calculator
 * 
 * Usage: Add to WordPress admin as a tools page or run via WP-CLI
 * 
 * @package Pronto_AB
 * @since 1.1.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class Pronto_AB_Test_Data_Generator
{
    /**
     * Generate test data for a campaign
     * 
     * @param int $campaign_id Campaign ID
     * @param array $scenarios Test scenarios to generate
     * @return array Results of data generation
     */
    public static function generate_test_data($campaign_id, $scenarios = array())
    {
        global $wpdb;

        // Get campaign variations
        $variations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM " . Pronto_AB_Database::get_variations_table() . "
             WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);

        if (count($variations) < 2) {
            return array('error' => 'Campaign needs at least 2 variations');
        }

        $control = $variations[0];
        $variant = $variations[1];

        // Default scenarios if none provided
        if (empty($scenarios)) {
            $scenarios = array(
                'clear_winner' => array(
                    'impressions' => 1000,
                    'control_rate' => 0.05,
                    'variant_rate' => 0.08
                )
            );
        }

        $results = array();

        foreach ($scenarios as $scenario_name => $scenario) {
            $result = self::generate_scenario(
                $campaign_id,
                $control['id'],
                $variant['id'],
                $scenario
            );

            $results[$scenario_name] = $result;
        }

        return $results;
    }

    /**
     * Generate data for a specific scenario
     * 
     * @param int $campaign_id Campaign ID
     * @param int $control_id Control variation ID
     * @param int $variant_id Variant variation ID
     * @param array $scenario Scenario parameters
     * @return array Generation results
     */
    private static function generate_scenario($campaign_id, $control_id, $variant_id, $scenario)
    {
        $impressions = $scenario['impressions'];
        $control_rate = $scenario['control_rate'];
        $variant_rate = $scenario['variant_rate'];

        // Generate impressions and conversions for control
        $control_conversions = self::generate_conversions($impressions, $control_rate);

        // Generate impressions and conversions for variant
        $variant_conversions = self::generate_conversions($impressions, $variant_rate);

        // Insert analytics data
        $control_inserted = self::insert_analytics_data(
            $campaign_id,
            $control_id,
            $impressions,
            $control_conversions
        );

        $variant_inserted = self::insert_analytics_data(
            $campaign_id,
            $variant_id,
            $impressions,
            $variant_conversions
        );

        // Update variation counters
        self::update_variation_counters($control_id, $impressions, $control_conversions);
        self::update_variation_counters($variant_id, $impressions, $variant_conversions);

        return array(
            'control' => array(
                'impressions' => $impressions,
                'conversions' => $control_conversions,
                'rate' => round(($control_conversions / $impressions) * 100, 2)
            ),
            'variant' => array(
                'impressions' => $impressions,
                'conversions' => $variant_conversions,
                'rate' => round(($variant_conversions / $impressions) * 100, 2)
            ),
            'events_inserted' => $control_inserted + $variant_inserted
        );
    }

    /**
     * Generate realistic conversion count based on rate
     * 
     * @param int $impressions Total impressions
     * @param float $conversion_rate Target conversion rate (0-1)
     * @return int Number of conversions
     */
    private static function generate_conversions($impressions, $conversion_rate)
    {
        $conversions = 0;

        // Simulate each impression as a Bernoulli trial
        for ($i = 0; $i < $impressions; $i++) {
            if (mt_rand() / mt_getrandmax() < $conversion_rate) {
                $conversions++;
            }
        }

        return $conversions;
    }

    /**
     * Insert analytics data into database
     * 
     * @param int $campaign_id Campaign ID
     * @param int $variation_id Variation ID
     * @param int $impressions Number of impressions
     * @param int $conversions Number of conversions
     * @return int Number of events inserted
     */
    private static function insert_analytics_data($campaign_id, $variation_id, $impressions, $conversions)
    {
        global $wpdb;

        $inserted = 0;
        $base_timestamp = strtotime('-7 days');

        // Insert impressions
        for ($i = 0; $i < $impressions; $i++) {
            $visitor_id = 'test_visitor_' . uniqid() . '_' . $i;
            $timestamp = date('Y-m-d H:i:s', $base_timestamp + ($i * 600)); // Spread over time

            $result = $wpdb->insert(
                Pronto_AB_Database::get_analytics_table(),
                array(
                    'campaign_id' => $campaign_id,
                    'variation_id' => $variation_id,
                    'visitor_id' => $visitor_id,
                    'session_id' => 'test_session_' . $i,
                    'event_type' => 'impression',
                    'user_agent' => 'Test User Agent',
                    'ip_address' => '127.0.0.1',
                    'timestamp' => $timestamp
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($result) {
                $inserted++;
            }
        }

        // Insert conversions (subset of impressions)
        $conversion_visitors = array_rand(range(0, $impressions - 1), min($conversions, $impressions));
        if (!is_array($conversion_visitors)) {
            $conversion_visitors = array($conversion_visitors);
        }

        foreach ($conversion_visitors as $visitor_index) {
            $visitor_id = 'test_visitor_' . uniqid() . '_' . $visitor_index;
            $timestamp = date('Y-m-d H:i:s', $base_timestamp + ($visitor_index * 600) + 60);

            $result = $wpdb->insert(
                Pronto_AB_Database::get_analytics_table(),
                array(
                    'campaign_id' => $campaign_id,
                    'variation_id' => $variation_id,
                    'visitor_id' => $visitor_id,
                    'session_id' => 'test_session_' . $visitor_index,
                    'event_type' => 'conversion',
                    'user_agent' => 'Test User Agent',
                    'ip_address' => '127.0.0.1',
                    'timestamp' => $timestamp
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($result) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Update variation counters
     * 
     * @param int $variation_id Variation ID
     * @param int $impressions Impressions to add
     * @param int $conversions Conversions to add
     */
    private static function update_variation_counters($variation_id, $impressions, $conversions)
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE " . Pronto_AB_Database::get_variations_table() . "
             SET impressions = impressions + %d,
                 conversions = conversions + %d
             WHERE id = %d",
            $impressions,
            $conversions,
            $variation_id
        ));
    }

    /**
     * Clear test data for a campaign
     * 
     * @param int $campaign_id Campaign ID
     * @return bool Success
     */
    public static function clear_test_data($campaign_id)
    {
        global $wpdb;

        // Delete analytics data
        $wpdb->delete(
            Pronto_AB_Database::get_analytics_table(),
            array('campaign_id' => $campaign_id),
            array('%d')
        );

        // Reset variation counters
        $wpdb->query($wpdb->prepare(
            "UPDATE " . Pronto_AB_Database::get_variations_table() . "
             SET impressions = 0, conversions = 0
             WHERE campaign_id = %d",
            $campaign_id
        ));

        return true;
    }

    /**
     * Get predefined test scenarios
     * 
     * @return array Test scenarios
     */
    public static function get_test_scenarios()
    {
        return array(
            'clear_winner_95' => array(
                'name' => 'Clear Winner (95% confidence)',
                'description' => 'Variant B clearly outperforms with 95% confidence',
                'impressions' => 1000,
                'control_rate' => 0.05,
                'variant_rate' => 0.08
            ),
            'clear_winner_99' => array(
                'name' => 'Clear Winner (99% confidence)',
                'description' => 'Variant B clearly outperforms with 99% confidence',
                'impressions' => 2000,
                'control_rate' => 0.05,
                'variant_rate' => 0.09
            ),
            'marginal_difference' => array(
                'name' => 'Marginal Difference',
                'description' => 'Small difference, not yet statistically significant',
                'impressions' => 500,
                'control_rate' => 0.05,
                'variant_rate' => 0.06
            ),
            'no_difference' => array(
                'name' => 'No Difference',
                'description' => 'Both variations perform equally',
                'impressions' => 1000,
                'control_rate' => 0.05,
                'variant_rate' => 0.05
            ),
            'insufficient_data' => array(
                'name' => 'Insufficient Data',
                'description' => 'Not enough data for reliable analysis',
                'impressions' => 50,
                'control_rate' => 0.05,
                'variant_rate' => 0.08
            ),
            'high_volume' => array(
                'name' => 'High Volume Test',
                'description' => 'Large sample size with small but significant difference',
                'impressions' => 5000,
                'control_rate' => 0.05,
                'variant_rate' => 0.055
            ),
            'negative_result' => array(
                'name' => 'Negative Result',
                'description' => 'Variant B performs worse than control',
                'impressions' => 1000,
                'control_rate' => 0.08,
                'variant_rate' => 0.05
            )
        );
    }

    /**
     * Generate admin page for test data generation
     */
    public static function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Handle form submission
        if (isset($_POST['generate_test_data'])) {
            check_admin_referer('pab_generate_test_data');

            $campaign_id = intval($_POST['campaign_id']);
            $scenario_key = sanitize_text_field($_POST['scenario']);

            $scenarios = self::get_test_scenarios();

            if (isset($scenarios[$scenario_key])) {
                $result = self::generate_test_data(
                    $campaign_id,
                    array($scenario_key => $scenarios[$scenario_key])
                );

                echo '<div class="notice notice-success"><p>Test data generated successfully!</p></div>';
                echo '<pre>' . print_r($result, true) . '</pre>';
            }
        }

        if (isset($_POST['clear_test_data'])) {
            check_admin_referer('pab_clear_test_data');

            $campaign_id = intval($_POST['campaign_id']);
            self::clear_test_data($campaign_id);

            echo '<div class="notice notice-success"><p>Test data cleared!</p></div>';
        }

        // Get all campaigns
        global $wpdb;
        $campaigns = $wpdb->get_results(
            "SELECT id, name FROM " . Pronto_AB_Database::get_campaigns_table()
        );

        $scenarios = self::get_test_scenarios();

?>
        <div class="wrap">
            <h1>A/B Test Data Generator</h1>
            <p>Generate realistic test data to validate statistical calculations.</p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Generate Data Form -->
                <div class="card">
                    <h2>Generate Test Data</h2>
                    <form method="post">
                        <?php wp_nonce_field('pab_generate_test_data'); ?>

                        <table class="form-table">
                            <tr>
                                <th><label for="campaign_id">Campaign</label></th>
                                <td>
                                    <select name="campaign_id" id="campaign_id" required>
                                        <option value="">Select Campaign</option>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <option value="<?php echo esc_attr($campaign->id); ?>">
                                                <?php echo esc_html($campaign->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="scenario">Test Scenario</label></th>
                                <td>
                                    <select name="scenario" id="scenario" required>
                                        <?php foreach ($scenarios as $key => $scenario): ?>
                                            <option value="<?php echo esc_attr($key); ?>">
                                                <?php echo esc_html($scenario['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description" id="scenario-description"></p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="generate_test_data" class="button button-primary" value="Generate Test Data">
                        </p>
                    </form>
                </div>

                <!-- Clear Data Form -->
                <div class="card">
                    <h2>Clear Test Data</h2>
                    <form method="post">
                        <?php wp_nonce_field('pab_clear_test_data'); ?>

                        <table class="form-table">
                            <tr>
                                <th><label for="clear_campaign_id">Campaign</label></th>
                                <td>
                                    <select name="campaign_id" id="clear_campaign_id" required>
                                        <option value="">Select Campaign</option>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <option value="<?php echo esc_attr($campaign->id); ?>">
                                                <?php echo esc_html($campaign->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">This will delete all analytics data and reset counters.</p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="clear_test_data" class="button button-secondary" value="Clear Test Data" onclick="return confirm('Are you sure? This cannot be undone.');">
                        </p>
                    </form>
                </div>
            </div>

            <!-- Scenario Descriptions -->
            <div class="card" style="margin-top: 20px;">
                <h2>Test Scenarios</h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Scenario</th>
                            <th>Description</th>
                            <th>Impressions</th>
                            <th>Control Rate</th>
                            <th>Variant Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scenarios as $key => $scenario): ?>
                            <tr>
                                <td><strong><?php echo esc_html($scenario['name']); ?></strong></td>
                                <td><?php echo esc_html($scenario['description']); ?></td>
                                <td><?php echo esc_html($scenario['impressions']); ?></td>
                                <td><?php echo esc_html($scenario['control_rate'] * 100); ?>%</td>
                                <td><?php echo esc_html($scenario['variant_rate'] * 100); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                var scenarios = <?php echo json_encode($scenarios); ?>;

                $('#scenario').on('change', function() {
                    var key = $(this).val();
                    if (scenarios[key]) {
                        $('#scenario-description').text(
                            scenarios[key].description +
                            ' (Impressions: ' + scenarios[key].impressions +
                            ', Control: ' + (scenarios[key].control_rate * 100) + '%, ' +
                            'Variant: ' + (scenarios[key].variant_rate * 100) + '%)'
                        );
                    }
                });

                // Trigger on page load
                $('#scenario').trigger('change');
            });
        </script>
<?php
    }
}
