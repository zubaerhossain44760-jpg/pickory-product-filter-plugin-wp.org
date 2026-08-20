import { fetchProducts } from "../api";
import { updateUrl, cssEscape } from "../utils/url";
import { runCompatBridge } from "../utils/compat-bridge";
import { ppfxGenerateHash } from "../utils/hash";
import {
  RESERVED_FILTER_KEYS,
  filterFormInstances,
  registerGlobalInstance,
  unregisterGlobalInstance,
  syncAllUIs,
  syncStateFromUrl,
  triggerWooCommerceEvents,
} from "./filter-globals";

/**
 * Check if price filter values are at their default bounds.
 *
 * @param {HTMLElement} formElement - The filter form element.
 * @param {object} [params] - Optional params object to check (if checking state values).
 * @returns {boolean} True if price is at default bounds.
 */
const isPriceAtDefaultBounds = (formElement, params = null) => {
  const priceGroup = formElement.querySelector(".ppfx-price-group");
  if (!priceGroup) return false;

  const minBound = Number.parseFloat(priceGroup.dataset.min);
  const maxBound = Number.parseFloat(priceGroup.dataset.max);

  if (!Number.isFinite(minBound) || !Number.isFinite(maxBound)) {
    return false;
  }

  let currentMin, currentMax;

  if (params) {
    // Checking params object (from state)
    currentMin = Number.parseFloat(params.min_price ?? minBound);
    currentMax = Number.parseFloat(params.max_price ?? maxBound);

    // Clamp invalid values to bounds
    if (!Number.isFinite(currentMin)) currentMin = minBound;
    if (!Number.isFinite(currentMax)) currentMax = maxBound;
  } else {
    // Checking DOM input values
    const minInput = formElement.querySelector('input[name="min_price"]');
    const maxInput = formElement.querySelector('input[name="max_price"]');
    if (!minInput || !maxInput) return false;
    currentMin = Number.parseFloat(minInput.value);
    currentMax = Number.parseFloat(maxInput.value);
  }

  return currentMin === minBound && currentMax === maxBound;
};

