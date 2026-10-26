export const cssEscape = (value) => {
  const stringValue = String(value);

  if (window.CSS && typeof window.CSS.escape === "function") {
    return window.CSS.escape(stringValue);
  }

  return stringValue.replace(/[^a-zA-Z0-9_-]/g, "\\$&");
};

export const FILTER_KEYS = [
  "category",
  "product_tag",
  "page",
  "min_price",
  "max_price",
  "rating",
  "on_sale",
  "orderby",
  "order",
];

const PUBLIC_PARAM_MAP = {
  category: "ppfx_category",
  product_tag: "ppfx_product_tag",
  page: "ppfx_page",
  orderby: "ppfx_orderby",
  order: "ppfx_order",
  min_price: "ppfx_min_price",
  max_price: "ppfx_max_price",
  rating: "ppfx_rating",
  on_sale: "ppfx_on_sale",
};

const INTERNAL_PARAM_MAP = Object.fromEntries(
  Object.entries(PUBLIC_PARAM_MAP).map(([k, v]) => [v, k]),
);

/**
 * Standardize array parameter values to a sorted, deduplicated comma-separated string.
 *
 * @param {string|string[]} values
 * @returns {string}
 */
export const serializeArrayValues = (values) => {
  if (!values) return "";
  const list = Array.isArray(values) ? values : String(values).split(",");
  const cleaned = Array.from(
    new Set(list.map((v) => String(v).trim()).filter(Boolean)),
  ).sort();
  return cleaned.join(",");
};

/**
 * Parse a comma-delimited string into a string array.
 *
 * @param {string|null|undefined} str
 * @returns {string[]}
 */
export const deserializeArrayValues = (str) => {
  if (!str) return [];
  return String(str)
    .split(",")
    .map((v) => v.trim())
    .filter(Boolean);
};

export const toPublicParams = (params) => {
  const result = {};
  Object.keys(params).forEach((key) => {
    if (key === "__proto__" || key === "constructor" || key === "prototype")
      return;
    const val = params[key];
    if (val === undefined || val === null || val === "") return;
    let publicKey;
    if (PUBLIC_PARAM_MAP[key]) {
      publicKey = PUBLIC_PARAM_MAP[key];
    } else if (
      key.startsWith("ppfx_") ||
      key === "instance_id" ||
      key === "toolbar_missing"
    ) {
      publicKey = key;
    } else {
      publicKey = `ppfx_${key}`;
    }
    result[publicKey] = val;
  });
  return result;
};

export const toInternalParams = (searchParams) => {
  const result = {};
  const params =
    searchParams instanceof URLSearchParams
      ? searchParams
      : new URLSearchParams(searchParams || "");

  // Handle both standard key=val and bracket notation key[]=val
  params.forEach((value, rawKey) => {
    if (
      rawKey === "__proto__" ||
      rawKey === "constructor" ||
      rawKey === "prototype"
    )
      return;

    const isArrayNotation = rawKey.endsWith("[]");
    const key = isArrayNotation ? rawKey.slice(0, -2) : rawKey;

    let internalKey;
    if (INTERNAL_PARAM_MAP[key]) {
      internalKey = INTERNAL_PARAM_MAP[key];
    } else if (key.startsWith("ppfx_filter_")) {
      internalKey = key.replace("ppfx_", "");
    } else if (key.startsWith("ppfx_")) {
      internalKey = key.replace("ppfx_", "");
    } else {
      internalKey = key;
    }

    if (isArrayNotation || result[internalKey]) {
      const existing = result[internalKey]
        ? String(result[internalKey]).split(",")
        : [];
      if (!existing.includes(value)) {
        existing.push(value);
      }
      result[internalKey] = existing.join(",");
    } else {
      result[internalKey] = value;
    }
  });

  return result;
};

/**
 * URL State Manager (Single Source of Truth)
 */
class PPFXURLStateManager {
  constructor() {
    this._listeners = new Set();
    this._initialized = false;
    this._initPopstate();
  }

  _initPopstate() {
    if (typeof window === "undefined" || this._initialized) return;
    this._initialized = true;

    window.addEventListener("popstate", (e) => {
      const canonicalState = this.getCanonicalState();
      this._dispatchChange(canonicalState, {
        source: "popstate",
        state: e.state,
      });
    });
  }

  /**
   * Reads current URL parameters and returns internal state representation.
   *
   * @returns {Record<string, string>}
   */
  getCanonicalState() {
    if (typeof window === "undefined") return {};
    return toInternalParams(new URLSearchParams(window.location.search));
  }

