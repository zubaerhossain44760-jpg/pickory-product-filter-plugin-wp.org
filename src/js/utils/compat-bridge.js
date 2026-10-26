/**
 * Pickory Product Filter - Compatibility Bridge
 *
 * Dispatches agnostic lifecycle signals after AJAX replaces product HTML,
 * allowing any listening plugin or theme to re-initialize its components.
 */

export const runCompatBridge = () => {
  // A small timeout ensures the browser's DOM parser has finished painting
  // the new innerHTML before other scripts query for their elements.
  setTimeout(() => {
    // 1. Native Event — standard "content loaded via AJAX" signal
    document.body.dispatchEvent(new Event("post-load"));

    // 2. jQuery Bridge — legacy path for plugins that listen via jQuery
    if (window.jQuery) {
      window.jQuery(document.body).trigger("post-load");
    }

    // 3. Custom Extension Point
    document.body.dispatchEvent(
      new CustomEvent("ppfx:compat_bridge_run", {
        bubbles: true,
        detail: { timestamp: Date.now() },
      }),
    );
  }, 50);
};
