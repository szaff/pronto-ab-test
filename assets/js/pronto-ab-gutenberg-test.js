/**
 * Simple test script to verify loading
 */

console.log("🚀 Pronto A/B Gutenberg TEST script loaded successfully!");

// Check WordPress dependencies
console.log("WordPress dependency check:", {
  wp: !!window.wp,
  blocks: !!window.wp?.blocks,
  element: !!window.wp?.element,
  data: !!window.wp?.data,
  blockEditor: !!window.wp?.blockEditor,
  components: !!window.wp?.components,
});

// Show alert to confirm loading
alert("Gutenberg test script loaded! Check console for details.");

// Simple DOM ready check
document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM ready - looking for Gutenberg containers...");

  const containers = document.querySelectorAll(".pronto-ab-gutenberg-editor");
  console.log(`Found ${containers.length} Gutenberg editor containers`);

  // Replace loading text with success message
  containers.forEach((container, index) => {
    const loading = container.querySelector(".gutenberg-loading");
    if (loading) {
      loading.innerHTML = `
                <div style="background: #d1edff; border: 1px solid #0073aa; padding: 20px; text-align: center; border-radius: 4px;">
                    <h3>✅ Test Script Working!</h3>
                    <p>Container ${index + 1} detected successfully.</p>
                    <p>Check browser console for WordPress dependency status.</p>
                </div>
            `;
    }
  });
});
