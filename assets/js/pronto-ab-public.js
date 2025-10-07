/**
 * Pronto A/B Testing - Enhanced Frontend JavaScript
 *
 * Comprehensive tracking, interaction monitoring, and A/B test management
 * for the frontend user experience.
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
   * Main A/B Test Frontend Controller
   */
  window.ProntoAB = {
    // Configuration from PHP
    config: {
      ajaxUrl: abTestData.ajax_url,
      nonce: abTestData.nonce,
      visitorId: abTestData.visitor_id,
      debug: abTestData.debug || false,
      autoTrack: abTestData.auto_track !== false,
      trackEvents: abTestData.track_events || {},
    },

    // Internal state
    state: {
      isInitialized: false,
      trackedEvents: new Set(),
      activeCampaigns: new Map(),
      trackingQueue: [],
      isOnline: navigator.onLine,
    },

    // Event handlers
    handlers: new Map(),

    /**
     * Initialize the A/B testing system
     */
    init: function () {
      if (this.state.isInitialized) {
        return;
      }

      this.log("Initializing Pronto A/B Frontend System");

      // Discover active campaigns on the page
      this.discoverActiveCampaigns();

      // Set up tracking systems
      if (this.config.autoTrack) {
        this.initializeAutoTracking();
      }

      // Set up manual tracking API
      this.initializeTrackingAPI();

      // Set up goal tracking
      this.initializeGoalTracking();

      // Set up event listeners
      this.initializeEventListeners();

      // Handle offline/online status
      this.initializeOfflineHandling();

      // Mark as initialized
      this.state.isInitialized = true;

      // Trigger initialization event
      this.trigger("initialized", {
        campaigns: Array.from(this.state.activeCampaigns.keys()),
        visitorId: this.config.visitorId,
      });

      this.log("A/B Testing system initialized successfully");
    },

    /**
     * Discover active A/B test campaigns on the current page
     */
    discoverActiveCampaigns: function () {
      const self = this;
      const campaignElements = document.querySelectorAll("[data-campaign]");

      campaignElements.forEach(function (element) {
        const campaignId = element.getAttribute("data-campaign");
        const variationId = element.getAttribute("data-variation");
        const elementType = element.getAttribute("data-element") || "content";

        if (campaignId && variationId) {
          const campaignData = {
            id: campaignId,
            variationId: variationId,
            elementType: elementType,
            element: element,
          };

          // Store campaign data
          if (!self.state.activeCampaigns.has(campaignId)) {
            self.state.activeCampaigns.set(campaignId, []);
          }
          self.state.activeCampaigns.get(campaignId).push(campaignData);

          // Add tracking attributes
          element.setAttribute("data-ab-tracked", "false");

          self.log("Discovered campaign:", campaignData);
        }
      });
    },

    /**
     * Initialize automatic tracking for common interaction patterns
     */
    initializeAutoTracking: function () {
      const self = this;

      // Click tracking
      if (this.config.trackEvents.clicks !== false) {
        this.initializeClickTracking();
      }

      // Form submission tracking
      if (this.config.trackEvents.forms !== false) {
        this.initializeFormTracking();
      }

      // Time on page tracking
      if (this.config.trackEvents.time_on_page !== false) {
        this.initializeTimeTracking();
      }

      // Scroll depth tracking
      if (this.config.trackEvents.scroll_depth === true) {
        this.initializeScrollTracking();
      }

      // Video interaction tracking
      this.initializeVideoTracking();

      // Page visibility tracking
      this.initializeVisibilityTracking();
    },

    /**
     * Initialize click tracking for A/B test elements
     */
    initializeClickTracking: function () {
      const self = this;

      // Track clicks on links and buttons within A/B test content
      $(document).on(
        "click",
        '.pronto-ab-content a, .pronto-ab-content button, .pronto-ab-content [role="button"], .pronto-ab-content input[type="submit"], .pronto-ab-content input[type="button"]',
        function (e) {
          const $clicked = $(this);
          const $container = $clicked.closest(".pronto-ab-content");

          if ($container.length) {
            const campaignId = $container.data("campaign");
            const variationId = $container.data("variation");
            const elementType = $container.data("element") || "content";

            if (campaignId && variationId) {
              // Determine click type
              const clickType = self.determineClickType($clicked[0]);

              // Prepare event data
              const eventData = {
                type: "click",
                clickType: clickType,
                elementType: elementType,
                elementText: $clicked.text().trim(),
                elementTag: $clicked[0].tagName.toLowerCase(),
                elementClass: $clicked.attr("class") || "",
                elementId: $clicked.attr("id") || "",
                href: $clicked.attr("href") || "",
                position: self.getElementPosition($clicked[0]),
                timestamp: Date.now(),
              };

              // Track the click
              self.trackEvent("conversion", campaignId, variationId, eventData);

              self.log("Click tracked:", eventData);
            }
          }
        }
      );
    },

    /**
     * Initialize form submission tracking
     */
    initializeFormTracking: function () {
      const self = this;

      // Track form submissions within A/B test content
      $(document).on("submit", ".pronto-ab-content form", function (e) {
        const $form = $(this);
        const $container = $form.closest(".pronto-ab-content");

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "form_submit",
              formId: $form.attr("id") || "",
              formClass: $form.attr("class") || "",
              formAction: $form.attr("action") || "",
              formMethod: $form.attr("method") || "get",
              fieldCount: $form.find("input, textarea, select").length,
              timestamp: Date.now(),
            };

            self.trackEvent("conversion", campaignId, variationId, eventData);
            self.log("Form submission tracked:", eventData);
          }
        }
      });

      // Track Contact Form 7 submissions
      $(document).on("wpcf7mailsent", function (event) {
        const $form = $(event.target);
        const $container = $form.closest(".pronto-ab-content");

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "cf7_submit",
              formId: $form.find('input[name="_wpcf7"]').val(),
              timestamp: Date.now(),
            };

            self.trackEvent("conversion", campaignId, variationId, eventData);
            self.log("Contact Form 7 submission tracked");
          }
        }
      });

      // Track Gravity Forms submissions
      $(document).on("gform_confirmation_loaded", function (event, formId) {
        const $container = $(".pronto-ab-content").has(`#gform_${formId}`);

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "gf_submit",
              formId: formId,
              timestamp: Date.now(),
            };

            self.trackEvent("conversion", campaignId, variationId, eventData);
            self.log("Gravity Forms submission tracked");
          }
        }
      });
    },

    /**
     * Initialize time on page tracking
     */
    initializeTimeTracking: function () {
      const self = this;
      const timeThresholds = [10, 30, 60, 120, 300]; // seconds

      timeThresholds.forEach(function (threshold) {
        setTimeout(function () {
          self.state.activeCampaigns.forEach(function (variations, campaignId) {
            variations.forEach(function (variation) {
              const eventData = {
                type: "time_on_page",
                threshold: threshold,
                timestamp: Date.now(),
              };

              self.trackEvent(
                "engagement",
                campaignId,
                variation.variationId,
                eventData
              );
            });
          });

          self.log(`Time tracking: ${threshold}s threshold reached`);
        }, threshold * 1000);
      });
    },

    /**
     * Initialize scroll depth tracking
     */
    initializeScrollTracking: function () {
      const self = this;
      let maxScrollDepth = 0;
      const scrollThresholds = [25, 50, 75, 100]; // percentages
      const trackedThresholds = new Set();

      $(window).on("scroll", function () {
        const scrollTop = $(window).scrollTop();
        const docHeight = $(document).height();
        const winHeight = $(window).height();
        const scrollPercent = Math.round(
          (scrollTop / (docHeight - winHeight)) * 100
        );

        if (scrollPercent > maxScrollDepth) {
          maxScrollDepth = scrollPercent;

          scrollThresholds.forEach(function (threshold) {
            if (
              scrollPercent >= threshold &&
              !trackedThresholds.has(threshold)
            ) {
              trackedThresholds.add(threshold);

              self.state.activeCampaigns.forEach(function (
                variations,
                campaignId
              ) {
                variations.forEach(function (variation) {
                  const eventData = {
                    type: "scroll_depth",
                    depth: threshold,
                    maxDepth: maxScrollDepth,
                    timestamp: Date.now(),
                  };

                  self.trackEvent(
                    "engagement",
                    campaignId,
                    variation.variationId,
                    eventData
                  );
                });
              });

              self.log(`Scroll depth tracked: ${threshold}%`);
            }
          });
        }
      });
    },

    /**
     * Initialize video interaction tracking
     */
    initializeVideoTracking: function () {
      const self = this;

      // Track video plays within A/B test content
      $(document).on("play", ".pronto-ab-content video", function () {
        const $video = $(this);
        const $container = $video.closest(".pronto-ab-content");

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "video_play",
              videoSrc: $video.attr("src") || "",
              duration: $video[0].duration || 0,
              timestamp: Date.now(),
            };

            self.trackEvent("engagement", campaignId, variationId, eventData);
            self.log("Video play tracked:", eventData);
          }
        }
      });

      // Track video completion
      $(document).on("ended", ".pronto-ab-content video", function () {
        const $video = $(this);
        const $container = $video.closest(".pronto-ab-content");

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "video_complete",
              videoSrc: $video.attr("src") || "",
              duration: $video[0].duration || 0,
              timestamp: Date.now(),
            };

            self.trackEvent("conversion", campaignId, variationId, eventData);
            self.log("Video completion tracked:", eventData);
          }
        }
      });
    },

    /**
     * Initialize page visibility tracking
     */
    initializeVisibilityTracking: function () {
      const self = this;

      // Track when user returns to page
      $(document).on("visibilitychange", function () {
        if (!document.hidden) {
          self.state.activeCampaigns.forEach(function (variations, campaignId) {
            variations.forEach(function (variation) {
              const eventData = {
                type: "page_return",
                timestamp: Date.now(),
              };

              self.trackEvent(
                "engagement",
                campaignId,
                variation.variationId,
                eventData
              );
            });
          });

          self.log("Page return tracked");
        }
      });
    },

    /**
     * Initialize manual tracking API
     */
    initializeTrackingAPI: function () {
      const self = this;

      // Global functions for manual tracking
      window.abTrackConversion = function (campaignId, variationId, data) {
        return self.trackEvent("conversion", campaignId, variationId, data);
      };

      window.abTrackEngagement = function (campaignId, variationId, data) {
        return self.trackEvent("engagement", campaignId, variationId, data);
      };

      window.abTrackGoal = function (goalName, campaignId, variationId, value) {
        // Find goal by name
        const goal = self.findGoalByName(goalName);

        if (!goal) {
          self.log("Goal not found:", goalName);
          return false;
        }

        return self.trackGoal(goal.id, goal.name, campaignId, variationId, value);
      };

      window.abGetActiveCampaigns = function () {
        return Array.from(self.state.activeCampaigns.keys());
      };

      window.abGetVisitorInfo = function () {
        return {
          visitorId: self.config.visitorId,
          userAgent: navigator.userAgent,
          screenSize: screen.width + "x" + screen.height,
          windowSize: window.innerWidth + "x" + window.innerHeight,
          referrer: document.referrer,
          timestamp: new Date().toISOString(),
        };
      };
    },

    /**
     * Initialize goal tracking (auto-tracking based on selectors, URLs, etc.)
     */
    initializeGoalTracking: function () {
      const self = this;

      // Check if goals are available
      if (!this.config.goals || this.config.goals.length === 0) {
        this.log("No goals configured for this page");
        return;
      }

      this.log("Initializing goal tracking for", this.config.goals.length, "goals");

      // Set up tracking for each goal
      this.config.goals.forEach(function (goal) {
        self.log("Setting up tracking for goal:", goal.name, "Method:", goal.tracking_method);

        if (goal.tracking_method === "selector" && goal.tracking_value) {
          self.initializeSelectorGoalTracking(goal);
        } else if (goal.tracking_method === "url" && goal.tracking_value) {
          self.initializeUrlGoalTracking(goal);
        }
      });
    },

    /**
     * Initialize CSS selector-based goal tracking
     */
    initializeSelectorGoalTracking: function (goal) {
      const self = this;
      const selector = goal.tracking_value;

      this.log("Setting up selector tracking for:", selector);

      // Track clicks on the selector within A/B test content
      $(document).on("click", selector, function (e) {
        const $clicked = $(this);
        const $container = $clicked.closest(".pronto-ab-content");

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            self.log("Selector goal triggered:", goal.name);
            self.trackGoal(goal.id, goal.name, campaignId, variationId, goal.default_value);
          }
        }
      });
    },

    /**
     * Initialize URL-based goal tracking
     */
    initializeUrlGoalTracking: function (goal) {
      const self = this;
      const urlPattern = goal.tracking_value;
      const currentUrl = window.location.href;
      const currentPath = window.location.pathname;

      // Check if current URL matches the pattern
      if (currentUrl.includes(urlPattern) || currentPath.includes(urlPattern)) {
        this.log("URL goal triggered:", goal.name);

        // Track for all active campaigns
        this.state.activeCampaigns.forEach(function (variations, campaignId) {
          variations.forEach(function (variation) {
            self.trackGoal(goal.id, goal.name, campaignId, variation.variationId, goal.default_value);
          });
        });
      }
    },

    /**
     * Track a goal completion
     */
    trackGoal: function (goalId, goalName, campaignId, variationId, value) {
      const self = this;

      // Create unique goal tracking key
      const goalKey = "goal_" + campaignId + "_" + variationId + "_" + goalId;

      // Check if already tracked this session (prevent duplicates)
      if (this.state.trackedEvents.has(goalKey)) {
        this.log("Goal already tracked this session:", goalName);
        return false;
      }

      this.log("Tracking goal:", goalName, "Value:", value);

      // Use dedicated goal tracking endpoint
      const trackingData = {
        action: "pronto_ab_track_goal",
        campaign_id: parseInt(campaignId),
        variation_id: parseInt(variationId),
        goal_id: parseInt(goalId),
        visitor_id: this.config.visitorId,
        nonce: this.config.nonce,
        goal_value: value || null,
        page_url: window.location.href,
        referrer: document.referrer || "",
        timestamp: Date.now(),
      };

      // Send tracking request
      $.post(this.config.ajaxUrl, trackingData)
        .done(function (response) {
          if (response.success) {
            self.state.trackedEvents.add(goalKey);
            self.log("Goal tracked successfully:", goalName);

            // Trigger custom event
            self.trigger("goalTracked", {
              goalId: goalId,
              goalName: goalName,
              campaignId: campaignId,
              variationId: variationId,
              value: value,
              response: response,
            });
          } else {
            self.log("Goal tracking failed:", response.data);
          }
        })
        .fail(function (xhr, status, error) {
          self.log("AJAX error tracking goal:", error);
        });

      return true;
    },

    /**
     * Find a goal by name
     */
    findGoalByName: function (goalName) {
      if (!this.config.goals) {
        return null;
      }

      for (let i = 0; i < this.config.goals.length; i++) {
        if (this.config.goals[i].name.toLowerCase() === goalName.toLowerCase()) {
          return this.config.goals[i];
        }
      }

      return null;
    },

    /**
     * Initialize general event listeners
     */
    initializeEventListeners: function () {
      const self = this;

      // Handle page unload
      $(window).on("beforeunload", function () {
        self.flushTrackingQueue();
      });

      // Handle AJAX complete events
      $(document).ajaxComplete(function () {
        // Re-discover campaigns after AJAX loads
        self.discoverActiveCampaigns();
      });

      // Handle dynamic content changes
      if (window.MutationObserver) {
        const observer = new MutationObserver(function (mutations) {
          let shouldRediscover = false;

          mutations.forEach(function (mutation) {
            if (mutation.type === "childList") {
              mutation.addedNodes.forEach(function (node) {
                if (
                  node.nodeType === Node.ELEMENT_NODE &&
                  (node.hasAttribute("data-campaign") ||
                    node.querySelector("[data-campaign]"))
                ) {
                  shouldRediscover = true;
                }
              });
            }
          });

          if (shouldRediscover) {
            self.discoverActiveCampaigns();
          }
        });

        observer.observe(document.body, {
          childList: true,
          subtree: true,
        });
      }
    },

    /**
     * Initialize offline handling
     */
    initializeOfflineHandling: function () {
      const self = this;

      $(window).on("online", function () {
        self.state.isOnline = true;
        self.flushTrackingQueue();
        self.log("Connection restored, flushing tracking queue");
      });

      $(window).on("offline", function () {
        self.state.isOnline = false;
        self.log("Connection lost, queuing tracking events");
      });
    },

    /**
     * Track an event with comprehensive error handling
     */
    trackEvent: function (eventType, campaignId, variationId, eventData) {
      const self = this;

      // Validate parameters
      if (!eventType || !campaignId || !variationId) {
        this.log("Error: Missing required parameters for trackEvent", {
          eventType: eventType,
          campaignId: campaignId,
          variationId: variationId,
        });
        return false;
      }

      // Create unique event key to prevent duplicates
      const eventKey = this.createEventKey(
        eventType,
        campaignId,
        variationId,
        eventData
      );

      if (this.state.trackedEvents.has(eventKey)) {
        this.log("Duplicate event prevented:", eventKey);
        return false;
      }

      // Prepare tracking data
      const trackingData = {
        action: "pronto_ab_track",
        campaign_id: parseInt(campaignId),
        variation_id: parseInt(variationId),
        event_type: eventType,
        visitor_id: this.config.visitorId,
        nonce: this.config.nonce,
        event_data: eventData || {},
        page_url: window.location.href,
        referrer: document.referrer || "",
        timestamp: Date.now(),
        user_agent: navigator.userAgent,
        screen_resolution: screen.width + "x" + screen.height,
        viewport_size: window.innerWidth + "x" + window.innerHeight,
      };

      // Queue or send immediately based on connection status
      if (this.state.isOnline) {
        this.sendTrackingData(trackingData, eventKey);
      } else {
        this.queueTrackingData(trackingData, eventKey);
      }

      return true;
    },

    /**
     * Send tracking data via AJAX
     */
    sendTrackingData: function (trackingData, eventKey) {
      const self = this;

      $.post(this.config.ajaxUrl, trackingData)
        .done(function (response) {
          if (response.success) {
            self.state.trackedEvents.add(eventKey);
            self.log("Event tracked successfully:", trackingData.event_type);

            // Trigger tracking success event
            self.trigger("eventTracked", {
              eventType: trackingData.event_type,
              campaignId: trackingData.campaign_id,
              variationId: trackingData.variation_id,
              data: trackingData.event_data,
              response: response,
            });
          } else {
            self.log("Event tracking failed:", response.data);

            // Retry failed events
            setTimeout(function () {
              self.sendTrackingData(trackingData, eventKey);
            }, 5000);
          }
        })
        .fail(function (xhr, status, error) {
          self.log("AJAX error tracking event:", error);

          // Queue for retry if network error
          if (xhr.status === 0 || xhr.status >= 500) {
            self.queueTrackingData(trackingData, eventKey);
          }
        });
    },

    /**
     * Queue tracking data for later sending
     */
    queueTrackingData: function (trackingData, eventKey) {
      this.state.trackingQueue.push({ data: trackingData, key: eventKey });
      this.log("Event queued for later:", trackingData.event_type);

      // Limit queue size
      if (this.state.trackingQueue.length > 100) {
        this.state.trackingQueue.shift(); // Remove oldest
      }

      // Store in localStorage for persistence
      try {
        localStorage.setItem(
          "pronto_ab_tracking_queue",
          JSON.stringify(this.state.trackingQueue)
        );
      } catch (e) {
        this.log("Failed to store tracking queue:", e);
      }
    },

    /**
     * Flush tracking queue when connection is restored
     */
    flushTrackingQueue: function () {
      const self = this;

      // Load queue from localStorage
      try {
        const storedQueue = localStorage.getItem("pronto_ab_tracking_queue");
        if (storedQueue) {
          const parsedQueue = JSON.parse(storedQueue);
          this.state.trackingQueue = this.state.trackingQueue.concat(
            parsedQueue
          );
        }
      } catch (e) {
        this.log("Failed to load tracking queue:", e);
      }

      // Send queued events
      while (this.state.trackingQueue.length > 0 && this.state.isOnline) {
        const queuedItem = this.state.trackingQueue.shift();
        this.sendTrackingData(queuedItem.data, queuedItem.key);
      }

      // Clear localStorage
      try {
        localStorage.removeItem("pronto_ab_tracking_queue");
      } catch (e) {
        this.log("Failed to clear tracking queue:", e);
      }
    },

    /**
     * Create unique event key for duplicate prevention
     */
    createEventKey: function (eventType, campaignId, variationId, eventData) {
      const dataString = JSON.stringify(eventData || {});
      const keyData = [eventType, campaignId, variationId, dataString].join(
        "_"
      );
      return btoa(keyData).replace(/[^a-zA-Z0-9]/g, "");
    },

    /**
     * Determine the type of click for tracking
     */
    determineClickType: function (element) {
      const tagName = element.tagName.toLowerCase();
      const type = element.type;
      const role = element.getAttribute("role");

      if (tagName === "a") {
        const href = element.getAttribute("href");
        if (!href || href === "#") return "anchor_no_link";
        if (href.startsWith("mailto:")) return "email_link";
        if (href.startsWith("tel:")) return "phone_link";
        if (href.startsWith("http")) return "external_link";
        return "internal_link";
      }

      if (tagName === "button" || role === "button") {
        return "button";
      }

      if (tagName === "input") {
        if (type === "submit") return "submit_button";
        if (type === "button") return "input_button";
        return "input_field";
      }

      return "other";
    },

    /**
     * Get element position relative to viewport
     */
    getElementPosition: function (element) {
      const rect = element.getBoundingClientRect();
      return {
        x: Math.round(rect.left),
        y: Math.round(rect.top),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
      };
    },

    /**
     * Trigger custom events
     */
    trigger: function (eventName, data) {
      const handlers = this.handlers.get(eventName) || [];
      handlers.forEach(function (handler) {
        try {
          handler(data);
        } catch (e) {
          console.error("Error in A/B test event handler:", e);
        }
      });

      // Also trigger as jQuery event
      $(document).trigger("prontoAB:" + eventName, [data]);
    },

    /**
     * Register event handlers
     */
    on: function (eventName, handler) {
      if (!this.handlers.has(eventName)) {
        this.handlers.set(eventName, []);
      }
      this.handlers.get(eventName).push(handler);
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
     * Get current state for debugging
     */
    getState: function () {
      return {
        isInitialized: this.state.isInitialized,
        activeCampaigns: Array.from(this.state.activeCampaigns.keys()),
        trackedEventsCount: this.state.trackedEvents.size,
        queuedEventsCount: this.state.trackingQueue.length,
        isOnline: this.state.isOnline,
        visitorId: this.config.visitorId,
      };
    },
  };

  /**
   * WordPress-specific integrations and compatibility
   */
  const WordPressIntegration = {
    /**
     * Initialize WordPress-specific tracking
     */
    init: function () {
      this.initWooCommerce();
      this.initEasyDigitalDownloads();
      this.initMembershipPlugins();
      this.initPageBuilders();
    },

    /**
     * WooCommerce integration
     */
    initWooCommerce: function () {
      // Track add to cart events
      $(document.body).on("added_to_cart", function (event, fragments, cart_hash, addToCartButton) {
        const $button = $(addToCartButton);
        const $container = $button.closest(".pronto-ab-content");

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const productId = $button.data("product_id") || "unknown";
            const eventData = {
              type: "wc_add_to_cart",
              productId: productId,
              cartHash: cart_hash,
              timestamp: Date.now(),
            };

            ProntoAB.trackEvent("conversion", campaignId, variationId, eventData);
            ProntoAB.log("WooCommerce add to cart tracked");
          }
        }
      });

      // Track checkout process
      if ($("body").hasClass("woocommerce-checkout")) {
        $(".pronto-ab-content").each(function () {
          const $container = $(this);
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "wc_checkout_view",
              timestamp: Date.now(),
            };

            ProntoAB.trackEvent("engagement", campaignId, variationId, eventData);
          }
        });
      }

      // Track order completion
      if ($("body").hasClass("woocommerce-order-received")) {
        $(".pronto-ab-content").each(function () {
          const $container = $(this);
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const orderValue = $(".woocommerce-order-overview .amount").text() || "";
            const eventData = {
              type: "wc_purchase",
              orderValue: orderValue,
              timestamp: Date.now(),
            };

            ProntoAB.trackEvent("conversion", campaignId, variationId, eventData);
          }
        });
      }
    },

    /**
     * Easy Digital Downloads integration
     */
    initEasyDigitalDownloads: function () {
      $(document).on("edd_cart_item_added", function (event, response) {
        $(".pronto-ab-content").each(function () {
          const $container = $(this);
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "edd_add_to_cart",
              downloadId: response.download_id || "",
              timestamp: Date.now(),
            };

            ProntoAB.trackEvent("conversion", campaignId, variationId, eventData);
          }
        });
      });
    },

    /**
     * Membership plugin integration
     */
    initMembershipPlugins: function () {
      // Track membership signup buttons
      $(document).on("click", ".pronto-ab-content .membership-signup, .pronto-ab-content .join-now", function () {
        const $button = $(this);
        const $container = $button.closest(".pronto-ab-content");

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "membership_signup_click",
              membershipLevel: $button.data("level") || "",
              timestamp: Date.now(),
            };

            ProntoAB.trackEvent("conversion", campaignId, variationId, eventData);
          }
        }
      });
    },

    /**
     * Page builder compatibility
     */
    initPageBuilders: function () {
      // Elementor compatibility
      if (window.elementorFrontend) {
        window.elementorFrontend.hooks.addAction("frontend/element_ready/global", function ($scope) {
          setTimeout(function () {
            ProntoAB.discoverActiveCampaigns();
          }, 100);
        });
      }

      // Divi compatibility
      if (window.et_pb_custom && window.et_pb_custom.is_builder_plugin_used) {
        $(window).on("et_pb_after_init_modules", function () {
          ProntoAB.discoverActiveCampaigns();
        });
      }

      // Beaver Builder compatibility
      if (window.FLBuilder) {
        window.FLBuilder.addHook("didRenderLayoutComplete", function () {
          ProntoAB.discoverActiveCampaigns();
        });
      }
    },
  };

  /**
   * Performance monitoring and optimization
   */
  const PerformanceMonitor = {
    metrics: {
      initTime: 0,
      trackingLatency: [],
      errorCount: 0,
    },

    init: function () {
      this.metrics.initTime = performance.now();
      this.monitorTracking();
      this.monitorErrors();
    },

    monitorTracking: function () {
      const self = this;

      ProntoAB.on("eventTracked", function (data) {
        const latency = Date.now() - data.timestamp;
        self.metrics.trackingLatency.push(latency);

        // Keep only last 50 measurements
        if (self.metrics.trackingLatency.length > 50) {
          self.metrics.trackingLatency.shift();
        }
      });
    },

    monitorErrors: function () {
      const self = this;
      const originalConsoleError = console.error;

      console.error = function () {
        if (arguments[0] && arguments[0].toString().includes("Pronto A/B")) {
          self.metrics.errorCount++;
        }
        originalConsoleError.apply(console, arguments);
      };
    },

    getMetrics: function () {
      const avgLatency = this.metrics.trackingLatency.length > 0
        ? this.metrics.trackingLatency.reduce((a, b) => a + b, 0) / this.metrics.trackingLatency.length
        : 0;

      return {
        initTime: this.metrics.initTime,
        averageTrackingLatency: Math.round(avgLatency),
        errorCount: this.metrics.errorCount,
        memoryUsage: performance.memory ? {
          used: Math.round(performance.memory.usedJSHeapSize / 1024 / 1024),
          total: Math.round(performance.memory.totalJSHeapSize / 1024 / 1024),
        } : null,
      };
    },
  };

  /**
   * Advanced tracking features
   */
  const AdvancedTracking = {
    init: function () {
      this.initHeatmapTracking();
      this.initMouseMovementTracking();
      this.initKeyboardTracking();
      this.initDeviceOrientationTracking();
    },

    /**
     * Basic heatmap-style click tracking
     */
    initHeatmapTracking: function () {
      if (!ProntoAB.config.trackEvents.heatmap) return;

      $(document).on("click", ".pronto-ab-content", function (e) {
        const $container = $(this);
        const campaignId = $container.data("campaign");
        const variationId = $container.data("variation");

        if (campaignId && variationId) {
          const rect = this.getBoundingClientRect();
          const x = ((e.clientX - rect.left) / rect.width) * 100;
          const y = ((e.clientY - rect.top) / rect.height) * 100;

          const eventData = {
            type: "heatmap_click",
            x: Math.round(x * 100) / 100,
            y: Math.round(y * 100) / 100,
            elementWidth: rect.width,
            elementHeight: rect.height,
            timestamp: Date.now(),
          };

          ProntoAB.trackEvent("engagement", campaignId, variationId, eventData);
        }
      });
    },

    /**
     * Mouse movement tracking (sampling)
     */
    initMouseMovementTracking: function () {
      if (!ProntoAB.config.trackEvents.mouse_movement) return;

      let mouseTrackingTimeout;
      let mouseMovements = [];

      $(document).on("mousemove", ".pronto-ab-content", function (e) {
        const $container = $(this);
        const campaignId = $container.data("campaign");
        const variationId = $container.data("variation");

        if (campaignId && variationId) {
          const rect = this.getBoundingClientRect();
          const x = e.clientX - rect.left;
          const y = e.clientY - rect.top;

          mouseMovements.push({ x: x, y: y, time: Date.now() });

          // Limit tracking data
          if (mouseMovements.length > 100) {
            mouseMovements.shift();
          }

          clearTimeout(mouseTrackingTimeout);
          mouseTrackingTimeout = setTimeout(function () {
            if (mouseMovements.length > 10) {
              const eventData = {
                type: "mouse_movement",
                movements: mouseMovements.slice(-20), // Last 20 movements
                duration: mouseMovements[mouseMovements.length - 1].time - mouseMovements[0].time,
                timestamp: Date.now(),
              };

              ProntoAB.trackEvent("engagement", campaignId, variationId, eventData);
              mouseMovements = [];
            }
          }, 2000);
        }
      });
    },

    /**
     * Keyboard interaction tracking
     */
    initKeyboardTracking: function () {
      if (!ProntoAB.config.trackEvents.keyboard) return;

      $(document).on("keydown", ".pronto-ab-content input, .pronto-ab-content textarea", function (e) {
        const $input = $(this);
        const $container = $input.closest(".pronto-ab-content");

        if ($container.length) {
          const campaignId = $container.data("campaign");
          const variationId = $container.data("variation");

          if (campaignId && variationId) {
            const eventData = {
              type: "keyboard_input",
              inputType: $input.attr("type") || "text",
              fieldName: $input.attr("name") || "",
              keyCode: e.keyCode,
              timestamp: Date.now(),
            };

            // Throttle keyboard tracking
            clearTimeout($input.data("keyTrackTimeout"));
            $input.data("keyTrackTimeout", setTimeout(function () {
              ProntoAB.trackEvent("engagement", campaignId, variationId, eventData);
            }, 1000));
          }
        }
      });
    },

    /**
     * Device orientation tracking (mobile)
     */
    initDeviceOrientationTracking: function () {
      if (!ProntoAB.config.trackEvents.device_orientation || !window.DeviceOrientationEvent) return;

      let lastOrientation = null;

      $(window).on("orientationchange", function () {
        const newOrientation = window.orientation;

        if (newOrientation !== lastOrientation) {
          lastOrientation = newOrientation;

          ProntoAB.state.activeCampaigns.forEach(function (variations, campaignId) {
            variations.forEach(function (variation) {
              const eventData = {
                type: "orientation_change",
                orientation: newOrientation,
                timestamp: Date.now(),
              };

              ProntoAB.trackEvent("engagement", campaignId, variation.variationId, eventData);
            });
          });
        }
      });
    },
  };

  /**
   * Utility functions
   */
  const Utilities = {
    /**
     * Debounce function to limit rapid firing
     */
    debounce: function (func, wait, immediate) {
      let timeout;
      return function () {
        const context = this;
        const args = arguments;
        const later = function () {
          timeout = null;
          if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
      };
    },

    /**
     * Throttle function to limit execution frequency
     */
    throttle: function (func, limit) {
      let inThrottle;
      return function () {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
          func.apply(context, args);
          inThrottle = true;
          setTimeout(() => (inThrottle = false), limit);
        }
      };
    },

    /**
     * Deep merge objects
     */
    deepMerge: function (target, source) {
      for (const key in source) {
        if (source[key] && typeof source[key] === "object" && !Array.isArray(source[key])) {
          if (!target[key]) target[key] = {};
          this.deepMerge(target[key], source[key]);
        } else {
          target[key] = source[key];
        }
      }
      return target;
    },

    /**
     * Generate unique ID
     */
    generateId: function () {
      return Date.now().toString(36) + Math.random().toString(36).substr(2);
    },

    /**
     * Check if element is visible in viewport
     */
    isInViewport: function (element) {
      const rect = element.getBoundingClientRect();
      return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
      );
    },

    /**
     * Get element text content safely
     */
    getTextContent: function (element) {
      return element.textContent || element.innerText || "";
    },

    /**
     * Sanitize string for use as CSS class
     */
    sanitizeForCSS: function (str) {
      return str.replace(/[^a-zA-Z0-9-_]/g, "").toLowerCase();
    },
  };

  /**
   * Initialize all systems when document is ready
   */
  $(document).ready(function () {
    // Initialize core A/B testing
    ProntoAB.init();

    // Initialize WordPress integrations
    WordPressIntegration.init();

    // Initialize performance monitoring
    PerformanceMonitor.init();

    // Initialize advanced tracking features
    AdvancedTracking.init();

    // Make utilities available globally
    window.ProntoABUtils = Utilities;

    // Log initialization complete
    if (ProntoAB.config.debug) {
      console.log("[Pronto A/B] All systems initialized");
      console.log("[Pronto A/B] Active campaigns:", ProntoAB.getState().activeCampaigns);
      console.log("[Pronto A/B] Performance metrics:", PerformanceMonitor.getMetrics());
    }

    // Trigger global ready event
    $(document).trigger("prontoAB:ready", [ProntoAB.getState()]);
  });

  /**
   * Handle page unload and cleanup
   */
  $(window).on("beforeunload", function () {
    // Flush any remaining tracking data
    ProntoAB.flushTrackingQueue();

    // Send final performance metrics if debug enabled
    if (ProntoAB.config.debug) {
      const metrics = PerformanceMonitor.getMetrics();
      ProntoAB.log("Final performance metrics:", metrics);
    }
  });

  /**
   * Expose main object globally for external access
   */
  window.ProntoABTracking = ProntoAB;

})(jQuery);