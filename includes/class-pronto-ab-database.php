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
    const DB_VERSION = '1.2.0';

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

    public static function get_notifications_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'pronto_ab_notifications';
    }

    public static function get_goals_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'pronto_ab_goals';
    }

    public static function get_campaign_goals_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'pronto_ab_campaign_goals';
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
            winner_declared_at datetime DEFAULT NULL,
            winner_declared_by bigint(20) unsigned DEFAULT NULL,
            auto_winner_enabled tinyint(1) DEFAULT 0,
            archived_at datetime DEFAULT NULL,
            archived_by bigint(20) unsigned DEFAULT NULL,
            total_impressions bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY target_post (target_post_id, target_post_type),
            KEY status (status),
            KEY dates (start_date, end_date),
            KEY winner (winner_variation_id),
            KEY archived (archived_at)
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
            KEY is_control (is_control)
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
            KEY timestamp (timestamp)
        ) $charset_collate;";

        // Notifications table
        $notifications_table = self::get_notifications_table();
        $notifications_sql = "CREATE TABLE $notifications_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            notification_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text,
            is_read tinyint(1) DEFAULT 0,
            user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY notification_type (notification_type),
            KEY is_read (is_read),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Goals table
        $goals_table = self::get_goals_table();
        $goals_sql = "CREATE TABLE $goals_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            goal_type varchar(50) NOT NULL DEFAULT 'conversion',
            tracking_method varchar(50) NOT NULL DEFAULT 'manual',
            tracking_value text,
            default_value decimal(10,2) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY goal_type (goal_type)
        ) $charset_collate;";

        // Campaign Goals relationship table
        $campaign_goals_table = self::get_campaign_goals_table();
        $campaign_goals_sql = "CREATE TABLE $campaign_goals_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            goal_id bigint(20) unsigned NOT NULL,
            is_primary tinyint(1) DEFAULT 0,
            goal_value decimal(10,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_goal (campaign_id, goal_id),
            KEY campaign_id (campaign_id),
            KEY goal_id (goal_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($campaigns_sql);
        dbDelta($variations_sql);
        dbDelta($analytics_sql);
        dbDelta($notifications_sql);
        dbDelta($goals_sql);
        dbDelta($campaign_goals_sql);

        // Update database version
        update_option('pronto_ab_db_version', self::DB_VERSION);
    }

    /**
     * Migrate database from older versions
     *
     * @param string $installed_version Current installed version
     */
    public static function migrate_database($installed_version)
    {
        // Migration from 1.0.0 to 1.1.0
        if (version_compare($installed_version, '1.1.0', '<')) {
            self::migrate_to_1_1_0();
        }

        // Migration from 1.1.0 to 1.2.0 (Goals system)
        if (version_compare($installed_version, '1.2.0', '<')) {
            self::migrate_to_1_2_0();
        }

        // Update version
        update_option('pronto_ab_db_version', self::DB_VERSION);
    }

    /**
     * Migration to version 1.1.0
     * Adds winner declaration and archiving columns
     */
    private static function migrate_to_1_1_0()
    {
        global $wpdb;

        $campaigns_table = self::get_campaigns_table();

        // Check if columns already exist to avoid errors
        $columns = $wpdb->get_col("DESC {$campaigns_table}", 0);

        // Add winner_declared_at column
        if (!in_array('winner_declared_at', $columns)) {
            $wpdb->query("ALTER TABLE {$campaigns_table} 
                ADD COLUMN winner_declared_at datetime DEFAULT NULL AFTER winner_variation_id");
        }

        // Add winner_declared_by column
        if (!in_array('winner_declared_by', $columns)) {
            $wpdb->query("ALTER TABLE {$campaigns_table} 
                ADD COLUMN winner_declared_by bigint(20) unsigned DEFAULT NULL AFTER winner_declared_at");
        }

        // Add auto_winner_enabled column
        if (!in_array('auto_winner_enabled', $columns)) {
            $wpdb->query("ALTER TABLE {$campaigns_table} 
                ADD COLUMN auto_winner_enabled tinyint(1) DEFAULT 0 AFTER winner_declared_by");
        }

        // Add archived_at column
        if (!in_array('archived_at', $columns)) {
            $wpdb->query("ALTER TABLE {$campaigns_table} 
                ADD COLUMN archived_at datetime DEFAULT NULL AFTER auto_winner_enabled");
        }

        // Add archived_by column
        if (!in_array('archived_by', $columns)) {
            $wpdb->query("ALTER TABLE {$campaigns_table} 
                ADD COLUMN archived_by bigint(20) unsigned DEFAULT NULL AFTER archived_at");
        }

        // Add indexes
        $wpdb->query("ALTER TABLE {$campaigns_table} 
            ADD KEY winner (winner_variation_id), 
            ADD KEY archived (archived_at)");

        // Create notifications table
        self::create_notifications_table();

        // Log migration
        error_log('Pronto A/B: Database migrated to version 1.1.0');
    }

    /**
     * Migration to version 1.2.0
     * Adds goals system tables and analytics goal tracking
     */
    private static function migrate_to_1_2_0()
    {
        global $wpdb;

        // Create goals tables
        self::create_goals_tables();

        // Add goal columns to analytics table
        $analytics_table = self::get_analytics_table();
        $columns = $wpdb->get_col("DESC {$analytics_table}", 0);

        // Add goal_id column
        if (!in_array('goal_id', $columns)) {
            $wpdb->query("ALTER TABLE {$analytics_table}
                ADD COLUMN goal_id bigint(20) unsigned DEFAULT NULL AFTER event_value");
        }

        // Add goal_value column
        if (!in_array('goal_value', $columns)) {
            $wpdb->query("ALTER TABLE {$analytics_table}
                ADD COLUMN goal_value decimal(10,2) DEFAULT NULL AFTER goal_id");
        }

        // Add index for goal_id if not exists
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$analytics_table} WHERE Key_name = 'goal_id'");
        if (empty($indexes)) {
            $wpdb->query("ALTER TABLE {$analytics_table} ADD KEY goal_id (goal_id)");
        }
    }

    /**
     * Create goals tables separately
     * Used for migrations and fresh installs
     */
    private static function create_goals_tables()
    {
        global $wpdb;

        $goals_table = self::get_goals_table();
        $campaign_goals_table = self::get_campaign_goals_table();
        $campaigns_table = self::get_campaigns_table();

        // Check if tables already exist
        if ($wpdb->get_var("SHOW TABLES LIKE '{$goals_table}'") === $goals_table) {
            return; // Tables already exist
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Create goals table (no foreign keys)
        $goals_sql = "CREATE TABLE IF NOT EXISTS $goals_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            goal_type varchar(50) NOT NULL DEFAULT 'conversion',
            tracking_method varchar(50) NOT NULL DEFAULT 'manual',
            tracking_value text,
            default_value decimal(10,2) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY goal_type (goal_type)
        ) $charset_collate";

        // Create campaign_goals table (no foreign keys in initial creation)
        $campaign_goals_sql = "CREATE TABLE IF NOT EXISTS $campaign_goals_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            goal_id bigint(20) unsigned NOT NULL,
            is_primary tinyint(1) DEFAULT 0,
            goal_value decimal(10,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_goal (campaign_id, goal_id),
            KEY campaign_id (campaign_id),
            KEY goal_id (goal_id)
        ) $charset_collate";

        // Execute table creation
        $wpdb->query($goals_sql);
        $wpdb->query($campaign_goals_sql);

        // Add foreign keys separately (if they don't already exist)
        // Check if foreign key already exists before adding
        $fk_check = $wpdb->get_results("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$campaign_goals_table'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");

        if (empty($fk_check)) {
            $wpdb->query("ALTER TABLE $campaign_goals_table
                ADD CONSTRAINT fk_campaign_goals_campaign
                FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE");

            $wpdb->query("ALTER TABLE $campaign_goals_table
                ADD CONSTRAINT fk_campaign_goals_goal
                FOREIGN KEY (goal_id) REFERENCES $goals_table(id) ON DELETE CASCADE");
        }
    }

    /**
     * Create notifications table separately
     * Used for migrations and fresh installs
     */
    private static function create_notifications_table()
    {
        global $wpdb;

        $notifications_table = self::get_notifications_table();
        $campaigns_table = self::get_campaigns_table();

        // Check if table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$notifications_table}'") === $notifications_table) {
            return; // Table already exists
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $notifications_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            notification_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text,
            is_read tinyint(1) DEFAULT 0,
            user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY notification_type (notification_type),
            KEY is_read (is_read),
            KEY user_id (user_id),
            KEY created_at (created_at),
            FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = array(
            self::get_notifications_table(),
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