export const initFilterForm = (
  formElement,
  productsContainer,
  options = {},
) => {
  // Shared global state
  window.ppfxGlobalState = window.ppfxGlobalState || {
    filters: {},
    sort: { orderby: "", order: "" },
    paged: 1,
  };

  const { onAfterUpdate = () => { }, isAutoFilter = false } = options;
  const productsSelector = ".ppfx-products-wrapper";
  const instanceId = formElement.dataset.ppfxInstance;
  const targetSelector = formElement.dataset.ppfxTarget;

  let currentProductsContainer = productsContainer;
  let filterTimeout;
  let currentAbortController = null;
  let activeRequestId = 0;
  let preloaderStartTime = 0;

  const root = formElement.closest(".ppfx-filter-root") || formElement;

  // Debug logger - only active when debug mode is enabled
  const debugLog = (...args) => {
    if (window.ppfxFilterData?.debug) {
      console.log(...args);
    }
  };

  /**
   * Resolves the target product container for this specific filter instance.
   */
  const getLiveProductsContainer = () => {
    if (currentProductsContainer && document.body.contains(currentProductsContainer)) {
      return currentProductsContainer;
    }

    let container = null;
    if (targetSelector) {
      container = document.querySelector(targetSelector);
    }
    if (!container && instanceId) {
      container = document.querySelector(`.ppfx-products-wrapper[data-ppfx-instance='${cssEscape(instanceId)}']`);
    }
    if (!container) {
      container =
        root.querySelector(".ppfx-products-wrapper") ||
        document.querySelector(".ppfx-products-wrapper") ||
        document.querySelector(".products") ||
        document.querySelector(".woocommerce-no-products") ||
        document.querySelector(".woocommerce-info");
    }
    return container;
  };

  /**
   * hasChipsApplied — checks if the chips UI has any active filter chips.
   */
  const hasChipsApplied = () => {
    const chipsContainer = root.querySelector("[data-ppfx-active-filters]");
    return chipsContainer && chipsContainer.children.length > 0;
  };

  /**
   * Check if URL contains any PPFX filter parameters.
   * Detects ppfx_*, filter_*, and known filter keys.
   */
  const urlHasPpfxFilters = () => {
    const params = new URLSearchParams(window.location.search);
    return Array.from(params.keys()).some((key) =>
      key.startsWith('ppfx_') ||
      key.startsWith('filter_') ||
      (window.ppfxFilterData?.filterKeys || []).includes(key)
    );
  };

  /**
   * hasActiveSelections — checks the DOM directly to see if the user has
   * engaged any filter widget.
   */
  const hasActiveSelections = () => {
    if (
      formElement.querySelector(
        'input[type="checkbox"]:checked, input[type="radio"]:checked',
      )
    ) {
      return true;
    }

    for (const select of formElement.querySelectorAll("select")) {
      if (select.value && select.selectedIndex !== 0) {
        return true;
      }
    }

    if (
      formElement.querySelector(
        ".ppfx-grid-btn.is-active, .ppfx-swatch-item.is-active, .ppfx-star-row.is-active, .ppfx-frontend-toggle.is-active",
      )
    ) {
      return true;
    }

    // Check price filter
    if (!isPriceAtDefaultBounds(formElement)) {
      return true;
    }

    return false;
  };

  /**
   * getParams — assembles the API request params from window.ppfxGlobalState.
   */
  const getParams = () => {
    const params = {};

    Object.assign(params, window.ppfxGlobalState.filters);

    // Remove price params if they're at default bounds
    if (isPriceAtDefaultBounds(formElement, params)) {
      delete params.min_price;
      delete params.max_price;
    }

    if (window.ppfxGlobalState.sort.orderby) {
      params.orderby = window.ppfxGlobalState.sort.orderby;
    }
    if (window.ppfxGlobalState.sort.order) {
      params.order = window.ppfxGlobalState.sort.order;
    }

    if (window.ppfxGlobalState.paged > 1) {
      params.page = String(window.ppfxGlobalState.paged);
    }

    return params;
  };

  const executeFilterChange = async (params, formElement, onAfterUpdate, inputType = "default") => {
    const requestId = ++activeRequestId;
    let hasError = false;
    let errorMessage = null;

    // CRITICAL: Update URL BEFORE fetch to prevent race conditions
    // This ensures rapid clicks don't lose state
    if (inputType !== "popstate") {
      updateUrl(params, true);
    }

    if (currentAbortController) {
      currentAbortController.abort();
    }
    currentAbortController = new AbortController();

    try {
      const requestParams = { ...params };
      if (instanceId) {
        requestParams.instance_id = instanceId;
      }

      const data = await fetchProducts(requestParams, currentAbortController.signal);

      if (requestId !== activeRequestId) return;

      if (!data || !data.success || !data.fragments) {
        hasError = true;
        errorMessage = data?.message || 'UnexpectedResponse';
      } else if (data && data.success && data.fragments) {
        const allowedFragmentSelectors = new Set([
          '.ppfx-products-wrapper',
          '.woocommerce-result-count',
          '.woocommerce-ordering',
          '.woocommerce-pagination',
        ]);

        for (const [selector, html] of Object.entries(data.fragments)) {
          if (!allowedFragmentSelectors.has(selector)) {
            continue;
          }

          if (selector === productsSelector) {
            const targetLiveContainer = getLiveProductsContainer();
            if (!targetLiveContainer) {
              continue;
            }

            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = html.trim();
            let newElement = tempDiv.querySelector('.ppfx-products-wrapper');

            if (!newElement) {
              newElement = document.createElement("div");
              newElement.className = "ppfx-products-wrapper";
              while (tempDiv.firstChild) {
                newElement.appendChild(tempDiv.firstChild);
              }
            }

            if (newElement) {
              targetLiveContainer.replaceWith(newElement);
              currentProductsContainer = newElement;
              if (instanceId && !newElement.dataset.ppfxInstance) {
                newElement.dataset.ppfxInstance = instanceId;
              }
              newElement.setAttribute("aria-live", "polite");
              newElement.setAttribute("aria-atomic", "false");
              newElement.setAttribute("aria-busy", "false");
            }
          } else {
            let target = root.querySelector(selector);
            if (!target) {
              const liveContainer = getLiveProductsContainer();
              if (liveContainer) {
                target = liveContainer.parentElement?.querySelector(selector);
              }
            }
            if (!target && filterFormInstances.size === 1) {
              target = document.querySelector(selector);
            }
            if (target) {
              if (selector === ".woocommerce-ordering") {
                const normalizedHtml = html.trim();
                target.innerHTML = normalizedHtml;
                target.style.display = normalizedHtml ? "" : "none";
              } else if (selector === ".woocommerce-pagination") {
                const normalizedHtml = html.trim();
                if (normalizedHtml && !normalizedHtml.includes('style="display:none"')) {
                  target.outerHTML = normalizedHtml;
                } else {
                  target.style.display = "none";
                }
              } else {
                target.innerHTML = html;
              }
            } else if (selector === ".woocommerce-pagination" && html.trim()) {
              // Scoped Fallback: Only reconstruct if the missing fragment is pagination.
              const liveContainer = getLiveProductsContainer();
              if (liveContainer) {
                const fragmentWrapper = document.createElement("div");
                fragmentWrapper.innerHTML = html.trim();
                const paginationEl =
                  fragmentWrapper.querySelector(".woocommerce-pagination") ||
                  fragmentWrapper.firstElementChild;
                if (paginationEl) {
                  liveContainer.insertAdjacentElement("afterend", paginationEl);
                  paginationEl.style.display = "";
                }
              }
            }
          }
        }

        runCompatBridge();
        triggerWooCommerceEvents();

        // CRITICAL: URL already updated above, now just sync other instances
        syncAllUIs();

        const eventDetail = {
          timestamp: Date.now(),
          container: getLiveProductsContainer(),
        };

        document.body.dispatchEvent(
          new CustomEvent("ppfx_products_rendered", {
            bubbles: true,
            detail: eventDetail,
          }),
        );

        document.body.dispatchEvent(
          new CustomEvent("ppfx:ajax_loaded", {
            bubbles: true,
            detail: eventDetail,
          }),
        );
      }
    } catch (err) {
      if (err.name === "AbortError") {
        return;
      }

      if (requestId !== activeRequestId) return;

      // Store error info for finally block
      hasError = true;
      errorMessage = err.message;
    } finally {
      // Unified cleanup with minimum preloader duration
      if (requestId === activeRequestId) {
        const elapsedTime = Date.now() - preloaderStartTime;
        const minimumDuration = 600; // ms - reduced from 1000ms for snappier UX
        const remainingDelay = Math.max(0, minimumDuration - elapsedTime);

        setTimeout(() => {
          if (requestId === activeRequestId) {
            debugLog('[PPFX] Removing overlays after delay', Date.now(), 'remainingDelay was:', remainingDelay);

            const cleanupContainer = getLiveProductsContainer();
            if (cleanupContainer) {
              cleanupContainer.classList.remove(
                "ppfx-loading",
                "ppfx-initial-load",
              );
              debugLog('[PPFX] Grid overlay REMOVED', Date.now());
              cleanupContainer.setAttribute("aria-busy", "false");

              // Add error class if request failed
              if (hasError) {
                cleanupContainer.classList.add("ppfx-error");
              }
            }
            document.documentElement.classList.remove("ppfx-has-url-filters");
            formElement.classList.remove("ppfx-loading");
            debugLog('[PPFX] Filter overlay REMOVED', Date.now());

            // Dispatch error event if needed
            if (hasError) {
              document.body.dispatchEvent(
                new CustomEvent("ppfx:filter_error", {
                  detail: { error: errorMessage },
                }),
              );
            }

            onAfterUpdate(params);
          }
        }, remainingDelay);
      }
    }
  };

  /**
   * handleFilterChange — entry point for all filter/sort/page changes.
   */
  const handleFilterChange = (resetPage = false, inputType = "default") => {
    if (resetPage) {
      window.ppfxGlobalState.paged = 1;
    }

    const params = getParams();

    clearTimeout(filterTimeout);

    let delay = 0;
    if (inputType === 'slider' || inputType === 'text') {
      delay = 300;
    }

    const triggerFetch = () => {
      preloaderStartTime = Date.now();
      const liveContainer = getLiveProductsContainer();
      if (liveContainer) {
        liveContainer.classList.add("ppfx-loading");
        debugLog('[PPFX] Grid overlay ADDED', Date.now());
        liveContainer.setAttribute("aria-busy", "true");
      }
      formElement.classList.add("ppfx-loading");
      debugLog('[PPFX] Filter overlay ADDED', Date.now());
      void executeFilterChange(params, formElement, onAfterUpdate, inputType);
    };

    if (delay === 0) {
      triggerFetch();
    } else {
      filterTimeout = setTimeout(triggerFetch, delay);
    }
  };

  const toggleFilterValue = (name, value, isChecked) => {
    if (name === '__proto__' || name === 'constructor' || name === 'prototype') return;
    if (RESERVED_FILTER_KEYS.has(name)) return;
    let current = window.ppfxGlobalState.filters[name] ? window.ppfxGlobalState.filters[name].split(",") : [];
    if (isChecked) {
      if (!current.includes(value)) current.push(value);
    } else {
      current = current.filter(v => v !== value);
    }
    if (current.length > 0) {
      window.ppfxGlobalState.filters[name] = current.join(",");
    } else {
      delete window.ppfxGlobalState.filters[name];
    }
  };

  const updateFilterState = (key, value) => {
    if (key === '__proto__' || key === 'constructor' || key === 'prototype') return;
    if (RESERVED_FILTER_KEYS.has(key)) return;
    if (value === undefined || value === null || value === "" || value === "0") {
      delete window.ppfxGlobalState.filters[key];
    } else {
      window.ppfxGlobalState.filters[key] = value;
    }
  };

  const updatePriceState = () => {
    const minInput = formElement.querySelector('input[name="min_price"]');
    const maxInput = formElement.querySelector('input[name="max_price"]');

    if (minInput && maxInput) {
      const minVal = minInput.value;
      const maxVal = maxInput.value;

      if (minVal && Number(minVal) !== Number(minInput.min)) {
        updateFilterState("min_price", minVal);
      } else {
        updateFilterState("min_price", "");
      }

      if (maxVal && Number(maxVal) !== Number(maxInput.max)) {
        updateFilterState("max_price", maxVal);
      } else {
        updateFilterState("max_price", "");
      }
    }
  };

  // --- NON-NATIVE INPUT HANDLERS ---

  // 1. Button Grid (Multi-select)
  formElement.addEventListener("click", (e) => {
    const btn = e.target.closest(".ppfx-grid-btn");
    if (!btn) return;
    if (btn.closest(".ppfx-swatch-list")) return;

    const grid = btn.closest(".ppfx-button-grid");
    if (!grid) return;

    const hiddenInput = grid.querySelector(".ppfx-grid-hidden-input");
    if (!hiddenInput) return;

    const name = hiddenInput.name;
    const val = btn.dataset.value;
    const isNowActive = !btn.classList.contains("is-active");

    toggleFilterValue(name, val, isNowActive);

    // Sync UI immediately to prevent visual drift during fast clicking
    syncAllUIs();

    if (isAutoFilter) {
      handleFilterChange(true, "checkbox");
    }
  });

  // 2. Toggle Switch (Binary)
  formElement.addEventListener("click", (e) => {
    const toggle = e.target.closest(".ppfx-frontend-toggle");
    if (!toggle) return;

    const row = toggle.closest(".ppfx-toggle-row");
    if (!row) return;
    const input = row.querySelector(".ppfx-toggle-input");
    if (!input) return;

    const name = input.name;
    const isNowActive = !toggle.classList.contains("is-active");

    updateFilterState(name, isNowActive ? "1" : "0");

    // Sync UI immediately to prevent visual drift
    syncAllUIs();

    if (isAutoFilter) {
      handleFilterChange(true, "checkbox");
    }
  });

  // 3. Star Rating (Single Select)
  formElement.addEventListener("click", (e) => {
    const row = e.target.closest(".ppfx-star-row");
    if (!row) return;

    const list = row.closest(".ppfx-star-rating-list");
    if (!list) return;
    const input = list.querySelector(".ppfx-rating-input");
    if (!input) return;

    const name = input.name;
    const ratingVal = row.dataset.rating;
    const isNowActive = !row.classList.contains("is-active");

    updateFilterState(name, isNowActive ? ratingVal : "");

    // Sync UI immediately to prevent visual drift
    syncAllUIs();

    if (isAutoFilter) {
      handleFilterChange(true, "checkbox");
    }
  });

  // 4. Swatches (Multi-select)
  formElement.addEventListener("click", (e) => {
    const target =
      e.target.closest(".ppfx-swatch-item") ||
      e.target.closest(".ppfx-swatch-list .ppfx-grid-btn");
    if (!target) return;

    const list = target.closest(".ppfx-swatch-list");
    if (!list) return;
    const hiddenInput = list.querySelector(".ppfx-swatch-hidden-input");
    if (!hiddenInput) return;

    const name = hiddenInput.name;
    const val = target.dataset.value;
    const isNowActive = !target.classList.contains("is-active");

    toggleFilterValue(name, val, isNowActive);

    // Sync UI immediately to prevent visual drift
    syncAllUIs();

    if (isAutoFilter) {
      handleFilterChange(true, "checkbox");
    }
  });

  // 5. Radio button deselect toggle
  formElement.addEventListener("click", (e) => {
    const target = e.target;
    if (target.type !== "radio") return;

    if (target.dataset.wasChecked === "true") {
      target.checked = false;
      target.dataset.wasChecked = "false";
      updateFilterState(target.name, "");
      syncAllUIs();
      if (isAutoFilter) {
        handleFilterChange(true, "checkbox");
      }
      return;
    }
    target.dataset.wasChecked = "true";

    // Reset wasChecked on all other radios with same name
    formElement
      .querySelectorAll(`input[type="radio"][name="${target.name}"]`)
      .forEach((r) => {
        if (r !== target) r.dataset.wasChecked = "false";
      });
  });

  // --- NATIVE INPUT HANDLERS ---

  formElement.addEventListener("change", (e) => {
    const target = e.target;
    if (target.type === "checkbox") {
      toggleFilterValue(target.name, target.value, target.checked);
      // Sync UI immediately for native inputs
      syncAllUIs();
      if (isAutoFilter) {
        handleFilterChange(true, "checkbox");
      }
    } else if (target.type === "radio") {
      if (target.checked) {
        updateFilterState(target.name, target.value);
      } else {
        updateFilterState(target.name, "");
      }
      // Sync UI immediately
      syncAllUIs();
      if (isAutoFilter) {
        handleFilterChange(true, "checkbox");
      }
    } else if (target.type === "select-one") {
      if (target.name === "orderby") return; // owned exclusively by the global sort handler
      updateFilterState(target.name, target.value);
      // Sync UI immediately
      syncAllUIs();
      if (isAutoFilter) {
        handleFilterChange(true, "checkbox");
      }
    }
  });

  formElement.addEventListener("submit", (e) => {
    e.preventDefault();
    updatePriceState();
    if (!hasActiveSelections() && !urlHasPpfxFilters()) {
      return;
    }
    handleFilterChange(true);
  });

  // Clear button
  const resetButton = root.querySelector("[data-ppfx-reset-btn]");
  if (resetButton) {
    resetButton.addEventListener("click", () => {
      if (!hasChipsApplied() && !hasActiveSelections() && !urlHasPpfxFilters()) {
        return;
      }

      // Reset global state first
      window.ppfxGlobalState.filters = {};
      window.ppfxGlobalState.sort = { orderby: "", order: "" };
      window.ppfxGlobalState.paged = 1;

      if (window.ppfxFilterData && window.ppfxFilterData.currentContext) {
        Object.assign(window.ppfxGlobalState.filters, window.ppfxFilterData.currentContext);
      }

      syncAllUIs();
      updateUrl(getParams(), true);

      handleFilterChange(true, "reset");
    });
  }

  // --- PAGINATION INTERCEPT ---
  const onPaginationClick = (e) => {
    const link = e.target.closest(".woocommerce-pagination a");
    if (!link) {
      return false;
    }

    const target = getLiveProductsContainer();
    const rootElement = formElement.closest(".ppfx-filter-root") || formElement;

    if (!target) {
      return false;
    }

    if (instanceId) {
      const paginationEl = link.closest('.woocommerce-pagination');
      const hasMatchingInstance =
        paginationEl?.closest(`[data-ppfx-instance='${cssEscape(instanceId)}']`) ||
        target.closest(`[data-ppfx-instance='${cssEscape(instanceId)}']`)?.parentElement?.contains(paginationEl);
      if (!hasMatchingInstance) {
        const isInsideRoot = rootElement.contains(link);
        const isInsideTarget = target.contains(link);
        const isSiblingOfTarget = target && target.parentElement && target.parentElement.contains(link);
        if (!isInsideRoot && !isInsideTarget && !isSiblingOfTarget) {
          return false;
        }
      }
    } else {
      const isInsideRoot = rootElement.contains(link);
      const isInsideTarget = target.contains(link);
      const isSiblingOfTarget = target && target.parentElement && target.parentElement.contains(link);
      if (!isInsideRoot && !isInsideTarget && !isSiblingOfTarget) {
        return false;
      }
    }

    e.preventDefault();

    const url = new URL(link.href);
    const pathMatch = url.pathname.match(/\/page\/(\d+)\/?/);

    const page = parseInt(
      pathMatch?.[1] ||
      url.searchParams.get("product-page") ||
      url.searchParams.get("paged") ||
      url.searchParams.get("page") ||
      "1",
      10,
    );

    // Consolidate page state
    window.ppfxGlobalState.paged = Number.isFinite(page) && page > 0 ? page : 1;
    handleFilterChange(false, "pagination");
    return true;
  };

  // --- SORT DROPDOWN INTERCEPT ---
  const onSortChange = (e) => {
    if (!e.target.matches('select[name="orderby"]')) {
      return false;
    }

    const target = getLiveProductsContainer();
    const rootElement = formElement.closest(".ppfx-filter-root") || formElement;

    if (!target) {
      return false;
    }

    if (instanceId) {
      const sortEl = e.target;
      const hasMatchingInstance =
        sortEl.closest(`[data-ppfx-instance='${cssEscape(instanceId)}']`) ||
        target.closest(`[data-ppfx-instance='${cssEscape(instanceId)}']`)?.parentElement?.contains(sortEl);
      if (!hasMatchingInstance) {
        const isInsideRoot = rootElement.contains(e.target);
        const isInsideTarget = target.contains(e.target);
        const isSiblingOfTarget = target && target.parentElement && target.parentElement.contains(e.target);
        if (!isInsideRoot && !isInsideTarget && !isSiblingOfTarget) {
          return false;
        }
      }
    } else {
      const isInsideRoot = rootElement.contains(e.target);
      const isInsideTarget = target.contains(e.target);
      const isSiblingOfTarget = target && target.parentElement && target.parentElement.contains(e.target);
      if (!isInsideRoot && !isInsideTarget && !isSiblingOfTarget) {
        return false;
      }
    }

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    // Explicit state update
    window.ppfxGlobalState.sort = { orderby: e.target.value, order: "" };
    handleFilterChange(true, "sort");
    return true;
  };

  const globalInstance = {
    formElement,
    root,
    onPaginationClick,
    onSortChange,
    handleFilterChange,
  };
  registerGlobalInstance(globalInstance);

  const urlParams = new URLSearchParams(window.location.search);
  const hasInitialFilters = ppfxGenerateHash(urlParams) !== "";

  // Helper to hydrate history state
  const hydrateHistoryState = () => {
    if (typeof window.history.replaceState === 'function') {
      window.history.replaceState(
        {
          filters: { ...window.ppfxGlobalState.filters },
          ppfx: true
        },
        document.title,
        window.location.href
      );
    }
  };

  if (hasInitialFilters) {
    const wrapper = document.querySelector(".ppfx-products-wrapper");
    const serverHash = wrapper ? wrapper.dataset.filterHash : null;
    const currentHash = ppfxGenerateHash(urlParams);

    if (!serverHash || serverHash !== currentHash) {
      // SSR doesn't match URL - need to fetch correct products
      // CRITICAL: Sync controls to URL first, so they reflect what's being fetched
      syncStateFromUrl();
      syncAllUIs();
      // Notify active filters that state has been hydrated
      document.dispatchEvent(new CustomEvent('ppfx-filters-updated'));
      handleFilterChange(false, "init");
    } else {
      // SSR matches URL - just hydrate controls to match
      syncStateFromUrl();
      syncAllUIs();
      // Notify active filters that state has been hydrated
      document.dispatchEvent(new CustomEvent('ppfx-filters-updated'));
      hydrateHistoryState();
    }
  } else {
    // No filters - ensure controls are in default state
    syncStateFromUrl();
    syncAllUIs();
    // Notify active filters that state has been hydrated
    document.dispatchEvent(new CustomEvent('ppfx-filters-updated'));
    hydrateHistoryState();
  }

  const destroy = () => {
    if (window.__ppfxDrawerInstance) window.__ppfxDrawerInstance.destroy();
    unregisterGlobalInstance(globalInstance);
    currentAbortController?.abort();
    clearTimeout(filterTimeout);
  };

  return { handleFilterChange, destroy, updatePriceState };
};
