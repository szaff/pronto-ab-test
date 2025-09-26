<?php

/**
 * WordPress Editor Integration Trait
 * Save this as: admin/trait-pronto-ab-admin-wp-editor.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Pronto_AB_Admin_WP_Editor
{
    /**
     * Render variation with WordPress built-in editor
     */
    public function render_variation_with_wp_editor($variation, $index)
    {
?>
        <div class="pronto-ab-variation" data-index="<?php echo esc_attr($index); ?>">
            <div class="variation-header">
                <h4 class="variation-title-display">
                    <span class="variation-name-display"><?php echo esc_html($variation->name ?? 'Variation ' . ($index + 1)); ?></span>
                    <?php if ($variation->is_control ?? false): ?>
                        <span class="control-badge"><?php esc_html_e('Control', 'pronto-ab'); ?></span>
                    <?php endif; ?>
                </h4>
                <div class="variation-actions">
                    <button type="button" class="button-link preview-variation" title="<?php esc_attr_e('Preview', 'pronto-ab'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <?php if (!($variation->is_control ?? false)): ?>
                        <button type="button" class="button-link-delete remove-variation">
                            <?php esc_html_e('Remove', 'pronto-ab'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="variation-content">
                <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][id]"
                    value="<?php echo esc_attr($variation->id ?? ''); ?>">
                <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][is_control]"
                    value="<?php echo ($variation->is_control ?? false) ? '1' : '0'; ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="variation_name_<?php echo esc_attr($index); ?>">
                                <?php esc_html_e('Name', 'pronto-ab'); ?> <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="variation_name_<?php echo esc_attr($index); ?>"
                                name="variations[<?php echo esc_attr($index); ?>][name]"
                                value="<?php echo esc_attr($variation->name ?? ''); ?>"
                                class="regular-text variation-name" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="variation_content_<?php echo esc_attr($index); ?>">
                                <?php esc_html_e('Content', 'pronto-ab'); ?>
                            </label>
                        </th>
                        <td>
                            <div class="variation-wp-editor">
                                <?php
                                $editor_id = 'variation_content_' . $index;
                                $editor_settings = array(
                                    'textarea_name' => 'variations[' . $index . '][content]',
                                    'textarea_rows' => 10,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'tinymce' => array(
                                        'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,justifyleft,justifycenter,justifyright,|,link,unlink,|,spellchecker,fullscreen,wp_adv',
                                        'toolbar2' => 'formatselect,fontsize,forecolor,backcolor,|,pastetext,removeformat,charmap,|,outdent,indent,|,undo,redo,wp_help',
                                        'resize' => true,
                                        'wp_autoresize_on' => true,
                                        'add_unload_trigger' => false
                                    ),
                                    'quicktags' => array(
                                        'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close'
                                    ),
                                    'drag_drop_upload' => true
                                );

                                wp_editor(
                                    $variation->content ?? '',
                                    $editor_id,
                                    $editor_settings
                                );
                                ?>
                            </div>
                            <div class="variation-editor-tools">
                                <button type="button" class="button preview-variation-content">
                                    <?php esc_html_e('Preview Content', 'pronto-ab'); ?>
                                </button>
                                <button type="button" class="button toggle-editor-mode" data-editor-id="<?php echo esc_attr($editor_id); ?>">
                                    <?php esc_html_e('Text/Visual', 'pronto-ab'); ?>
                                </button>
                            </div>
                            <p class="description">
                                <?php esc_html_e('Create your variation content using the rich text editor above.', 'pronto-ab'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="variation_weight_<?php echo esc_attr($index); ?>">
                                <?php esc_html_e('Weight %', 'pronto-ab'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="variation_weight_<?php echo esc_attr($index); ?>"
                                name="variations[<?php echo esc_attr($index); ?>][weight_percentage]"
                                value="<?php echo esc_attr($variation->weight_percentage ?? 50); ?>"
                                min="0" max="100" step="0.01" class="small-text variation-weight">%
                            <div class="weight-slider" style="margin-top: 8px; width: 200px;"></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
<?php
    }

    /**
     * Enqueue assets for wp_editor integration
     */
    public function enqueue_wp_editor_assets($hook)
    {
        if (strpos($hook, 'pronto-abs') === false || !$this->is_campaign_edit_page()) {
            return;
        }

        // Enqueue WordPress editor assets
        wp_enqueue_editor();
        wp_enqueue_media();

        // Custom JavaScript for dynamic editor management
        wp_enqueue_script(
            'pronto-ab-wp-editor',
            PAB_ASSETS_URL . 'js/pronto-ab-wp-editor.js',
            array('jquery', 'editor'),
            PAB_VERSION,
            true
        );

        wp_localize_script('pronto-ab-wp-editor', 'prontoABWPEditor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pronto_ab_ajax_nonce'),
            'editorSettings' => $this->get_wp_editor_settings()
        ));
    }

    /**
     * Get WordPress editor settings
     */
    private function get_wp_editor_settings()
    {
        return array(
            'textarea_rows' => 10,
            'media_buttons' => true,
            'teeny' => false,
            'tinymce' => array(
                'toolbar1' => 'bold,italic,underline,|,bullist,numlist,blockquote,|,justifyleft,justifycenter,justifyright,|,link,unlink,|,wp_adv',
                'toolbar2' => 'formatselect,forecolor,backcolor,|,pastetext,removeformat,|,outdent,indent,|,undo,redo',
                'resize' => true,
                'wp_autoresize_on' => true,
                'add_unload_trigger' => false
            ),
            'quicktags' => array(
                'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code'
            )
        );
    }

    /**
     * Handle dynamic editor creation for new variations
     */
    public function ajax_create_variation_editor()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $variation_index = intval($_POST['variation_index'] ?? 0);

        // Create a temporary buffer to capture wp_editor output
        ob_start();

        $editor_id = 'variation_content_' . $variation_index;
        $editor_settings = array(
            'textarea_name' => 'variations[' . $variation_index . '][content]',
            'textarea_rows' => 10,
            'media_buttons' => true,
            'teeny' => false,
            'tinymce' => $this->get_wp_editor_settings()['tinymce'],
            'quicktags' => $this->get_wp_editor_settings()['quicktags'],
            'drag_drop_upload' => true
        );

        wp_editor('', $editor_id, $editor_settings);

        $editor_html = ob_get_clean();

        wp_send_json_success(array(
            'editor_html' => $editor_html,
            'editor_id' => $editor_id
        ));
    }

    /**
     * Check if we're on a campaign edit page
     */
    private function is_campaign_edit_page()
    {
        return isset($_GET['page']) &&
            ($_GET['page'] === 'pronto-abs-new' ||
                (isset($_GET['campaign_id']) && $_GET['page'] === 'pronto-abs-new'));
    }
}
