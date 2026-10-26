import {
  cssEscape,
  PPFX_URL_State,
  deserializeArrayValues,
  serializeArrayValues,
} from "../utils/url";

export const initActiveFilters = (formElement) => {
  // Tear down any previous instance to prevent listener leaks
  if (
    formElement._ppfxActiveFilters &&
    typeof formElement._ppfxActiveFilters.destroy === "function"
  ) {
    formElement._ppfxActiveFilters.destroy();
  }

  const root = formElement.closest(".ppfx-filter-root") || formElement;

  const minPriceInput = formElement.querySelector('input[name="min_price"]');
  const maxPriceInput = formElement.querySelector('input[name="max_price"]');

  const getTargetContainer = () => {
    const wrap = root.querySelector(".ppfx-active-filters-wrap");
    const isSuppressed = wrap && wrap.dataset.ppfxChipsSuppressed === "true";
    const filterId = root.dataset.ppfxFilterId;
    const isPrimary =
      root.classList.contains("ppfx-primary-filter") ||
      document.querySelector(".ppfx-filter-root") === root;

    let detachedWrap = filterId
      ? document.querySelector(`[data-ppfx-chips-for="${cssEscape(filterId)}"]`)
      : null;
    if (!detachedWrap && isPrimary) {
      detachedWrap = document.querySelector('[data-ppfx-chips-for="*"]');
    }

    if (detachedWrap) {
      return {
        container: detachedWrap.querySelector("[data-ppfx-active-filters]"),
        noChips: detachedWrap.querySelector("[data-ppfx-no-chips]"),
        detachedWrap,
      };
    }

    if (isSuppressed) {
      return { container: null, noChips: null, detachedWrap: null };
    }

    return {
      container: root.querySelector("[data-ppfx-active-filters]"),
      noChips: root.querySelector("[data-ppfx-no-chips]"),
      detachedWrap: null,
    };
  };

  const updateUrlAfterChipRemoval = (modifierFn) => {
    const state = { ...PPFX_URL_State.getCanonicalState() };
    delete state.page;
    delete state.paged;
    modifierFn(state);
    PPFX_URL_State.setCanonicalState(state, {
      pushHistory: true,
      source: "chip_remove",
    });
  };

  const IGNORED_CHIP_KEYS = new Set([
    "orderby",
    "order",
    "page",
    "paged",
    "s",
    "post_type",
    "instance_id",
    "toolbar_missing",
    "min_price",
    "max_price",
  ]);

  const humanize = (val) => {
    return String(val)
      .replace(/[-_]/g, " ")
      .replace(/\b\w/g, (c) => c.toUpperCase())
      .trim();
  };

  const safeQuery = (selector) => {
    try {
      return formElement.querySelector(selector);
    } catch (err) {
      void err;
      return null;
    }
  };

  const resolveLabel = (key, val) => {
    // 1. Check for on_sale toggle
    if (key === "on_sale") {
      const toggleLabel = formElement.querySelector(".ppfx-toggle-label");
      return (
        toggleLabel?.textContent.trim() ||
        window.ppfxFilterData?.i18n?.onSale ||
        "On Sale"
      );
    }

    // 2. Check for checkbox / radio
    const input = safeQuery(
      `input[name="${cssEscape(key)}"][value="${cssEscape(val)}"]`,
    );
    if (input) {
      const labelElement = input.closest("label");
      const text =
        input.dataset.label ||
        labelElement?.textContent.replace(/\s*\(\d+\)\s*$/, "").trim();
      if (text) return text;
    }

    // 3. Check for select option
    const option = safeQuery(
      `select[name="${cssEscape(key)}"] option[value="${cssEscape(val)}"]`,
    );
    if (option) {
      const text =
        option.dataset.label ||
        option.textContent.replace(/\s*\(\d+\)\s*$/, "").trim();
      if (text) return text;
    }

    // 4. Check for swatches, button grid, star rating
    const customEl = safeQuery(
      `.ppfx-swatch-item[data-value="${cssEscape(val)}"], ` +
        `.ppfx-grid-btn[data-value="${cssEscape(val)}"], ` +
        `.ppfx-star-row[data-value="${cssEscape(val)}"], ` +
        `.ppfx-star-row[data-rating="${cssEscape(val)}"]`,
    );
    if (customEl) {
      let text =
        customEl.dataset.label || customEl.title || customEl.textContent.trim();
      if (!text && customEl.dataset.rating) {
        text =
          customEl.dataset.rating +
          " " +
          (window.ppfxFilterData?.i18n?.stars || "Stars");
      }
      if (!text && customEl.dataset.value) {
        text = humanize(customEl.dataset.value);
      }
      if (text) return text;
    }

    // 5. Fallback: humanize raw value
    return humanize(val) || window.ppfxFilterData?.i18n?.filter || "Filter";
  };

  const renderChips = () => {
    const { container, noChips, detachedWrap } = getTargetContainer();
    if (!container) return;

    if (!container.hasAttribute("aria-live")) {
      container.setAttribute("aria-live", "polite");
      container.setAttribute("role", "status");
    }

    container.innerHTML = "";
    let hasActive = false;

    const state = PPFX_URL_State.getCanonicalState();

    // 1. Process regular filter params from canonical URL state
    Object.entries(state).forEach(([key, rawValue]) => {
      if (IGNORED_CHIP_KEYS.has(key) || !rawValue) return;

      const values = deserializeArrayValues(rawValue);
      values.forEach((val) => {
        if (!val) return;
        hasActive = true;
        const labelText = resolveLabel(key, val);
        const chip = createChip(labelText, () => {
          updateUrlAfterChipRemoval((nextState) => {
            const currentVals = deserializeArrayValues(nextState[key]);
            const filtered = currentVals.filter((v) => v !== val);
            if (filtered.length > 0) {
              nextState[key] = serializeArrayValues(filtered);
            } else {
              delete nextState[key];
            }
          });
        });
        container.appendChild(chip);
      });
    });

    // 2. Price slider chip from canonical state
    const hasMinPrice =
      typeof state.min_price !== "undefined" && state.min_price !== "";
    const hasMaxPrice =
      typeof state.max_price !== "undefined" && state.max_price !== "";

    if (hasMinPrice || hasMaxPrice) {
      const minBound = minPriceInput ? minPriceInput.min : null;
      const maxBound = maxPriceInput ? maxPriceInput.max : null;
      const currentMin = hasMinPrice ? state.min_price : minBound;
      const currentMax = hasMaxPrice ? state.max_price : maxBound;

      const isDefaultBounds =
        minBound !== null &&
        maxBound !== null &&
        Number(currentMin) === Number(minBound) &&
        Number(currentMax) === Number(maxBound);

      if (!isDefaultBounds && currentMin !== null && currentMax !== null) {
        hasActive = true;
        const sliderGroup = formElement.querySelector(".ppfx-price-group");
        const minLabel = sliderGroup?.querySelector(".ppfx-price-min-label");
        const currency =
          sliderGroup?.dataset.currency ||
          minLabel?.textContent.replace(/[0-9.,\s]/g, "").trim() ||
          "$";
        const priceFormat =
          sliderGroup?.dataset.priceFormat ||
          (minLabel?.textContent.trim().startsWith(currency)
            ? `${currency}%v`
            : `%v ${currency}`);
        const locale = document.documentElement.lang || "en-US";
        const step = minPriceInput
          ? Number.parseFloat(minPriceInput.step) || 1
          : 1;
        const precision = Number.isInteger(step)
          ? 0
          : Math.max(
              0,
              (String(minPriceInput?.step || "").split(".")[1] || "").length,
            );

        const formatPriceVal = (val) => {
          const formatted = new Intl.NumberFormat(locale, {
            minimumFractionDigits: precision,
            maximumFractionDigits: Math.max(precision, 2),
          }).format(Number(val));
          return priceFormat.replace("%v", formatted).trim();
        };

        const chip = createChip(
          `${formatPriceVal(currentMin)} \u2013 ${formatPriceVal(currentMax)}`,
          () => {
            if (minPriceInput) minPriceInput.value = minBound;
            if (maxPriceInput) maxPriceInput.value = maxBound;
            if (minPriceInput) minPriceInput.dispatchEvent(new Event("input"));
            updateUrlAfterChipRemoval((nextState) => {
              delete nextState.min_price;
              delete nextState.max_price;
            });
          },
        );
        container.appendChild(chip);
      }
    }

    if (noChips) {
      noChips.classList.toggle("ppfx-hidden", hasActive);
    }
    container.classList.toggle("ppfx-active", hasActive);

    if (detachedWrap && detachedWrap.contains(container)) {
      detachedWrap.classList.toggle("ppfx-hidden", !hasActive);
    }
  };

  const createChip = (text, onRemove) => {
    const chip = document.createElement("span");
    chip.className = "ppfx-chip";

    const label = document.createTextNode(text + " ");
    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "ppfx-chip-x";
    removeBtn.setAttribute("aria-label", `Remove filter: ${text}`);
    removeBtn.textContent = "\u2715";
    removeBtn.addEventListener("click", onRemove);

    chip.appendChild(label);
    chip.appendChild(removeBtn);
    return chip;
  };

  // Initial render
  renderChips();

  // Listen for state updates to re-render chips
  document.addEventListener("ppfx-filters-updated", renderChips);

  const instance = {
    render: renderChips,
    destroy: () => {
      const { container } = getTargetContainer();
      if (container) container.innerHTML = "";
      document.removeEventListener("ppfx-filters-updated", renderChips);
      if (formElement._ppfxActiveFilters === instance) {
        delete formElement._ppfxActiveFilters;
      }
    },
  };

  formElement._ppfxActiveFilters = instance;
  return instance;
};
