<?php

/**
 * Gutenberg Block Editor Integration for A/B Testing
 * Save as: admin/trait-pronto-ab-admin-gutenberg.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Pronto_AB_Admin_Gutenberg
{
    /**
     * Check if Gutenberg is available
     */
    private function is_gutenberg_available()
    {
        return function_exists('register_block_type') &&
            function_exists('get_block_editor_settings');
    }

    /**
     * Render variation with Gutenberg block editor
     */
    public function render_variation_with_gutenberg($variation, $index)
    {
        if (!$this->is_gutenberg_available()) {
            // Fallback to wp_editor if Gutenberg isn't available
            $this->render_variation_with_wp_editor($variation, $index);
            return;
        }

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
                <!-- Hidden form fields -->
                <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][id]"
                    value="<?php echo esc_attr($variation->id ?? ''); ?>">
                <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][is_control]"
                    value="<?php echo ($variation->is_control ?? false) ? '1' : '0'; ?>">
                <input type="hidden" name="variations[<?php echo esc_attr($index); ?>][content]"
                    class="variation-content-input"
                    value="<?php echo esc_attr($variation->content ?? ''); ?>">

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
                            <label for="variation_editor_<?php echo esc_attr($index); ?>">
                                <?php esc_html_e('Content', 'pronto-ab'); ?>
                            </label>
                        </th>
                        <td>
                            <!-- Gutenberg Editor Container -->
                            <div class="pronto-ab-gutenberg-editor"
                                id="variation_editor_<?php echo esc_attr($index); ?>"
                                data-variation-index="<?php echo esc_attr($index); ?>"
                                data-initial-content="<?php echo esc_attr($variation->content ?? ''); ?>">
                                <div class="gutenberg-loading">
                                    <div class="spinner is-active"></div>
                                    <p><?php esc_html_e('Loading block editor...', 'pronto-ab'); ?></p>
                                </div>
                            </div>

                            <!-- Fallback textarea (hidden by default) -->
                            <div class="gutenberg-fallback" style="display: none;">
                                <textarea name="variations[<?php echo esc_attr($index); ?>][content_fallback]"
                                    rows="8" class="large-text code"><?php echo esc_textarea($variation->content ?? ''); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Block editor could not be loaded. Using fallback editor.', 'pronto-ab'); ?>
                                </p>
                            </div>

                            <div class="gutenberg-editor-tools">
                                <button type="button" class="button preview-variation-content">
                                    <?php esc_html_e('Preview Content', 'pronto-ab'); ?>
                                </button>
                                <button type="button" class="button toggle-code-editor">
                                    <?php esc_html_e('Code Editor', 'pronto-ab'); ?>
                                </button>
                                <button type="button" class="button save-blocks">
                                    <?php esc_html_e('Save Blocks', 'pronto-ab'); ?>
                                </button>
                            </div>

                            <p class="description">
                                <?php esc_html_e('Use the block editor to create rich content for this variation.', 'pronto-ab'); ?>
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
     * Enqueue Gutenberg block editor assets
     */
    public function enqueue_gutenberg_assets($hook)
    {
        error_log("Pronto A/B Debug: enqueue_gutenberg_assets called with hook: " . $hook);

        if (strpos($hook, 'pronto-abs') === false || !$this->is_campaign_edit_page()) {
            error_log("Pronto A/B Debug: Early exit - hook or page check failed");
            return;
        }

        error_log("Pronto A/B Debug: Loading Gutenberg assets");

        // Load WordPress block editor dependencies
        $dependencies = array(
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-block-editor',
            'wp-components'
        );

        // Enqueue WordPress scripts
        foreach ($dependencies as $dep) {
            wp_enqueue_script($dep);
        }

        // Load block library
        wp_enqueue_script('wp-block-library');
        wp_enqueue_style('wp-block-library');

        wp_enqueue_script(
            'pronto-ab-gutenberg',
            PAB_ASSETS_URL . 'js/pronto-ab-gutenberg.js',
            $dependencies,
            PAB_VERSION,
            true
        );

        // Simple localization
        wp_localize_script('pronto-ab-gutenberg', 'prontoABGutenberg', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pronto_ab_ajax_nonce'),
            'debug' => true
        ));

        error_log("Pronto A/B Debug: Gutenberg script enqueued");
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

    /**
     * Get Gutenberg editor settings
     */
    private function get_gutenberg_editor_settings()
    {
        // Get default editor settings
        $editor_settings = get_block_editor_settings(array(), null);

        // Customize settings for A/B test variations
        $editor_settings['bodyPlaceholder'] = __('Start creating your variation content...', 'pronto-ab');
        $editor_settings['titlePlaceholder'] = __('Variation content', 'pronto-ab');

        // Simplified interface
        $editor_settings['hasFixedToolbar'] = true;
        $editor_settings['hasReducedUI'] = false;
        $editor_settings['focusMode'] = false;
        $editor_settings['distractionFree'] = false;

        // Disable some features we don't need
        $editor_settings['__experimentalSetIsInserterOpened'] = false;
        $editor_settings['showIconLabels'] = false;

        // Enable media uploads
        $editor_settings['mediaUpload'] = current_user_can('upload_files');

        return $editor_settings;
    }

    /**
     * Get allowed blocks for variations
     */
    private function get_allowed_blocks()
    {
        // Allow most common blocks, but exclude some that don't make sense in variations
        $allowed_blocks = array(
            // Text blocks
            'core/paragraph',
            'core/heading',
            'core/list',
            'core/list-item',
            'core/quote',
            'core/code',
            'core/preformatted',
            'core/pullquote',
            'core/table',
            'core/verse',

            // Media blocks
            'core/image',
            'core/gallery',
            'core/video',
            'core/audio',
            'core/cover',
            'core/file',
            'core/media-text',

            // Design blocks
            'core/buttons',
            'core/button',
            'core/columns',
            'core/column',
            'core/group',
            'core/row',
            'core/stack',
            'core/spacer',
            'core/separator',

            // Widgets
            'core/shortcode',
            'core/html',
            'core/embed',

            // Formatting
            'core/more',
            'core/nextpage'
        );

        return apply_filters('pronto_ab_allowed_blocks', $allowed_blocks);
    }

    /**
     * AJAX handler for saving Gutenberg blocks
     */
    public function ajax_save_gutenberg_blocks()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $variation_id = intval($_POST['variation_id'] ?? 0);
        $blocks_content = wp_kses_post($_POST['content'] ?? '');
        $blocks_data = json_decode(wp_unslash($_POST['blocks'] ?? '[]'), true);

        if ($variation_id) {
            $variation = Pronto_AB_Variation::find($variation_id);
            if ($variation) {
                $variation->content = $blocks_content;

                // Store blocks data as meta if needed for advanced features
                if (!empty($blocks_data)) {
                    $variation->blocks_data = wp_json_encode($blocks_data);
                }

                if ($variation->save()) {
                    wp_send_json_success(array(
                        'message' => __('Variation blocks saved', 'pronto-ab'),
                        'content' => $blocks_content
                    ));
                }
            }
        }

        wp_send_json_error(__('Failed to save variation blocks', 'pronto-ab'));
    }

    /**
     * Register REST API endpoints for block editor
     */
    public function register_gutenberg_endpoints()
    {
        register_rest_route('pronto-ab/v1', '/variation/(?P<id>\d+)/blocks', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_variation_blocks'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));

        register_rest_route('pronto-ab/v1', '/variation/(?P<id>\d+)/blocks', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_save_variation_blocks'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
    }

    /**
     * REST API: Get variation blocks
     */
    public function rest_get_variation_blocks($request)
    {
        $variation_id = intval($request['id']);
        $variation = Pronto_AB_Variation::find($variation_id);

        if (!$variation) {
            return new WP_Error('variation_not_found', 'Variation not found', array('status' => 404));
        }

        $blocks = parse_blocks($variation->content);

        return rest_ensure_response(array(
            'content' => $variation->content,
            'blocks' => $blocks,
            'blocks_data' => json_decode($variation->blocks_data ?? '[]', true)
        ));
    }

    /**
     * REST API: Save variation blocks
     */
    public function rest_save_variation_blocks($request)
    {
        $variation_id = intval($request['id']);
        $content = wp_kses_post($request->get_param('content'));
        $blocks_data = $request->get_param('blocks');

        $variation = Pronto_AB_Variation::find($variation_id);
        if (!$variation) {
            return new WP_Error('variation_not_found', 'Variation not found', array('status' => 404));
        }

        $variation->content = $content;
        if ($blocks_data) {
            $variation->blocks_data = wp_json_encode($blocks_data);
        }

        if ($variation->save()) {
            return rest_ensure_response(array(
                'success' => true,
                'content' => $content
            ));
        }

        return new WP_Error('save_failed', 'Failed to save variation', array('status' => 500));
    }

    /**
     * REST API permission check
     */
    public function rest_permission_check()
    {
        return current_user_can('manage_options');
    }

    /**
     * Debug WordPress script loading - ADD THIS METHOD FOR DEBUGGING
     */
    public function debug_wp_scripts($hook)
    {
        if (strpos($hook, 'pronto-abs') === false) {
            return;
        }

        // Log which scripts are registered and enqueued
        global $wp_scripts;

        $required_scripts = array(
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-block-editor',
            'wp-components',
            'wp-blocks',
            'wp-edit-post'
        );

        foreach ($required_scripts as $script) {
            $registered = isset($wp_scripts->registered[$script]);
            $enqueued = in_array($script, $wp_scripts->queue);

            error_log("Pronto A/B Debug: Script '$script' - Registered: " . ($registered ? 'YES' : 'NO') . ", Enqueued: " . ($enqueued ? 'YES' : 'NO'));
        }
    }
}
