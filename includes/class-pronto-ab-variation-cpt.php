<?php

/**
 * A/B Test Variation Custom Post Type Implementation
 * Add this to your main plugin file or create a new includes/class-pronto-ab-variation-cpt.php
 */

class Pronto_AB_Variation_CPT
{
    /**
     * Initialize the custom post type
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_variation_meta'));
        add_filter('manage_ab_variation_posts_columns', array(__CLASS__, 'custom_columns'));
        add_action('manage_ab_variation_posts_custom_column', array(__CLASS__, 'custom_column_content'), 10, 2);
        add_filter('post_row_actions', array(__CLASS__, 'custom_row_actions'), 10, 2);
    }

    /**
     * Register the A/B Test Variation post type
     */
    public static function register_post_type()
    {
        $labels = array(
            'name'                  => _x('A/B Variations', 'Post type general name', 'pronto-ab'),
            'singular_name'         => _x('A/B Variation', 'Post type singular name', 'pronto-ab'),
            'menu_name'             => _x('A/B Variations', 'Admin Menu text', 'pronto-ab'),
            'name_admin_bar'        => _x('A/B Variation', 'Add New on Toolbar', 'pronto-ab'),
            'add_new'               => __('Add New', 'pronto-ab'),
            'add_new_item'          => __('Add New Variation', 'pronto-ab'),
            'new_item'              => __('New Variation', 'pronto-ab'),
            'edit_item'             => __('Edit Variation', 'pronto-ab'),
            'view_item'             => __('View Variation', 'pronto-ab'),
            'all_items'             => __('All Variations', 'pronto-ab'),
            'search_items'          => __('Search Variations', 'pronto-ab'),
            'parent_item_colon'     => __('Parent Variations:', 'pronto-ab'),
            'not_found'             => __('No variations found.', 'pronto-ab'),
            'not_found_in_trash'    => __('No variations found in Trash.', 'pronto-ab'),
            'featured_image'        => _x('Variation Featured Image', 'Overrides the "Featured Image" phrase for this post type. Added in 4.3', 'pronto-ab'),
            'set_featured_image'    => _x('Set featured image', 'Overrides the "Set featured image" phrase for this post type. Added in 4.3', 'pronto-ab'),
            'remove_featured_image' => _x('Remove featured image', 'Overrides the "Remove featured image" phrase for this post type. Added in 4.3', 'pronto-ab'),
            'use_featured_image'    => _x('Use as featured image', 'Overrides the "Use as featured image" phrase for this post type. Added in 4.3', 'pronto-ab'),
            'archives'              => _x('Variation archives', 'The post type archive label used in nav menus. Default "Post Archives". Added in 4.4', 'pronto-ab'),
            'insert_into_item'      => _x('Insert into variation', 'Overrides the "Insert into post"/"Insert into page" phrase (used when inserting media into a post). Added in 4.4', 'pronto-ab'),
            'uploaded_to_this_item' => _x('Uploaded to this variation', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase (used when viewing media attached to a post). Added in 4.4', 'pronto-ab'),
            'filter_items_list'     => _x('Filter variations list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'pronto-ab'),
            'items_list_navigation' => _x('Variations list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'pronto-ab'),
            'items_list'            => _x('Variations list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'pronto-ab'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // We'll add it as a submenu manually
            'show_in_rest'       => true, // Enable block editor
            'query_var'          => true,
            'rewrite'            => array('slug' => 'ab-variation'),
            'capability_type'    => 'post',
            'capabilities'       => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'delete_posts'       => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
            ),
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'revisions'),
            'template'           => array(
                array('core/paragraph', array(
                    'placeholder' => 'Start creating your variation content...',
                )),
            ),
        );

        register_post_type('ab_variation', $args);
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes()
    {
        add_meta_box(
            'ab_variation_details',
            __('Variation Details', 'pronto-ab'),
            array(__CLASS__, 'variation_details_meta_box'),
            'ab_variation',
            'side',
            'high'
        );

        add_meta_box(
            'ab_variation_stats',
            __('Variation Statistics', 'pronto-ab'),
            array(__CLASS__, 'variation_stats_meta_box'),
            'ab_variation',
            'side',
            'default'
        );
    }

    /**
     * Variation details meta box
     */
    public static function variation_details_meta_box($post)
    {
        wp_nonce_field('ab_variation_meta_box', 'ab_variation_meta_box_nonce');

        $campaign_id = get_post_meta($post->ID, '_ab_campaign_id', true);
        $is_control = get_post_meta($post->ID, '_ab_is_control', true);
        $weight_percentage = get_post_meta($post->ID, '_ab_weight_percentage', true) ?: 50;

        // Get available campaigns
        $campaigns = Pronto_AB_Campaign::get_campaigns();
?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ab_campaign_id"><?php esc_html_e('Campaign', 'pronto-ab'); ?></label>
                </th>
                <td>
                    <select name="ab_campaign_id" id="ab_campaign_id" class="widefat">
                        <option value=""><?php esc_html_e('Select Campaign', 'pronto-ab'); ?></option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?php echo esc_attr($campaign->id); ?>" <?php selected($campaign_id, $campaign->id); ?>>
                                <?php echo esc_html($campaign->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Which A/B test campaign does this variation belong to?', 'pronto-ab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ab_is_control"><?php esc_html_e('Control Variation', 'pronto-ab'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="ab_is_control" id="ab_is_control" value="1" <?php checked($is_control, '1'); ?>>
                        <?php esc_html_e('This is the control (original) variation', 'pronto-ab'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('The control variation is what visitors see by default.', 'pronto-ab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ab_weight_percentage"><?php esc_html_e('Traffic Weight', 'pronto-ab'); ?></label>
                </th>
                <td>
                    <input type="number" name="ab_weight_percentage" id="ab_weight_percentage"
                        value="<?php echo esc_attr($weight_percentage); ?>"
                        min="0" max="100" step="0.1" class="small-text">%
                    <p class="description">
                        <?php esc_html_e('Percentage of traffic that should see this variation.', 'pronto-ab'); ?>
                    </p>
                </td>
            </tr>
        </table>
    <?php
    }

    /**
     * Variation statistics meta box
     */
    public static function variation_stats_meta_box($post)
    {
        $campaign_id = get_post_meta($post->ID, '_ab_campaign_id', true);

        if (!$campaign_id) {
            echo '<p>' . esc_html__('Please assign this variation to a campaign to see statistics.', 'pronto-ab') . '</p>';
            return;
        }

        // Get variation from database (if it exists there too)
        global $wpdb;
        $table = Pronto_AB_Database::get_variations_table();
        $variation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE campaign_id = %d AND name = %s",
            $campaign_id,
            $post->post_title
        ));

        if (!$variation) {
            echo '<p>' . esc_html__('No statistics available yet. Activate the campaign to start collecting data.', 'pronto-ab') . '</p>';
            return;
        }

        $conversion_rate = $variation->impressions > 0 ?
            round(($variation->conversions / $variation->impressions) * 100, 2) : 0;

    ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Impressions', 'pronto-ab'); ?></th>
                <td><strong><?php echo number_format($variation->impressions); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Conversions', 'pronto-ab'); ?></th>
                <td><strong><?php echo number_format($variation->conversions); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Conversion Rate', 'pronto-ab'); ?></th>
                <td><strong><?php echo $conversion_rate; ?>%</strong></td>
            </tr>
        </table>

        <?php if ($campaign_id): ?>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-analytics&campaign_id=' . $campaign_id)); ?>"
                    class="button button-secondary">
                    <?php esc_html_e('View Detailed Analytics', 'pronto-ab'); ?>
                </a>
            </p>
        <?php endif; ?>
<?php
    }

    /**
     * Save variation meta data
     */
    public static function save_variation_meta($post_id)
    {
        // Check if our nonce is set.
        if (!isset($_POST['ab_variation_meta_box_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['ab_variation_meta_box_nonce'], 'ab_variation_meta_box')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (!current_user_can('manage_options')) {
            return;
        }

        // Sanitize and save the data
        $campaign_id = intval($_POST['ab_campaign_id']);
        $is_control = isset($_POST['ab_is_control']) ? '1' : '0';
        $weight_percentage = floatval($_POST['ab_weight_percentage']);

        update_post_meta($post_id, '_ab_campaign_id', $campaign_id);
        update_post_meta($post_id, '_ab_is_control', $is_control);
        update_post_meta($post_id, '_ab_weight_percentage', $weight_percentage);

        // Also sync with database table if needed
        if ($campaign_id) {
            self::sync_variation_to_database($post_id, $campaign_id);
        }
    }

    /**
     * Sync variation post to database table
     */
    private static function sync_variation_to_database($post_id, $campaign_id)
    {
        $post = get_post($post_id);
        if (!$post) return;

        $is_control = get_post_meta($post_id, '_ab_is_control', true);
        $weight_percentage = get_post_meta($post_id, '_ab_weight_percentage', true);

        // Check if variation already exists in database
        global $wpdb;
        $table = Pronto_AB_Database::get_variations_table();
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE campaign_id = %d AND name = %s",
            $campaign_id,
            $post->post_title
        ));

        $data = array(
            'campaign_id' => $campaign_id,
            'name' => $post->post_title,
            'content' => $post->post_content,
            'content_type' => 'blocks',
            'is_control' => $is_control ? 1 : 0,
            'weight_percentage' => $weight_percentage ?: 50
        );

        if ($existing) {
            // Update existing
            $wpdb->update(
                $table,
                $data,
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s', '%d', '%f'),
                array('%d')
            );
        } else {
            // Insert new
            $wpdb->insert(
                $table,
                $data,
                array('%d', '%s', '%s', '%s', '%d', '%f')
            );
        }
    }

    /**
     * Custom columns for variation list
     */
    public static function custom_columns($columns)
    {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['campaign'] = __('Campaign', 'pronto-ab');
        $new_columns['is_control'] = __('Type', 'pronto-ab');
        $new_columns['weight'] = __('Weight', 'pronto-ab');
        $new_columns['stats'] = __('Performance', 'pronto-ab');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    /**
     * Custom column content
     */
    public static function custom_column_content($column, $post_id)
    {
        switch ($column) {
            case 'campaign':
                $campaign_id = get_post_meta($post_id, '_ab_campaign_id', true);
                if ($campaign_id) {
                    $campaign = Pronto_AB_Campaign::find($campaign_id);
                    if ($campaign) {
                        $edit_url = admin_url('admin.php?page=pronto-abs-new&campaign_id=' . $campaign_id);
                        echo '<a href="' . esc_url($edit_url) . '">' . esc_html($campaign->name) . '</a>';
                    } else {
                        echo '<span style="color: #999;">Campaign not found</span>';
                    }
                } else {
                    echo '<span style="color: #999;">No campaign assigned</span>';
                }
                break;

            case 'is_control':
                $is_control = get_post_meta($post_id, '_ab_is_control', true);
                if ($is_control) {
                    echo '<span style="color: #0073aa; font-weight: bold;">Control</span>';
                } else {
                    echo '<span style="color: #00a32a;">Variation</span>';
                }
                break;

            case 'weight':
                $weight = get_post_meta($post_id, '_ab_weight_percentage', true);
                echo esc_html($weight ?: '50') . '%';
                break;

            case 'stats':
                $campaign_id = get_post_meta($post_id, '_ab_campaign_id', true);
                if ($campaign_id) {
                    global $wpdb;
                    $table = Pronto_AB_Database::get_variations_table();
                    $variation = $wpdb->get_row($wpdb->prepare(
                        "SELECT impressions, conversions FROM $table WHERE campaign_id = %d AND name = %s",
                        $campaign_id,
                        get_the_title($post_id)
                    ));

                    if ($variation && $variation->impressions > 0) {
                        $rate = round(($variation->conversions / $variation->impressions) * 100, 2);
                        echo number_format($variation->impressions) . ' views<br>';
                        echo number_format($variation->conversions) . ' conversions (' . $rate . '%)';
                    } else {
                        echo '<span style="color: #999;">No data yet</span>';
                    }
                } else {
                    echo '<span style="color: #999;">No campaign</span>';
                }
                break;
        }
    }

    /**
     * Custom row actions
     */
    public static function custom_row_actions($actions, $post)
    {
        if ($post->post_type === 'ab_variation') {
            $campaign_id = get_post_meta($post->ID, '_ab_campaign_id', true);

            if ($campaign_id) {
                $actions['view_campaign'] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(admin_url('admin.php?page=pronto-abs-new&campaign_id=' . $campaign_id)),
                    __('View Campaign', 'pronto-ab')
                );
            }

            $actions['duplicate'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(wp_nonce_url(admin_url('admin.php?action=duplicate_variation&post=' . $post->ID), 'duplicate_variation_' . $post->ID)),
                __('Duplicate', 'pronto-ab')
            );
        }

        return $actions;
    }
}
