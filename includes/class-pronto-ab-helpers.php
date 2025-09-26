<?php

if (!class_exists('Pronto_AB_Null_Safe_Helpers')) {
    /**
     * Null-safe helper functions for the A/B testing plugin
     */
    class Pronto_AB_Null_Safe_Helpers
    {
        /**
         * Null-safe esc_attr wrapper
         */
        public static function safe_esc_attr($value, $default = '')
        {
            return esc_attr($value ?? $default);
        }

        /**
         * Null-safe esc_html wrapper
         */
        public static function safe_esc_html($value, $default = '')
        {
            return esc_html($value ?? $default);
        }

        /**
         * Null-safe esc_textarea wrapper
         */
        public static function safe_esc_textarea($value, $default = '')
        {
            return esc_textarea($value ?? $default);
        }

        /**
         * Null-safe number formatting
         */
        public static function safe_number_format($value, $decimals = 0, $default = 0)
        {
            $number = is_numeric($value) ? (float)$value : $default;
            return number_format($number, $decimals);
        }

        /**
         * Null-safe selected() wrapper
         */
        public static function safe_selected($selected, $current, $default = '')
        {
            return selected($selected ?? $default, $current, false);
        }

        /**
         * Null-safe checked() wrapper
         */
        public static function safe_checked($checked, $current = true, $default = false)
        {
            return checked($checked ?? $default, $current, false);
        }

        /**
         * Get string value with null safety
         */
        public static function get_string($value, $default = '')
        {
            return (string)($value ?? $default);
        }

        /**
         * Get integer value with null safety
         */
        public static function get_int($value, $default = 0)
        {
            return (int)($value ?? $default);
        }

        /**
         * Get float value with null safety
         */
        public static function get_float($value, $default = 0.0)
        {
            return (float)($value ?? $default);
        }
    }
}
