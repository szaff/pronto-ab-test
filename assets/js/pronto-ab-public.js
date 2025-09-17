/**
 * Pronto A/B Testing - Public JavaScript
 *
 * Handles frontend tracking, event monitoring, and AJAX communication
 * for A/B test campaigns.
 */

(function ($) {
  "use strict";

  // Check if abTestData is available (localized from PHP)
  if (typeof abTestData === "undefined") {
    console.warn(
      "Pronto A/B: abTestData not found. Plugin may not be properly initialized."
    );
    return;
  }

  /**
   * Main A/B Test tracking object
   */
  window.abTestTracking = {
    // Configuration from PHP
    visitorId: abTestData.visitor_id,
    ajaxUrl: abTestData.ajax_url,
    nonce: abTestData.nonce,
    debug: abTestData.debug || false,

    // Internal tracking state
    trackedEvents: new Set(),
    isInitialized: false,

    /**
     * Initialize tracking system
     */
    init: function () {
      if (this.isInitialized) {
        return;
      }

      this.log("Initializing A/B Test tracking for visitor:", this.visitorId);

      // Set up automatic tracking
      this.initAutoTracking();

      // Set up manual tracking API
      this.initManualTracking();

      // Mark as initialized
      this.isInitialized = true;

      this.log("A/B Test tracking initialized successfully");
    },

    /**
     * Automatic tracking for common conversion events
     */
    initAutoTracking: function () {
      const self = this;

      // Track button clicks in A/B test content
      $("body").on(
        "click",
        ".pronto-ab-content button, .pronto-ab-content .btn, .pronto-ab-content a.button",
        function (e) {
          const $button = $(this);
          const $container = $button.closest(".pronto-ab-content");
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const buttonText =
              $button.text().trim() || $button.val() || "Button Click";
            self.trackConversion(campaignId, variationId, {
              type: "button_click",
              element: buttonText,
              url: window.location.href,
            });

            self.log("Auto-tracked button click:", buttonText);
          }
        }
      );

      // Track form submissions in A/B test content
      $("body").on("submit", ".pronto-ab-content form", function (e) {
        const $form = $(this);
        const $container = $form.closest(".pronto-ab-content");
        const campaignId = $container.data("campaign");
        const variationId = $container.data("variation");

        if (campaignId && variationId) {
          const formId = $form.attr("id") || $form.attr("class") || "form";
          self.trackConversion(campaignId, variationId, {
            type: "form_submit",
            element: formId,
            url: window.location.href,
          });

          self.log("Auto-tracked form submission:", formId);
        }
      });

      // Track link clicks in A/B test content (external links)
      $("body").on(
        "click",
        '.pronto-ab-content a[href^="http"], .pronto-ab-content a[href^="mailto"], .pronto-ab-content a[href^="tel"]',
        function (e) {
          const $link = $(this);
          const $container = $link.closest(".pronto-ab-content");
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const href = $link.attr("href");
            const linkText = $link.text().trim() || href;

            self.trackConversion(campaignId, variationId, {
              type: "link_click",
              element: linkText,
              target_url: href,
              url: window.location.href,
            });

            self.log("Auto-tracked link click:", linkText, "to:", href);
          }
        }
      );

      // Track video plays in A/B test content
      $("body").on("play", ".pronto-ab-content video", function (e) {
        const $video = $(this);
        const $container = $video.closest(".pronto-ab-content");
        const campaignId = $container.data("campaign");
        const variationId = $container.data("variation");

        if (campaignId && variationId) {
          const videoSrc = $video.attr("src") || "Video";
          self.trackConversion(campaignId, variationId, {
            type: "video_play",
            element: videoSrc,
            url: window.location.href,
          });

          self.log("Auto-tracked video play:", videoSrc);
        }
      });

      // Track time on page for A/B test content (after 30 seconds)
      if ($(".pronto-ab-content").length > 0) {
        setTimeout(function () {
          $(".pronto-ab-content").each(function () {
            const $container = $(this);
            const campaignId = $container.data("campaign");
            const variationId = $container.data("variation");

            if (campaignId && variationId) {
              self.trackEvent("engagement", campaignId, variationId, {
                type: "time_on_page",
                duration: 30,
                url: window.location.href,
              });

              self.log("Auto-tracked 30-second engagement");
            }
          });
        }, 30000); // 30 seconds
      }
    },

    /**
     * Set up manual tracking API
     */
    initManualTracking: function () {
      // Make tracking functions globally available
      window.abTrackConversion = this.trackConversion.bind(this);
      window.abTrackEvent = this.trackEvent.bind(this);
      window.abTrackCustomGoal = this.trackCustomGoal.bind(this);
    },

    /**
     * Track a conversion event
     */
    trackConversion: function (campaignId, variationId, data) {
      data = data || {};
      data.event_type = "conversion";
      this.trackEvent("conversion", campaignId, variationId, data);
    },

    /**
     * Track a custom goal
     */
    trackCustomGoal: function (goalName, campaignId, variationId, value) {
      this.trackEvent("goal", campaignId, variationId, {
        goal_name: goalName,
        goal_value: value || "",
        url: window.location.href,
      });
    },

    /**
     * Generic event tracking method
     */
    trackEvent: function (eventType, campaignId, variationId, additionalData) {
      // Validate required parameters
      if (!eventType || !campaignId || !variationId) {
        this.log("Error: Missing required parameters for trackEvent", {
          eventType: eventType,
          campaignId: campaignId,
          variationId: variationId,
        });
        return false;
      }

      // Prevent duplicate tracking of the same event
      const eventKey = `${eventType}_${campaignId}_${variationId}_${JSON.stringify(
        additionalData
      )}`;
      if (this.trackedEvents.has(eventKey)) {
        this.log("Duplicate event prevented:", eventKey);
        return false;
      }

      // Prepare data for AJAX request
      const postData = {
        action: "pronto_ab_track",
        campaign_id: parseInt(campaignId),
        variation_id: parseInt(variationId),
        event_type: eventType,
        visitor_id: this.visitorId,
        nonce: this.nonce,
        event_value: JSON.stringify(additionalData || {}),
        timestamp: Date.now(),
        page_url: window.location.href,
        referrer: document.referrer || "",
      };

      // Send AJAX request
      const self = this;
      $.post(this.ajaxUrl, postData)
        .done(function (response) {
          if (response.success) {
            self.trackedEvents.add(eventKey);
            self.log("Event tracked successfully:", eventType, response.data);

            // Trigger custom event for other scripts to listen to
            $(document).trigger("abTestEventTracked", {
              eventType: eventType,
              campaignId: campaignId,
              variationId: variationId,
              data: additionalData,
              response: response,
            });
          } else {
            self.log("Event tracking failed:", response.data);
          }
        })
        .fail(function (xhr, status, error) {
          self.log("AJAX error tracking event:", error, xhr.responseText);
        });

      return true;
    },

    /**
     * Utility method for debug logging
     */
    log: function () {
      if (this.debug && console && console.log) {
        const args = Array.prototype.slice.call(arguments);
        args.unshift("[Pronto A/B]");
        console.log.apply(console, args);
      }
    },

    /**
     * Get visitor information
     */
    getVisitorInfo: function () {
      return {
        visitorId: this.visitorId,
        userAgent: navigator.userAgent,
        screenSize: screen.width + "x" + screen.height,
        windowSize: $(window).width() + "x" + $(window).height(),
        referrer: document.referrer,
        timestamp: new Date().toISOString(),
      };
    },

    /**
     * Check if visitor is assigned to a specific variation
     */
    isAssignedToVariation: function (campaignId, variationId) {
      const selector = `.pronto-ab-content[data-campaign="${campaignId}"][data-variation="${variationId}"]`;
      return $(selector).length > 0;
    },

    /**
     * Get all active campaigns on current page
     */
    getActiveCampaigns: function () {
      const campaigns = {};
      $(".pronto-ab-content").each(function () {
        const $content = $(this);
        const campaignId = $content.data("campaign");
        const variationId = $content.data("variation");

        if (campaignId && variationId) {
          campaigns[campaignId] = {
            campaignId: campaignId,
            variationId: variationId,
            element: $content[0],
          };
        }
      });
      return campaigns;
    },
  };

  /**
   * WordPress-specific integrations
   */
  const WordPressIntegration = {
    /**
     * Track Contact Form 7 submissions
     */
    initContactForm7: function () {
      $(document).on("wpcf7mailsent", function (event) {
        const $form = $(event.target);
        const $container = $form.closest(".pronto-ab-content");
        const campaignId = $container.data("campaign");
        const variationId = $container.data("variation");

        if (campaignId && variationId) {
          window.abTestTracking.trackConversion(campaignId, variationId, {
            type: "cf7_submit",
            form_id: $form.find('input[name="_wpcf7"]').val(),
            url: window.location.href,
          });

          window.abTestTracking.log("Contact Form 7 submission tracked");
        }
      });
    },

    /**
     * Track Gravity Forms submissions
     */
    initGravityForms: function () {
      $(document).on("gform_confirmation_loaded", function (event, formId) {
        const $container = $(".pronto-ab-content").has(`#gform_${formId}`);
        const campaignId = $container.data("campaign");
        const variationId = $container.data("variation");

        if (campaignId && variationId) {
          window.abTestTracking.trackConversion(campaignId, variationId, {
            type: "gf_submit",
            form_id: formId,
            url: window.location.href,
          });

          window.abTestTracking.log("Gravity Forms submission tracked");
        }
      });
    },

    /**
     * Track WooCommerce add to cart
     */
    initWooCommerce: function () {
      $(document).on(
        "added_to_cart",
        function (event, fragments, cart_hash, addToCartButton) {
          const $button = $(addToCartButton);
          const $container = $button.closest(".pronto-ab-content");
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const productId = $button.data("product_id") || "unknown";
            window.abTestTracking.trackConversion(campaignId, variationId, {
              type: "wc_add_to_cart",
              product_id: productId,
              url: window.location.href,
            });

            window.abTestTracking.log("WooCommerce add to cart tracked");
          }
        }
      );
    },
  };

  /**
   * Initialize everything when document is ready
   */
  $(document).ready(function () {
    // Initialize main tracking
    window.abTestTracking.init();

    // Initialize WordPress integrations
    WordPressIntegration.initContactForm7();
    WordPressIntegration.initGravityForms();
    WordPressIntegration.initWooCommerce();

    // Log initial state if debugging
    if (window.abTestTracking.debug) {
      const activeCampaigns = window.abTestTracking.getActiveCampaigns();
      window.abTestTracking.log("Active campaigns on page:", activeCampaigns);
      window.abTestTracking.log(
        "Visitor info:",
        window.abTestTracking.getVisitorInfo()
      );
    }
  });

  /**
   * Handle page visibility changes (track when user comes back to tab)
   */
  $(document).on("visibilitychange", function () {
    if (!document.hidden) {
      // User returned to page - could track as engagement
      $(".pronto-ab-content").each(function () {
        const $container = $(this);
        const campaignId = $container.data("campaign");
        const variationId = $container.data("variation");

        if (campaignId && variationId) {
          window.abTestTracking.trackEvent(
            "engagement",
            campaignId,
            variationId,
            {
              type: "page_return",
              url: window.location.href,
            }
          );
        }
      });
    }
  });
})(jQuery);
