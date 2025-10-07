<?php

/**
 * Analytics Dashboard Trait
 * 
 * Handles rendering of the comprehensive analytics page with charts,
 * date filtering, and export functionality.
 *
 * @package Pronto_AB
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Pronto_AB_Admin_Analytics
{
    /**
     * Render analytics dashboard page
     */
    public function analytics_page()
    {
        // Get all campaigns for selector
        $campaigns = Pronto_AB_Campaign::get_campaigns();

        // Get selected campaign from URL or use first campaign
        $selected_campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : null;

        if (!$selected_campaign_id && !empty($campaigns)) {
            $selected_campaign_id = $campaigns[0]->id;
        }

        // Get date range from URL or use defaults (last 30 days)
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

?>
        <div class="wrap pronto-ab-analytics-page">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('A/B Test Analytics', 'pronto-ab'); ?>
            </h1>

            <?php if (!empty($campaigns)): ?>
                <!-- Filters Section -->
                <div class="pronto-ab-analytics-filters">
                    <form method="get" id="analytics-filter-form">
                        <input type="hidden" name="page" value="pronto-abs-analytics">

                        <div class="filter-row">
                            <!-- Campaign Selector -->
                            <div class="filter-item">
                                <label for="campaign-select"><?php esc_html_e('Campaign:', 'pronto-ab'); ?></label>
                                <select name="campaign_id" id="campaign-select" class="pronto-ab-campaign-select">
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo esc_attr($campaign->id); ?>"
                                            <?php selected($selected_campaign_id, $campaign->id); ?>>
                                            <?php echo esc_html($campaign->name); ?>
                                            <?php if ($campaign->status !== 'active'): ?>
                                                (<?php echo esc_html(ucfirst($campaign->status)); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Date Range -->
                            <div class="filter-item">
                                <label for="date-from"><?php esc_html_e('From:', 'pronto-ab'); ?></label>
                                <input type="date" name="date_from" id="date-from"
                                    value="<?php echo esc_attr($date_from); ?>"
                                    max="<?php echo esc_attr(date('Y-m-d')); ?>">
                            </div>

                            <div class="filter-item">
                                <label for="date-to"><?php esc_html_e('To:', 'pronto-ab'); ?></label>
                                <input type="date" name="date_to" id="date-to"
                                    value="<?php echo esc_attr($date_to); ?>"
                                    max="<?php echo esc_attr(date('Y-m-d')); ?>">
                            </div>

                            <!-- Quick Date Filters -->
                            <div class="filter-item">
                                <button type="button" class="button quick-date" data-days="7">
                                    <?php esc_html_e('Last 7 Days', 'pronto-ab'); ?>
                                </button>
                                <button type="button" class="button quick-date" data-days="30">
                                    <?php esc_html_e('Last 30 Days', 'pronto-ab'); ?>
                                </button>
                                <button type="button" class="button quick-date" data-days="90">
                                    <?php esc_html_e('Last 90 Days', 'pronto-ab'); ?>
                                </button>
                            </div>

                            <!-- Apply & Export Buttons -->
                            <div class="filter-item">
                                <button type="submit" class="button button-primary">
                                    <?php esc_html_e('Apply Filters', 'pronto-ab'); ?>
                                </button>
                                <button type="button" id="export-csv" class="button">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Export CSV', 'pronto-ab'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if ($selected_campaign_id): ?>
                    <?php $this->render_analytics_dashboard($selected_campaign_id, $date_from, $date_to); ?>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="pronto-ab-empty-state">
                    <div class="empty-state-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <h2><?php esc_html_e('No Campaigns Yet', 'pronto-ab'); ?></h2>
                    <p><?php esc_html_e('Create your first A/B test campaign to start collecting analytics data.', 'pronto-ab'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-new')); ?>" class="button button-primary button-large">
                        <?php esc_html_e('Create First Campaign', 'pronto-ab'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Render the main analytics dashboard for a campaign
     */
    private function render_analytics_dashboard($campaign_id, $date_from, $date_to)
    {
        // FIX: Use find() instead of get()
        $campaign = Pronto_AB_Campaign::find($campaign_id);

        if (!$campaign) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Campaign not found.', 'pronto-ab') . '</p></div>';
            return;
        }

        $variations = $campaign->get_variations();

        if (empty($variations)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('This campaign has no variations yet.', 'pronto-ab') . '</p></div>';
            return;
        }

        // Get analytics data
        $analytics_data = $this->get_analytics_data($campaign_id, $variations, $date_from, $date_to);
        $time_series_data = $this->get_time_series_data($campaign_id, $variations, $date_from, $date_to);
        $statistical_results = Pronto_AB_Statistics::calculate_campaign_metrics($campaign_id);

    ?>
        <div class="pronto-ab-analytics-dashboard">

            <!-- Summary Cards -->
            <div class="analytics-summary-cards">
                <?php $this->render_summary_cards($analytics_data); ?>
            </div>

            <!-- Main Charts Row -->
            <div class="analytics-charts-row">
                <!-- Conversion Rate Comparison Chart -->
                <div class="analytics-chart-container">
                    <div class="chart-header">
                        <h3><?php esc_html_e('Conversion Rate Comparison', 'pronto-ab'); ?></h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="conversion-rate-chart"></canvas>
                    </div>
                </div>

                <!-- Performance Over Time Chart -->
                <div class="analytics-chart-container">
                    <div class="chart-header">
                        <h3><?php esc_html_e('Performance Over Time', 'pronto-ab'); ?></h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="time-series-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Goal Performance Charts Row -->
            <?php if (!empty($analytics_data[0]['goal_stats'])): ?>
            <div class="analytics-charts-row">
                <!-- Goal Conversion Rate Comparison Chart -->
                <div class="analytics-chart-container">
                    <div class="chart-header">
                        <h3><?php esc_html_e('Goal Conversion Rates', 'pronto-ab'); ?></h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="goal-conversion-chart"></canvas>
                    </div>
                </div>

                <!-- Goal Revenue Chart -->
                <div class="analytics-chart-container">
                    <div class="chart-header">
                        <h3><?php esc_html_e('Goal Revenue by Variation', 'pronto-ab'); ?></h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="goal-revenue-chart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistical Significance Section -->
            <div class="analytics-statistics-section">
                <h2><?php esc_html_e('Statistical Analysis', 'pronto-ab'); ?></h2>
                <?php $this->render_statistical_analysis($statistical_results); ?>
            </div>

            <!-- Detailed Variation Table -->
            <div class="analytics-table-section">
                <h2><?php esc_html_e('Variation Details', 'pronto-ab'); ?></h2>
                <?php $this->render_variations_table($analytics_data); ?>
            </div>

        </div>

        <!-- Pass data to JavaScript -->
        <script type="text/javascript">
            var prontoAbAnalyticsData = <?php echo json_encode([
                                            'variations' => $analytics_data,
                                            'timeSeries' => $time_series_data,
                                            'campaignId' => $campaign_id,
                                            'dateFrom' => $date_from,
                                            'dateTo' => $date_to
                                        ]); ?>;
        </script>
    <?php
    }


    /**
     * Render summary cards
     */
    private function render_summary_cards($analytics_data)
    {
        $total_impressions = 0;
        $total_conversions = 0;
        $total_visitors = 0;
        $total_goal_conversions = 0;
        $total_goal_revenue = 0;

        foreach ($analytics_data as $data) {
            $total_impressions += $data['impressions'];
            $total_conversions += $data['conversions'];
            $total_visitors += $data['unique_visitors'];
            $total_goal_conversions += $data['goal_conversions'];
            $total_goal_revenue += $data['goal_revenue'];
        }

        $overall_rate = $total_impressions > 0 ? ($total_conversions / $total_impressions) * 100 : 0;
        $overall_goal_rate = $total_impressions > 0 ? ($total_goal_conversions / $total_impressions) * 100 : 0;

        // Find winner if exists
        $winner_name = null;
        $winner_lift = null;
        foreach ($analytics_data as $data) {
            if (isset($data['is_winner']) && $data['is_winner']) {
                $winner_name = $data['name'];
                $winner_lift = $data['lift'];
                break;
            }
        }

    ?>
        <div class="summary-card">
            <div class="card-icon">
                <span class="dashicons dashicons-visibility"></span>
            </div>
            <div class="card-content">
                <div class="card-value"><?php echo number_format($total_impressions); ?></div>
                <div class="card-label"><?php esc_html_e('Total Impressions', 'pronto-ab'); ?></div>
            </div>
        </div>

        <div class="summary-card">
            <div class="card-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="card-content">
                <div class="card-value"><?php echo number_format($total_conversions); ?></div>
                <div class="card-label"><?php esc_html_e('Total Conversions', 'pronto-ab'); ?></div>
            </div>
        </div>

        <div class="summary-card">
            <div class="card-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="card-content">
                <div class="card-value"><?php echo number_format($total_visitors); ?></div>
                <div class="card-label"><?php esc_html_e('Unique Visitors', 'pronto-ab'); ?></div>
            </div>
        </div>

        <div class="summary-card">
            <div class="card-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <div class="card-content">
                <div class="card-value"><?php echo number_format($overall_rate, 2); ?>%</div>
                <div class="card-label"><?php esc_html_e('Overall Conversion Rate', 'pronto-ab'); ?></div>
            </div>
        </div>

        <?php if ($winner_name): ?>
            <div class="summary-card summary-card-winner">
                <div class="card-icon">
                    <span class="dashicons dashicons-trophy"></span>
                </div>
                <div class="card-content">
                    <div class="card-value"><?php echo esc_html($winner_name); ?></div>
                    <div class="card-label">
                        <?php esc_html_e('Winner', 'pronto-ab'); ?>
                        <?php if ($winner_lift): ?>
                            <span class="winner-lift">(+<?php echo number_format($winner_lift, 1); ?>%)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Goal Metrics Cards -->
        <?php if ($total_goal_conversions > 0): ?>
            <div class="summary-card summary-card-goal">
                <div class="card-icon">
                    <span class="dashicons dashicons-flag"></span>
                </div>
                <div class="card-content">
                    <div class="card-value"><?php echo number_format($total_goal_conversions); ?></div>
                    <div class="card-label"><?php esc_html_e('Goal Conversions', 'pronto-ab'); ?></div>
                </div>
            </div>

            <div class="summary-card summary-card-goal">
                <div class="card-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="card-content">
                    <div class="card-value"><?php echo number_format($overall_goal_rate, 2); ?>%</div>
                    <div class="card-label"><?php esc_html_e('Goal Conversion Rate', 'pronto-ab'); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($total_goal_revenue > 0): ?>
            <div class="summary-card summary-card-revenue">
                <div class="card-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="card-content">
                    <div class="card-value">$<?php echo number_format($total_goal_revenue, 2); ?></div>
                    <div class="card-label"><?php esc_html_e('Total Revenue', 'pronto-ab'); ?></div>
                </div>
            </div>
        <?php endif; ?>
    <?php
    }

    /**
     * Render statistical analysis section
     */
    private function render_statistical_analysis($statistical_results)
    {
        if (isset($statistical_results['error'])) {
            echo '<div class="notice notice-info inline"><p>' . esc_html($statistical_results['error']) . '</p></div>';
            return;
        }

        foreach ($statistical_results as $result) {
            $this->render_variation_comparison($result);
        }
    }

    /**
     * Render variations table
     */
    private function render_variations_table($analytics_data)
    {
    ?>
        <table class="wp-list-table widefat fixed striped pronto-ab-variations-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Variation', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Impressions', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Conversions', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Conversion Rate', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Goal Conversions', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Goal Rate', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Revenue', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Unique Visitors', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Traffic Split', 'pronto-ab'); ?></th>
                    <th><?php esc_html_e('Status', 'pronto-ab'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($analytics_data as $data): ?>
                    <tr class="<?php echo $data['is_control'] ? 'is-control' : ''; ?> <?php echo isset($data['is_winner']) && $data['is_winner'] ? 'is-winner' : ''; ?>">
                        <td>
                            <strong><?php echo esc_html($data['name']); ?></strong>
                            <?php if ($data['is_control']): ?>
                                <span class="badge badge-control"><?php esc_html_e('Control', 'pronto-ab'); ?></span>
                            <?php endif; ?>
                            <?php if (isset($data['is_winner']) && $data['is_winner']): ?>
                                <span class="badge badge-winner"><?php esc_html_e('Winner', 'pronto-ab'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($data['impressions']); ?></td>
                        <td><?php echo number_format($data['conversions']); ?></td>
                        <td>
                            <strong><?php echo number_format($data['conversion_rate'], 2); ?>%</strong>
                            <?php if (isset($data['lift']) && $data['lift'] != 0): ?>
                                <span class="lift-indicator <?php echo $data['lift'] > 0 ? 'positive' : 'negative'; ?>">
                                    (<?php echo $data['lift'] > 0 ? '+' : ''; ?><?php echo number_format($data['lift'], 1); ?>%)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($data['goal_conversions']); ?></td>
                        <td><strong><?php echo number_format($data['goal_conversion_rate'], 2); ?>%</strong></td>
                        <td>
                            <?php if ($data['goal_revenue'] > 0): ?>
                                <strong>$<?php echo number_format($data['goal_revenue'], 2); ?></strong>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($data['unique_visitors']); ?></td>
                        <td><?php echo number_format($data['weight'], 1); ?>%</td>
                        <td>
                            <?php if ($data['impressions'] < 100): ?>
                                <span class="status-insufficient"><?php esc_html_e('Insufficient Data', 'pronto-ab'); ?></span>
                            <?php else: ?>
                                <span class="status-active"><?php esc_html_e('Collecting Data', 'pronto-ab'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }

    /**
     * Get analytics data for variations
     */
    private function get_analytics_data($campaign_id, $variations, $date_from, $date_to)
    {
        global $wpdb;
        $analytics_table = Pronto_AB_Database::get_analytics_table();

        $data = [];
        $control_rate = null;

        // Get campaign goals
        $campaign_goals = Pronto_AB_Goal::get_by_campaign($campaign_id);

        foreach ($variations as $variation) {
            $sql = "SELECT
                        COUNT(DISTINCT CASE WHEN event_type = 'impression' THEN visitor_id END) as unique_visitors,
                        SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                        SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions,
                        SUM(CASE WHEN event_type = 'goal' THEN 1 ELSE 0 END) as goal_conversions,
                        SUM(CASE WHEN event_type = 'goal' AND goal_value IS NOT NULL THEN goal_value ELSE 0 END) as goal_revenue
                    FROM {$analytics_table}
                    WHERE campaign_id = %d
                    AND variation_id = %d
                    AND timestamp >= %s
                    AND timestamp <= %s";

            $result = $wpdb->get_row($wpdb->prepare(
                $sql,
                $campaign_id,
                $variation->id,
                $date_from . ' 00:00:00',
                $date_to . ' 23:59:59'
            ), ARRAY_A);

            $impressions = $result['impressions'] ?? 0;
            $conversions = $result['conversions'] ?? 0;
            $goal_conversions = $result['goal_conversions'] ?? 0;
            $goal_revenue = $result['goal_revenue'] ?? 0;
            $conversion_rate = $impressions > 0 ? ($conversions / $impressions) * 100 : 0;
            $goal_conversion_rate = $impressions > 0 ? ($goal_conversions / $impressions) * 100 : 0;

            // Calculate lift vs control
            $lift = 0;
            if ($variation->is_control) {
                $control_rate = $conversion_rate;
            } elseif ($control_rate !== null && $control_rate > 0) {
                $lift = (($conversion_rate - $control_rate) / $control_rate) * 100;
            }

            // Get goal-specific stats
            $goal_stats = [];
            foreach ($campaign_goals as $goal) {
                $goal_stat = Pronto_AB_Goal_Tracker::get_goal_stats($campaign_id, $variation->id, $goal->id);
                $goal_stats[] = [
                    'goal_id' => $goal->id,
                    'goal_name' => $goal->name,
                    'conversions' => $goal_stat['conversions'] ?? 0,
                    'conversion_rate' => $goal_stat['conversion_rate'] ?? 0,
                    'revenue' => $goal_stat['revenue'] ?? 0
                ];
            }

            $data[] = [
                'id' => $variation->id,
                'name' => $variation->name,
                'is_control' => (bool)$variation->is_control,
                'impressions' => (int)$impressions,
                'conversions' => (int)$conversions,
                'conversion_rate' => $conversion_rate,
                'goal_conversions' => (int)$goal_conversions,
                'goal_conversion_rate' => $goal_conversion_rate,
                'goal_revenue' => (float)$goal_revenue,
                'unique_visitors' => (int)($result['unique_visitors'] ?? 0),
                'weight' => (float)$variation->weight_percentage,
                'lift' => $lift,
                'goal_stats' => $goal_stats
            ];
        }

        return $data;
    }

    /**
     * Get time series data for charts
     */
    private function get_time_series_data($campaign_id, $variations, $date_from, $date_to)
    {
        global $wpdb;
        $analytics_table = Pronto_AB_Database::get_analytics_table();

        $series_data = [];

        foreach ($variations as $variation) {
            $sql = "SELECT 
                        DATE(timestamp) as date,
                        SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                        SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions
                    FROM {$analytics_table}
                    WHERE campaign_id = %d 
                    AND variation_id = %d
                    AND timestamp >= %s
                    AND timestamp <= %s
                    GROUP BY DATE(timestamp)
                    ORDER BY date ASC";

            $results = $wpdb->get_results($wpdb->prepare(
                $sql,
                $campaign_id,
                $variation->id,
                $date_from . ' 00:00:00',
                $date_to . ' 23:59:59'
            ), ARRAY_A);

            $daily_data = [];
            foreach ($results as $row) {
                $conversion_rate = $row['impressions'] > 0 ? ($row['conversions'] / $row['impressions']) * 100 : 0;
                $daily_data[] = [
                    'date' => $row['date'],
                    'impressions' => (int)$row['impressions'],
                    'conversions' => (int)$row['conversions'],
                    'conversion_rate' => $conversion_rate
                ];
            }

            $series_data[] = [
                'variation_id' => $variation->id,
                'variation_name' => $variation->name,
                'is_control' => (bool)$variation->is_control,
                'data' => $daily_data
            ];
        }

        return $series_data;
    }
}