  /**
   * Updates the URL and dispatches the synthetic 'ppfx:url-change' event.
   *
   * @param {Record<string, any>} newInternalParams
   * @param {object} [options]
   * @param {boolean} [options.pushHistory=true] Whether to push or replace state
   * @param {string} [options.source='user'] Event source (e.g. 'user', 'slider', 'reset', 'popstate')
   * @param {boolean} [options.force=false] Force dispatch even if URL string is unchanged
   * @returns {boolean} True if state changed or forced
   */
  setCanonicalState(newInternalParams, options = {}) {
    const { pushHistory = true, source = "user", force = false } = options;
    const currentUrl = new URL(window.location.href);
    const newUrl = new URL(window.location.href);

    // Identify and clear all existing filter parameters
    const publicFilterKeys = new Set([
      ...Object.values(PUBLIC_PARAM_MAP),
      ...(window.ppfxFilterData?.filterKeys ?? []),
      "ppfx_instance_id",
    ]);

    Array.from(newUrl.searchParams.keys()).forEach((key) => {
      const baseKey = key.endsWith("[]");
      const checkKey = baseKey ? key.slice(0, -2) : key;
      if (
        publicFilterKeys.has(checkKey) ||
        checkKey.startsWith("ppfx_filter_") ||
        checkKey.startsWith("ppfx_")
      ) {
        newUrl.searchParams.delete(key);
      }
    });

    // When page is reset, ensure native WordPress pagination params are purged too
    if (!newInternalParams.page) {
      newUrl.searchParams.delete("paged");
      newUrl.searchParams.delete("page");
      newUrl.searchParams.delete("product-page");
    }

    const publicParams = toPublicParams(newInternalParams);

    // Apply new public params
    Object.keys(publicParams).forEach((key) => {
      if (key === "__proto__" || key === "constructor" || key === "prototype")
        return;
      const val = publicParams[key];
      if (val !== undefined && val !== null && val !== "") {
        newUrl.searchParams.set(key, String(val));
      }
    });

    const urlChanged = currentUrl.href !== newUrl.href;

    if (urlChanged) {
      if (pushHistory) {
        window.history.pushState({ ppfx: newInternalParams }, "", newUrl);
      } else {
        window.history.replaceState({ ppfx: newInternalParams }, "", newUrl);
      }
    }

    if (urlChanged || force) {
      this._dispatchChange(newInternalParams, { source, url: newUrl.href });
      return true;
    }

    return false;
  }

  _dispatchChange(params, detail = {}) {
    const eventDetail = { params, ...detail };
    window.dispatchEvent(
      new CustomEvent("ppfx:url-change", { detail: eventDetail }),
    );
    this._listeners.forEach((fn) => {
      try {
        fn(params, eventDetail);
      } catch (err) {
        void err;
      }
    });
  }

  /**
   * Subscribe to URL state changes.
   *
   * @param {(params: Record<string, string>, detail: any) => void} listener
   * @returns {() => void} Unsubscribe function
   */
  subscribe(listener) {
    this._listeners.add(listener);
    return () => this._listeners.delete(listener);
  }
}

export const PPFX_URL_State = new PPFXURLStateManager();

/**
 * Idempotently hydrate form UI inputs to match the canonical filter state.
 * Preserves active document element focus, cursor selection, and slider drag states.
 *
 * @param {HTMLElement} formElement
 * @param {Record<string, any>} filters
 */
