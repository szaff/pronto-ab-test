/**
 * Pronto A/B Testing - Admin JavaScript
 *
 * Handles admin interface functionality including campaign management,
 * variation editing, and dynamic form interactions.
 * UPDATED FOR GUTENBERG BLOCK EDITOR INTEGRATION
 */

(function ($) {
  "use strict";

  // Check if abTestAjax is available (localized from PHP)
  if (typeof abTestAjax === "undefined") {
    console.info(
      "Pronto A/B Admin: Not on a campaign page, skipping admin JavaScript initialization."
    );
    return;
  }

  /**
   * Main Admin object
   */
  const ProntoABAdmin = {
    // Configuration
    config: {
      ajaxUrl: abTestAjax.ajax_url,
      nonce: abTestAjax.nonce,
      strings: abTestAjax.strings || {},
    },

    // State tracking
    variationIndex: 0,
    isInitialized: false,

    /**
     * Initialize admin functionality
     */
    init: function () {
      if (this.isInitialized) {
        return;
      }

      console.log("Initializing Pronto A/B Admin");

      // Initialize different admin page functionalities
      this.initCampaignsList();
      this.initCampaignEditor();
      this.initPostTypeSelector();
      this.initVariationEditor();
      this.initFormValidation();
      this.initBulkActions();
      this.initRealTimeStats();

      this.isInitialized = true;
      console.log("Pronto A/B Admin initialized successfully");
    },

    /**
     * Campaigns list page functionality
     */
    initCampaignsList: function () {
      if (!$(".pronto-ab-campaigns-table").length) {
        return;
      }

      // Enhanced delete confirmation
      $('.row-actions a[href*="action=delete"]').on("click", function (e) {
        const campaignName = $(this)
          .closest("tr")
          .find("td:first strong a")
          .text();
        const message = ProntoABAdmin.config.strings.confirm_delete.replace(
          "%s",
          campaignName
        );

        if (!confirm(message)) {
          e.preventDefault();
          return false;
        }
      });

      // Quick status toggle
      $(".campaign-status-toggle").on("click", function (e) {
        e.preventDefault();
        const $button = $(this);
        const campaignId = $button.data("campaign-id");
        const newStatus = $button.data("status");

        ProntoABAdmin.toggleCampaignStatus(campaignId, newStatus, $button);
      });

      // Initialize sortable columns
      this.initSortableColumns();

      // Initialize search/filter
      this.initCampaignSearch();
    },

    /**
     * Campaign editor functionality
     */
    initCampaignEditor: function () {
      if (!$("#pronto-ab-campaign-form").length) {
        return;
      }

      // Auto-save draft functionality
      this.initAutoSave();

      // Form change tracking
      this.initChangeTracking();

      // Date validation
      this.initDateValidation();

      // Traffic split visualization
      this.initTrafficSplitVisualizer();

      console.log("Campaign editor initialized");
    },

    /**
     * Post type selector functionality
     */
    initPostTypeSelector: function () {
      const $postTypeSelect = $("#target_post_type");
      const $postSelect = $("#target_post_id");

      if (!$postTypeSelect.length || !$postSelect.length) {
        return;
      }

      $postTypeSelect.on("change", function () {
        const postType = $(this).val();

        if (!postType) {
          $postSelect
            .hide()
            .html(
              '<option value="">' +
                (ProntoABAdmin.config.strings.select_content ||
                  "Select content") +
                "</option>"
            );
          return;
        }

        // Show loading state
        $postSelect
          .prop("disabled", true)
          .html(
            '<option value="">' +
              (ProntoABAdmin.config.strings.loading || "Loading...") +
              "</option>"
          );

        // Fetch posts via AJAX
        $.post(ProntoABAdmin.config.ajaxUrl, {
          action: "pronto_ab_get_posts",
          post_type: postType,
          nonce: ProntoABAdmin.config.nonce,
        })
          .done(function (response) {
            if (response.success && response.data) {
              let options =
                '<option value="">' +
                (ProntoABAdmin.config.strings.select_content ||
                  "Select content") +
                "</option>";

              $.each(response.data, function (i, post) {
                options += `<option value="${post.ID}">${post.post_title}</option>`;
              });

              $postSelect.html(options).prop("disabled", false).show();
            } else {
              ProntoABAdmin.showNotice("error", "Failed to load posts");
              $postSelect
                .html(
                  '<option value="">' +
                    (ProntoABAdmin.config.strings.error_loading ||
                      "Error loading posts") +
                    "</option>"
                )
                .prop("disabled", false);
            }
          })
          .fail(function () {
            ProntoABAdmin.showNotice("error", "AJAX request failed");
            $postSelect
              .html(
                '<option value="">' +
                  (ProntoABAdmin.config.strings.error || "Error occurred") +
                  "</option>"
              )
              .prop("disabled", false);
          });
      });
    },

    /**
     * Variation editor functionality - UPDATED FOR GUTENBERG
     */
    initVariationEditor: function () {
      if (!$("#pronto-ab-variations").length) {
        return;
      }

      // Set initial variation index
      this.variationIndex = $(".pronto-ab-variation").length;

      // Add variation button
      $("#add-variation").on("click", function (e) {
        e.preventDefault();
        ProntoABAdmin.addVariation();
      });

      // Remove variation buttons (delegated)
      $(document).on("click", ".remove-variation", function (e) {
        e.preventDefault();
        ProntoABAdmin.removeVariation($(this));
      });

      // Weight percentage validation
      $(document).on("change", ".variation-weight", function () {
        ProntoABAdmin.validateVariationWeights();
      });

      // Initialize sortable variations
      this.initSortableVariations();

      console.log("Variation editor initialized");
    },

    /**
     * Add new variation - UPDATED FOR GUTENBERG
     */
    addVariation: function () {
      const template = this.getVariationTemplate(this.variationIndex);
      $("#pronto-ab-variations").append(template);

      // Initialize components for new variation
      this.initVariationComponents(this.variationIndex);

      this.variationIndex++;

      // Update variation weights
      this.redistributeVariationWeights();

      // Show animation
      $(".pronto-ab-variation").last().hide().slideDown(300);

      console.log("Added variation", this.variationIndex - 1);
    },

    /**
     * Remove variation - UPDATED FOR GUTENBERG
     */
    removeVariation: function ($button) {
      const $variation = $button.closest(".pronto-ab-variation");
      const variationIndex = $variation.data("index");
      const variationName =
        $variation.find(".variation-name").val() || "Unnamed";

      if (!confirm(`Are you sure you want to remove "${variationName}"?`)) {
        return;
      }

      // Clean up Gutenberg editor if it exists
      if (window.prontoABGutenbergManager && variationIndex !== undefined) {
        window.prontoABGutenbergManager.destroyEditor(variationIndex);
      }

      $variation.slideUp(300, function () {
        $(this).remove();
        ProntoABAdmin.redistributeVariationWeights();
        ProntoABAdmin.updateVariationIndexes();
      });

      console.log("Removed variation:", variationName);
    },

    /**
     * Get variation template HTML - UPDATED FOR GUTENBERG
     */
    getVariationTemplate: function (index) {
      return `
                <div class="pronto-ab-variation" data-index="${index}">
                    <div class="variation-header">
                        <h4>
                            <span class="variation-title">Variation ${String.fromCharCode(
                              65 + index
                            )}</span>
                            <span class="variation-stats" style="display: none;">
                                <small>0 impressions, 0 conversions (0%)</small>
                            </span>
                        </h4>
                        <div class="variation-actions">
                            <button type="button" class="button-link preview-variation" title="Preview">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button type="button" class="button-link-delete remove-variation">
                                ${this.config.strings.remove || "Remove"}
                            </button>
                        </div>
                    </div>

                    <div class="variation-content">
                        <input type="hidden" name="variations[${index}][id]" value="">
                        <input type="hidden" name="variations[${index}][is_control]" value="0">
                        <input type="hidden" name="variations[${index}][content]" 
                               class="variation-content-input" value="">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="variation_name_${index}">Name</label>
                                </th>
                                <td>
                                    <input type="text" id="variation_name_${index}" 
                                           name="variations[${index}][name]" 
                                           value="Variation ${String.fromCharCode(
                                             65 + index
                                           )}"
                                           class="regular-text variation-name" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="variation_editor_${index}">Content</label>
                                </th>
                                <td>
                                    <!-- Gutenberg Editor Container -->
                                    <div class="pronto-ab-gutenberg-editor" 
                                         id="variation_editor_${index}"
                                         data-variation-index="${index}"
                                         data-initial-content="">
                                        <div class="gutenberg-loading">
                                            <div class="spinner is-active"></div>
                                            <p>Loading block editor...</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Fallback textarea (hidden by default) -->
                                    <div class="gutenberg-fallback" style="display: none;">
                                        <textarea name="variations[${index}][content_fallback]"
                                                 rows="8" class="large-text code"></textarea>
                                        <p class="description">
                                            Block editor could not be loaded. Using fallback editor.
                                        </p>
                                    </div>

                                    <div class="gutenberg-editor-tools">
                                        <button type="button" class="button preview-variation-content">
                                            Preview Content
                                        </button>
                                        <button type="button" class="button save-blocks">
                                            Save Blocks
                                        </button>
                                    </div>
                                    
                                    <p class="description">
                                        Use the block editor to create rich content for this variation.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="variation_weight_${index}">Weight %</label>
                                </th>
                                <td>
                                    <input type="number" id="variation_weight_${index}" 
                                           name="variations[${index}][weight_percentage]"
                                           value="50" min="0" max="100" step="0.01" 
                                           class="small-text variation-weight">%
                                    <div class="weight-slider" style="margin-top: 8px; width: 200px;"></div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            `;
    },

    /**
     * Initialize components for a new variation - UPDATED FOR GUTENBERG
     */
    initVariationComponents: function (index) {
      const $variation = $(`.pronto-ab-variation[data-index="${index}"]`);

      // Initialize weight slider
      $variation.find(".weight-slider").slider({
        min: 0,
        max: 100,
        value: 50,
        step: 0.01,
        slide: function (event, ui) {
          $variation.find(".variation-weight").val(ui.value);
        },
      });

      // Initialize preview button
      $variation.find(".preview-variation").on("click", function () {
        ProntoABAdmin.showVariationPreview(index);
      });

      // The Gutenberg editor will be initialized by the Gutenberg manager
      // when it detects the new variation container
    },

    /**
     * Show variation preview - UPDATED FOR GUTENBERG
     */
    showVariationPreview: function (index) {
      let content = "";

      // Try to get content from Gutenberg manager first
      if (window.prontoABGutenbergManager) {
        content = window.prontoABGutenbergManager.getEditorContent(index);
      }

      // Fallback to hidden input field
      if (!content) {
        const $variation = $(`.pronto-ab-variation[data-index="${index}"]`);
        content = $variation.find(".variation-content-input").val();
      }

      if (!content || content.trim() === "") {
        alert("No content to preview. Add some content first!");
        return;
      }

      // Create preview modal
      const modal = `
        <div class="variation-preview-modal">
          <div class="variation-preview-content">
            <div class="variation-preview-header">
              <h3>Variation Preview</h3>
              <button type="button" class="variation-preview-close">&times;</button>
            </div>
            <div class="variation-preview-body">
              ${content}
            </div>
          </div>
        </div>
      `;

      $("body").append(modal);

      // Close modal handlers
      $(".variation-preview-close, .variation-preview-modal").on(
        "click",
        function (e) {
          if (e.target === this) {
            $(".variation-preview-modal").remove();
          }
        }
      );

      // ESC key to close
      $(document).on("keydown.preview-modal", function (e) {
        if (e.keyCode === 27) {
          // ESC key
          $(".variation-preview-modal").remove();
          $(document).off("keydown.preview-modal");
        }
      });
    },

    /**
     * Validate variation weights
     */
    validateVariationWeights: function () {
      let totalWeight = 0;
      $(".variation-weight").each(function () {
        totalWeight += parseFloat($(this).val()) || 0;
      });

      const $indicator = $("#weight-total-indicator");
      if ($indicator.length === 0) {
        $("#add-variation").before(`
                    <div id="weight-total-indicator" class="weight-indicator">
                        Total weight: <span class="weight-value">${totalWeight.toFixed(
                          1
                        )}%</span>
                    </div>
                `);
      } else {
        $indicator.find(".weight-value").text(totalWeight.toFixed(1) + "%");
      }

      // Color coding
      if (totalWeight < 99) {
        $indicator
          .removeClass("weight-over weight-perfect")
          .addClass("weight-under");
      } else if (totalWeight > 101) {
        $indicator
          .removeClass("weight-under weight-perfect")
          .addClass("weight-over");
      } else {
        $indicator
          .removeClass("weight-under weight-over")
          .addClass("weight-perfect");
      }
    },

    /**
     * Redistribute variation weights evenly
     */
    redistributeVariationWeights: function () {
      const $weights = $(".variation-weight");
      const count = $weights.length;

      if (count > 0) {
        const evenWeight = (100 / count).toFixed(2);
        $weights.val(evenWeight);
        $weights.each(function () {
          const index = $(this).closest(".pronto-ab-variation").data("index");
          $(
            `.pronto-ab-variation[data-index="${index}"] .weight-slider`
          ).slider("value", evenWeight);
        });
      }

      this.validateVariationWeights();
    },

    /**
     * Update variation indexes after removal
     */
    updateVariationIndexes: function () {
      $(".pronto-ab-variation").each(function (newIndex) {
        const $variation = $(this);
        const oldIndex = $variation.data("index");

        if (oldIndex !== newIndex) {
          // Update data attribute
          $variation.attr("data-index", newIndex);

          // Update all form field names and IDs
          $variation
            .find('[name*="variations[' + oldIndex + ']"]')
            .each(function () {
              const oldName = $(this).attr("name");
              const newName = oldName.replace(
                `variations[${oldIndex}]`,
                `variations[${newIndex}]`
              );
              $(this).attr("name", newName);

              if ($(this).attr("id")) {
                const oldId = $(this).attr("id");
                const newId = oldId.replace(`_${oldIndex}`, `_${newIndex}`);
                $(this).attr("id", newId);
              }
            });

          // Update labels
          $variation.find('label[for*="_' + oldIndex + '"]').each(function () {
            const oldFor = $(this).attr("for");
            const newFor = oldFor.replace(`_${oldIndex}`, `_${newIndex}`);
            $(this).attr("for", newFor);
          });

          // Update Gutenberg editor data attributes
          $variation.find(".pronto-ab-gutenberg-editor").each(function () {
            $(this).attr("data-variation-index", newIndex);
            $(this).attr("id", `variation_editor_${newIndex}`);
          });
        }
      });
    },

    /**
     * Initialize sortable variations
     */
    initSortableVariations: function () {
      $("#pronto-ab-variations").sortable({
        handle: ".variation-header h4",
        placeholder: "variation-placeholder",
        stop: function () {
          ProntoABAdmin.updateVariationIndexes();
        },
      });
    },

    /**
     * Form validation - UPDATED FOR GUTENBERG
     */
    initFormValidation: function () {
      $("#pronto-ab-campaign-form").on("submit", function (e) {
        // Sync all Gutenberg editors before validation
        if (window.prontoABGutenbergManager) {
          window.prontoABGutenbergManager.saveAllEditors();
        }

        const errors = ProntoABAdmin.validateCampaignForm();

        if (errors.length > 0) {
          e.preventDefault();
          ProntoABAdmin.showValidationErrors(errors);
          return false;
        }
      });
    },

    /**
     * Validate campaign form
     */
    validateCampaignForm: function () {
      const errors = [];

      // Campaign name required
      if (!$("#campaign_name").val().trim()) {
        errors.push("Campaign name is required");
      }

      // At least one variation required
      if ($(".pronto-ab-variation").length === 0) {
        errors.push("At least one variation is required");
      }

      // Validate variation weights
      let totalWeight = 0;
      $(".variation-weight").each(function () {
        totalWeight += parseFloat($(this).val()) || 0;
      });

      if (Math.abs(totalWeight - 100) > 1) {
        errors.push("Total variation weights should equal 100%");
      }

      // Date validation
      const startDate = $("#start_date").val();
      const endDate = $("#end_date").val();

      if (startDate && endDate && new Date(startDate) >= new Date(endDate)) {
        errors.push("End date must be after start date");
      }

      return errors;
    },

    /**
     * Show validation errors
     */
    showValidationErrors: function (errors) {
      let errorHtml =
        '<div class="notice notice-error"><p><strong>Please fix the following errors:</strong></p><ul>';
      errors.forEach(function (error) {
        errorHtml += `<li>${error}</li>`;
      });
      errorHtml += "</ul></div>";

      $(".wrap h1").after(errorHtml);
      $("html, body").animate({ scrollTop: 0 }, 500);

      // Remove after 10 seconds
      setTimeout(function () {
        $(".notice-error").slideUp();
      }, 10000);
    },

    /**
     * Auto-save functionality - UPDATED FOR GUTENBERG
     */
    initAutoSave: function () {
      let autoSaveTimer;
      const $form = $("#pronto-ab-campaign-form");

      $form.on("change input", function () {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function () {
          ProntoABAdmin.autoSaveCampaign();
        }, 30000); // Auto-save after 30 seconds of inactivity
      });
    },

    /**
     * Auto-save campaign - UPDATED FOR GUTENBERG
     */
    autoSaveCampaign: function () {
      // Sync all Gutenberg editors before auto-save
      if (window.prontoABGutenbergManager) {
        window.prontoABGutenbergManager.saveAllEditors();
      }

      const $form = $("#pronto-ab-campaign-form");
      const formData =
        $form.serialize() +
        "&action=pronto_ab_autosave&nonce=" +
        this.config.nonce;

      $.post(this.config.ajaxUrl, formData)
        .done(function (response) {
          if (response.success) {
            ProntoABAdmin.showNotice("info", "Campaign auto-saved", 3000);
          }
        })
        .fail(function () {
          console.log("Auto-save failed");
        });
    },

    /**
     * Initialize real-time stats
     */
    initRealTimeStats: function () {
      if ($(".campaign-stats").length === 0) {
        return;
      }

      // Update stats every 30 seconds
      setInterval(function () {
        ProntoABAdmin.updateCampaignStats();
      }, 30000);
    },

    /**
     * Update campaign statistics
     */
    updateCampaignStats: function () {
      const campaignId = $('input[name="campaign_id"]').val();

      if (!campaignId) {
        return;
      }

      $.post(this.config.ajaxUrl, {
        action: "pronto_ab_get_stats",
        campaign_id: campaignId,
        nonce: this.config.nonce,
      }).done(function (response) {
        if (response.success) {
          ProntoABAdmin.renderUpdatedStats(response.data);
        }
      });
    },

    /**
     * Render updated statistics
     */
    renderUpdatedStats: function (stats) {
      // Update overall stats
      $(".stat-impressions").text(stats.impressions.toLocaleString());
      $(".stat-conversions").text(stats.conversions.toLocaleString());
      $(".stat-visitors").text(stats.unique_visitors.toLocaleString());
      $(".stat-rate").text(stats.conversion_rate + "%");

      // Update variation stats
      if (stats.variations) {
        stats.variations.forEach(function (variation) {
          const $variationStat = $(
            `.variation-stat[data-variation="${variation.id}"]`
          );
          if ($variationStat.length) {
            $variationStat
              .find(".variation-impressions")
              .text(variation.impressions.toLocaleString());
            $variationStat
              .find(".variation-conversions")
              .text(variation.conversions.toLocaleString());
            $variationStat
              .find(".variation-rate")
              .text(variation.conversion_rate + "%");
          }
        });
      }
    },

    /**
     * Toggle campaign status
     */
    toggleCampaignStatus: function (campaignId, newStatus, $button) {
      $button
        .prop("disabled", true)
        .text(this.config.strings.saving || "Saving...");

      $.post(this.config.ajaxUrl, {
        action: "pronto_ab_toggle_status",
        campaign_id: campaignId,
        status: newStatus,
        nonce: this.config.nonce,
      })
        .done(function (response) {
          if (response.success) {
            location.reload(); // Refresh to show updated status
          } else {
            ProntoABAdmin.showNotice(
              "error",
              response.data || "Failed to update status"
            );
            $button.prop("disabled", false).text("Retry");
          }
        })
        .fail(function () {
          ProntoABAdmin.showNotice("error", "Request failed");
          $button.prop("disabled", false).text("Retry");
        });
    },

    /**
     * Initialize sortable columns
     */
    initSortableColumns: function () {
      $(".wp-list-table th.sortable a").on("click", function (e) {
        e.preventDefault();
        const url = new URL($(this).attr("href"), window.location.origin);
        window.location.href = url.href;
      });
    },

    /**
     * Initialize campaign search
     */
    initCampaignSearch: function () {
      const $searchInput = $("#campaign-search");
      const $campaignRows = $(".pronto-ab-campaigns-table tbody tr");

      if (!$searchInput.length) {
        // Add search input if it doesn't exist
        $(".pronto-ab-campaigns-table").before(`
                    <div class="alignleft actions">
                        <input type="search" id="campaign-search" placeholder="Search campaigns..." />
                    </div>
                `);
        $searchInput = $("#campaign-search");
      }

      $searchInput.on("input", function () {
        const searchTerm = $(this).val().toLowerCase();

        $campaignRows.each(function () {
          const $row = $(this);
          const campaignName = $row.find("td:first").text().toLowerCase();
          const description = $row.find("td:first small").text().toLowerCase();

          if (
            campaignName.includes(searchTerm) ||
            description.includes(searchTerm)
          ) {
            $row.show();
          } else {
            $row.hide();
          }
        });
      });
    },

    /**
     * Initialize bulk actions
     */
    initBulkActions: function () {
      // Add bulk action dropdown if it doesn't exist
      if ($("#bulk-action-selector-top").length === 0) {
        $(".pronto-ab-campaigns-table").before(`
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <select name="action" id="bulk-action-selector-top">
                                <option value="-1">Bulk Actions</option>
                                <option value="activate">Activate</option>
                                <option value="pause">Pause</option>
                                <option value="delete">Delete</option>
                            </select>
                            <input type="submit" id="doaction" class="button action" value="Apply">
                        </div>
                    </div>
                `);
      }

      // Handle bulk actions
      $("#doaction").on("click", function (e) {
        e.preventDefault();
        const action = $("#bulk-action-selector-top").val();
        const selectedCampaigns = $(".cb-select:checked")
          .map(function () {
            return $(this).val();
          })
          .get();

        if (action === "-1" || selectedCampaigns.length === 0) {
          return;
        }

        if (action === "delete") {
          if (
            !confirm(
              `Are you sure you want to delete ${selectedCampaigns.length} campaign(s)?`
            )
          ) {
            return;
          }
        }

        ProntoABAdmin.executeBulkAction(action, selectedCampaigns);
      });
    },

    /**
     * Execute bulk action
     */
    executeBulkAction: function (action, campaignIds) {
      $.post(this.config.ajaxUrl, {
        action: "pronto_ab_bulk_action",
        bulk_action: action,
        campaign_ids: campaignIds,
        nonce: this.config.nonce,
      })
        .done(function (response) {
          if (response.success) {
            location.reload();
          } else {
            ProntoABAdmin.showNotice(
              "error",
              response.data || "Bulk action failed"
            );
          }
        })
        .fail(function () {
          ProntoABAdmin.showNotice("error", "Request failed");
        });
    },

    /**
     * Show admin notice
     */
    showNotice: function (type, message, duration) {
      const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);

      $(".wrap h1").after($notice);

      if (duration) {
        setTimeout(function () {
          $notice.slideUp();
        }, duration);
      }
    },

    /**
     * Initialize date validation
     */
    initDateValidation: function () {
      $("#start_date, #end_date").on("change", function () {
        const startDate = $("#start_date").val();
        const endDate = $("#end_date").val();

        if (startDate && endDate && new Date(startDate) >= new Date(endDate)) {
          $(this).addClass("invalid");
          ProntoABAdmin.showNotice(
            "warning",
            "End date should be after start date",
            5000
          );
        } else {
          $("#start_date, #end_date").removeClass("invalid");
        }
      });
    },

    /**
     * Initialize traffic split visualizer
     */
    initTrafficSplitVisualizer: function () {
      const $trafficSplit = $("#traffic_split");

      if ($trafficSplit.length === 0) {
        return;
      }

      // Add visualizer
      $trafficSplit.after('<div id="traffic-split-visualizer"></div>');

      const updateVisualizer = function () {
        const split = $trafficSplit.val();
        const parts = split.split("/");
        const total = parts.reduce((sum, part) => sum + parseInt(part), 0);

        let html = '<div class="traffic-split-bars">';
        parts.forEach((part, index) => {
          const percentage = ((parseInt(part) / total) * 100).toFixed(1);
          html += `<div class="split-bar split-${index}" style="width: ${percentage}%">
                               <span>${percentage}%</span>
                             </div>`;
        });
        html += "</div>";

        $("#traffic-split-visualizer").html(html);
      };

      $trafficSplit.on("change", updateVisualizer);
      updateVisualizer(); // Initial render
    },

    /**
     * Initialize change tracking - UPDATED FOR GUTENBERG
     */
    initChangeTracking: function () {
      const $form = $("#pronto-ab-campaign-form");
      let originalData = $form.serialize();
      let hasChanges = false;

      $form.on("change input", function () {
        // Sync Gutenberg editors before checking changes
        if (window.prontoABGutenbergManager) {
          window.prontoABGutenbergManager.saveAllEditors();
        }

        hasChanges = $form.serialize() !== originalData;

        if (hasChanges) {
          $(".button-primary").addClass("unsaved-changes");
        } else {
          $(".button-primary").removeClass("unsaved-changes");
        }
      });

      // Warn before leaving with unsaved changes
      $(window).on("beforeunload", function () {
        if (hasChanges) {
          return "You have unsaved changes. Are you sure you want to leave?";
        }
      });

      // Clear warning on successful save
      $form.on("submit", function () {
        $(window).off("beforeunload");
      });
    },
  };

  /**
   * Initialize when document is ready
   */
  $(document).ready(function () {
    ProntoABAdmin.init();
  });

  // Make ProntoABAdmin globally available for debugging
  window.ProntoABAdmin = ProntoABAdmin;
})(jQuery);
