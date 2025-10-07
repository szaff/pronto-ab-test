<?php

/**
 * A/B Test Goal Model
 * Handles CRUD operations for goals
 *
 * @package Pronto_AB
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Pronto_AB_Goal
{
    public $id;
    public $name;
    public $description;
    public $goal_type; // 'click', 'form', 'page_view', 'custom_event', 'revenue'
    public $tracking_method; // 'auto', 'manual', 'selector', 'url'
    public $tracking_value; // CSS selector, URL pattern, or custom event name
    public $default_value; // For revenue goals
    public $status;
    public $created_at;
    public $updated_at;

    /**
     * Constructor
     */
    public function __construct($data = array())
    {
        // Initialize all properties with default values
        $this->id = null;
        $this->name = '';
        $this->description = '';
        $this->goal_type = 'conversion';
        $this->tracking_method = 'manual';
        $this->tracking_value = '';
        $this->default_value = null;
        $this->status = 'active';
        $this->created_at = null;
        $this->updated_at = null;

        if (!empty($data)) {
            $this->fill($data);
        }
    }

    /**
     * Fill object with data
     */
    public function fill($data)
    {
        $properties = array(
            'id',
            'name',
            'description',
            'goal_type',
            'tracking_method',
            'tracking_value',
            'default_value',
            'status',
            'created_at',
            'updated_at'
        );

        foreach ($properties as $property) {
            if (isset($data[$property])) {
                // Special handling for string properties that should never be null
                if (in_array($property, ['name', 'description', 'goal_type', 'tracking_method', 'tracking_value', 'status'])) {
                    $this->$property = (string)$data[$property];
                } else {
                    $this->$property = $data[$property];
                }
            }
        }
    }

    /**
     * Save goal (create or update)
     */
    public function save()
    {
        global $wpdb;

        // Verify table exists
        $table = Pronto_AB_Database::get_goals_table();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if (!$table_exists) {
            error_log("Pronto A/B Debug: Goals table doesn't exist, creating tables");
            Pronto_AB_Database::create_tables();
        }

        // Prepare data array
        $data = array(
            'name' => $this->name,
            'description' => $this->description,
            'goal_type' => $this->goal_type ?: 'conversion',
            'tracking_method' => $this->tracking_method ?: 'manual',
            'tracking_value' => $this->tracking_value,
            'default_value' => $this->default_value,
            'status' => $this->status ?: 'active'
        );

        // Format specifications
        $formats = array(
            '%s',  // name
            '%s',  // description
            '%s',  // goal_type
            '%s',  // tracking_method
            '%s',  // tracking_value
            '%f',  // default_value
            '%s'   // status
        );

        // Update existing goal
        if ($this->id) {
            $result = $wpdb->update(
                $table,
                $data,
                array('id' => $this->id),
                $formats,
                array('%d')
            );

            if ($result === false) {
                error_log("Pronto A/B Error: Failed to update goal ID {$this->id}");
                error_log("MySQL Error: " . $wpdb->last_error);
                return false;
            }

            return true;
        }

        // Insert new goal
        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false) {
            error_log("Pronto A/B Error: Failed to insert new goal");
            error_log("MySQL Error: " . $wpdb->last_error);
            return false;
        }

        $this->id = $wpdb->insert_id;
        return true;
    }

    /**
     * Find goal by ID
     */
    public static function find($id)
    {
        global $wpdb;

        $goal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Pronto_AB_Database::get_goals_table() . " WHERE id = %d",
            $id
        ), ARRAY_A);

        return $goal ? new self($goal) : null;
    }

    /**
     * Get all goals with optional filters
     */
    public static function get_all($args = array())
    {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'goal_type' => '',
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['goal_type'])) {
            $where[] = 'goal_type = %s';
            $values[] = $args['goal_type'];
        }

        $sql = "SELECT * FROM " . Pronto_AB_Database::get_goals_table() .
            " WHERE " . implode(' AND ', $where) .
            " ORDER BY {$args['orderby']} {$args['order']}" .
            " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $goals = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function ($goal) {
            return new self($goal);
        }, $goals);
    }

    /**
     * Get goals by campaign ID
     */
    public static function get_by_campaign($campaign_id)
    {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT g.*, cg.is_primary, cg.goal_value as campaign_goal_value
            FROM " . Pronto_AB_Database::get_goals_table() . " g
            INNER JOIN " . Pronto_AB_Database::get_campaign_goals_table() . " cg
                ON g.id = cg.goal_id
            WHERE cg.campaign_id = %d
            ORDER BY cg.is_primary DESC, g.created_at ASC
        ", $campaign_id);

        $goals = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function ($goal) {
            $goal_obj = new self($goal);
            $goal_obj->is_primary = (bool)$goal['is_primary'];
            $goal_obj->campaign_goal_value = $goal['campaign_goal_value'];
            return $goal_obj;
        }, $goals);
    }

    /**
     * Get active goals for current page (for frontend tracking)
     */
    public static function get_active_for_page()
    {
        global $wpdb, $post;

        if (!$post) {
            return array();
        }

        // Get all active campaigns for this post
        $campaigns_table = Pronto_AB_Database::get_campaigns_table();
        $campaign_ids = $wpdb->get_col($wpdb->prepare("
            SELECT id FROM {$campaigns_table}
            WHERE status = 'active'
            AND target_post_id = %d
            AND (start_date IS NULL OR start_date <= NOW())
            AND (end_date IS NULL OR end_date >= NOW())
        ", $post->ID));

        if (empty($campaign_ids)) {
            return array();
        }

        // Get all goals for these campaigns
        $placeholders = implode(',', array_fill(0, count($campaign_ids), '%d'));
        $sql = $wpdb->prepare("
            SELECT DISTINCT g.*
            FROM " . Pronto_AB_Database::get_goals_table() . " g
            INNER JOIN " . Pronto_AB_Database::get_campaign_goals_table() . " cg
                ON g.id = cg.goal_id
            WHERE cg.campaign_id IN ({$placeholders})
            AND g.status = 'active'
            AND g.tracking_method IN ('selector', 'url')
        ", $campaign_ids);

        $goals = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function ($goal) {
            return array(
                'id' => (int)$goal['id'],
                'name' => $goal['name'],
                'goal_type' => $goal['goal_type'],
                'tracking_method' => $goal['tracking_method'],
                'tracking_value' => $goal['tracking_value'],
                'default_value' => (float)$goal['default_value']
            );
        }, $goals);
    }

    /**
     * Delete goal
     */
    public function delete()
    {
        global $wpdb;

        if (!$this->id) {
            return false;
        }

        // Delete campaign associations first
        $wpdb->delete(
            Pronto_AB_Database::get_campaign_goals_table(),
            array('goal_id' => $this->id),
            array('%d')
        );

        // Delete the goal
        return $wpdb->delete(
            Pronto_AB_Database::get_goals_table(),
            array('id' => $this->id),
            array('%d')
        ) !== false;
    }

    /**
     * Get campaigns using this goal
     */
    public function get_campaigns()
    {
        global $wpdb;

        if (!$this->id) {
            return array();
        }

        $sql = $wpdb->prepare("
            SELECT c.*
            FROM " . Pronto_AB_Database::get_campaigns_table() . " c
            INNER JOIN " . Pronto_AB_Database::get_campaign_goals_table() . " cg
                ON c.id = cg.campaign_id
            WHERE cg.goal_id = %d
            ORDER BY c.created_at DESC
        ", $this->id);

        $campaigns = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function ($campaign) {
            return new Pronto_AB_Campaign($campaign);
        }, $campaigns);
    }

    /**
     * Assign goal to campaign
     */
    public static function assign_to_campaign($goal_id, $campaign_id, $is_primary = false, $goal_value = null)
    {
        global $wpdb;

        // If this is being set as primary, unset other primary goals for this campaign
        if ($is_primary) {
            $wpdb->update(
                Pronto_AB_Database::get_campaign_goals_table(),
                array('is_primary' => 0),
                array('campaign_id' => $campaign_id),
                array('%d'),
                array('%d')
            );
        }

        // Insert or update the assignment
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM " . Pronto_AB_Database::get_campaign_goals_table() . "
            WHERE campaign_id = %d AND goal_id = %d
        ", $campaign_id, $goal_id));

        if ($existing) {
            // Update existing
            return $wpdb->update(
                Pronto_AB_Database::get_campaign_goals_table(),
                array(
                    'is_primary' => $is_primary ? 1 : 0,
                    'goal_value' => $goal_value
                ),
                array('id' => $existing),
                array('%d', '%f'),
                array('%d')
            ) !== false;
        } else {
            // Insert new
            return $wpdb->insert(
                Pronto_AB_Database::get_campaign_goals_table(),
                array(
                    'campaign_id' => $campaign_id,
                    'goal_id' => $goal_id,
                    'is_primary' => $is_primary ? 1 : 0,
                    'goal_value' => $goal_value
                ),
                array('%d', '%d', '%d', '%f')
            ) !== false;
        }
    }

    /**
     * Remove goal from campaign
     */
    public static function remove_from_campaign($goal_id, $campaign_id)
    {
        global $wpdb;

        return $wpdb->delete(
            Pronto_AB_Database::get_campaign_goals_table(),
            array(
                'campaign_id' => $campaign_id,
                'goal_id' => $goal_id
            ),
            array('%d', '%d')
        ) !== false;
    }

    /**
     * Get total conversions for this goal across all campaigns
     */
    public function get_total_conversions()
    {
        global $wpdb;

        if (!$this->id) {
            return 0;
        }

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM " . Pronto_AB_Database::get_analytics_table() . "
            WHERE goal_id = %d
            AND event_type = 'goal'
        ", $this->id));

        return (int)$count;
    }

    /**
     * Get goal statistics for a specific campaign
     */
    public function get_campaign_stats($campaign_id)
    {
        global $wpdb;

        if (!$this->id || !$campaign_id) {
            return array(
                'total_conversions' => 0,
                'total_value' => 0,
                'average_value' => 0
            );
        }

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_conversions,
                SUM(goal_value) as total_value,
                AVG(goal_value) as average_value
            FROM " . Pronto_AB_Database::get_analytics_table() . "
            WHERE campaign_id = %d
            AND goal_id = %d
            AND event_type = 'goal'
        ", $campaign_id, $this->id), ARRAY_A);

        return array(
            'total_conversions' => (int)($stats['total_conversions'] ?? 0),
            'total_value' => (float)($stats['total_value'] ?? 0),
            'average_value' => (float)($stats['average_value'] ?? 0)
        );
    }

    /**
     * Check if goal is assigned to campaign
     */
    public static function is_assigned_to_campaign($goal_id, $campaign_id)
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM " . Pronto_AB_Database::get_campaign_goals_table() . "
            WHERE campaign_id = %d AND goal_id = %d
        ", $campaign_id, $goal_id));

        return (int)$exists > 0;
    }

    /**
     * Get primary goal for campaign
     */
    public static function get_primary_for_campaign($campaign_id)
    {
        global $wpdb;

        $goal = $wpdb->get_row($wpdb->prepare("
            SELECT g.*
            FROM " . Pronto_AB_Database::get_goals_table() . " g
            INNER JOIN " . Pronto_AB_Database::get_campaign_goals_table() . " cg
                ON g.id = cg.goal_id
            WHERE cg.campaign_id = %d
            AND cg.is_primary = 1
            LIMIT 1
        ", $campaign_id), ARRAY_A);

        return $goal ? new self($goal) : null;
    }
}