export const applyFilterState = (formElement, filters = {}) => {
  if (!formElement) return;
  const activeEl = document.activeElement;

  // 1. CHECKBOX / RADIO:
  formElement
    .querySelectorAll('input[type="checkbox"], input[type="radio"]')
    .forEach((input) => {
      const stateVal = filters[input.name];
      const shouldBeChecked = stateVal
        ? deserializeArrayValues(stateVal).includes(input.value)
        : false;
      if (input.checked !== shouldBeChecked) {
        input.checked = shouldBeChecked;
      }
      input.dataset.wasChecked = String(shouldBeChecked);
    });

  // 2. SELECT (exclude orderby which is handled by applySortState):
  formElement
    .querySelectorAll('select:not([name="orderby"])')
    .forEach((select) => {
      if (select === activeEl) return;
      const stateVal = filters[select.name] ?? "";
      if (select.value !== String(stateVal)) {
        select.value = String(stateVal);
      }
    });

  // 3. BUTTON GRID / SWATCHES:
  formElement
    .querySelectorAll(".ppfx-filter-section[data-attr]")
    .forEach((section) => {
      const attr = section.dataset.attr;
      const key = attr.startsWith("pa_")
        ? "filter_" + attr.replace("pa_", "")
        : attr;
      const activeVals = new Set(deserializeArrayValues(filters[key]));

      section
        .querySelectorAll(".ppfx-grid-btn, .ppfx-swatch-item")
        .forEach((btn) => {
          const shouldBeActive = activeVals.has(btn.dataset.value);
          if (btn.classList.contains("is-active") !== shouldBeActive) {
            btn.classList.toggle("is-active", shouldBeActive);
            btn.setAttribute("aria-pressed", String(shouldBeActive));
          }
        });

      const hidden = section.querySelector(
        ".ppfx-grid-hidden-input, .ppfx-swatch-hidden-input",
      );
      if (hidden) {
        hidden.value = serializeArrayValues(Array.from(activeVals));
      }
    });

  // 4. TOGGLE SWITCH:
  const toggle = formElement.querySelector(
    ".ppfx-type-toggle .ppfx-frontend-toggle",
  );
  if (toggle) {
    const shouldBeActive = filters.on_sale === "1";
    if (toggle.classList.contains("is-active") !== shouldBeActive) {
      toggle.classList.toggle("is-active", shouldBeActive);
      toggle.setAttribute("aria-checked", String(shouldBeActive));
      const hidden = toggle
        .closest(".ppfx-toggle-row")
        ?.querySelector(".ppfx-toggle-input");
      if (hidden) hidden.value = shouldBeActive ? "1" : "";
    }
  }

  // 5. STAR RATING:
  formElement.querySelectorAll(".ppfx-star-row").forEach((row) => {
    const shouldBeActive =
      String(filters.rating ?? "") === String(row.dataset.rating);
    if (row.classList.contains("is-active") !== shouldBeActive) {
      row.classList.toggle("is-active", shouldBeActive);
      row.setAttribute("aria-pressed", String(shouldBeActive));
    }
  });
  const ratingHidden = formElement.querySelector(".ppfx-rating-input");
  if (ratingHidden) {
    ratingHidden.value = filters.rating ?? "";
  }

  // 6. PRICE SLIDER:
  const minInput = formElement.querySelector('input[name="min_price"]');
  const maxInput = formElement.querySelector('input[name="max_price"]');
  if (minInput && maxInput) {
    const defaultMin = minInput.min || minInput.dataset.min || "";
    const defaultMax = maxInput.max || maxInput.dataset.max || "";
    const newMin =
      filters.min_price !== undefined && filters.min_price !== ""
        ? filters.min_price
        : defaultMin;
    const newMax =
      filters.max_price !== undefined && filters.max_price !== ""
        ? filters.max_price
        : defaultMax;

    let sliderNeedsUpdate = false;

    // Do not disrupt focus or active slider thumb if currently dragging
    if (minInput !== activeEl && minInput.value !== String(newMin)) {
      minInput.value = newMin;
      sliderNeedsUpdate = true;
    }
    if (maxInput !== activeEl && maxInput.value !== String(newMax)) {
      maxInput.value = newMax;
      sliderNeedsUpdate = true;
    }

    if (sliderNeedsUpdate) {
      minInput.dispatchEvent(new Event("input", { bubbles: true }));
    }
  }

  // 7. TEXT SEARCH INPUTS:
  formElement.querySelectorAll(".ppfx-search-input").forEach((input) => {
    const key = input.name;
    if (!key) return;
    const expected = filters[key] ?? "";
    if (input.value !== expected) {
      if (input === activeEl) {
        const start = input.selectionStart;
        const end = input.selectionEnd;
        input.value = expected;
        if (start !== null && end !== null) {
          input.setSelectionRange(start, end);
        }
      } else {
        input.value = expected;
      }
    }
  });
};

export const applySortState = (root, sort = {}) => {
  if (!root) return;
  const target = sort.orderby || "";
  const selector =
    'select[name="orderby"], select.orderby, .woocommerce-ordering select';
  let selects = root.querySelectorAll(selector);
  if (
    selects.length === 0 &&
    root !== document &&
    typeof document !== "undefined"
  ) {
    selects = document.querySelectorAll(selector);
  }

  selects.forEach((select) => {
    if (target) {
      const matchingOption = select.querySelector(
        `option[value="${cssEscape(target)}"]`,
      );
      if (matchingOption) {
        if (select.value !== target) {
          select.value = target;
        }
        return;
      }
    }

    // Default/fallback: if target is empty or invalid, resolve to the default option.
    // Never assign an empty string if no option has value="".
    const defaultOption =
      select.querySelector('option[value=""]') ||
      select.querySelector("option[selected]") ||
      select.querySelector('option[value="menu_order"]') ||
      select.options[0];

    if (defaultOption && select.value !== defaultOption.value) {
      select.value = defaultOption.value;
    }
  });
};
