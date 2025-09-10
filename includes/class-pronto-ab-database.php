<?php

/**
 * A/B Test Database Management
 * 
 * This file contains database table creation and CRUD operations
 * for the A/B testing system.
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

/**
 * A/B Test Database Manager
 * Handles table creation, updates, and basic database operations
 */
class Pronto_AB_Database
{

    /**
     * Database version for managing updates
     */
    const DB_VERSION = '1.0.0';

    /**
     * Table names with WordPress prefix
     */
    public static function get_campaigns_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'pronto_ab_campaigns';
    }

    public static function get_variations_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'pronto_ab_variations';
    }

    public static function get_analytics_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'pronto_ab_analytics';
    }

    /**
     * Create all database tables
     */
    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Campaigns table
        $campaigns_table = self::get_campaigns_table();
        $campaigns_sql = "CREATE TABLE $campaigns_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            status varchar(20) NOT NULL DEFAULT 'draft',
            target_post_id bigint(20) unsigned,
            target_post_type varchar(50),
            traffic_split varchar(50) DEFAULT '50/50',
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            winner_variation_id bigint(20) unsigned DEFAULT NULL,
            total_impressions bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY target_post (target_post_id, target_post_type),
            KEY status (status),
            KEY dates (start_date, end_date)
        ) $charset_collate;";

        // Variations table
        $variations_table = self::get_variations_table();
        $variations_sql = "CREATE TABLE $variations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            content longtext,
            content_type varchar(50) DEFAULT 'html',
            is_control tinyint(1) DEFAULT 0,
            weight_percentage decimal(5,2) DEFAULT 50.00,
            impressions bigint(20) unsigned DEFAULT 0,
            conversions bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY is_control (is_control),
            FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Analytics table
        $analytics_table = self::get_analytics_table();
        $analytics_sql = "CREATE TABLE $analytics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned NOT NULL,
            visitor_id varchar(255) NOT NULL,
            session_id varchar(255),
            event_type varchar(50) NOT NULL,
            event_value varchar(255),
            user_agent text,
            ip_address varchar(45),
            referrer text,
            additional_data longtext,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_variation (campaign_id, variation_id),
            KEY visitor_id (visitor_id),
            KEY event_type (event_type),
            KEY timestamp (timestamp),
            FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE,
            FOREIGN KEY (variation_id) REFERENCES $variations_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($campaigns_sql);
        dbDelta($variations_sql);
        dbDelta($analytics_sql);

        // Update database version
        update_option('pronto_ab_db_version', self::DB_VERSION);
    }

    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = array(
            self::get_analytics_table(),
            self::get_variations_table(),
            self::get_campaigns_table()
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('pronto_ab_db_version');
    }

    /**
     * Check if database needs update
     */
    public static function needs_update()
    {
        $current_version = get_option('pronto_ab_db_version', '0.0.0');
        return version_compare($current_version, self::DB_VERSION, '<');
    }
}
