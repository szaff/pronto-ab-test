/**
 * Pronto A/B Testing - FULL Gutenberg Integration with All Required Stores
 */

(function () {
  "use strict";

  console.log("Loading FULL Gutenberg integration with all stores...");

  // Enhanced dependency checking including all required stores
  function checkAllDependencies() {
    const deps = {
      wp: !!window.wp,
      element: !!window.wp?.element,
      data: !!window.wp?.data,
      blocks: !!window.wp?.blocks,
      blockEditor: !!window.wp?.blockEditor,
      components: !!window.wp?.components,
      keyboardShortcuts: !!window.wp?.keyboardShortcuts,
      interface: !!window.wp?.interface,
      preferences: !!window.wp?.preferences,
    };

    const missing = Object.entries(deps)
      .filter(([key, exists]) => !exists)
      .map(([key]) => key);

    console.log("Full dependency check:", deps);

    if (missing.length === 0) {
      console.log("✓ ALL required dependencies available");
      return true;
    } else {
      console.warn("✗ Missing dependencies:", missing);
      return false;
    }
  }

  // Wait for all dependencies with better retry logic
  function waitForAllDependencies() {
    return new Promise((resolve, reject) => {
      let attempts = 0;
      const maxAttempts = 10;

      function checkDeps() {
        attempts++;
        console.log(`Full dependency check attempt ${attempts}/${maxAttempts}`);

        if (checkAllDependencies()) {
          console.log("All dependencies ready after", attempts, "attempts");
          resolve();
        } else if (attempts >= maxAttempts) {
          console.warn(
            "Some dependencies missing after",
            attempts,
            "attempts - proceeding anyway"
          );
          resolve(); // Proceed even with missing deps
        } else {
          setTimeout(checkDeps, 500);
        }
      }

      checkDeps();
    });
  }

  class FullGutenbergManager {
    constructor() {
      this.editors = new Map();
      this.isInitialized = false;
      console.log("FullGutenbergManager created");
    }

    async init() {
      try {
        console.log("Starting FULL Gutenberg initialization...");

        await waitForAllDependencies();
        await this.initBlockLibrary();
        this.initializeExistingEditors();
        this.setupEventListeners();

        this.isInitialized = true;
        console.log("✓ FULL Gutenberg initialization completed");
      } catch (error) {
        console.error("✗ Full initialization failed:", error);
        this.showAllFallbacks();
      }
    }

    async initBlockLibrary() {
      try {
        if (wp.blockLibrary?.registerCoreBlocks) {
          wp.blockLibrary.registerCoreBlocks();
          console.log("✓ Core blocks registered");
        }

        // Set default categories
        if (wp.blocks?.setCategories) {
          wp.blocks.setCategories([
            { slug: "text", title: "Text" },
            { slug: "media", title: "Media" },
            { slug: "design", title: "Design" },
            { slug: "widgets", title: "Widgets" },
            { slug: "embed", title: "Embeds" },
          ]);
        }
      } catch (error) {
        console.warn("Block library init warning:", error);
      }
    }

    initializeExistingEditors() {
      const containers = document.querySelectorAll(
        ".pronto-ab-gutenberg-editor"
      );
      console.log(`Found ${containers.length} editor containers`);

      containers.forEach((container, i) => {
        const index = container.dataset.variationIndex;
        console.log(`Initializing FULL editor for variation ${index}`);

        setTimeout(() => {
          this.createFullEditor(index, container);
        }, 200 + i * 150);
      });
    }

    async createFullEditor(variationIndex, container) {
      console.log(`Creating FULL editor for variation ${variationIndex}`);

      try {
        // Get all WordPress components
        const { createElement: el, useState, useEffect } = wp.element;
        const { useSelect, useDispatch } = wp.data;
        const { BlockEditorProvider, BlockList, BlockToolbar } = wp.blockEditor;
        const { SlotFillProvider, Popover } = wp.components;
        const { createBlock, parse, serialize } = wp.blocks;

        // Get content input
        const contentInput = container
          .closest(".pronto-ab-variation")
          ?.querySelector(".variation-content-input");

        // Parse initial content
        const initialContent = container.dataset.initialContent || "";
        let initialBlocks;

        try {
          initialBlocks = initialContent
            ? parse(initialContent)
            : [createBlock("core/paragraph")];
        } catch (parseError) {
          console.warn("Content parsing failed:", parseError);
          initialBlocks = [createBlock("core/paragraph")];
        }

        // ENHANCED: Create registry with ALL required stores
        const registry = wp.data.createRegistry();

        // Register ALL stores in correct order
        const storeRegistrations = [
          // Core stores (highest priority)
          { store: wp.coreData?.store, name: "Core Data" },
          { store: wp.blockEditor?.store, name: "Block Editor" },
          { store: wp.blocks?.store, name: "Blocks" },

          // Interface stores (critical for shortcuts)
          { store: wp.keyboardShortcuts?.store, name: "Keyboard Shortcuts" },
          { store: wp.interface?.store, name: "Interface" },
          { store: wp.preferences?.store, name: "Preferences" },

          // Additional stores
          { store: wp.notices?.store, name: "Notices" },
          { store: wp.viewport?.store, name: "Viewport" },
          { store: wp.richText?.store, name: "Rich Text" },

          // Edit post store (might contain shortcuts)
          { store: wp.editPost?.store, name: "Edit Post" },
        ];

        let registeredCount = 0;

        for (const { store, name } of storeRegistrations) {
          if (store) {
            try {
              registry.register(store);
              console.log(`✓ ${name} store registered`);
              registeredCount++;
            } catch (regError) {
              console.warn(`⚠️ Failed to register ${name} store:`, regError);
            }
          } else {
            console.warn(`⚠️ ${name} store not available`);
          }
        }

        console.log(`✓ ${registeredCount} stores registered successfully`);

        if (registeredCount < 3) {
          throw new Error(
            "Too few stores registered - minimum requirements not met"
          );
        }

        // Initialize blocks in the registry
        try {
          const dispatch = registry.dispatch("core/block-editor");
          if (dispatch && dispatch.resetBlocks) {
            dispatch.resetBlocks(initialBlocks);

            // Verify blocks were set
            const select = registry.select("core/block-editor");
            const testBlocks = select.getBlocks();
            console.log(
              `✓ Blocks initialized for variation ${variationIndex} (${testBlocks.length} blocks)`
            );
          } else {
            throw new Error("Block editor dispatch not available");
          }
        } catch (initError) {
          console.error("Block initialization failed:", initError);
          throw initError;
        }

        // ENHANCED: Full editor component with better error handling
        function FullEditorComponent() {
          const [hasError, setHasError] = useState(false);
          const [errorMessage, setErrorMessage] = useState("");

          // Get blocks with error handling
          const blocks = useSelect((select) => {
            try {
              return select("core/block-editor").getBlocks() || [];
            } catch (error) {
              console.warn("Error selecting blocks:", error);
              setErrorMessage("Block selection error: " + error.message);
              return [];
            }
          }, []);

          // Get dispatch with error handling
          const blockEditorDispatch = useDispatch("core/block-editor");

          if (!blockEditorDispatch) {
            console.error("Block editor dispatch not available");
            setHasError(true);
            setErrorMessage("Block editor dispatch not available");
          }

          const { updateBlocksWithUndo, resetBlocks } =
            blockEditorDispatch || {};
          const updateBlocks =
            updateBlocksWithUndo ||
            resetBlocks ||
            (() => {
              console.warn("No update function available");
            });

          // Update content when blocks change
          useEffect(() => {
            if (hasError) return;

            try {
              const content = serialize(blocks);
              if (contentInput) {
                contentInput.value = content;
                contentInput.dispatchEvent(
                  new Event("change", { bubbles: true })
                );
              }
            } catch (error) {
              console.warn("Serialization error:", error);
            }
          }, [blocks, hasError]);

          // Global error handler
          useEffect(() => {
            const handleError = (event) => {
              const error = event.error || event.reason;
              if (error && error.message) {
                if (error.message.includes("registerShortcut")) {
                  console.error("Keyboard shortcuts error:", error);
                  setHasError(true);
                  setErrorMessage("Keyboard shortcuts not available");
                  event.preventDefault();
                  return;
                }

                if (error.message.includes("useDispatch")) {
                  console.error("useDispatch error:", error);
                  setHasError(true);
                  setErrorMessage("Store dispatch error");
                  event.preventDefault();
                  return;
                }
              }
            };

            window.addEventListener("error", handleError);
            window.addEventListener("unhandledrejection", handleError);

            return () => {
              window.removeEventListener("error", handleError);
              window.removeEventListener("unhandledrejection", handleError);
            };
          }, []);

          if (hasError) {
            return el(
              "div",
              {
                style: {
                  padding: "20px",
                  border: "1px solid #dc3232",
                  background: "#fcf0f1",
                  color: "#721c24",
                  borderRadius: "4px",
                },
              },
              [
                el("p", { key: "title" }, "⚠️ Full Block Editor Error"),
                el("p", { key: "message" }, errorMessage),
                el(
                  "p",
                  { key: "fallback" },
                  "Falling back to simplified editor..."
                ),
              ]
            );
          }

          return el(
            BlockEditorProvider,
            {
              value: blocks,
              onInput: updateBlocks,
              onChange: updateBlocks,
              settings: {
                // Enhanced settings for full editor
                hasFixedToolbar: true,
                focusMode: false,
                hasReducedUI: false,
                disableCustomColors: false,
                disableCustomFontSizes: false,
                enableCustomLineHeight: true,
                enableCustomSpacing: true,
                supportsLayout: true,
                allowedBlockTypes: true, // Allow all blocks
                mediaUpload: true,
                bodyPlaceholder: "Start creating your variation content...",
              },
            },
            el(
              "div",
              {
                className: "pronto-ab-gutenberg-content",
                style: {
                  border: "1px solid #007cba",
                  borderRadius: "4px",
                  minHeight: "300px",
                  backgroundColor: "#fff",
                },
              },
              el(BlockList, {
                className: "pronto-ab-variation-blocks",
              })
            )
          );
        }

        // App wrapper with SlotFillProvider for full functionality
        function FullAppComponent() {
          return el(
            SlotFillProvider,
            null,
            el(Popover.Slot),
            el(FullEditorComponent)
          );
        }

        // Render the full editor
        try {
          wp.element.render(
            el(
              wp.data.RegistryProvider,
              { value: registry },
              el(FullAppComponent)
            ),
            container
          );

          console.log(
            `✓ FULL editor rendered successfully for variation ${variationIndex}`
          );
        } catch (renderError) {
          console.error("Full editor render failed:", renderError);

          // If render fails, try minimal fallback
          this.renderMinimalFallback(container, initialBlocks, contentInput);
        }

        // Store reference
        this.editors.set(variationIndex, { registry, container, contentInput });

        // Hide loading
        const loading = container.querySelector(".gutenberg-loading");
        if (loading) {
          loading.style.display = "none";
        }
      } catch (error) {
        console.error(
          `✗ Failed to create FULL editor for variation ${variationIndex}:`,
          error
        );
        this.renderMinimalFallback(container, null, null);
      }
    }

    renderMinimalFallback(container, initialBlocks, contentInput) {
      const { createElement: el } = wp.element;

      console.log("Rendering minimal fallback editor");

      const fallbackEditor = el(
        "div",
        {
          className: "pronto-ab-gutenberg-content",
          style: {
            border: "1px solid #ddd",
            borderRadius: "4px",
            minHeight: "200px",
            padding: "16px",
            backgroundColor: "#fff",
          },
        },
        [
          el(
            "div",
            {
              key: "notice",
              style: {
                background: "#fff3cd",
                border: "1px solid #ffeaa7",
                borderRadius: "2px",
                padding: "12px",
                marginBottom: "16px",
                color: "#856404",
              },
            },
            "Full block editor not available - using simplified mode"
          ),
          el("textarea", {
            key: "editor",
            placeholder: "Enter your HTML content here...",
            style: {
              width: "100%",
              minHeight: "150px",
              border: "1px solid #ddd",
              borderRadius: "2px",
              padding: "12px",
              fontFamily: "Monaco, Consolas, monospace",
              fontSize: "13px",
              resize: "vertical",
            },
            onChange: (e) => {
              if (contentInput) {
                contentInput.value = e.target.value;
                contentInput.dispatchEvent(
                  new Event("change", { bubbles: true })
                );
              }
            },
          }),
        ]
      );

      wp.element.render(fallbackEditor, container);

      const loading = container.querySelector(".gutenberg-loading");
      if (loading) {
        loading.style.display = "none";
      }
    }

    showAllFallbacks() {
      console.log("Showing fallbacks for all editors");

      document.querySelectorAll(".gutenberg-loading").forEach((loading) => {
        loading.innerHTML = `
          <div style="color: #d63638; padding: 16px; text-align: center; border: 1px solid #dc3232; background: #fcf0f1; border-radius: 4px;">
            <p><strong>⚠️ Full Block Editor Not Available</strong></p>
            <p>Your WordPress version may not support all required features.</p>
            <p><small>Using HTML editors for all variations.</small></p>
          </div>
        `;
      });

      document.querySelectorAll(".gutenberg-fallback").forEach((fallback) => {
        fallback.style.display = "block";
      });
    }

    setupEventListeners() {
      document.addEventListener("click", (e) => {
        if (e.target.classList.contains("preview-variation-content")) {
          const variation = e.target.closest(".pronto-ab-variation");
          const index = variation?.dataset.index;
          if (index) {
            this.previewContent(index);
          }
        }

        if (e.target.classList.contains("save-blocks")) {
          const variation = e.target.closest(".pronto-ab-variation");
          const index = variation?.dataset.index;
          if (index) {
            this.saveEditor(index, true);
          }
        }
      });
    }

    // Public API
    getEditorContent(variationIndex) {
      const editor = this.editors.get(variationIndex);
      if (editor) {
        try {
          const blocks = editor.registry
            .select("core/block-editor")
            .getBlocks();
          return wp.blocks.serialize(blocks);
        } catch (error) {
          console.warn(
            `Error getting content for variation ${variationIndex}:`,
            error
          );
        }
      }
      return null;
    }

    saveAllEditors() {
      this.editors.forEach((editor, index) => {
        this.saveEditor(index);
      });
    }

    saveEditor(variationIndex, showNotice = false) {
      const editor = this.editors.get(variationIndex);
      if (editor) {
        try {
          const blocks = editor.registry
            .select("core/block-editor")
            .getBlocks();
          const content = wp.blocks.serialize(blocks);

          if (editor.contentInput) {
            editor.contentInput.value = content;
            editor.contentInput.dispatchEvent(
              new Event("change", { bubbles: true })
            );
          }

          if (showNotice) {
            console.log(
              `✓ Full editor content saved for variation ${variationIndex}`
            );
          }
        } catch (error) {
          console.warn(`Error saving full editor ${variationIndex}:`, error);
        }
      }
    }

    previewContent(variationIndex) {
      const content = this.getEditorContent(variationIndex);
      if (content && content.trim()) {
        const preview = window.open("", "preview", "width=800,height=600");
        preview.document.write(`
          <html>
            <head>
              <title>Variation Preview</title>
              <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; line-height: 1.6; max-width: 800px; margin: 0 auto; }
                .preview-content { border: 1px solid #ddd; padding: 20px; background: #fff; border-radius: 4px; }
                h1, h2, h3, h4, h5, h6 { margin-top: 0; color: #333; }
                .wp-block { margin-bottom: 1em; }
                .wp-block:last-child { margin-bottom: 0; }
              </style>
            </head>
            <body>
              <h2>Variation ${variationIndex} Preview</h2>
              <div class="preview-content">${content}</div>
            </body>
          </html>
        `);
        preview.document.close();
      } else {
        alert("No content to preview. Add some content first!");
      }
    }

    destroyEditor(variationIndex) {
      const editor = this.editors.get(variationIndex);
      if (editor) {
        try {
          wp.element.unmountComponentAtNode(editor.container);
          this.editors.delete(variationIndex);
          console.log(
            `✓ Full editor destroyed for variation ${variationIndex}`
          );
        } catch (error) {
          console.warn(
            `Error destroying full editor ${variationIndex}:`,
            error
          );
        }
      }
    }
  }

  // Initialize when DOM is ready
  function initializeWhenReady() {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init);
    } else {
      init();
    }

    function init() {
      console.log("DOM ready, checking for FULL Gutenberg editors...");

      if (document.querySelector(".pronto-ab-gutenberg-editor")) {
        console.log("Editor containers found, starting FULL initialization...");

        window.prontoABGutenbergManager = new FullGutenbergManager();
        window.prontoABGutenbergManager.init();

        // Enhanced debugging
        window.debugFullGutenberg = () => {
          console.group("🔍 Full Gutenberg Debug Info");
          console.log("Manager:", window.prontoABGutenbergManager);
          console.log("Editors:", window.prontoABGutenbergManager?.editors);

          if (wp.data) {
            try {
              const coreDataStore = wp.data.select("core/data");
              console.log(
                "Available stores:",
                Object.keys(coreDataStore.getSelectors())
              );
            } catch (e) {
              console.log("Could not get store list:", e.message);
            }
          }

          console.log("WordPress dependencies:", {
            wp: !!window.wp,
            blocks: !!window.wp?.blocks,
            element: !!window.wp?.element,
            data: !!window.wp?.data,
            blockEditor: !!window.wp?.blockEditor,
            components: !!window.wp?.components,
            keyboardShortcuts: !!window.wp?.keyboardShortcuts,
            interface: !!window.wp?.interface,
            preferences: !!window.wp?.preferences,
            editPost: !!window.wp?.editPost,
          });
          console.groupEnd();
        };
      } else {
        console.log("No editor containers found");
      }
    }
  }

  // Start initialization
  initializeWhenReady();
})();
