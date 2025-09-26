/**
 * WordPress Editor Integration for Pronto A/B Testing
 * Save as: assets/js/pronto-ab-wp-editor.js
 */
(function ($) {
  "use strict";

  const ProntoABWPEditor = {
    variationIndex: 0,
    editorSettings: window.prontoABWPEditor?.editorSettings || {},

    init: function () {
      this.variationIndex = $(".pronto-ab-variation").length;
      this.bindEvents();
      console.log("Pronto A/B WordPress Editor initialized");
    },

    bindEvents: function () {
      // Handle add variation button
      $("#add-variation")
        .off("click.wp-editor")
        .on("click.wp-editor", (e) => {
          e.preventDefault();
          this.addVariationWithEditor();
        });

      // Handle remove variation
      $(document)
        .off("click.wp-editor", ".remove-variation")
        .on("click.wp-editor", ".remove-variation", (e) => {
          e.preventDefault();
          this.removeVariationWithEditor($(e.target));
        });

      // Handle editor mode toggle
      $(document)
        .off("click.wp-editor", ".toggle-editor-mode")
        .on("click.wp-editor", ".toggle-editor-mode", (e) => {
          e.preventDefault();
          this.toggleEditorMode($(e.target));
        });

      // Handle content preview
      $(document)
        .off("click.wp-editor", ".preview-variation-content")
        .on("click.wp-editor", ".preview-variation-content", (e) => {
          e.preventDefault();
          this.previewVariationContent($(e.target));
        });

      // Auto-save functionality
      this.setupAutoSave();
    },

    addVariationWithEditor: function () {
      const template = this.getVariationTemplate(this.variationIndex);
      $("#pronto-ab-variations").append(template);

      // Request editor creation via AJAX
      this.createEditor(this.variationIndex);

      this.variationIndex++;

      // Show animation
      $(".pronto-ab-variation").last().hide().slideDown(300);
    },

    createEditor: function (index) {
      $.post(ajaxurl, {
        action: "pronto_ab_create_variation_editor",
        variation_index: index,
        nonce: window.prontoABWPEditor.nonce,
      })
        .done((response) => {
          if (response.success) {
            const $container = $(
              `.pronto-ab-variation[data-index="${index}"] .variation-wp-editor`
            );
            $container.html(response.data.editor_html);

            // Initialize TinyMCE for the new editor
            if (typeof tinyMCE !== "undefined") {
              tinyMCE.execCommand(
                "mceAddEditor",
                false,
                response.data.editor_id
              );
            }

            // Initialize QuickTags
            if (typeof QTags !== "undefined") {
              QTags.addButton(
                response.data.editor_id,
                "strong",
                "B",
                "<strong>",
                "</strong>",
                "Bold"
              );
            }

            console.log("Editor created for variation", index);
          } else {
            console.error("Failed to create editor:", response.data);
          }
        })
        .fail(() => {
          console.error(
            "AJAX failed: Could not create editor for variation",
            index
          );
        });
    },

    removeVariationWithEditor: function ($button) {
      const $variation = $button.closest(".pronto-ab-variation");
      const index = $variation.data("index");
      const variationName =
        $variation.find(".variation-name").val() || "Unnamed";

      if (!confirm(`Are you sure you want to remove "${variationName}"?`)) {
        return;
      }

      // Remove TinyMCE editor instance
      const editorId = `variation_content_${index}`;
      if (typeof tinyMCE !== "undefined" && tinyMCE.get(editorId)) {
        tinyMCE.execCommand("mceRemoveEditor", false, editorId);
      }

      $variation.slideUp(300, function () {
        $(this).remove();
      });
    },

    toggleEditorMode: function ($button) {
      const editorId = $button.data("editor-id");

      if (typeof switchEditors !== "undefined") {
        switchEditors.go(editorId);
      } else {
        console.warn("switchEditors not available");
      }
    },

    previewVariationContent: function ($button) {
      const $variation = $button.closest(".pronto-ab-variation");
      const index = $variation.data("index");
      const editorId = `variation_content_${index}`;

      let content = "";

      // Get content from active editor mode
      if (
        typeof tinyMCE !== "undefined" &&
        tinyMCE.get(editorId) &&
        !tinyMCE.get(editorId).isHidden()
      ) {
        content = tinyMCE.get(editorId).getContent();
      } else {
        content = $(`#${editorId}`).val();
      }

      this.showPreviewModal(content);
    },

    showPreviewModal: function (content) {
      const modal = `
                <div class="variation-preview-modal">
                    <div class="variation-preview-content">
                        <div class="variation-preview-header">
                            <h3>Content Preview</h3>
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

    setupAutoSave: function () {
      let autoSaveTimer;

      // Auto-save when content changes
      $(document).on(
        "input change",
        'textarea[name*="variations"]',
        function () {
          clearTimeout(autoSaveTimer);
          autoSaveTimer = setTimeout(() => {
            if (window.ProntoABAdmin && window.ProntoABAdmin.autoSaveCampaign) {
              window.ProntoABAdmin.autoSaveCampaign();
            }
          }, 30000); // 30 seconds
        }
      );

      // Save before page unload
      window.addEventListener("beforeunload", () => {
        this.saveAllEditors();
      });
    },

    saveAllEditors: function () {
      $(".pronto-ab-variation").each(function () {
        const index = $(this).data("index");
        const editorId = `variation_content_${index}`;

        // Sync TinyMCE content to textarea
        if (typeof tinyMCE !== "undefined" && tinyMCE.get(editorId)) {
          tinyMCE.get(editorId).save();
        }
      });
    },

    getVariationTemplate: function (index) {
      const variationLetter = String.fromCharCode(65 + (index % 26)); // A, B, C, etc.

      return `
                <div class="pronto-ab-variation" data-index="${index}">
                    <div class="variation-header">
                        <h4>
                            <span class="variation-title">Variation ${variationLetter}</span>
                        </h4>
                        <div class="variation-actions">
                            <button type="button" class="button-link preview-variation" title="Preview">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button type="button" class="button-link-delete remove-variation">
                                Remove
                            </button>
                        </div>
                    </div>
                    <div class="variation-content">
                        <input type="hidden" name="variations[${index}][id]" value="">
                        <input type="hidden" name="variations[${index}][is_control]" value="0">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="variation_name_${index}">Name *</label>
                                </th>
                                <td>
                                    <input type="text" id="variation_name_${index}" 
                                           name="variations[${index}][name]" 
                                           value="Variation ${variationLetter}"
                                           class="regular-text variation-name" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label>Content</label>
                                </th>
                                <td>
                                    <div class="variation-wp-editor">
                                        <div class="editor-loading">
                                            <span class="spinner is-active"></span>
                                            <span>Loading editor...</span>
                                        </div>
                                    </div>
                                    <div class="variation-editor-tools">
                                        <button type="button" class="button preview-variation-content">
                                            Preview Content
                                        </button>
                                        <button type="button" class="button toggle-editor-mode" data-editor-id="variation_content_${index}">
                                            Text/Visual
                                        </button>
                                    </div>
                                    <p class="description">
                                        Create your variation content using the rich text editor above.
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
  };

  // Initialize when document is ready
  $(document).ready(function () {
    if ($(".pronto-ab-variation").length || $("#add-variation").length) {
      ProntoABWPEditor.init();
    }
  });

  // Make globally available
  window.ProntoABWPEditor = ProntoABWPEditor;
})(jQuery);
