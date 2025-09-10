<?php

/**
 * A/B Test Variation Model
 */
class Pronto_AB_Variation
{

    public $id;
    public $campaign_id;
    public $name;
    public $content;
    public $content_type;
    public $is_control;
    public $weight_percentage;
    public $impressions;
    public $conversions;
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
            'campaign_id',
            'name',
            'content',
            'content_type',
            'is_control',
            'weight_percentage',
            'impressions',
            'conversions',
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
     * Save variation
     */
    public function save()
    {
        global $wpdb;

        $data = array(
            'campaign_id' => $this->campaign_id,
            'name' => $this->name,
            'content' => $this->content,
            'content_type' => $this->content_type ?: 'html',
            'is_control' => $this->is_control ? 1 : 0,
            'weight_percentage' => $this->weight_percentage ?: 50.00
        );

        $formats = array('%d', '%s', '%s', '%s', '%d', '%f');

        if ($this->id) {
            $result = $wpdb->update(
                Pronto_AB_Database::get_variations_table(),
                $data,
                array('id' => $this->id),
                $formats,
                array('%d')
            );
        } else {
            $result = $wpdb->insert(
                Pronto_AB_Database::get_variations_table(),
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
     * Find variation by ID
     */
    public static function find($id)
    {
        global $wpdb;

        $variation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Pronto_AB_Database::get_variations_table() . " WHERE id = %d",
            $id
        ), ARRAY_A);

        return $variation ? new self($variation) : null;
    }

    /**
     * Get variations by campaign ID
     */
    public static function get_by_campaign($campaign_id)
    {
        global $wpdb;

        $variations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . Pronto_AB_Database::get_variations_table() .
                " WHERE campaign_id = %d ORDER BY is_control DESC, id ASC",
            $campaign_id
        ), ARRAY_A);

        return array_map(function ($variation) {
            return new self($variation);
        }, $variations);
    }

    /**
     * Delete variation
     */
    public function delete()
    {
        global $wpdb;

        if (!$this->id) {
            return false;
        }

        return $wpdb->delete(
            Pronto_AB_Database::get_variations_table(),
            array('id' => $this->id),
            array('%d')
        ) !== false;
    }

    /**
     * Calculate conversion rate
     */
    public function get_conversion_rate()
    {
        if (!$this->impressions || $this->impressions == 0) {
            return 0;
        }

        return round(($this->conversions / $this->impressions) * 100, 2);
    }
}
