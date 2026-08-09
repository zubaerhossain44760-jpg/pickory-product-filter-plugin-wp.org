import "../scss/main.scss";
import { initFilterForm } from "./components/filter-form";
import { initPriceSlider } from "./components/price-slider";
import { initActiveFilters } from "./components/active-filters";
import { initSearchList } from "./components/search-list";
import { initMobileDrawer } from "./components/mobile-drawer";
import { cssEscape } from "./utils/url";

document.addEventListener("DOMContentLoaded", () => {
  const filterForms = document.querySelectorAll("[data-ppfx-filter-form]");

  if (!filterForms.length) return;

  // Prevent WooCommerce's native ordering form from triggering a full-page
  // reload. The plugin's global sort handler (select[name="orderby"] on
  // document) owns the sort interaction exclusively via e.stopImmediatePropagation().
  document.querySelectorAll('.woocommerce-ordering').forEach((form) => {
    form.addEventListener('submit', (e) => e.preventDefault());
  });

  const isAutoFilter =
    window.ppfxFilterData &&
    (window.ppfxFilterData.autoFilter === "1" ||
      window.ppfxFilterData.autoFilter === true);

  filterForms.forEach((filterForm) => {
      // Idempotent initialization guard to prevent double-binding
      if (filterForm.dataset.ppfxInitialized === "1") return;
      filterForm.dataset.ppfxInitialized = "1";

      // Resolve container per form
      const root = filterForm.closest(".ppfx-filter-root") || document;
      const filterContainer = filterForm.closest(".ppfx-filter-container");
      const filterId = root.dataset.ppfxFilterId;
      const instanceId = filterForm.dataset.ppfxInstance;
      const targetSelector = filterForm.dataset.ppfxTarget;

      let productsContainer = null;
    if (targetSelector) {
      productsContainer = document.querySelector(targetSelector);
    }
    // Try instance-based selector with escaped ID
    if (!productsContainer && instanceId) {
      productsContainer = document.querySelector(`.ppfx-products-wrapper[data-ppfx-instance='${cssEscape(instanceId)}']`);
    }
    // Use global fallback if we can't find instance-specific container
    if (!productsContainer) {
      productsContainer =
          root.querySelector(".ppfx-products-wrapper") ||
          document.querySelector(".ppfx-products-wrapper") ||
          document.querySelector(".products") ||
          document.querySelector(".woocommerce-no-products") ||
          document.querySelector(".woocommerce-info");
    }

      if (!productsContainer) return;

      // Set up ARIA attributes per container
      productsContainer.setAttribute("aria-live", "polite");
      productsContainer.setAttribute("aria-atomic", "false");
      productsContainer.setAttribute("aria-busy", "false");

    // Initialize Search-capable lists
    filterForm.querySelectorAll(".ppfx-type-search-list").forEach((container) => {
      initSearchList(container);
    });

    // Initialize Active Filters (Chips)
    const activeFilters = initActiveFilters(filterForm, () => {
      formController?.handleFilterChange(true);
    });

    // Initialize Filter Form Controller
    const formController = initFilterForm(filterForm, productsContainer, {
      isAutoFilter,
      onAfterUpdate: () => {
        activeFilters?.render();
      },
    });

    // Initialize Price Slider
    initPriceSlider(filterForm, (resetPage = true, inputType = "slider") => {
      formController.updatePriceState();
      if (isAutoFilter) {
        formController.handleFilterChange(resetPage, inputType);
      }
    });

    // Initialize Mobile Drawer for this instance
    if (filterContainer && filterId) {
      const mobileToggle = document.querySelector(`[data-ppfx-mobile-toggle="${filterId}"]`);
      const backdrop = document.querySelector(`[data-ppfx-backdrop="${filterId}"]`);
      const drawerInstance = initMobileDrawer(filterContainer, mobileToggle, backdrop, filterForm);
      window.__ppfxDrawerInstance = drawerInstance;

      window.addEventListener('ppfx:destroy', function() {
          if ( drawerInstance ) drawerInstance.destroy();
      }, { once: true });
    }
  });
});
