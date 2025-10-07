<?php

/**
 * A/B Test Admin Interface - Goals Management
 *
 * This trait contains all goals management methods for the admin interface.
 * Include this in your main Pronto_AB_Admin class.
 */

trait Pronto_AB_Admin_Goals
{
    /**
     * Goals management page
     */
    public function goals_page()
    {
        // Handle form submissions
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'edit' && isset($_GET['goal_id'])) {
                $this->goal_edit_page();
                return;
            } elseif ($_GET['action'] === 'new') {
                $this->goal_edit_page();
                return;
            } elseif ($_GET['action'] === 'delete' && isset($_GET['goal_id'])) {
                $this->handle_goal_delete();
            }
        }

        // Get all goals
        $goals = Pronto_AB_Goal::get_all(array(
            'orderby' => 'created_at',
            'order' => 'DESC'
        ));

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Goals', 'pronto-ab'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-goals&action=new')); ?>" class="page-title-action">
                <?php esc_html_e('Add New Goal', 'pronto-ab'); ?>
            </a>

            <?php $this->render_admin_notices(); ?>

            <div class="pronto-ab-goals-page">
                <?php if (empty($goals)): ?>
                    <div class="pronto-ab-empty-state">
                        <div class="empty-state-icon">🎯</div>
                        <h2><?php esc_html_e('No goals yet', 'pronto-ab'); ?></h2>
                        <p><?php esc_html_e('Goals help you track what matters most in your A/B tests - conversions, form submissions, clicks, revenue, and more.', 'pronto-ab'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-goals&action=new')); ?>" class="button button-primary button-large">
                            <?php esc_html_e('Create Your First Goal', 'pronto-ab'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped goals-table">
                        <thead>
                            <tr>
                                <th class="manage-column column-primary"><?php esc_html_e('Goal Name', 'pronto-ab'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Type', 'pronto-ab'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Tracking Method', 'pronto-ab'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Tracking Value', 'pronto-ab'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Campaigns', 'pronto-ab'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Total Conversions', 'pronto-ab'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Status', 'pronto-ab'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Actions', 'pronto-ab'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($goals as $goal):
                                $campaigns = $goal->get_campaigns();
                                $total_conversions = $goal->get_total_conversions();
                            ?>
                            <tr>
                                <td class="column-primary" data-colname="Goal Name">
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-goals&action=edit&goal_id=' . $goal->id)); ?>">
                                            <?php echo esc_html($goal->name); ?>
                                        </a>
                                    </strong>
                                    <?php if ($goal->description): ?>
                                        <p class="description"><?php echo esc_html($goal->description); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td data-colname="Type">
                                    <span class="goal-type-badge goal-type-<?php echo esc_attr($goal->goal_type); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $goal->goal_type))); ?>
                                    </span>
                                </td>
                                <td data-colname="Tracking Method">
                                    <?php echo esc_html(ucfirst($goal->tracking_method)); ?>
                                </td>
                                <td data-colname="Tracking Value">
                                    <?php if ($goal->tracking_value): ?>
                                        <code><?php echo esc_html($goal->tracking_value); ?></code>
                                    <?php else: ?>
                                        <span class="na">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-colname="Campaigns">
                                    <?php if (count($campaigns) > 0): ?>
                                        <a href="#" class="goal-campaigns-link" data-goal-id="<?php echo esc_attr($goal->id); ?>">
                                            <?php echo count($campaigns); ?> <?php echo _n('campaign', 'campaigns', count($campaigns), 'pronto-ab'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="na">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-colname="Total Conversions">
                                    <strong><?php echo number_format($total_conversions); ?></strong>
                                </td>
                                <td data-colname="Status">
                                    <span class="status-badge status-<?php echo esc_attr($goal->status); ?>">
                                        <?php echo esc_html(ucfirst($goal->status)); ?>
                                    </span>
                                </td>
                                <td data-colname="Actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-goals&action=edit&goal_id=' . $goal->id)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'pronto-ab'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pronto-abs-goals&action=delete&goal_id=' . $goal->id), 'delete_goal_' . $goal->id)); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this goal? This will remove it from all campaigns.', 'pronto-ab'); ?>');">
                                        <?php esc_html_e('Delete', 'pronto-ab'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .pronto-ab-empty-state {
                text-align: center;
                padding: 60px 20px;
                max-width: 600px;
                margin: 40px auto;
            }
            .empty-state-icon {
                font-size: 72px;
                margin-bottom: 20px;
            }
            .pronto-ab-empty-state h2 {
                font-size: 24px;
                margin-bottom: 12px;
            }
            .pronto-ab-empty-state p {
                font-size: 16px;
                color: #666;
                margin-bottom: 24px;
            }
            .goal-type-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .goal-type-click { background: #e3f2fd; color: #1976d2; }
            .goal-type-form { background: #f3e5f5; color: #7b1fa2; }
            .goal-type-page_view { background: #fff3e0; color: #f57c00; }
            .goal-type-custom_event { background: #e8f5e9; color: #388e3c; }
            .goal-type-revenue { background: #fce4ec; color: #c2185b; }
            .goal-type-conversion { background: #e0f2f1; color: #00796b; }
            .status-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            .status-active { background: #d4edda; color: #155724; }
            .status-inactive { background: #f8d7da; color: #721c24; }
            .na {
                color: #999;
            }
            .goals-table code {
                background: #f5f5f5;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
            }
        </style>
        <?php
    }

    /**
     * Goal edit/create page
     */
    public function goal_edit_page()
    {
        $goal_id = isset($_GET['goal_id']) ? intval($_GET['goal_id']) : 0;
        $goal = $goal_id ? Pronto_AB_Goal::find($goal_id) : new Pronto_AB_Goal();

        // Handle form submission
        if (isset($_POST['save_goal']) && check_admin_referer('pronto_ab_save_goal')) {
            $goal->name = sanitize_text_field($_POST['goal_name']);
            $goal->description = sanitize_textarea_field($_POST['goal_description']);
            $goal->goal_type = sanitize_text_field($_POST['goal_type']);
            $goal->tracking_method = sanitize_text_field($_POST['tracking_method']);
            $goal->tracking_value = sanitize_text_field($_POST['tracking_value']);
            $goal->default_value = !empty($_POST['default_value']) ? floatval($_POST['default_value']) : null;
            $goal->status = sanitize_text_field($_POST['goal_status']);

            if ($goal->save()) {
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'pronto-abs-goals',
                        'message' => $goal_id ? 'updated' : 'created'
                    ),
                    admin_url('admin.php')
                );
                wp_redirect($redirect_url);
                exit;
            }
        }

        $is_new = !$goal->id;
        ?>
        <div class="wrap">
            <h1>
                <?php echo $is_new ? esc_html__('Add New Goal', 'pronto-ab') : esc_html__('Edit Goal', 'pronto-ab'); ?>
            </h1>

            <form method="post" class="pronto-ab-goal-form">
                <?php wp_nonce_field('pronto_ab_save_goal'); ?>
                <input type="hidden" name="save_goal" value="1">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="goal-name"><?php esc_html_e('Goal Name', 'pronto-ab'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="goal-name" name="goal_name"
                                   value="<?php echo esc_attr($goal->name); ?>"
                                   class="regular-text" required>
                            <p class="description">
                                <?php esc_html_e('A descriptive name for this goal (e.g., "Newsletter Signup", "Purchase Complete")', 'pronto-ab'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="goal-description"><?php esc_html_e('Description', 'pronto-ab'); ?></label>
                        </th>
                        <td>
                            <textarea id="goal-description" name="goal_description" rows="3" class="large-text"><?php echo esc_textarea($goal->description); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Optional description to help you remember what this goal tracks', 'pronto-ab'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="goal-type"><?php esc_html_e('Goal Type', 'pronto-ab'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <select id="goal-type" name="goal_type" required>
                                <option value="conversion" <?php selected($goal->goal_type, 'conversion'); ?>>
                                    <?php esc_html_e('Conversion (General)', 'pronto-ab'); ?>
                                </option>
                                <option value="click" <?php selected($goal->goal_type, 'click'); ?>>
                                    <?php esc_html_e('Click / Button', 'pronto-ab'); ?>
                                </option>
                                <option value="form" <?php selected($goal->goal_type, 'form'); ?>>
                                    <?php esc_html_e('Form Submission', 'pronto-ab'); ?>
                                </option>
                                <option value="page_view" <?php selected($goal->goal_type, 'page_view'); ?>>
                                    <?php esc_html_e('Page View', 'pronto-ab'); ?>
                                </option>
                                <option value="custom_event" <?php selected($goal->goal_type, 'custom_event'); ?>>
                                    <?php esc_html_e('Custom Event', 'pronto-ab'); ?>
                                </option>
                                <option value="revenue" <?php selected($goal->goal_type, 'revenue'); ?>>
                                    <?php esc_html_e('Revenue Goal', 'pronto-ab'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('The type of goal you want to track', 'pronto-ab'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="tracking-method"><?php esc_html_e('Tracking Method', 'pronto-ab'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <select id="tracking-method" name="tracking_method" required>
                                <option value="manual" <?php selected($goal->tracking_method, 'manual'); ?>>
                                    <?php esc_html_e('Manual (via API)', 'pronto-ab'); ?>
                                </option>
                                <option value="selector" <?php selected($goal->tracking_method, 'selector'); ?>>
                                    <?php esc_html_e('CSS Selector', 'pronto-ab'); ?>
                                </option>
                                <option value="url" <?php selected($goal->tracking_method, 'url'); ?>>
                                    <?php esc_html_e('URL Pattern', 'pronto-ab'); ?>
                                </option>
                                <option value="auto" <?php selected($goal->tracking_method, 'auto'); ?>>
                                    <?php esc_html_e('Automatic', 'pronto-ab'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('How this goal should be tracked', 'pronto-ab'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr class="tracking-value-row">
                        <th scope="row">
                            <label for="tracking-value"><?php esc_html_e('Tracking Value', 'pronto-ab'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="tracking-value" name="tracking_value"
                                   value="<?php echo esc_attr($goal->tracking_value); ?>"
                                   class="regular-text"
                                   placeholder="e.g., .download-button or /thank-you">
                            <p class="description tracking-value-help">
                                <span class="for-selector" style="display:none;">
                                    <?php esc_html_e('Enter a CSS selector (e.g., .download-button, #signup-form)', 'pronto-ab'); ?>
                                </span>
                                <span class="for-url" style="display:none;">
                                    <?php esc_html_e('Enter a URL or path (e.g., /thank-you, /checkout/success)', 'pronto-ab'); ?>
                                </span>
                                <span class="for-manual">
                                    <?php esc_html_e('Leave empty for manual tracking via JavaScript API', 'pronto-ab'); ?>
                                </span>
                            </p>
                        </td>
                    </tr>

                    <tr class="revenue-row" style="<?php echo $goal->goal_type === 'revenue' ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="default-value"><?php esc_html_e('Default Value ($)', 'pronto-ab'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="default-value" name="default_value"
                                   value="<?php echo esc_attr($goal->default_value); ?>"
                                   step="0.01" min="0" class="small-text">
                            <p class="description">
                                <?php esc_html_e('Default monetary value for this goal (optional)', 'pronto-ab'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="goal-status"><?php esc_html_e('Status', 'pronto-ab'); ?></label>
                        </th>
                        <td>
                            <select id="goal-status" name="goal_status">
                                <option value="active" <?php selected($goal->status, 'active'); ?>>
                                    <?php esc_html_e('Active', 'pronto-ab'); ?>
                                </option>
                                <option value="inactive" <?php selected($goal->status, 'inactive'); ?>>
                                    <?php esc_html_e('Inactive', 'pronto-ab'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        <?php echo $is_new ? esc_html__('Create Goal', 'pronto-ab') : esc_html__('Update Goal', 'pronto-ab'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-goals')); ?>" class="button button-large">
                        <?php esc_html_e('Cancel', 'pronto-ab'); ?>
                    </a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Show/hide tracking value help based on method
            function updateTrackingValueHelp() {
                const method = $('#tracking-method').val();
                $('.tracking-value-help span').hide();
                $('.tracking-value-help .for-' + method).show();
            }

            $('#tracking-method').on('change', updateTrackingValueHelp);
            updateTrackingValueHelp();

            // Show/hide revenue value field based on goal type
            $('#goal-type').on('change', function() {
                if ($(this).val() === 'revenue') {
                    $('.revenue-row').show();
                } else {
                    $('.revenue-row').hide();
                }
            });
        });
        </script>

        <style>
            .required {
                color: #d63638;
            }
            .pronto-ab-goal-form .form-table th {
                width: 200px;
            }
        </style>
        <?php
    }

    /**
     * Handle goal deletion
     */
    private function handle_goal_delete()
    {
        $goal_id = intval($_GET['goal_id']);

        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_goal_' . $goal_id)) {
            wp_die(__('Security check failed', 'pronto-ab'));
        }

        $goal = Pronto_AB_Goal::find($goal_id);
        if ($goal && $goal->delete()) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'pronto-abs-goals',
                    'message' => 'deleted'
                ),
                admin_url('admin.php')
            );
            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Render campaign goals box for campaign edit page
     */
    public function render_campaign_goals_box($campaign)
    {
        if (!$campaign || !$campaign->id) {
            return;
        }

        $assigned_goals = Pronto_AB_Goal::get_by_campaign($campaign->id);
        $all_goals = Pronto_AB_Goal::get_all(array('status' => 'active'));
        $available_goals = array_filter($all_goals, function ($goal) use ($assigned_goals) {
            foreach ($assigned_goals as $assigned) {
                if ($assigned->id === $goal->id) {
                    return false;
                }
            }
            return true;
        });

        ?>
        <div class="postbox campaign-goals-box">
            <div class="postbox-header">
                <h2><?php esc_html_e('Goals', 'pronto-ab'); ?></h2>
            </div>
            <div class="inside">
                <div class="campaign-goals-list">
                    <?php if (empty($assigned_goals)): ?>
                        <p class="no-goals-message">
                            <?php esc_html_e('No goals assigned yet.', 'pronto-ab'); ?>
                        </p>
                    <?php else: ?>
                        <ul class="goals-list">
                            <?php foreach ($assigned_goals as $goal): ?>
                                <li class="goal-item" data-goal-id="<?php echo esc_attr($goal->id); ?>">
                                    <div class="goal-info">
                                        <span class="goal-name">
                                            <strong><?php echo esc_html($goal->name); ?></strong>
                                            <?php if (isset($goal->is_primary) && $goal->is_primary): ?>
                                                <span class="badge badge-primary" title="<?php esc_attr_e('Primary goal', 'pronto-ab'); ?>">
                                                    <?php esc_html_e('Primary', 'pronto-ab'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="goal-type">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $goal->goal_type))); ?>
                                        </span>
                                    </div>
                                    <div class="goal-actions">
                                        <?php if (!isset($goal->is_primary) || !$goal->is_primary): ?>
                                            <button type="button" class="button button-small make-primary-goal"
                                                    data-goal-id="<?php echo esc_attr($goal->id); ?>"
                                                    title="<?php esc_attr_e('Make primary goal', 'pronto-ab'); ?>">
                                                <?php esc_html_e('Make Primary', 'pronto-ab'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="button button-small button-link-delete remove-goal"
                                                data-goal-id="<?php echo esc_attr($goal->id); ?>"
                                                title="<?php esc_attr_e('Remove goal', 'pronto-ab'); ?>">
                                            <?php esc_html_e('Remove', 'pronto-ab'); ?>
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="add-goal-section">
                    <hr>
                    <div class="goal-selector-wrapper">
                        <select id="goal-selector" class="goal-selector" style="width: 100%; margin-bottom: 8px;">
                            <option value=""><?php esc_html_e('Select a goal...', 'pronto-ab'); ?></option>
                            <?php foreach ($available_goals as $goal): ?>
                                <option value="<?php echo esc_attr($goal->id); ?>"
                                        data-type="<?php echo esc_attr($goal->goal_type); ?>">
                                    <?php echo esc_html($goal->name); ?>
                                    (<?php echo esc_html(ucfirst(str_replace('_', ' ', $goal->goal_type))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="add-goal-btn" class="button button-secondary" style="width: 100%; margin-bottom: 4px;">
                            <?php esc_html_e('Add Goal', 'pronto-ab'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pronto-abs-goals&action=new')); ?>"
                           class="button button-link" style="width: 100%; text-align: center; display: block;">
                            <?php esc_html_e('+ Create New Goal', 'pronto-ab'); ?>
                        </a>
                    </div>
                </div>

                <p class="description" style="margin-top: 12px;">
                    <?php esc_html_e('Goals help you track what matters in your A/B test - conversions, clicks, form submissions, and more.', 'pronto-ab'); ?>
                </p>
            </div>
        </div>

        <style>
            .campaign-goals-box .inside {
                padding: 12px;
            }
            .goals-list {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            .goal-item {
                padding: 10px;
                margin-bottom: 8px;
                background: #f9f9f9;
                border: 1px solid #e5e5e5;
                border-radius: 3px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .goal-info {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .goal-name {
                font-size: 14px;
            }
            .goal-type {
                font-size: 12px;
                color: #666;
            }
            .goal-actions {
                display: flex;
                gap: 4px;
            }
            .badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                margin-left: 6px;
            }
            .badge-primary {
                background: #2271b1;
                color: white;
            }
            .no-goals-message {
                text-align: center;
                color: #666;
                padding: 20px 0;
                margin: 0;
            }
            .add-goal-section {
                margin-top: 12px;
            }
            .add-goal-section hr {
                margin: 12px 0;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            const campaignId = <?php echo intval($campaign->id); ?>;

            // Add goal to campaign
            $('#add-goal-btn').on('click', function() {
                const goalId = $('#goal-selector').val();
                if (!goalId) {
                    alert('<?php esc_html_e('Please select a goal', 'pronto-ab'); ?>');
                    return;
                }

                $.post(ajaxurl, {
                    action: 'pronto_ab_assign_goal',
                    nonce: '<?php echo wp_create_nonce('pronto_ab_ajax_nonce'); ?>',
                    campaign_id: campaignId,
                    goal_id: goalId,
                    is_primary: $('.goal-item').length === 0 // Make first goal primary
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Failed to add goal', 'pronto-ab'); ?>');
                    }
                });
            });

            // Remove goal from campaign
            $(document).on('click', '.remove-goal', function() {
                if (!confirm('<?php esc_html_e('Remove this goal from the campaign?', 'pronto-ab'); ?>')) {
                    return;
                }

                const goalId = $(this).data('goal-id');

                $.post(ajaxurl, {
                    action: 'pronto_ab_remove_goal',
                    nonce: '<?php echo wp_create_nonce('pronto_ab_ajax_nonce'); ?>',
                    campaign_id: campaignId,
                    goal_id: goalId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Failed to remove goal', 'pronto-ab'); ?>');
                    }
                });
            });

            // Make goal primary
            $(document).on('click', '.make-primary-goal', function() {
                const goalId = $(this).data('goal-id');

                $.post(ajaxurl, {
                    action: 'pronto_ab_assign_goal',
                    nonce: '<?php echo wp_create_nonce('pronto_ab_ajax_nonce'); ?>',
                    campaign_id: campaignId,
                    goal_id: goalId,
                    is_primary: true
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Failed to update goal', 'pronto-ab'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
