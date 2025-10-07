<?php

/**
 * Winner Declaration Testing Page
 * 
 * @package Pronto_AB
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only allow admins
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}

?>
<div class="wrap">
    <h1>Winner Declaration Class Tests</h1>

    <?php
    // Get a test campaign - CORRECTED METHOD NAME
    $campaigns = Pronto_AB_Campaign::get_campaigns(array('limit' => 1));

    if (empty($campaigns)) {
        echo '<div class="notice notice-warning"><p>No campaigns found. Please create a campaign first.</p></div>';
        echo '<p><a href="' . admin_url('admin.php?page=pronto-abs-new') . '" class="button button-primary">Create Campaign</a></p>';
        return;
    }

    $campaign = $campaigns[0];
    $variations = $campaign->get_variations();
    ?>

    <div class="card">
        <h2>Test Campaign</h2>
        <p><strong>Campaign:</strong> <?php echo esc_html($campaign->name); ?> (ID: <?php echo $campaign->id; ?>)</p>
        <p><strong>Status:</strong> <?php echo esc_html($campaign->status); ?></p>
        <p><strong>Variations:</strong> <?php echo count($variations); ?></p>
    </div>

    <?php if (count($variations) < 2): ?>
        <div class="notice notice-warning">
            <p>Campaign needs at least 2 variations to test winner declaration.</p>
        </div>
    <?php else: ?>

        <!-- Test 1: Validate Declaration -->
        <div class="card">
            <h2>Test 1: Validate Declaration</h2>
            <?php
            $test_variation = $variations[1]; // Second variation
            $validation = Pronto_AB_Winner_Declaration::validate_declaration(
                $campaign->id,
                $test_variation->id
            );

            if (is_wp_error($validation)) {
                echo '<p style="color: orange;">⚠ Validation Result: ' . esc_html($validation->get_error_message()) . '</p>';
            } else {
                echo '<p style="color: green;">✓ Validation Passed - Ready to declare winner</p>';
            }
            ?>
        </div>

        <!-- Test 2: Check Minimum Data -->
        <div class="card">
            <h2>Test 2: Check Minimum Data</h2>
            <?php
            $data_check = Pronto_AB_Winner_Declaration::check_minimum_data($campaign->id);

            if (is_wp_error($data_check)) {
                echo '<p style="color: orange;">⚠ Data Check: ' . esc_html($data_check->get_error_message()) . '</p>';
            } else {
                echo '<p style="color: green;">✓ Campaign has sufficient data</p>';
            }

            // Show variation data
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Variation</th><th>Control?</th><th>Impressions</th><th>Conversions</th><th>Rate</th></tr></thead>';
            echo '<tbody>';
            foreach ($variations as $var) {
                $rate = $var->impressions > 0 ? ($var->conversions / $var->impressions * 100) : 0;
                echo '<tr>';
                echo '<td><strong>' . esc_html($var->name) . '</strong></td>';
                echo '<td>' . ($var->is_control ? '✓ Control' : 'Variant') . '</td>';
                echo '<td>' . number_format($var->impressions) . '</td>';
                echo '<td>' . number_format($var->conversions) . '</td>';
                echo '<td>' . number_format($rate, 2) . '%</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            ?>
        </div>

        <!-- Test 3: Get Winner Recommendation -->
        <div class="card">
            <h2>Test 3: Get Winner Recommendation</h2>

            <?php
            $recommendation = Pronto_AB_Winner_Declaration::get_recommendation($campaign->id);

            if (is_wp_error($recommendation)) {
                echo '<p style="color: orange;">⚠ ' . esc_html($recommendation->get_error_code()) . ': ' . esc_html($recommendation->get_error_message()) . '</p>';

                // Provide helpful suggestions
                $error_code = $recommendation->get_error_code();
                if ($error_code === 'no_impressions') {
                    echo '<p><strong>Suggestion:</strong> Generate test data using the Test Data Generator.</p>';
                }
            } else {
                echo '<p style="color: green; font-weight: bold;">✓ Recommendation Generated Successfully</p>';

                echo '<table class="widefat striped">';
                echo '<tbody>';
                echo '<tr><th style="width: 200px;">Recommended Winner</th><td><strong>' . esc_html($recommendation['variation_name']) . '</strong></td></tr>';
                echo '<tr><th>Control Variation</th><td>' . esc_html($recommendation['control_name']) . '</td></tr>';
                echo '<tr><th>Winner Conversion Rate</th><td><strong>' . number_format($recommendation['conversion_rate'], 2) . '%</strong></td></tr>';
                echo '<tr><th>Control Conversion Rate</th><td>' . number_format($recommendation['control_rate'], 2) . '%</td></tr>';
                echo '<tr><th>Lift</th><td>';

                $lift = $recommendation['lift'];
                $lift_color = $lift > 0 ? 'green' : ($lift < 0 ? 'red' : 'gray');
                echo '<span style="color: ' . $lift_color . '; font-weight: bold;">';
                echo ($lift > 0 ? '+' : '') . number_format($lift, 2) . '%';
                echo '</span></td></tr>';

                echo '<tr><th>Statistical Significance</th><td>';
                if ($recommendation['is_significant']) {
                    echo '<span style="color: green; font-weight: bold;">✓ Statistically Significant</span>';
                } else {
                    echo '<span style="color: orange;">✗ Not Yet Significant</span>';
                }
                echo '</td></tr>';

                if (isset($recommendation['z_score'])) {
                    echo '<tr><th>Z-Score</th><td>' . number_format($recommendation['z_score'], 4) . '</td></tr>';
                }

                if (isset($recommendation['p_value'])) {
                    echo '<tr><th>P-Value</th><td>' . number_format($recommendation['p_value'], 4) . '</td></tr>';
                }

                echo '<tr><th>Recommendation</th><td><em>' . esc_html($recommendation['recommendation']) . '</em></td></tr>';
                echo '</tbody>';
                echo '</table>';

                // Show confidence levels
                if (!empty($recommendation['confidence'])) {
                    echo '<h4 style="margin-top: 20px;">Confidence Levels</h4>';
                    echo '<table class="widefat">';
                    echo '<thead><tr><th>Level</th><th>Status</th></tr></thead>';
                    echo '<tbody>';

                    $levels = array(
                        'confidence_90' => '90% Confidence',
                        'confidence_95' => '95% Confidence',
                        'confidence_99' => '99% Confidence'
                    );

                    foreach ($levels as $key => $label) {
                        if (isset($recommendation['confidence'][$key])) {
                            $reached = $recommendation['confidence'][$key];
                            echo '<tr>';
                            echo '<td>' . esc_html($label) . '</td>';
                            echo '<td>';
                            if ($reached) {
                                echo '<span style="color: green; font-weight: bold;">✓ Reached</span>';
                            } else {
                                echo '<span style="color: gray;">✗ Not reached</span>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                    }

                    echo '</tbody></table>';
                }

                // Show sample size recommendation
                if (isset($recommendation['confidence']['sample_size_recommendation'])) {
                    $sample = $recommendation['confidence']['sample_size_recommendation'];
                    echo '<h4 style="margin-top: 20px;">Sample Size Recommendation</h4>';
                    echo '<table class="widefat">';
                    echo '<tr><th>Recommended per Variation</th><td>' . number_format($sample['recommended_per_variation']) . '</td></tr>';
                    echo '<tr><th>Total Recommended</th><td>' . number_format($sample['total_recommended']) . '</td></tr>';
                    echo '<tr><th>Baseline Rate</th><td>' . number_format($sample['baseline_rate'], 2) . '%</td></tr>';
                    echo '</table>';
                }
            }
            ?>
        </div>

        <!-- Test 4: Campaign Statistics -->
        <div class="card">
            <h2>Test 4: Campaign Statistics</h2>
            <?php
            $stats = $campaign->get_stats();

            echo '<table class="widefat">';
            echo '<tr><th>Total Events</th><td>' . number_format($stats['total_events']) . '</td></tr>';
            echo '<tr><th>Unique Visitors</th><td>' . number_format($stats['unique_visitors']) . '</td></tr>';
            echo '<tr><th>Impressions</th><td>' . number_format($stats['impressions']) . '</td></tr>';
            echo '<tr><th>Conversions</th><td>' . number_format($stats['conversions']) . '</td></tr>';
            echo '</table>';
            ?>
        </div>

        <!-- Test 5: Method Availability Check -->
        <div class="card">
            <h2>Test 5: Method Availability Check</h2>
            <?php
            $methods = array(
                'declare',
                'auto_detect_winner',
                'apply_winner',
                'archive_campaign',
                'restore_campaign',
                'validate_declaration',
                'check_minimum_data',
                'get_recommendation',
                'get_winner_summary',
                'archive_losers'
            );

            echo '<p>Checking if all required methods exist in Pronto_AB_Winner_Declaration:</p>';
            echo '<ul style="list-style: none; padding-left: 0;">';
            $all_exist = true;
            foreach ($methods as $method) {
                $exists = method_exists('Pronto_AB_Winner_Declaration', $method);
                $all_exist = $all_exist && $exists;
                $status = $exists ? '<span style="color: green;">✓</span>' : '<span style="color: red;">✗</span>';
                echo "<li>{$status} {$method}()</li>";
            }
            echo '</ul>';

            if ($all_exist) {
                echo '<p style="color: green; font-weight: bold;">✓ All methods are available!</p>';
            }
            ?>
        </div>

        <!-- Test 6: Check if Campaign is Active -->
        <div class="card">
            <h2>Test 6: Campaign Status Check</h2>
            <?php
            $is_active = $campaign->is_active();
            echo '<p><strong>Campaign Status:</strong> ' . esc_html($campaign->status) . '</p>';
            echo '<p><strong>Is Active?</strong> ' . ($is_active ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: orange;">✗ No</span>') . '</p>';

            if ($campaign->start_date) {
                echo '<p><strong>Start Date:</strong> ' . esc_html($campaign->start_date) . '</p>';
            }
            if ($campaign->end_date) {
                echo '<p><strong>End Date:</strong> ' . esc_html($campaign->end_date) . '</p>';
            }
            ?>
        </div>

        <!-- Test 7: Winner Summary (if winner exists) -->
        <?php if ($campaign->winner_variation_id): ?>
            <div class="card">
                <h2>Test 7: Get Winner Summary</h2>
                <?php
                $summary = Pronto_AB_Winner_Declaration::get_winner_summary($campaign->id);

                if (is_wp_error($summary)) {
                    echo '<p style="color: red;">✗ Error: ' . esc_html($summary->get_error_message()) . '</p>';
                } else {
                    echo '<p style="color: green;">✓ Winner Summary Retrieved</p>';
                    echo '<table class="widefat">';
                    echo '<tr><th>Campaign Name</th><td>' . esc_html($summary['campaign_name']) . '</td></tr>';
                    echo '<tr><th>Winner Name</th><td><strong>' . esc_html($summary['winner_name']) . '</strong></td></tr>';
                    echo '<tr><th>Winner ID</th><td>' . esc_html($summary['winner_id']) . '</td></tr>';
                    echo '<tr><th>Declared At</th><td>' . esc_html($summary['declared_at']) . '</td></tr>';
                    echo '<tr><th>Declared By User ID</th><td>' . esc_html($summary['declared_by']) . '</td></tr>';
                    echo '<tr><th>Is Applied (100% traffic)</th><td>' . ($summary['is_applied'] ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: orange;">✗ No</span>') . '</td></tr>';
                    echo '<tr><th>Is Archived</th><td>' . ($summary['is_archived'] ? 'Yes' : 'No') . '</td></tr>';
                    echo '<tr><th>Total Variations</th><td>' . esc_html($summary['total_variations']) . '</td></tr>';
                    echo '</table>';
                }
                ?>
            </div>
        <?php else: ?>
            <div class="card">
                <h2>Test 7: Winner Status</h2>
                <p style="color: orange;">⚠ No winner has been declared for this campaign yet.</p>
            </div>
        <?php endif; ?>

    <?php endif; // End variations check 
    ?>

    <div class="card">
        <h2>Actions</h2>
        <p>
            <a href="<?php echo admin_url('admin.php?page=pronto-abs'); ?>" class="button">
                View All Campaigns
            </a>
            <a href="<?php echo admin_url('admin.php?page=pronto-abs-test-winner&refresh=1'); ?>" class="button button-primary">
                Refresh Tests
            </a>
            <?php if (!empty($campaigns)): ?>
                <a href="<?php echo admin_url('admin.php?page=pronto-abs-new&id=' . $campaign->id); ?>" class="button">
                    Edit This Campaign
                </a>
            <?php endif; ?>
        </p>
    </div>

</div>

<style>
    .card {
        background: white;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        margin: 20px 0;
        padding: 20px;
    }

    .card h2 {
        margin-top: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .widefat {
        margin-top: 10px;
    }
</style>