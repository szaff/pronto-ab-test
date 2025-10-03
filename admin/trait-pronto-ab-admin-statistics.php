<?php

/**
 * Admin trait for displaying statistical significance
 * 
 * @package Pronto_AB
 * @since 1.1.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

trait Pronto_AB_Admin_Statistics
{
    /**
     * Render statistical significance box for campaign
     * 
     * @param int $campaign_id Campaign ID
     */
    public function render_statistics_box($campaign_id)
    {
        $metrics = Pronto_AB_Statistics::calculate_campaign_metrics($campaign_id);

        if (isset($metrics['error'])) {
            $this->render_statistics_error($metrics['error']);
            return;
        }

?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php _e('Statistical Analysis', 'pronto-ab'); ?></h2>
            </div>
            <div class="inside">
                <?php foreach ($metrics as $result): ?>
                    <?php $this->render_variation_comparison($result); ?>
                <?php endforeach; ?>

                <div class="pab-stats-footer">
                    <p class="description">
                        <?php _e('Statistical significance indicates confidence that observed differences are real, not due to chance.', 'pronto-ab'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render comparison between control and variation
     * 
     * @param array $result Comparison results
     */
    private function render_variation_comparison($result)
    {
        $stats = $result['stats'];
        $data_check = $result['data_check'];

        // Check if there's an error in stats calculation
        if (isset($stats['error'])) {
        ?>
            <div class="pab-variation-comparison">
                <div class="pab-comparison-header">
                    <h4 class="pab-comparison-title">
                        <?php echo esc_html($result['variation_name']); ?> vs <?php echo esc_html($result['control_name']); ?>
                    </h4>
                </div>
                <div class="notice notice-warning inline">
                    <p><?php echo esc_html($stats['error']); ?></p>
                </div>
            </div>
        <?php
            return;
        }

        $is_significant = isset($stats['is_significant']) ? $stats['is_significant'] : false;

        $confidence_class = 'low';
        if (isset($stats['confidence_99']) && $stats['confidence_99']) {
            $confidence_class = 'high';
        } elseif (isset($stats['confidence_95']) && $stats['confidence_95']) {
            $confidence_class = 'high';
        } elseif (isset($stats['confidence_90']) && $stats['confidence_90']) {
            $confidence_class = 'medium';
        }

        ?>
        <div class="pab-variation-comparison <?php echo $is_significant ? 'significant' : ''; ?>">
            <div class="pab-comparison-header">
                <h4 class="pab-comparison-title">
                    <?php echo esc_html($result['variation_name']); ?> vs <?php echo esc_html($result['control_name']); ?>
                    <?php if (isset($stats['winner']) && $stats['winner'] === 'b'): ?>
                        <span class="pab-winner-badge"><?php _e('Winner', 'pronto-ab'); ?></span>
                    <?php endif; ?>
                </h4>
                <span class="pab-confidence-badge <?php echo $confidence_class; ?>">
                    <?php echo esc_html($stats['confidence_level'] ?? 'Not significant'); ?> <?php _e('Confidence', 'pronto-ab'); ?>
                </span>
            </div>

            <?php if (!$data_check['sufficient']): ?>
                <div class="pab-data-warnings">
                    <strong>⚠️ <?php _e('Data Quality Warning:', 'pronto-ab'); ?></strong>
                    <ul>
                        <?php foreach ($data_check['warnings'] as $warning): ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="pab-metrics-grid">
                <div class="pab-metric-card pab-has-tooltip">
                    <div class="pab-metric-label">
                        <?php printf(__('Conversion Rate: %s', 'pronto-ab'), esc_html($result['control_name'])); ?>
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="pab-tooltip">
                        <?php _e('The percentage of visitors who completed the desired action. The CI (Confidence Interval) below shows the range where the true conversion rate likely falls with 95% certainty.', 'pronto-ab'); ?>
                    </div>
                    <div class="pab-metric-value"><?php echo esc_html($stats['conversion_rate_a'] ?? '0.00'); ?>%</div>
                    <?php if (isset($stats['confidence_interval_a'])): ?>
                        <div class="pab-metric-subtitle">
                            <?php printf(
                                __('CI: %s%% - %s%%', 'pronto-ab'),
                                $stats['confidence_interval_a']['lower'] ?? '0',
                                $stats['confidence_interval_a']['upper'] ?? '0'
                            ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pab-metric-card pab-has-tooltip">
                    <div class="pab-metric-label">
                        <?php printf(__('Conversion Rate: %s', 'pronto-ab'), esc_html($result['variation_name'])); ?>
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="pab-tooltip">
                        <?php _e('The percentage of visitors who completed the desired action. The CI (Confidence Interval) below shows the range where the true conversion rate likely falls with 95% certainty.', 'pronto-ab'); ?>
                    </div>
                    <div class="pab-metric-value"><?php echo esc_html($stats['conversion_rate_b'] ?? '0.00'); ?>%</div>
                    <?php if (isset($stats['confidence_interval_b'])): ?>
                        <div class="pab-metric-subtitle">
                            <?php printf(
                                __('CI: %s%% - %s%%', 'pronto-ab'),
                                $stats['confidence_interval_b']['lower'] ?? '0',
                                $stats['confidence_interval_b']['upper'] ?? '0'
                            ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pab-metric-card pab-has-tooltip">
                    <div class="pab-metric-label">
                        <?php _e('Lift', 'pronto-ab'); ?>
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="pab-tooltip">
                        <?php _e('The percentage improvement (or decline) in conversion rate compared to the control. Positive lift means the variation performs better.', 'pronto-ab'); ?>
                    </div>
                    <?php $lift = $stats['lift'] ?? 0; ?>
                    <div class="pab-metric-value <?php echo $lift > 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $lift > 0 ? '+' : ''; ?><?php echo esc_html(number_format($lift, 2)); ?>%
                    </div>
                    <div class="pab-metric-subtitle">
                        <?php _e('Relative improvement', 'pronto-ab'); ?>
                    </div>
                </div>

                <div class="pab-metric-card pab-has-tooltip">
                    <div class="pab-metric-label">
                        <?php _e('P-Value', 'pronto-ab'); ?>
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="pab-tooltip">
                        <?php _e('The probability that the observed difference occurred by random chance. Values below 0.05 indicate the difference is statistically significant and likely real.', 'pronto-ab'); ?>
                    </div>
                    <div class="pab-metric-value"><?php echo esc_html($stats['p_value'] ?? 'N/A'); ?></div>
                    <div class="pab-metric-subtitle">
                        <?php _e('< 0.05 is significant', 'pronto-ab'); ?>
                    </div>
                </div>
            </div>

            <div class="pab-interpretation">
                <strong><?php _e('Interpretation:', 'pronto-ab'); ?></strong>
                <?php echo esc_html($result['interpretation']); ?>
            </div>

            <?php if (!$data_check['sufficient']): ?>
                <div class="pab-sample-progress">
                    <h5><?php _e('Data Collection Progress', 'pronto-ab'); ?></h5>

                    <div style="margin: 10px 0;">
                        <div class="pab-metric-label"><?php _e('Impressions A', 'pronto-ab'); ?></div>
                        <div class="pab-progress-bar">
                            <div class="pab-progress-fill" style="width: <?php echo min(100, $data_check['progress']['impressions_a']); ?>%"></div>
                        </div>
                        <small><?php echo esc_html($data_check['progress']['impressions_a']); ?>%</small>
                    </div>

                    <div style="margin: 10px 0;">
                        <div class="pab-metric-label"><?php _e('Conversions A', 'pronto-ab'); ?></div>
                        <div class="pab-progress-bar">
                            <div class="pab-progress-fill" style="width: <?php echo min(100, $data_check['progress']['conversions_a']); ?>%"></div>
                        </div>
                        <small><?php echo esc_html($data_check['progress']['conversions_a']); ?>%</small>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($stats['sample_size_recommendation'])): ?>
                <div style="margin-top: 16px; padding: 10px; background: #fff; border-radius: 4px;">
                    <small>
                        <strong><?php _e('Recommendation:', 'pronto-ab'); ?></strong>
                        <?php printf(
                            __('For reliable results, aim for %d impressions per variation (based on %.1f%% baseline conversion rate).', 'pronto-ab'),
                            $stats['sample_size_recommendation']['recommended_per_variation'],
                            $stats['sample_size_recommendation']['baseline_rate']
                        ); ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Render error message
     * 
     * @param string $error Error message
     */
    private function render_statistics_error($error)
    {
    ?>
        <div class="notice notice-warning">
            <p><?php echo esc_html($error); ?></p>
        </div>
        <?php
    }

    /**
     * Render compact statistics badge for campaign list
     * 
     * @param int $campaign_id Campaign ID
     */
    public function render_statistics_badge($campaign_id)
    {
        $metrics = Pronto_AB_Statistics::calculate_campaign_metrics($campaign_id);

        if (isset($metrics['error']) || empty($metrics)) {
            echo '<span class="pab-badge">-</span>';
            return;
        }

        // Find the best performing variation
        $best_result = null;
        foreach ($metrics as $result) {
            if ($result['stats']['is_significant']) {
                $best_result = $result;
                break;
            }
        }

        if ($best_result) {
            $confidence = $best_result['stats']['confidence_level'];
            $lift = $best_result['stats']['lift'];

        ?>
            <span class="pab-badge pab-badge-significant" title="<?php echo esc_attr($best_result['interpretation']); ?>">
                ✓ <?php echo esc_html($confidence); ?>
                <small>(<?php echo $lift > 0 ? '+' : ''; ?><?php echo esc_html(number_format($lift, 1)); ?>%)</small>
            </span>
            <style>
                .pab-badge-significant {
                    background: #46b450;
                    color: #fff;
                    padding: 4px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: 600;
                }
            </style>
        <?php
        } else {
        ?>
            <span class="pab-badge pab-badge-testing" title="<?php _e('Test in progress - more data needed', 'pronto-ab'); ?>">
                ⏳ <?php _e('Testing', 'pronto-ab'); ?>
            </span>
            <style>
                .pab-badge-testing {
                    background: #f0f0f1;
                    color: #666;
                    padding: 4px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                }
            </style>
<?php
        }
    }
}
