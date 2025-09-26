/**
 * Pronto A/B Testing - Working Gutenberg Block Editor Integration
 */

(function () {
  "use strict";

  console.log("Gutenberg integration script loaded");

  // Simple dependency check
  const checkDependencies = () => {
    const required = {
      wp: window.wp,
      "wp.element": window.wp?.element,
      "wp.data": window.wp?.data,
      "wp.blocks": window.wp?.blocks,
      "wp.blockEditor": window.wp?.blockEditor,
      "wp.components": window.wp?.components,
    };

    const missing = [];
    for (const [name, obj] of Object.entries(required)) {
      if (!obj) {
        missing.push(name);
      }
    }

    if (missing.length === 0) {
      console.log("✓ All WordPress dependencies available");
      return true;
    } else {
      console.error("✗ Missing WordPress dependencies:", missing);
      return false;
    }
  };

  // Wait for dependencies
  const waitForWP = () => {
    return new Promise((resolve, reject) => {
      let attempts = 0;
      const maxAttempts = 30;

      const check = () => {
        attempts++;

        if (checkDependencies()) {
          resolve();
        } else if (attempts >= maxAttempts) {
          reject(new Error("WordPress dependencies timeout"));
        } else {
          setTimeout(check, 100);
        }
      };

      check();
    });
  };

  /**
   * Working Gutenberg Manager
   */
  class WorkingGutenbergManager {
    constructor() {
      this.editors = new Map();
      console.log("WorkingGutenbergManager created");
    }

    async init() {
      console.log("Starting Gutenberg initialization...");

      try {
        await waitForWP();

        // Register core blocks
        if (wp.blockLibrary && wp.blockLibrary.registerCoreBlocks) {
          wp.blockLibrary.registerCoreBlocks();
          console.log("Core blocks registered");
        }

        this.initializeExistingEditors();
        this.watchForNewVariations();
        this.setupEventListeners();

        console.log("✓ Gutenberg initialization completed");
      } catch (error) {
        console.error("✗ Gutenberg initialization failed:", error);
        this.showAllFallbacks();
      }
    }

    initializeExistingEditors() {
      const containers = document.querySelectorAll(
        ".pronto-ab-gutenberg-editor"
      );
      console.log(`Found ${containers.length} editor containers`);

      containers.forEach((container, i) => {
        const index = container.dataset.variationIndex;
        console.log(`Initializing editor ${i + 1} for variation ${index}`);

        setTimeout(() => {
          this.createEditor(index, container);
        }, 100 + i * 100);
      });
    }

    watchForNewVariations() {
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1) {
              const container = node.querySelector?.(
                ".pronto-ab-gutenberg-editor"
              );
              if (container) {
                const index = container.dataset.variationIndex;
                console.log(
                  `New editor container detected for variation ${index}`
                );
                setTimeout(() => {
                  this.createEditor(index, container);
                }, 300);
              }
            }
          });
        });
      });

      const variationsContainer = document.getElementById(
        "pronto-ab-variations"
      );
      if (variationsContainer) {
        observer.observe(variationsContainer, {
          childList: true,
          subtree: true,
        });
      }
    }

    async createEditor(variationIndex, container) {
      console.log(`Creating editor for variation ${variationIndex}`);

      try {
        // Get WordPress components
        const { createElement: el } = wp.element;
        const { BlockEditorProvider, BlockList } = wp.blockEditor;
        const { SlotFillProvider } = wp.components;
        const { createBlock, parse, serialize } = wp.blocks;

        // Get content input
        const contentInput = container
          .closest(".pronto-ab-variation")
          ?.querySelector(".variation-content-input");

        // Initial content
        const initialContent = container.dataset.initialContent || "";
        const initialBlocks = initialContent
          ? parse(initialContent)
          : [createBlock("core/paragraph")];

        // FIXED: Proper store registration with error checking
        const registry = wp.data.createRegistry();

        // Check if stores exist before registering
        if (wp.blockEditor.store) {
          registry.register(wp.blockEditor.store);
          console.log("Block editor store registered");
        } else if (wp.blockEditor.storeConfig) {
          registry.registerStore(
            "core/block-editor",
            wp.blockEditor.storeConfig
          );
          console.log("Block editor store registered via storeConfig");
        } else {
          throw new Error("Block editor store not available");
        }

        if (wp.blocks.store) {
          registry.register(wp.blocks.store);
          console.log("Blocks store registered");
        } else if (wp.blocks.storeConfig) {
          registry.registerStore("core/blocks", wp.blocks.storeConfig);
          console.log("Blocks store registered via storeConfig");
        } else {
          console.warn("Blocks store not available");
        }

        // Optional stores (don't fail if missing)
        try {
          if (wp.notices?.store) {
            registry.register(wp.notices.store);
          } else if (wp.notices?.storeConfig) {
            registry.registerStore("core/notices", wp.notices.storeConfig);
          }
        } catch (noticeError) {
          console.warn("Notices store not available:", noticeError);
        }

        // Initialize editor state - FIXED with error checking
        try {
          registry.dispatch("core/block-editor").resetBlocks(initialBlocks);
          console.log(`Blocks initialized for variation ${variationIndex}`);
        } catch (dispatchError) {
          console.error("Failed to initialize blocks:", dispatchError);
          // Try alternative initialization
          registry
            .dispatch("core/block-editor")
            .updateBlocksWithUndo(initialBlocks);
        }

        // FIXED: Simpler editor component with better error handling
        const SimpleEditor = () => {
          const { useSelect, useDispatch } = wp.data;

          const blocks = useSelect((select) => {
            try {
              return select("core/block-editor").getBlocks() || [];
            } catch (selectError) {
              console.warn("Error selecting blocks:", selectError);
              return [];
            }
          }, []);

          const { updateBlocks, resetBlocks } =
            useDispatch("core/block-editor");

          // Update hidden input when blocks change
          wp.element.useEffect(() => {
            try {
              const content = serialize(blocks);
              if (contentInput) {
                contentInput.value = content;
                contentInput.dispatchEvent(
                  new Event("change", { bubbles: true })
                );
              }
            } catch (serializeError) {
              console.warn("Serialization error:", serializeError);
            }
          }, [blocks]);

          return el(
            SlotFillProvider,
            null,
            el(
              BlockEditorProvider,
              {
                value: blocks,
                onInput: updateBlocks,
                onChange: updateBlocks,
                settings: {
                  hasFixedToolbar: true,
                  focusMode: false,
                },
              },
              el(
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
                el(BlockList, {
                  className: "pronto-ab-variation-blocks",
                })
              )
            )
          );
        };

        // Render editor with error boundary
        try {
          wp.element.render(
            el(wp.data.RegistryProvider, { value: registry }, el(SimpleEditor)),
            container
          );
          console.log(`Editor rendered for variation ${variationIndex}`);
        } catch (renderError) {
          console.error("Render error:", renderError);
          throw renderError;
        }

        // Store reference
        this.editors.set(variationIndex, { registry, container, contentInput });

        // Hide loading
        const loading = container.querySelector(".gutenberg-loading");
        if (loading) {
          loading.style.display = "none";
        }

        console.log(
          `✓ Editor created successfully for variation ${variationIndex}`
        );
      } catch (error) {
        console.error(
          `✗ Failed to create editor for variation ${variationIndex}:`,
          error
        );
        this.showFallback(variationIndex, container);
      }
    }

    showFallback(variationIndex, container) {
      const loading = container.querySelector(".gutenberg-loading");
      const variation = container.closest(".pronto-ab-variation");
      const fallback = variation?.querySelector(".gutenberg-fallback");

      if (loading) {
        loading.innerHTML = `
                    <div style="color: #d63638; padding: 20px; text-align: center;">
                        <p><strong>Block editor failed to load</strong></p>
                        <p>Using HTML editor instead.</p>
                    </div>
                `;
      }

      if (fallback) {
        fallback.style.display = "block";
      }
    }

    showAllFallbacks() {
      document.querySelectorAll(".gutenberg-loading").forEach((loading) => {
        loading.innerHTML = `
                    <div style="color: #d63638; padding: 20px; text-align: center;">
                        <p><strong>Block editor initialization failed</strong></p>
                        <p>Using HTML editors instead.</p>
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

    previewContent(variationIndex) {
      const content = this.getEditorContent(variationIndex);
      if (content && content.trim()) {
        const preview = window.open("", "preview", "width=600,height=400");
        preview.document.write(`
                    <html>
                    <head><title>Variation Preview</title></head>
                    <body style="font-family: Arial; padding: 20px; line-height: 1.6;">
                        <h3>Content Preview</h3>
                        <div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
                            ${content}
                        </div>
                    </body>
                    </html>
                `);
        preview.document.close();
      } else {
        alert("No content to preview");
      }
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
          console.error(
            `Error getting content for variation ${variationIndex}:`,
            error
          );
        }
      }
      return null;
    }

    destroyEditor(variationIndex) {
      const editor = this.editors.get(variationIndex);
      if (editor) {
        try {
          wp.element.unmountComponentAtNode(editor.container);
          this.editors.delete(variationIndex);
          console.log(`Editor destroyed for variation ${variationIndex}`);
        } catch (error) {
          console.error(`Error destroying editor ${variationIndex}:`, error);
        }
      }
    }

    saveAllEditors() {
      this.editors.forEach((editor, index) => {
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
        } catch (error) {
          console.error(`Error saving editor ${index}:`, error);
        }
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
            console.log(`Content saved for variation ${variationIndex}`);
          }
        } catch (error) {
          console.error(`Error saving editor ${variationIndex}:`, error);
        }
      }
    }
  }

  // Initialize when DOM is ready
  document.addEventListener("DOMContentLoaded", () => {
    console.log("DOM ready, checking for Gutenberg editors...");

    if (document.querySelector(".pronto-ab-gutenberg-editor")) {
      console.log("Gutenberg editor containers found, initializing...");
      window.prontoABGutenbergManager = new WorkingGutenbergManager();
      window.prontoABGutenbergManager.init();
    } else {
      console.log("No Gutenberg editor containers found");
    }
  });
})();
