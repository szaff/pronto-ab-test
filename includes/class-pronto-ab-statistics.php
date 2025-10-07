<?php

/**
 * Statistical Significance Calculator for A/B Testing
 * 
 * Implements Z-test for comparing conversion rates between variations
 * Provides confidence levels, p-values, and sample size recommendations
 *
 * @package Pronto_AB
 * @since 1.1.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class Pronto_AB_Statistics
{
    /**
     * Calculate statistical significance between two variations using Z-test
     * 
     * @param int $impressions_a Impressions for variation A
     * @param int $conversions_a Conversions for variation A
     * @param int $impressions_b Impressions for variation B
     * @param int $conversions_b Conversions for variation B
     * @return array Statistical analysis results
     */
    public static function calculate_significance($impressions_a, $conversions_a, $impressions_b, $conversions_b)
    {
        // Validate inputs
        if ($impressions_a <= 0 || $impressions_b <= 0) {
            return array(
                'error' => 'Invalid sample sizes',
                'is_significant' => false
            );
        }

        // Calculate conversion rates
        $rate_a = $conversions_a / $impressions_a;
        $rate_b = $conversions_b / $impressions_b;

        // Calculate pooled probability
        $pooled_prob = ($conversions_a + $conversions_b) / ($impressions_a + $impressions_b);

        // Calculate standard error
        $se = sqrt($pooled_prob * (1 - $pooled_prob) * (1 / $impressions_a + 1 / $impressions_b));

        // Avoid division by zero
        if ($se == 0) {
            return array(
                'error' => 'Standard error is zero',
                'is_significant' => false
            );
        }

        // Calculate Z-score
        $z_score = ($rate_b - $rate_a) / $se;

        // Calculate p-value (two-tailed test)
        $p_value = 2 * (1 - self::normal_cdf(abs($z_score)));

        // Determine confidence levels
        $confidence_90 = abs($z_score) >= 1.645; // 90% confidence
        $confidence_95 = abs($z_score) >= 1.96;  // 95% confidence
        $confidence_99 = abs($z_score) >= 2.576; // 99% confidence

        // Calculate lift (improvement percentage)
        $lift = $rate_a > 0 ? (($rate_b - $rate_a) / $rate_a) * 100 : 0;

        // Calculate confidence intervals
        $ci_a = self::calculate_confidence_interval($rate_a, $impressions_a);
        $ci_b = self::calculate_confidence_interval($rate_b, $impressions_b);

        // Determine which variation is winning
        $winner = null;
        if ($confidence_95) {
            $winner = $rate_b > $rate_a ? 'b' : 'a';
        }

        return array(
            'conversion_rate_a' => round($rate_a * 100, 2),
            'conversion_rate_b' => round($rate_b * 100, 2),
            'z_score' => round($z_score, 4),
            'p_value' => round($p_value, 4),
            'confidence_90' => $confidence_90,
            'confidence_95' => $confidence_95,
            'confidence_99' => $confidence_99,
            'is_significant' => $confidence_95,
            'lift' => round($lift, 2),
            'confidence_interval_a' => $ci_a,
            'confidence_interval_b' => $ci_b,
            'winner' => $winner,
            'sample_size_recommendation' => self::recommend_sample_size($rate_a, $rate_b),
            'confidence_level' => self::get_confidence_level($confidence_90, $confidence_95, $confidence_99)
        );
    }

    /**
     * Calculate confidence interval for a conversion rate
     * 
     * @param float $rate Conversion rate
     * @param int $sample_size Sample size
     * @return array Lower and upper bounds of 95% confidence interval
     */
    private static function calculate_confidence_interval($rate, $sample_size)
    {
        // Standard error for proportion
        $se = sqrt(($rate * (1 - $rate)) / $sample_size);

        // 95% confidence interval (Z = 1.96)
        $margin = 1.96 * $se;

        return array(
            'lower' => max(0, round(($rate - $margin) * 100, 2)),
            'upper' => min(100, round(($rate + $margin) * 100, 2))
        );
    }

    /**
     * Approximate cumulative distribution function for standard normal distribution
     * Using Abramowitz and Stegun approximation
     * 
     * @param float $x Value
     * @return float Probability
     */
    private static function normal_cdf($x)
    {
        $t = 1 / (1 + 0.2316419 * abs($x));
        $d = 0.3989423 * exp(-$x * $x / 2);
        $probability = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));

        if ($x > 0) {
            $probability = 1 - $probability;
        }

        return $probability;
    }

    /**
     * Get confidence level as a string
     * 
     * @param bool $conf_90 90% confidence
     * @param bool $conf_95 95% confidence
     * @param bool $conf_99 99% confidence
     * @return string Confidence level description
     */
    private static function get_confidence_level($conf_90, $conf_95, $conf_99)
    {
        if ($conf_99) {
            return '99%';
        } elseif ($conf_95) {
            return '95%';
        } elseif ($conf_90) {
            return '90%';
        } else {
            return 'Not significant';
        }
    }

    /**
     * Recommend additional sample size needed for statistical significance
     * 
     * @param float $rate_a Conversion rate A
     * @param float $rate_b Conversion rate B
     * @return array Recommendation details
     */
    private static function recommend_sample_size($rate_a, $rate_b)
    {
        // Baseline conversion rate
        $baseline = max($rate_a, $rate_b);
        if ($baseline == 0) {
            $baseline = 0.05; // Assume 5% if no conversions yet
        }

        // Minimum detectable effect (we'll use 20% relative improvement)
        $mde = 0.20;

        // Calculate required sample size per variation
        // Using simplified formula: n = 16 * p * (1-p) / (mde * p)^2
        $required = ceil(16 * $baseline * (1 - $baseline) / pow($mde * $baseline, 2));

        return array(
            'recommended_per_variation' => $required,
            'total_recommended' => $required * 2,
            'baseline_rate' => round($baseline * 100, 2)
        );
    }

    /**
     * Get human-readable interpretation of results
     *
     * @param array $stats Statistical results
     * @param string $control_name Optional name of control variation
     * @param string $variant_name Optional name of variant variation
     * @return string Interpretation message
     */
    public static function interpret_results($stats, $control_name = null, $variant_name = null)
    {
        if (isset($stats['error'])) {
            return $stats['error'];
        }

        $rate_a = $stats['conversion_rate_a'];
        $rate_b = $stats['conversion_rate_b'];
        $lift = $stats['lift'];
        $confidence = $stats['confidence_level'];

        if (!$stats['is_significant']) {
            return "The difference between variations is not yet statistically significant. Continue testing to gather more data.";
        }

        // Use actual variation names if provided, otherwise fall back to generic names
        $name_a = $control_name ?: 'Variation A';
        $name_b = $variant_name ?: 'Variation B';

        $better = $rate_b > $rate_a ? $name_b : $name_a;
        $direction = $lift > 0 ? 'increase' : 'decrease';

        return sprintf(
            "%s is performing better with %s confidence. It shows a %.1f%% %s in conversion rate.",
            $better,
            $confidence,
            abs($lift),
            $direction
        );
    }

    /**
     * Check if test has enough data for reliable results
     * 
     * @param int $impressions_a Impressions for variation A
     * @param int $conversions_a Conversions for variation A
     * @param int $impressions_b Impressions for variation B
     * @param int $conversions_b Conversions for variation B
     * @return array Status and message
     */
    public static function check_data_sufficiency($impressions_a, $conversions_a, $impressions_b, $conversions_b)
    {
        $min_impressions = 100;
        $min_conversions_per_variation = 10;

        $total_conversions_a = $conversions_a;
        $total_conversions_b = $conversions_b;

        $warnings = array();

        if ($impressions_a < $min_impressions || $impressions_b < $min_impressions) {
            $warnings[] = "Insufficient impressions. Aim for at least {$min_impressions} impressions per variation.";
        }

        if ($total_conversions_a < $min_conversions_per_variation || $total_conversions_b < $min_conversions_per_variation) {
            $warnings[] = "Insufficient conversions. Aim for at least {$min_conversions_per_variation} conversions per variation for reliable results.";
        }

        return array(
            'sufficient' => empty($warnings),
            'warnings' => $warnings,
            'progress' => array(
                'impressions_a' => round(($impressions_a / $min_impressions) * 100, 0),
                'impressions_b' => round(($impressions_b / $min_impressions) * 100, 0),
                'conversions_a' => round(($total_conversions_a / $min_conversions_per_variation) * 100, 0),
                'conversions_b' => round(($total_conversions_b / $min_conversions_per_variation) * 100, 0)
            )
        );
    }

    /**
     * Calculate advanced metrics for a campaign
     * 
     * @param int $campaign_id Campaign ID
     * @return array Advanced metrics
     */
    public static function calculate_campaign_metrics($campaign_id)
    {
        global $wpdb;

        // Get all variations for this campaign
        $variations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, is_control, impressions, conversions 
             FROM " . Pronto_AB_Database::get_variations_table() . "
             WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);

        if (count($variations) < 2) {
            return array('error' => 'Need at least 2 variations for analysis');
        }

        // Find control variation
        $control = null;
        $variants = array();

        foreach ($variations as $variation) {
            if ($variation['is_control']) {
                $control = $variation;
            } else {
                $variants[] = $variation;
            }
        }

        if (!$control) {
            $control = $variations[0]; // Use first as control if none marked
            $variants = array_slice($variations, 1);
        }

        $results = array();

        foreach ($variants as $variant) {
            $stats = self::calculate_significance(
                $control['impressions'],
                $control['conversions'],
                $variant['impressions'],
                $variant['conversions']
            );

            $results[] = array(
                'variation_id' => $variant['id'],
                'variation_name' => $variant['name'],
                'control_name' => $control['name'],
                'stats' => $stats,
                'interpretation' => self::interpret_results($stats, $control['name'], $variant['name']),
                'data_check' => self::check_data_sufficiency(
                    $control['impressions'],
                    $control['conversions'],
                    $variant['impressions'],
                    $variant['conversions']
                )
            );
        }

        return $results;
    }
}
