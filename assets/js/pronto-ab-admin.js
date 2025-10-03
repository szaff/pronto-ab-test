/**
 * Pronto A/B Testing - Admin JavaScript (Cleaned)
 *
 * Handles admin interface functionality including campaign management,
 * form validation, and basic admin interactions.
 * Removed all Gutenberg/complex editor integrations.
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
      debug: abTestAjax.debug || false,
    },

    // State tracking
    isInitialized: false,

    /**
     * Initialize admin functionality
     */
    init: function () {
      if (this.isInitialized) {
        return;
      }

      this.log("Initializing Pronto A/B Admin");

      // Initialize different admin page functionalities
      this.initCampaignsList();
      this.initCampaignEditor();
      this.initPostTypeSelector();
      this.initFormValidation();
      this.initBulkActions();
      this.initRealTimeStats();
      this.initStatisticsRefresh();

      this.isInitialized = true;
      this.log("Pronto A/B Admin initialized successfully");
    },

    /**
     * Debug logging
     */
    log: function () {
      if (this.config.debug && console && console.log) {
        const args = Array.prototype.slice.call(arguments);
        args.unshift("[Pronto A/B]");
        console.log.apply(console, args);
      }
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

      this.log("Campaign editor initialized");
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
     * Form validation
     */
    initFormValidation: function () {
      $("#pronto-ab-campaign-form").on("submit", function (e) {
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
     * Auto-save functionality
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
     * Auto-save campaign
     */
    autoSaveCampaign: function () {
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
          ProntoABAdmin.log("Auto-save failed");
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

          // Also refresh statistics significance box if it exists
          if ($(".pab-statistics-box").length > 0) {
            ProntoABAdmin.refreshStatistics(null, true); // Silent refresh
          }
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
      let $searchInput = $("#campaign-search");
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
     * Initialize change tracking
     */
    initChangeTracking: function () {
      const $form = $("#pronto-ab-campaign-form");
      let originalData = $form.serialize();
      let hasChanges = false;

      $form.on("change input", function () {
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

    /**
     * Handle variation management actions
     */
    initVariationActions: function () {
      // Handle variation weight changes
      $(document).on("change input", ".weight-slider", function () {
        const $slider = $(this);
        const variationId = $slider.data("variation-id");
        const weight = $slider.val();

        // Update display
        $slider
          .closest(".variation-card")
          .find(".weight-value")
          .text(weight + "%");

        // Debounced AJAX save
        clearTimeout(this.weightSaveTimeout);
        this.weightSaveTimeout = setTimeout(function () {
          ProntoABAdmin.saveVariationWeight(variationId, weight);
        }, 1000);
      });

      // Handle variation duplication
      $(document).on("click", ".duplicate-variation", function (e) {
        e.preventDefault();
        const variationId = $(this).data("variation-id");
        ProntoABAdmin.duplicateVariation(variationId);
      });

      // Handle variation deletion
      $(document).on("click", ".delete-variation", function (e) {
        e.preventDefault();
        const variationId = $(this).data("variation-id");
        const variationName = $(this).data("variation-name");

        if (
          confirm(
            `Are you sure you want to delete the variation "${variationName}"?`
          )
        ) {
          ProntoABAdmin.deleteVariation(variationId);
        }
      });
    },

    /**
     * Save variation weight via AJAX
     */
    saveVariationWeight: function (variationId, weight) {
      $.post(this.config.ajaxUrl, {
        action: "pronto_ab_save_variation_weights",
        weights: { [variationId]: weight },
        nonce: this.config.nonce,
      }).done(function (response) {
        if (response.success) {
          ProntoABAdmin.log("Weight saved successfully");
        }
      });
    },

    /**
     * Duplicate variation via AJAX
     */
    duplicateVariation: function (variationId) {
      $.post(this.config.ajaxUrl, {
        action: "pronto_ab_duplicate_variation",
        variation_id: variationId,
        nonce: this.config.nonce,
      })
        .done(function (response) {
          if (response.success) {
            ProntoABAdmin.showNotice("success", response.data.message);
            // Optionally reload or update the UI
            setTimeout(() => location.reload(), 1000);
          } else {
            ProntoABAdmin.showNotice("error", response.data);
          }
        })
        .fail(function () {
          ProntoABAdmin.showNotice("error", "Failed to duplicate variation");
        });
    },

    /**
     * Delete variation via AJAX
     */
    deleteVariation: function (variationId) {
      $.post(this.config.ajaxUrl, {
        action: "pronto_ab_delete_variation",
        variation_id: variationId,
        nonce: this.config.nonce,
      })
        .done(function (response) {
          if (response.success) {
            ProntoABAdmin.showNotice("success", response.data.message);
            // Remove the variation card from UI
            $(`.variation-card[data-variation-id="${variationId}"]`).fadeOut(
              300,
              function () {
                $(this).remove();
              }
            );
          } else {
            ProntoABAdmin.showNotice("error", response.data);
          }
        })
        .fail(function () {
          ProntoABAdmin.showNotice("error", "Failed to delete variation");
        });
    },

    /**
     * Initialize statistics refresh functionality
     */
    initStatisticsRefresh: function () {
      const self = this;

      // Only initialize if statistics box exists on page
      if (!$(".pab-statistics-box").length) {
        return;
      }

      self.log("Initializing statistics refresh");

      // Auto-refresh every 30 seconds
      const autoRefresh = this.config.strings.auto_refresh_stats !== false;
      if (autoRefresh) {
        setInterval(function () {
          if ($(".pab-statistics-box").length > 0) {
            self.refreshStatistics(null, true); // true = silent refresh
          }
        }, 30000); // 30 seconds
      }
    },

    /**
     * Refresh campaign statistics via AJAX
     *
     * @param {jQuery} $button - The button element (if manually triggered)
     * @param {boolean} silent - If true, don't show loading state
     */
    refreshStatistics: function ($button, silent) {
      const self = this;
      const campaignId = $('input[name="campaign_id"]').val();

      if (!campaignId) {
        self.log("No campaign ID found for statistics refresh");
        return;
      }

      // Store original button state
      let originalText = "";
      if ($button && !silent) {
        originalText = $button.text();
        $button
          .prop("disabled", true)
          .html(
            '<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear;"></span> ' +
              (this.config.strings.refreshing || "Refreshing...")
          );
      }

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "pronto_ab_refresh_statistics",
          campaign_id: campaignId,
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success && response.data.html) {
            // Replace the entire statistics box with fresh HTML
            $(".pab-statistics-box").replaceWith(response.data.html);

            // Show success message (if not silent)
            if (!silent) {
              self.showNotice(
                "success",
                self.config.strings.stats_refreshed || "Statistics refreshed!",
                3000
              );
            }

            // Log the update
            self.log(
              "Statistics refreshed successfully",
              response.data.timestamp
            );

            // Trigger custom event for other scripts to hook into
            $(document).trigger("prontoAB:statsRefreshed", [response.data]);
          } else {
            self.showNotice(
              "error",
              response.data || "Failed to refresh statistics"
            );
            self.log("Statistics refresh failed", response);
          }
        },
        error: function (xhr, status, error) {
          if (!silent) {
            self.showNotice(
              "error",
              self.config.strings.error || "Error refreshing statistics"
            );
          }
          self.log("AJAX error refreshing statistics:", error);
        },
        complete: function () {
          // Restore button state
          if ($button && !silent) {
            $button
              .prop("disabled", false)
              .html(
                '<span class="dashicons dashicons-update"></span> ' +
                  originalText
              );
          }
        },
      });
    },
  };

  /**
   * Initialize when document is ready
   */
  $(document).ready(function () {
    ProntoABAdmin.init();
    ProntoABAdmin.initVariationActions();
  });

  // Make ProntoABAdmin globally available for debugging
  window.ProntoABAdmin = ProntoABAdmin;
})(jQuery);
