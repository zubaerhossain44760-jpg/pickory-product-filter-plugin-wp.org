import { fetchProducts } from "../api";
import {
  cssEscape,
  PPFX_URL_State,
  toPublicParams,
  serializeArrayValues,
  deserializeArrayValues,
} from "../utils/url";
import { runCompatBridge } from "../utils/compat-bridge";
import {
  RESERVED_FILTER_KEYS,
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
 * @param {object} [params] - Optional params object to check.
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
    currentMin = Number.parseFloat(params.min_price ?? minBound);
    currentMax = Number.parseFloat(params.max_price ?? maxBound);
    if (!Number.isFinite(currentMin)) currentMin = minBound;
    if (!Number.isFinite(currentMax)) currentMax = maxBound;
  } else {
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
  const { onAfterUpdate = () => {}, isAutoFilter = false } = options;
  const productsSelector = ".ppfx-products-wrapper";
  const instanceId = formElement.dataset.ppfxInstance;
  const targetSelector = formElement.dataset.ppfxTarget;

  let currentProductsContainer = productsContainer;
  let filterTimeout;
  let currentAbortController = null;
  let activeRequestId = 0;

  const root = formElement.closest(".ppfx-filter-root") || formElement;
  const isPrimary =
    formElement.closest(".ppfx-primary-filter") !== null ||
    root === document.querySelector(".ppfx-filter-root");
  const domAbortController = new AbortController();
  const { signal } = domAbortController;

  /**
   * Resolves the target product container for this specific filter instance.
   */
  const getLiveProductsContainer = () => {
    if (
      currentProductsContainer &&
      document.body.contains(currentProductsContainer)
    ) {
      return currentProductsContainer;
    }

    let container = null;
    if (targetSelector) {
      container = document.querySelector(targetSelector);
    }
    if (!container && instanceId) {
      container = document.querySelector(
        `.ppfx-products-wrapper[data-ppfx-instance='${cssEscape(instanceId)}']`,
      );
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

  /** Resolves the before-loop fragment immediately preceding this product loop. */
  const getLiveToolbar = () => {
    const container = getLiveProductsContainer();
    let sibling = container?.previousElementSibling;

    while (sibling) {
      if (sibling.matches("[data-ppfx-loop-toolbar]")) {
        return sibling;
      }
      sibling = sibling.previousElementSibling;
    }

    return document.querySelector("[data-ppfx-loop-toolbar]");
  };

  const hasChipsApplied = () => {
    let chipsContainer = root.querySelector("[data-ppfx-active-filters]");
    const detachedWrap = document.querySelector('[data-ppfx-chips-for="*"]');
    if (detachedWrap) {
      chipsContainer = detachedWrap.querySelector("[data-ppfx-active-filters]");
    }
    return chipsContainer && chipsContainer.children.length > 0;
  };

  const urlHasPpfxFilters = () => {
    const params = new URLSearchParams(window.location.search);
    return Array.from(params.keys()).some(
      (key) =>
        key.startsWith("ppfx_") ||
        key.startsWith("filter_") ||
        (window.ppfxFilterData?.filterKeys || []).includes(key),
    );
  };

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

    if (!isPriceAtDefaultBounds(formElement)) {
      return true;
    }

    return false;
  };

  /**
   * Reads canonical state and strips default prices before API requests.
   */
  const getCleanRequestParams = (canonicalParams = null) => {
    const params = {
      ...(canonicalParams || PPFX_URL_State.getCanonicalState()),
    };
    return params;
  };

  /**
   * Executes the API fetch with AbortController to prevent network race conditions.
   *
   * @param {object} params - Filter and sort parameters.
   * @param {string} [source="filter"] - Triggering interaction source.
   */
  const executeFilterChange = async (params, source = "filter") => {
    const requestId = ++activeRequestId;
    let hasError = false;
    let errorMessage = null;

    // Cancel in-flight request
    if (currentAbortController) {
      currentAbortController.abort();
    }
    currentAbortController = new AbortController();

    try {
      const publicParams = toPublicParams(params);
      const requestParams = { ...publicParams };

      // Preserve archive taxonomy context (e.g. category, product_tag) if not explicitly overridden by user.
      const currentContext = window.ppfxFilterData?.currentContext;
      if (currentContext && typeof currentContext === "object") {
        const publicContext = toPublicParams(currentContext);
        for (const [contextKey, contextVal] of Object.entries(publicContext)) {
          if (
            contextKey === "__proto__" ||
            contextKey === "constructor" ||
            contextKey === "prototype"
          ) {
            continue;
          }
          if (
            contextVal !== undefined &&
            contextVal !== null &&
            contextVal !== "" &&
            (requestParams[contextKey] === undefined ||
              requestParams[contextKey] === null ||
              requestParams[contextKey] === "")
          ) {
            requestParams[contextKey] = contextVal;
          }
        }
      }

      if (instanceId) {
        requestParams.instance_id = instanceId;
      }
      requestParams.toolbar_missing = getLiveToolbar() ? "0" : "1";

      const data = await fetchProducts(
        requestParams,
        currentAbortController.signal,
      );

      if (requestId !== activeRequestId) return;

      if (!data || !data.success || !data.fragments) {
        hasError = true;
        errorMessage = data?.message || "UnexpectedResponse";
      } else if (data && data.success && data.fragments) {
        const allowedFragmentSelectors = new Set([
          ".ppfx-loop-toolbar",
          ".ppfx-products-wrapper",
          ".woocommerce-result-count",
          ".woocommerce-pagination",
        ]);

        let toolbarInserted = false;

        for (const [selector, html] of Object.entries(data.fragments)) {
          if (!allowedFragmentSelectors.has(selector)) {
            continue;
          }

          if (selector === ".ppfx-loop-toolbar") {
            const targetToolbar = getLiveToolbar();
            if (!targetToolbar) {
              const liveContainer = getLiveProductsContainer();
              if (liveContainer && html.trim()) {
                liveContainer.insertAdjacentHTML("beforebegin", html.trim());
                toolbarInserted = true;
              }
            }
          } else if (selector === productsSelector) {
            const targetLiveContainer = getLiveProductsContainer();
            if (!targetLiveContainer) {
              continue;
            }

            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = html.trim();
            let newElement = tempDiv.querySelector(".ppfx-products-wrapper");

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
            if (!target) {
              target = document.querySelector(selector);
            }
            if (target) {
              if (selector === ".woocommerce-pagination") {
                const normalizedHtml = html.trim();
                if (
                  normalizedHtml &&
                  !normalizedHtml.includes('style="display:none"')
                ) {
                  target.outerHTML = normalizedHtml;
                } else {
                  target.style.display = "none";
                }
              } else {
                target.innerHTML = html;
              }
            } else if (selector === ".woocommerce-pagination" && html.trim()) {
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

        if (typeof data.loop?.has_results === "boolean") {
          const toolbar = getLiveToolbar();
          if (toolbar) {
            toolbar.hidden = !data.loop.has_results;
          }
        }

        runCompatBridge();
        triggerWooCommerceEvents();
        syncAllUIs();

        if (typeof jQuery !== "undefined" && jQuery(document.body).trigger) {
          jQuery(document.body).trigger("wc-enhanced-select-init");
        }

        const eventDetail = {
          timestamp: Date.now(),
          container: getLiveProductsContainer(),
          source,
        };

        if (toolbarInserted) {
          document.body.dispatchEvent(
            new CustomEvent("ppfx:toolbar_inserted", {
              bubbles: true,
              detail: eventDetail,
            }),
          );
        }

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
      hasError = true;
      errorMessage = err.message;
    } finally {
      if (requestId === activeRequestId) {
        const cleanupContainer = getLiveProductsContainer();
        if (cleanupContainer) {
          cleanupContainer.classList.remove(
            "ppfx-loading",
            "ppfx-initial-load",
          );
          cleanupContainer.setAttribute("aria-busy", "false");
          if (hasError) {
            cleanupContainer.classList.add("ppfx-error");
          }
        }
        document.documentElement.classList.remove("ppfx-has-url-filters");
        formElement.classList.remove("ppfx-loading");

        if (hasError) {
          document.body.dispatchEvent(
            new CustomEvent("ppfx:filter_error", {
              detail: { error: errorMessage },
            }),
          );
        }

        onAfterUpdate(params);
      }
    }
  };

  /**
   * Called whenever URL state changes (unidirectional subscriber).
   */
  const onUrlStateChange = (params, source = "default") => {
    clearTimeout(filterTimeout);

    const cleanParams = getCleanRequestParams(params);

    const delay =
      source === "slider" || source === "text"
        ? (window.ppfxFilterData?.debounceDelay ?? 300)
        : 0;

    const triggerFetch = () => {
      const liveContainer = getLiveProductsContainer();
      if (liveContainer) {
        liveContainer.classList.add("ppfx-loading");
        liveContainer.setAttribute("aria-busy", "true");
      }
      formElement.classList.add("ppfx-loading");
      void executeFilterChange(cleanParams, source);
    };

    if (delay === 0) {
      triggerFetch();
    } else {
      filterTimeout = setTimeout(triggerFetch, delay);
    }
  };

  /**
   * Helper to commit a state transition via the URL State Manager.
   */
  const commitFilterUpdate = (modifierFn, options = {}) => {
    const { resetPage = true, pushHistory = true, source = "user" } = options;
    const current = PPFX_URL_State.getCanonicalState();
    const next = { ...current };

    if (resetPage) {
      delete next.page;
      delete next.paged;
    }

    modifierFn(next);

    if (
      isAutoFilter ||
      source === "submit" ||
      source === "pagination" ||
      source === "sort" ||
      source === "reset"
    ) {
      PPFX_URL_State.setCanonicalState(next, { pushHistory, source });
    }
  };

  // --- STATE MUTATION HELPERS ---

  const toggleMultiValue = (name, value, isChecked) => {
    if (name === "__proto__" || name === "constructor" || name === "prototype")
      return;
    if (RESERVED_FILTER_KEYS.has(name)) return;

    commitFilterUpdate(
      (state) => {
        const list = deserializeArrayValues(state[name]);
        const nextList = isChecked
          ? Array.from(new Set([...list, value]))
          : list.filter((v) => v !== value);

        if (nextList.length > 0) {
          state[name] = serializeArrayValues(nextList);
        } else {
          delete state[name];
        }
      },
      { resetPage: true, pushHistory: true, source: "toggle_multivalue" },
    );
  };

  const setSingleValue = (name, value, options = {}) => {
    if (name === "__proto__" || name === "constructor" || name === "prototype")
      return;
    if (RESERVED_FILTER_KEYS.has(name)) return;

    const { pushHistory = true, source = "set_value" } = options;

    commitFilterUpdate(
      (state) => {
        if (
          value === undefined ||
          value === null ||
          value === "" ||
          value === "0"
        ) {
          delete state[name];
        } else {
          state[name] = String(value);
        }
      },
      { resetPage: true, pushHistory, source },
    );
  };

  const updatePriceState = (pushHistory = false) => {
    const minInput = formElement.querySelector('input[name="min_price"]');
    const maxInput = formElement.querySelector('input[name="max_price"]');

    if (minInput && maxInput) {
      const minVal = minInput.value;
      const maxVal = maxInput.value;

      commitFilterUpdate(
        (state) => {
          if (minVal) {
            state.min_price = minVal;
          } else {
            delete state.min_price;
          }

          if (maxVal) {
            state.max_price = maxVal;
          } else {
            delete state.max_price;
          }
        },
        {
          resetPage: true,
          pushHistory,
          source: pushHistory ? "slider_commit" : "slider",
        },
      );
    }
  };

  // --- FORM STATE SERIALIZER (for manual filter submission) ---

  const serializeFormState = () => {
    const state = {};

    // 1. Checkboxes and Radios
    formElement
      .querySelectorAll(
        'input[type="checkbox"]:checked, input[type="radio"]:checked',
      )
      .forEach((input) => {
        const name = input.name;
        if (!name || name.startsWith("__")) return;
        if (input.type === "checkbox") {
          const list = state[name] ? state[name].split(",") : [];
          if (!list.includes(input.value)) list.push(input.value);
          state[name] = list.join(",");
        } else {
          state[name] = input.value;
        }
      });

    // 2. Selects (except orderby)
    formElement
      .querySelectorAll('select:not([name="orderby"])')
      .forEach((select) => {
        if (select.name && select.value) {
          state[select.name] = select.value;
        }
      });

    // 3. Hidden inputs (swatches, button grid, star ratings, toggle switches)
    formElement
      .querySelectorAll(
        ".ppfx-grid-hidden-input, .ppfx-swatch-hidden-input, .ppfx-rating-input, .ppfx-toggle-input",
      )
      .forEach((input) => {
        if (input.name && input.value) {
          state[input.name] = input.value;
        }
      });

    // 4. Price slider inputs
    const minInput = formElement.querySelector('input[name="min_price"]');
    const maxInput = formElement.querySelector('input[name="max_price"]');
    if (minInput && maxInput) {
      const minBound = Number.parseFloat(minInput.min || minInput.dataset.min);
      const maxBound = Number.parseFloat(maxInput.max || maxInput.dataset.max);
      const minVal = Number.parseFloat(minInput.value);
      const maxVal = Number.parseFloat(maxInput.value);

      if (
        Number.isFinite(minVal) &&
        Number.isFinite(minBound) &&
        minVal !== minBound
      ) {
        state.min_price = String(minVal);
      }
      if (
        Number.isFinite(maxVal) &&
        Number.isFinite(maxBound) &&
        maxVal !== maxBound
      ) {
        state.max_price = String(maxVal);
      }
    }

    // 5. Preserve existing sort/orderby from URL state
    const current = PPFX_URL_State.getCanonicalState();
    if (current.orderby) state.orderby = current.orderby;
    if (current.order) state.order = current.order;

    return state;
  };

  // --- NON-NATIVE INPUT HANDLERS ---

  // 1. Button Grid (Multi-select)
  formElement.addEventListener(
    "click",
    (e) => {
      const btn = e.target.closest(".ppfx-grid-btn");
      if (!btn || btn.closest(".ppfx-swatch-list")) return;

      const grid = btn.closest(".ppfx-button-grid");
      if (!grid) return;

      const hiddenInput = grid.querySelector(".ppfx-grid-hidden-input");
      if (!hiddenInput) return;

      const name = hiddenInput.name;
      const val = btn.dataset.value;
      const isNowActive = !btn.classList.contains("is-active");

      toggleMultiValue(name, val, isNowActive);
    },
    { signal },
  );

  // 2. Toggle Switch (Binary)
  formElement.addEventListener(
    "click",
    (e) => {
      const toggle = e.target.closest(".ppfx-frontend-toggle");
      if (!toggle) return;

      const row = toggle.closest(".ppfx-toggle-row");
      if (!row) return;
      const input = row.querySelector(".ppfx-toggle-input");
      if (!input) return;

      const name = input.name;
      const isNowActive = !toggle.classList.contains("is-active");

      setSingleValue(name, isNowActive ? "1" : "");
    },
    { signal },
  );

  // 2b. Keyboard accessibility for custom toggle switch (Space / Enter)
  formElement.addEventListener(
    "keydown",
    (e) => {
      if (e.key !== " " && e.key !== "Enter") return;
      const toggle = e.target.closest(".ppfx-frontend-toggle");
      if (!toggle) return;

      e.preventDefault();
      toggle.click();
    },
    { signal },
  );

  // 3. Star Rating (Single Select)
  formElement.addEventListener(
    "click",
    (e) => {
      const row = e.target.closest(".ppfx-star-row");
      if (!row) return;

      const list = row.closest(".ppfx-star-rating-list");
      if (!list) return;
      const input = list.querySelector(".ppfx-rating-input");
      if (!input) return;

      const name = input.name;
      const ratingVal = row.dataset.rating;
      const isNowActive = !row.classList.contains("is-active");

      setSingleValue(name, isNowActive ? ratingVal : "");
    },
    { signal },
  );

  // 4. Swatches (Multi-select)
  formElement.addEventListener(
    "click",
    (e) => {
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

      toggleMultiValue(name, val, isNowActive);
    },
    { signal },
  );

  // Radio uncheck toggle
  formElement.addEventListener(
    "click",
    (e) => {
      const target = e.target;
      if (target.type !== "radio") return;

      if (target.dataset.wasChecked === "true") {
        target.checked = false;
        target.dataset.wasChecked = "false";
        setSingleValue(target.name, "");
        return;
      }
      target.dataset.wasChecked = "true";

      formElement
        .querySelectorAll(`input[type="radio"][name="${target.name}"]`)
        .forEach((r) => {
          if (r !== target) r.dataset.wasChecked = "false";
        });
    },
    { signal },
  );

  // --- NATIVE INPUT HANDLERS ---

  formElement.addEventListener(
    "change",
    (e) => {
      const target = e.target;
      if (target.type === "checkbox") {
        toggleMultiValue(target.name, target.value, target.checked);
      } else if (target.type === "radio") {
        setSingleValue(target.name, target.checked ? target.value : "");
      } else if (target.type === "select-one") {
        if (target.name === "orderby") return;
        setSingleValue(target.name, target.value);
      }
    },
    { signal },
  );

  formElement.addEventListener(
    "submit",
    (e) => {
      e.preventDefault();
      updatePriceState(true);

      const nextParams = isAutoFilter
        ? getCleanRequestParams()
        : serializeFormState();

      if (
        !hasActiveSelections() &&
        !urlHasPpfxFilters() &&
        Object.keys(nextParams).length === 0
      ) {
        return;
      }

      delete nextParams.page;
      delete nextParams.paged;

      PPFX_URL_State.setCanonicalState(nextParams, {
        pushHistory: true,
        source: "submit",
        force: true,
      });
    },
    { signal },
  );

  // Clear buttons
  const resetButtons = [];
  const internalResetBtn = root.querySelector("[data-ppfx-reset-btn]");
  if (internalResetBtn) resetButtons.push(internalResetBtn);

  const detachedResetBtn = document.querySelector(
    '[data-ppfx-chips-for="*"] [data-ppfx-reset-btn]',
  );
  if (detachedResetBtn) resetButtons.push(detachedResetBtn);

  resetButtons.forEach((btn) => {
    btn.addEventListener(
      "click",
      () => {
        if (
          !hasChipsApplied() &&
          !hasActiveSelections() &&
          !urlHasPpfxFilters()
        ) {
          return;
        }

        const emptyState = {};
        PPFX_URL_State.setCanonicalState(emptyState, {
          pushHistory: true,
          source: "reset",
        });
      },
      { signal },
    );
  });

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
      const paginationEl = link.closest(".woocommerce-pagination");
      const hasMatchingInstance =
        paginationEl?.closest(
          `[data-ppfx-instance='${cssEscape(instanceId)}']`,
        ) ||
        target
          .closest(`[data-ppfx-instance='${cssEscape(instanceId)}']`)
          ?.parentElement?.contains(paginationEl);
      if (!hasMatchingInstance) {
        const isInsideRoot = rootElement.contains(link);
        const isInsideTarget = target.contains(link);
        const isSiblingOfTarget =
          target && target.parentElement && target.parentElement.contains(link);
        if (!isInsideRoot && !isInsideTarget && !isSiblingOfTarget) {
          return false;
        }
      }
    } else {
      const isInsideRoot = rootElement.contains(link);
      const isInsideTarget = target.contains(link);
      const isSiblingOfTarget =
        target && target.parentElement && target.parentElement.contains(link);
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

    const validPage = Number.isFinite(page) && page > 0 ? page : 1;

    commitFilterUpdate(
      (state) => {
        if (validPage > 1) {
          state.page = String(validPage);
        } else {
          delete state.page;
        }
      },
      { resetPage: false, pushHistory: true, source: "pagination" },
    );

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
        target
          .closest(`[data-ppfx-instance='${cssEscape(instanceId)}']`)
          ?.parentElement?.contains(sortEl);
      if (!hasMatchingInstance) {
        const isInsideRoot = rootElement.contains(e.target);
        const isInsideTarget = target.contains(e.target);
        const isSiblingOfTarget =
          target &&
          target.parentElement &&
          target.parentElement.contains(e.target);
        if (!isInsideRoot && !isInsideTarget && !isSiblingOfTarget) {
          return false;
        }
      }
    } else {
      const isInsideRoot = rootElement.contains(e.target);
      const isInsideTarget = target.contains(e.target);
      const isSiblingOfTarget =
        target &&
        target.parentElement &&
        target.parentElement.contains(e.target);
      if (!isInsideRoot && !isInsideTarget && !isSiblingOfTarget) {
        return false;
      }
    }

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const newSort = e.target.value;

    commitFilterUpdate(
      (state) => {
        if (newSort) {
          state.orderby = newSort;
        } else {
          delete state.orderby;
        }
        delete state.order;
      },
      { resetPage: true, pushHistory: true, source: "sort" },
    );

    return true;
  };

  const globalInstance = {
    formElement,
    root,
    isPrimary,
    onPaginationClick,
    onSortChange,
    onUrlStateChange,
  };
  registerGlobalInstance(globalInstance);

  // Initial SSR hydration: synchronize all UI elements (inputs, selects, chips) from canonical URL state
  // without dispatching an unnecessary network fetch.
  syncStateFromUrl();

  const destroy = () => {
    domAbortController.abort();
    if (
      formElement._ppfxActiveFilters &&
      typeof formElement._ppfxActiveFilters.destroy === "function"
    ) {
      formElement._ppfxActiveFilters.destroy();
    }
    if (window.__ppfxDrawerInstance) window.__ppfxDrawerInstance.destroy();
    unregisterGlobalInstance(globalInstance);
    currentAbortController?.abort();
    clearTimeout(filterTimeout);
  };

  return {
    handleFilterChange: (source = "default") => {
      const current = PPFX_URL_State.getCanonicalState();
      onUrlStateChange(current, source);
    },
    destroy,
    updatePriceState,
  };
};
