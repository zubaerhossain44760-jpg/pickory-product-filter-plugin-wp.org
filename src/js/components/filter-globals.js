import { applyFilterState, applySortState, toInternalParams, FILTER_KEYS } from "../utils/url";

/**
 * Global registry of all active filter form instances on the page.
 *
 * Enables coordinated event delegation (pagination clicks, sort changes,
 * popstate) across multiple filter widgets without each instance adding
 * its own top-level listeners.
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
export const RESERVED_FILTER_KEYS = new Set(["orderby", "order", "page", "paged"]);

/** @type {boolean} */
let popstateRegistered = false;

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
 * Register a filter form instance into the global registry.
 *
 * Attaches document-level event listeners on first registration and the
 * popstate listener once, regardless of how many instances exist.
 *
 * @param {object} instance
 */
export const registerGlobalInstance = (instance) => {
  if (filterFormInstances.size === 0) {
    document.addEventListener("click", handleGlobalPaginationClick);
    document.addEventListener("change", handleGlobalSortChange, true);
  }

  if (!popstateRegistered) {
    window.addEventListener("popstate", handleGlobalPopState);
    popstateRegistered = true;
  }

  filterFormInstances.add(instance);
};

/**
 * Remove a filter form instance from the global registry.
 *
 * Removes document-level listeners when the last instance is destroyed.
 *
 * @param {object} instance
 */
export const unregisterGlobalInstance = (instance) => {
  filterFormInstances.delete(instance);

  if (filterFormInstances.size === 0) {
    document.removeEventListener("click", handleGlobalPaginationClick);
    document.removeEventListener("change", handleGlobalSortChange, true);

    if (popstateRegistered) {
      window.removeEventListener("popstate", handleGlobalPopState);
      popstateRegistered = false;
    }
  }
};

// ---------------------------------------------------------------------------
// Global state sync
// ---------------------------------------------------------------------------

/**
 * Push the current global filter/sort state back into every registered
 * instance's UI controls. Called after any shared state mutation
 * (filter change, reset, popstate).
 */
export const syncAllUIs = () => {
  for (const instance of filterFormInstances) {
    applyFilterState(instance.formElement, window.ppfxGlobalState.filters);
    applySortState(instance.root, window.ppfxGlobalState.sort);
  }
};

/**
 * Read the current URL search params and hydrate window.ppfxGlobalState.
 *
 * Merges any taxonomy archive context (e.g. on a /product-category/ page)
 * so that the implicit category constraint is preserved in state even when
 * not present as an explicit URL param.
 */
export const syncStateFromUrl = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const internalParams = toInternalParams(urlParams);
  const filters = {};

  const rawFilterKeys = window.ppfxFilterData?.filterKeys || FILTER_KEYS;
  // Convert public filterKeys to internal keys for comparison
  const internalFilterKeys = new Set(
    rawFilterKeys.map(key => {
      const converted = toInternalParams(new URLSearchParams([[key, '']]));
      return Object.keys(converted)[0] || key;
    })
  );

  Object.entries(internalParams).forEach(([key, value]) => {
    if (key === '__proto__' || key === 'constructor' || key === 'prototype') return;
    if (key.startsWith('filter_') || internalFilterKeys.has(key)) {
      if (key !== 'page' && key !== 'orderby' && key !== 'order') {
        filters[key] = value;
      }
    }
  });

  // Merge current taxonomy context (category/tag archive pages) if not overridden
  if (window.ppfxFilterData && window.ppfxFilterData.currentContext) {
    Object.entries(window.ppfxFilterData.currentContext).forEach(([key, val]) => {
      if (key === '__proto__' || key === 'constructor' || key === 'prototype') return;
      if (!filters[key]) {
        filters[key] = val;
      }
    });
  }

  window.ppfxGlobalState.filters = filters;
  window.ppfxGlobalState.sort = {
    orderby: internalParams.orderby || '',
    order: internalParams.order || '',
  };
  window.ppfxGlobalState.paged = parseInt(internalParams.page || '1', 10);
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

// ---------------------------------------------------------------------------
// Popstate handler — defined after syncStateFromUrl & syncAllUIs
// ---------------------------------------------------------------------------

/**
 * Handle browser back/forward navigation.
 *
 * CRITICAL: State and controls must be synced BEFORE triggering the AJAX
 * fetch so the UI never shows stale selections while the request is in flight.
 */
const handleGlobalPopState = () => {
  syncStateFromUrl();
  syncAllUIs();

  for (const instance of Array.from(filterFormInstances)) {
    instance.handleFilterChange(false, "popstate");
  }
};
