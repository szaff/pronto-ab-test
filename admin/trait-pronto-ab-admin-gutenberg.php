<?php

/**
 * FIXED Gutenberg Block Editor Integration for A/B Testing
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
            function_exists('get_block_editor_settings') &&
            function_exists('wp_enqueue_editor');
    }

    /**
     * Render variation with Gutenberg block editor
     */
    public function render_variation_with_gutenberg($variation, $index)
    {
        if (!$this->is_gutenberg_available()) {
            // Fallback to wp_editor if Gutenberg isn't available
            if (method_exists($this, 'render_variation_with_wp_editor')) {
                $this->render_variation_with_wp_editor($variation, $index);
            }
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

                            <!-- Enhanced fallback textarea -->
                            <div class="gutenberg-fallback" style="display: none;">
                                <div class="fallback-header">
                                    <strong>HTML Content Editor</strong>
                                    <small>(Block editor not available)</small>
                                </div>
                                <textarea name="variations[<?php echo esc_attr($index); ?>][content_fallback]"
                                    rows="12" class="large-text code variation-fallback-content"
                                    placeholder="Enter your HTML content here..."><?php echo esc_textarea($variation->content ?? ''); ?></textarea>
                                <div class="fallback-tools">
                                    <button type="button" class="button preview-fallback-content">Preview HTML</button>
                                    <button type="button" class="button toggle-html-help">Show HTML Help</button>
                                </div>
                                <div class="html-help" style="display: none;">
                                    <p><strong>Common HTML tags:</strong></p>
                                    <code>&lt;h2&gt;Heading&lt;/h2&gt;</code><br>
                                    <code>&lt;p&gt;Paragraph&lt;/p&gt;</code><br>
                                    <code>&lt;strong&gt;Bold&lt;/strong&gt;</code><br>
                                    <code>&lt;a href="url"&gt;Link&lt;/a&gt;</code><br>
                                    <code>&lt;img src="url" alt="description"&gt;</code>
                                </div>
                            </div>

                            <div class="gutenberg-editor-tools">
                                <button type="button" class="button preview-variation-content">
                                    <?php esc_html_e('Preview Content', 'pronto-ab'); ?>
                                </button>
                                <button type="button" class="button save-blocks">
                                    <?php esc_html_e('Save Blocks', 'pronto-ab'); ?>
                                </button>
                                <button type="button" class="button show-fallback-toggle" style="display: none;">
                                    <?php esc_html_e('Switch to HTML Editor', 'pronto-ab'); ?>
                                </button>
                            </div>

                            <p class="description">
                                <?php esc_html_e('Use the block editor to create rich content for this variation. If the block editor fails to load, you can use the HTML editor as a fallback.', 'pronto-ab'); ?>
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
     * CRITICAL FIX: Ensure all required Gutenberg stores are loaded
     */
    public function enqueue_gutenberg_assets($hook)
    {
        // Only load on campaign edit pages
        if (strpos($hook, 'pronto-abs') === false || !$this->is_campaign_edit_page()) {
            return;
        }

        if (!$this->is_gutenberg_available()) {
            return;
        }

        // CRITICAL: Load WordPress editor with ALL dependencies
        wp_enqueue_editor();
        wp_enqueue_media();

        // ENHANCED: Load ALL block editor related scripts to ensure stores exist
        $all_editor_scripts = array(
            // Core WordPress
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-components',
            'wp-block-editor',
            'wp-editor',
            'wp-edit-post',
            'wp-core-data',
            'wp-notices',
            'wp-viewport',

            // CRITICAL: These ensure keyboard shortcuts store exists
            'wp-keyboard-shortcuts',
            'wp-interface',
            'wp-preferences',

            // Additional stores that might be needed
            'wp-rich-text',
            'wp-compose',
            'wp-primitives',
            'wp-html-entities',
            'wp-format-library'
        );

        foreach ($all_editor_scripts as $script) {
            wp_enqueue_script($script);
        }

        // Load block library with styles
        wp_enqueue_script('wp-block-library');
        wp_enqueue_style('wp-block-library');
        wp_enqueue_style('wp-block-editor');

        // CRITICAL: Also load edit-post styles which include keyboard shortcuts
        wp_enqueue_style('wp-edit-post');
        wp_enqueue_script('wp-edit-post');

        // Load our script AFTER all dependencies
        wp_enqueue_script(
            'pronto-ab-gutenberg',
            PAB_ASSETS_URL . 'js/pronto-ab-gutenberg.js',
            $all_editor_scripts, // All dependencies
            PAB_VERSION,
            true
        );

        // Enhanced localization
        wp_localize_script('pronto-ab-gutenberg', 'prontoABGutenberg', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pronto_ab_ajax_nonce'),
            'editorSettings' => $this->get_gutenberg_editor_settings(),
            'allowedBlocks' => $this->get_allowed_blocks(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'wpVersion' => get_bloginfo('version'),
            'strings' => array(
                'loading' => __('Loading block editor...', 'pronto-ab'),
                'failed' => __('Block editor failed to load', 'pronto-ab'),
                'fallback' => __('Using simplified editor instead', 'pronto-ab'),
                'save_success' => __('Content saved successfully', 'pronto-ab'),
                'save_error' => __('Failed to save content', 'pronto-ab'),
            )
        ));

        // DEBUGGING: Add script to check what's loaded
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_add_inline_script('pronto-ab-gutenberg', '
            console.log("🔍 Gutenberg Dependencies Check:", {
                "wp-keyboard-shortcuts": typeof wp?.keyboardShortcuts,
                "wp-interface": typeof wp?.interface,
                "wp-preferences": typeof wp?.preferences,
                "wp-block-editor": typeof wp?.blockEditor,
                "wp-data": typeof wp?.data,
                "allStores": wp?.data ? Object.keys(wp.data.select("core/data").getSelectors()) : "N/A"
            });
        ', 'before');
        }

        error_log("Pronto A/B: Enhanced Gutenberg assets loaded with all dependencies");
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
     * FIXED: Get enhanced Gutenberg editor settings
     */
    private function get_gutenberg_editor_settings()
    {
        // Get default editor settings with error handling
        $editor_settings = array();

        try {
            if (function_exists('get_block_editor_settings')) {
                $editor_settings = get_block_editor_settings(array(), null);
            }
        } catch (Exception $e) {
            error_log("Pronto A/B: Error getting block editor settings: " . $e->getMessage());
        }

        // Enhanced settings for A/B test variations
        $enhanced_settings = array_merge($editor_settings, array(
            'bodyPlaceholder' => __('Start creating your variation content...', 'pronto-ab'),
            'titlePlaceholder' => __('Variation content', 'pronto-ab'),
            'hasFixedToolbar' => true,
            'hasReducedUI' => true,
            'focusMode' => false,
            'distractionFree' => false,
            'showIconLabels' => false,
            'mediaUpload' => current_user_can('upload_files'),
            'allowedBlockTypes' => $this->get_allowed_blocks(),
            'disableCustomColors' => false,
            'disableCustomFontSizes' => false,
            'enableCustomLineHeight' => true,
            'enableCustomSpacing' => true,
            'supportsLayout' => false,
            'maxWidth' => 'none',
        ));

        return $enhanced_settings;
    }

    /**
     * Get allowed blocks for variations
     */
    private function get_allowed_blocks()
    {
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
     * ENHANCED: AJAX handler for saving Gutenberg blocks
     */
    public function ajax_save_gutenberg_blocks()
    {
        // Security checks
        if (!check_ajax_referer('pronto_ab_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'pronto-ab')
            ));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'pronto-ab')
            ));
        }

        // Get and validate input
        $variation_id = intval($_POST['variation_id'] ?? 0);
        $blocks_content = wp_kses_post($_POST['content'] ?? '');
        $blocks_data = $_POST['blocks'] ?? '';

        // Validate blocks data
        if (!empty($blocks_data)) {
            $blocks_data = wp_unslash($blocks_data);
            $decoded_blocks = json_decode($blocks_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array(
                    'message' => __('Invalid blocks data format', 'pronto-ab')
                ));
            }
        }

        if ($variation_id) {
            $variation = Pronto_AB_Variation::find($variation_id);
            if ($variation) {
                $variation->content = $blocks_content;

                // Store blocks data as meta if needed for advanced features
                if (!empty($blocks_data)) {
                    $variation->blocks_data = $blocks_data;
                }

                if ($variation->save()) {
                    wp_send_json_success(array(
                        'message' => __('Variation blocks saved successfully', 'pronto-ab'),
                        'content' => $blocks_content,
                        'variation_id' => $variation_id
                    ));
                } else {
                    wp_send_json_error(array(
                        'message' => __('Failed to save variation to database', 'pronto-ab')
                    ));
                }
            } else {
                wp_send_json_error(array(
                    'message' => __('Variation not found', 'pronto-ab')
                ));
            }
        }

        wp_send_json_error(array(
            'message' => __('No variation ID provided', 'pronto-ab')
        ));
    }

    /**
     * ENHANCED: Register REST API endpoints for block editor
     */
    public function register_gutenberg_endpoints()
    {
        // Get variation blocks
        register_rest_route('pronto-ab/v1', '/variation/(?P<id>\d+)/blocks', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_variation_blocks'),
            'permission_callback' => array($this, 'rest_permission_check'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));

        // Save variation blocks
        register_rest_route('pronto-ab/v1', '/variation/(?P<id>\d+)/blocks', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_save_variation_blocks'),
            'permission_callback' => array($this, 'rest_permission_check'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'content' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_kses_post',
                ),
                'blocks' => array(
                    'sanitize_callback' => function ($param) {
                        return wp_unslash($param);
                    }
                ),
            ),
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
            return new WP_Error('variation_not_found', __('Variation not found', 'pronto-ab'), array('status' => 404));
        }

        try {
            $blocks = parse_blocks($variation->content);
            $blocks_data = json_decode($variation->blocks_data ?? '[]', true);

            return rest_ensure_response(array(
                'content' => $variation->content,
                'blocks' => $blocks,
                'blocks_data' => $blocks_data,
                'variation_id' => $variation_id
            ));
        } catch (Exception $e) {
            return new WP_Error('parse_error', __('Failed to parse blocks', 'pronto-ab'), array('status' => 500));
        }
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
            return new WP_Error('variation_not_found', __('Variation not found', 'pronto-ab'), array('status' => 404));
        }

        try {
            $variation->content = $content;
            if ($blocks_data) {
                // Validate JSON before saving
                $decoded = json_decode($blocks_data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $variation->blocks_data = $blocks_data;
                }
            }

            if ($variation->save()) {
                return rest_ensure_response(array(
                    'success' => true,
                    'content' => $content,
                    'message' => __('Variation saved successfully', 'pronto-ab')
                ));
            } else {
                return new WP_Error('save_failed', __('Failed to save variation', 'pronto-ab'), array('status' => 500));
            }
        } catch (Exception $e) {
            return new WP_Error('save_error', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * REST API permission check
     */
    public function rest_permission_check()
    {
        return current_user_can('manage_options');
    }

    /**
     * ENHANCED: Debug WordPress script loading
     */
    public function debug_wp_scripts($hook)
    {
        if (strpos($hook, 'pronto-abs') === false) {
            return;
        }

        // Only debug if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        global $wp_scripts;

        $required_scripts = array(
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-block-editor',
            'wp-components',
            'wp-editor',
            'wp-block-library',
            'wp-core-data'
        );

        $debug_info = array();

        foreach ($required_scripts as $script) {
            $registered = isset($wp_scripts->registered[$script]);
            $enqueued = in_array($script, $wp_scripts->queue);

            $debug_info[$script] = array(
                'registered' => $registered,
                'enqueued' => $enqueued,
                'src' => $registered ? $wp_scripts->registered[$script]->src : 'N/A'
            );

            error_log("Pronto A/B Debug: Script '$script' - Registered: " . ($registered ? 'YES' : 'NO') . ", Enqueued: " . ($enqueued ? 'YES' : 'NO'));
        }

        // Add debug info to page
        add_action('admin_footer', function () use ($debug_info) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<script>console.log("Pronto A/B Script Debug:", ' . json_encode($debug_info) . ');</script>';
            }
        });
    }

    /**
     * Handle dynamic editor creation for new variations (AJAX)
     */
    public function ajax_create_variation_editor()
    {
        check_ajax_referer('pronto_ab_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'pronto-ab'));
        }

        $variation_index = intval($_POST['variation_index'] ?? 0);

        ob_start();

        // Create empty variation object for rendering
        $variation = (object) array(
            'id' => '',
            'name' => 'Variation ' . chr(65 + $variation_index),
            'content' => '',
            'is_control' => false,
            'weight_percentage' => 50
        );

        $this->render_variation_with_gutenberg($variation, $variation_index);

        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'variation_index' => $variation_index
        ));
    }
}
