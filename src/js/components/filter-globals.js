import { applyFilterState, applySortState, PPFX_URL_State } from "../utils/url";

/**
 * Global registry of all active filter form instances on the page.
 *
 * Enables coordinated event delegation across multiple filter widgets.
 *
 * @type {Set<object>}
 */
export const filterFormInstances = new Set();

/**
 * Query params that are managed by the sort/pagination system,
 * not by the filter form state machine.
 *
 * @type {Set<string>}
 */
export const RESERVED_FILTER_KEYS = new Set([
  "orderby",
  "order",
  "page",
  "paged",
]);

let urlListenerRegistered = false;

// ---------------------------------------------------------------------------
// Global event handlers — delegated once for all instances
// ---------------------------------------------------------------------------

const handleGlobalPaginationClick = (event) => {
  for (const instance of Array.from(filterFormInstances)) {
    if (instance.onPaginationClick(event)) {
      break;
    }
  }
};

const handleGlobalSortChange = (event) => {
  for (const instance of Array.from(filterFormInstances)) {
    if (instance.onSortChange(event)) {
      break;
    }
  }
};

/**
 * Handle unified URL changes (both synthetic and popstate).
 *
 * @param {CustomEvent} event
 */
const handleUnifiedUrlChange = (event) => {
  const { params, source } = event.detail || {};
  syncAllUIs(params);

  // Dedup: Trigger data update on a single coordinator instance to avoid duplicate AJAX calls
  const instances = Array.from(filterFormInstances);
  const fetcherInstance =
    instances.find((inst) => inst.isPrimary) || instances[0];
  if (
    fetcherInstance &&
    typeof fetcherInstance.onUrlStateChange === "function"
  ) {
    fetcherInstance.onUrlStateChange(params, source);
  }
};

/**
 * Register a filter form instance into the global registry.
 *
 * Attaches document-level event listeners on first registration.
 *
 * @param {object} instance
 */
export const registerGlobalInstance = (instance) => {
  if (filterFormInstances.size === 0) {
    document.addEventListener("click", handleGlobalPaginationClick);
    document.addEventListener("change", handleGlobalSortChange, true);
  }

  if (!urlListenerRegistered) {
    window.addEventListener("ppfx:url-change", handleUnifiedUrlChange);
    urlListenerRegistered = true;
  }

  filterFormInstances.add(instance);
};

/**
 * Remove a filter form instance from the global registry.
 *
 * @param {object} instance
 */
export const unregisterGlobalInstance = (instance) => {
  filterFormInstances.delete(instance);

  if (filterFormInstances.size === 0) {
    document.removeEventListener("click", handleGlobalPaginationClick);
    document.removeEventListener("change", handleGlobalSortChange, true);

    if (urlListenerRegistered) {
      window.removeEventListener("ppfx:url-change", handleUnifiedUrlChange);
      urlListenerRegistered = false;
    }
  }
};

// ---------------------------------------------------------------------------
// Global state sync — driven by URL as Single Source of Truth
// ---------------------------------------------------------------------------

/**
 * Push the canonical URL filter/sort state into every registered instance's UI controls.
 *
 * @param {Record<string, any>} [providedState] Optional pre-parsed canonical state
 */
export const syncAllUIs = (providedState = null) => {
  const canonical = providedState || PPFX_URL_State.getCanonicalState();

  for (const instance of filterFormInstances) {
    applyFilterState(instance.formElement, canonical);
  }

  // Also sync document-level sort selects (such as WooCommerce loop toolbar)
  if (typeof document !== "undefined") {
    applySortState(document, {
      orderby: canonical.orderby || "",
      order: canonical.order || "",
    });
  }

  document.dispatchEvent(
    new CustomEvent("ppfx-filters-updated", { detail: { state: canonical } }),
  );
};

/**
 * Read the current URL search params and return canonical state.
 *
 * @returns {Record<string, any>}
 */
export const syncStateFromUrl = () => {
  const canonical = PPFX_URL_State.getCanonicalState();

  // Merge current taxonomy archive context if present and not overridden
  if (window.ppfxFilterData && window.ppfxFilterData.currentContext) {
    Object.entries(window.ppfxFilterData.currentContext).forEach(
      ([key, val]) => {
        if (key === "__proto__" || key === "constructor" || key === "prototype")
          return;
        if (!canonical[key]) {
          canonical[key] = val;
        }
      },
    );
  }

  syncAllUIs(canonical);
  return canonical;
};

/**
 * Trigger WooCommerce fragment-refresh events so third-party plugins
 * (wishlists, mini-carts, etc.) re-initialize after an AJAX product update.
 */
export const triggerWooCommerceEvents = () => {
  if (!window.jQuery) {
    return;
  }

  const $body = window.jQuery(document.body);
  $body.trigger("wc_fragments_refreshed");
  $body.trigger("yith_infs_added_elem");
};
