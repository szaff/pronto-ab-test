<?php

/**
 * A/B Test Campaign Model
 * Handles CRUD operations for campaigns
 */
class Pronto_AB_Campaign
{

    public $id;
    public $name;
    public $description;
    public $status;
    public $target_post_id;
    public $target_post_type;
    public $traffic_split;
    public $start_date;
    public $end_date;
    public $winner_variation_id;
    public $winner_declared_at;
    public $winner_declared_by;
    public $auto_winner_enabled;
    public $archived_at;
    public $archived_by;
    public $total_impressions;
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
        $this->status = 'draft';
        $this->target_post_id = null;
        $this->target_post_type = '';
        $this->traffic_split = '50/50';
        $this->start_date = null;
        $this->end_date = null;
        $this->winner_variation_id = null;
        $this->winner_declared_at = null;
        $this->winner_declared_by = null;
        $this->auto_winner_enabled = 0;
        $this->archived_at = null;
        $this->archived_by = null;
        $this->total_impressions = 0;
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
            'status',
            'target_post_id',
            'target_post_type',
            'traffic_split',
            'start_date',
            'end_date',
            'winner_variation_id',
            'winner_declared_at',
            'winner_declared_by',
            'auto_winner_enabled',
            'archived_at',
            'archived_by',
            'total_impressions',
            'created_at',
            'updated_at'
        );

        foreach ($properties as $property) {
            if (isset($data[$property])) {
                // Special handling for string properties that should never be null
                if (in_array($property, ['name', 'description', 'status', 'target_post_type', 'traffic_split'])) {
                    $this->$property = (string)$data[$property];
                } else {
                    $this->$property = $data[$property];
                }
            }
        }
    }

    /**
     * Save campaign (create or update) with enhanced debugging
     */
    public function save()
    {
        global $wpdb;

        // Verify table exists
        $table = Pronto_AB_Database::get_campaigns_table();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if (!$table_exists) {
            error_log("Pronto A/B Debug: Campaigns table doesn't exist, creating tables");
            Pronto_AB_Database::create_tables();
        }

        // Prepare data array - ALL fields that go into the database
        $data = array(
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status ?: 'draft',
            'target_post_id' => $this->target_post_id,
            'target_post_type' => $this->target_post_type,
            'traffic_split' => $this->traffic_split ?: '50/50',
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'winner_variation_id' => $this->winner_variation_id,
            'winner_declared_at' => $this->winner_declared_at,
            'winner_declared_by' => $this->winner_declared_by,
            'auto_winner_enabled' => $this->auto_winner_enabled,
            'archived_at' => $this->archived_at,
            'archived_by' => $this->archived_by,
            'total_impressions' => $this->total_impressions
        );

        // Format specifications for each field
        // %s = string, %d = integer, %f = float
        $formats = array(
            '%s',  // name (varchar)
            '%s',  // description (text)
            '%s',  // status (varchar)
            '%d',  // target_post_id (bigint) - nullable, but %d handles NULL
            '%s',  // target_post_type (varchar)
            '%s',  // traffic_split (varchar)
            '%s',  // start_date (datetime)
            '%s',  // end_date (datetime)
            '%d',  // winner_variation_id (bigint)
            '%s',  // winner_declared_at (datetime)
            '%d',  // winner_declared_by (bigint)
            '%d',  // auto_winner_enabled (tinyint)
            '%s',  // archived_at (datetime)
            '%d',  // archived_by (bigint)
            '%d'   // total_impressions (bigint)
        );

        // Update existing campaign
        if ($this->id) {
            $result = $wpdb->update(
                $table,
                $data,
                array('id' => $this->id),
                $formats,
                array('%d') // ID is an integer
            );

            if ($result === false) {
                error_log("Pronto A/B Error: Failed to update campaign ID {$this->id}");
                error_log("MySQL Error: " . $wpdb->last_error);
                return false;
            }

            return true;
        }

        // Insert new campaign
        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false) {
            error_log("Pronto A/B Error: Failed to insert new campaign");
            error_log("MySQL Error: " . $wpdb->last_error);
            return false;
        }

        $this->id = $wpdb->insert_id;
        return true;
    }

    /**
     * Find campaign by ID
     */
    public static function find($id)
    {
        global $wpdb;

        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Pronto_AB_Database::get_campaigns_table() . " WHERE id = %d",
            $id
        ), ARRAY_A);

        return $campaign ? new self($campaign) : null;
    }

    /**
     * Get all campaigns with optional filters
     */
    public static function get_campaigns($args = array())
    {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'limit' => 20,
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

        $sql = "SELECT * FROM " . Pronto_AB_Database::get_campaigns_table() .
            " WHERE " . implode(' AND ', $where) .
            " ORDER BY {$args['orderby']} {$args['order']}" .
            " LIMIT {$args['limit']} OFFSET {$args['offset']}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $campaigns = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function ($campaign) {
            return new self($campaign);
        }, $campaigns);
    }

    /**
     * Delete campaign
     */
    public function delete()
    {
        global $wpdb;

        if (!$this->id) {
            return false;
        }

        return $wpdb->delete(
            Pronto_AB_Database::get_campaigns_table(),
            array('id' => $this->id),
            array('%d')
        ) !== false;
    }

    /**
     * Get campaign variations
     */
    public function get_variations()
    {
        if (!$this->id) {
            return array();
        }

        return Pronto_AB_Variation::get_by_campaign($this->id);
    }

    /**
     * Check if campaign is active
     */
    public function is_active()
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = current_time('mysql');

        // Check if started
        if ($this->start_date && $now < $this->start_date) {
            return false;
        }

        // Check if ended
        if ($this->end_date && $now > $this->end_date) {
            return false;
        }

        return true;
    }

    /**
     * Get campaign statistics
     */
    public function get_stats()
    {
        global $wpdb;

        // Default stats structure
        $default_stats = array(
            'total_events' => 0,
            'unique_visitors' => 0,
            'impressions' => 0,
            'conversions' => 0
        );

        // If no campaign ID, return defaults
        if (!$this->id) {
            return $default_stats;
        }

        $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(*) as total_events,
            COUNT(DISTINCT visitor_id) as unique_visitors,
            SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
            SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions
        FROM " . Pronto_AB_Database::get_analytics_table() . "
        WHERE campaign_id = %d
    ", $this->id), ARRAY_A);

        // If query failed or returned null, use defaults
        if (!$stats || !is_array($stats)) {
            return $default_stats;
        }

        // Ensure all values are integers (never null)
        foreach ($default_stats as $key => $default_value) {
            $stats[$key] = isset($stats[$key]) ? (int)$stats[$key] : $default_value;
        }

        return $stats;
    }
}
