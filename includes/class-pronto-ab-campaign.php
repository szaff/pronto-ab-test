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
    public $total_impressions;
    public $created_at;
    public $updated_at;

    /**
     * Constructor
     */
    public function __construct($data = array())
    {
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
            'total_impressions',
            'created_at',
            'updated_at'
        );

        foreach ($properties as $property) {
            if (isset($data[$property])) {
                $this->$property = $data[$property];
            }
        }
    }

    /**
     * Save campaign (create or update)
     */
    public function save()
    {
        global $wpdb;

        $data = array(
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status ?: 'draft',
            'target_post_id' => $this->target_post_id,
            'target_post_type' => $this->target_post_type,
            'traffic_split' => $this->traffic_split ?: '50/50',
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'winner_variation_id' => $this->winner_variation_id
        );

        $formats = array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d');

        if ($this->id) {
            // Update existing campaign
            $result = $wpdb->update(
                Pronto_AB_Database::get_campaigns_table(),
                $data,
                array('id' => $this->id),
                $formats,
                array('%d')
            );
        } else {
            // Create new campaign
            $result = $wpdb->insert(
                Pronto_AB_Database::get_campaigns_table(),
                $data,
                $formats
            );

            if ($result) {
                $this->id = $wpdb->insert_id;
            }
        }

        return $result !== false;
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

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT visitor_id) as unique_visitors,
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions
            FROM " . Pronto_AB_Database::get_analytics_table() . "
            WHERE campaign_id = %d
        ", $this->id), ARRAY_A);

        return $stats ?: array(
            'total_events' => 0,
            'unique_visitors' => 0,
            'impressions' => 0,
            'conversions' => 0
        );
    }
}
