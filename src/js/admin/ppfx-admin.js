import "./ppfx-term-meta.js";

/**
 * Pickory Product Filter — Admin JS
 * Handles: jQuery UI Sortable, toggle switches, color pickers, tabs, and index-based ordering.
 */
(function ($) {
  "use strict";

  const SORTABLE_ID = "#ppfx-sortable";

  /**
   * Update each row's hidden order input to match current DOM position.
   * This relies on the native array saving logic in the backend.
   */
  function updateOrderInputs() {
    $(SORTABLE_ID + " tr.ppfx-section-row").each(function (index) {
      // Update the hidden input value to the current 1-based index
      $(this)
        .find(".ppfx-order-input")
        .val(index + 1);
    });
  }

  /**
   * Init jQuery UI Sortable on the section rows tbody.
   */
  function initSortable() {
    const $sortable = $(SORTABLE_ID);
    if (!$sortable.length) return;

    $sortable.sortable({
      axis: "y",
      handle: ".ppfx-drag-handle",
      placeholder: "ui-sortable-placeholder",
      helper: function (e, tr) {
        // Lock column widths during drag so the row doesn't collapse
        const originals = tr.children();
        const helper = tr.clone();
        helper.children().each(function (index) {
          $(this).width(originals.eq(index).width());
        });
        return helper;
      },
      stop: function () {
        updateOrderInputs();
      },
    });
  }

  /**
   * Handle declarative toggle switch clicks & keyboard navigation.
   * Supports both table row switches and standalone component switches.
   */
  function initToggles() {
    $(document).on("click keydown", ".ppfx-toggle-switch", function (e) {
      if (e.type === "keydown" && e.key !== "Enter" && e.key !== " ") {
        return;
      }

      const $toggle = $(this);

      // Guard: do not process clicks on a disabled toggle.
      if ($toggle.hasClass("ppfx-toggle-disabled")) {
        return;
      }

      e.preventDefault();

      const isOn = $toggle.hasClass("ppfx-toggle-on");
      const newState = !isOn;
      const $row = $toggle.closest("tr");
      const dataKey = $toggle.data("key");

      // Find associated input:
      // 1. data-target selector if specified
      // 2. Immediate next/sibling hidden input
      // 3. Row-scoped input based on key/class
      let $input = $toggle.data("target") ? $($toggle.data("target")) : null;

      if (!$input || !$input.length) {
        $input = $toggle.next('input[type="hidden"]');
      }
      if (
        (!$input || !$input.length) &&
        $toggle.siblings('input[type="hidden"]').length
      ) {
        $input = $toggle.siblings('input[type="hidden"]').first();
      }

      const isCollapsibleToggle =
        dataKey && dataKey.toString().endsWith("-collapsible");
      const isCountToggle = dataKey && dataKey.toString().endsWith("-count");

      if (isCollapsibleToggle && (!$input || !$input.length)) {
        $input = $row.find(".ppfx-collapsible-input");
      } else if (isCountToggle && (!$input || !$input.length)) {
        $input = $row.find(".ppfx-show-count-input");
      } else if (!$input || !$input.length) {
        $input = $row.find(".ppfx-enabled-input");
      }

      // Update visual and accessibility state
      $toggle.toggleClass("ppfx-toggle-on", newState);
      $toggle.attr("aria-checked", newState ? "true" : "false");

      if ($input && $input.length) {
        $input.val(newState ? "1" : "0").trigger("change");
      }

      // If this is a main section enabled switch in a table row, toggle disabled row style
      if ($row.length && !isCollapsibleToggle && !isCountToggle) {
        $toggle.attr(
          "title",
          newState
            ? "Enabled — click to disable"
            : "Disabled — click to enable",
        );
        $row.toggleClass("ppfx-row-disabled", !newState);
      }
    });
  }

  /**
   * Sync the collapsible (See More) toggle enabled/disabled state based on
   * the max_height input in the same row.
   */
  function initCollapsibleDependency() {
    /**
     * For a given max_height input, enable or disable the collapsible toggle
     * in the same row depending on whether the input has a non-zero value.
     *
     * @param {jQuery} $maxHeightInput
     */
    function syncCollapsibleToggle($maxHeightInput) {
      const $row = $maxHeightInput.closest("tr");
      const $toggle = $row.find(".ppfx-collapsible-toggle");
      const $input = $row.find(".ppfx-collapsible-input");

      if (!$toggle.length) return;

      const val = parseInt($maxHeightInput.val(), 10);
      const hasValue = $maxHeightInput.val() !== "" && !isNaN(val) && val >= 1;

      if (hasValue) {
        // Enable the toggle.
        $toggle.removeClass("ppfx-toggle-disabled");
        // Restore the state that was saved before the field was cleared.
        const saved = $toggle.data("pre-disable-state");
        if (saved === "1") {
          $toggle.addClass("ppfx-toggle-on");
          $toggle.attr("aria-checked", "true");
          $input.val("1");
          $toggle.removeData("pre-disable-state");
        }
      } else {
        // Stash current state BEFORE clobbering it.
        $toggle.data("pre-disable-state", $input.val());
        // Disable the toggle and force it off.
        $toggle.addClass("ppfx-toggle-disabled");
        $toggle.removeClass("ppfx-toggle-on");
        $toggle.attr("aria-checked", "false");
        $input.val("0");
      }
    }

    // Sync on every keystroke / change to the max_height input.
    $(document).on("input change", ".ppfx-max-height-input", function () {
      syncCollapsibleToggle($(this));
    });

    // Initial sync on page load for all existing rows.
    $(".ppfx-max-height-input").each(function () {
      syncCollapsibleToggle($(this));
    });
  }

  /**
   * Handle type select change — show/hide swatch warning if needed.
   */
  function initTypeSelects() {
    $(document).on("change", ".ppfx-type-select", function () {
      const $select = $(this);
      const $row = $select.closest("tr");
      const $warning = $row.find(".ppfx-swatch-warning");
      const isPa = $row.data("is-pa") === 1;
      const val = $select.val();
      const isSwatch = val === "swatch" || val === "image-swatch";

      if (!isPa && isSwatch) {
        $warning.show();
      } else {
        $warning.hide();
      }
    });

    // Initial check for existing values on page load
    $(".ppfx-type-select").each(function () {
      $(this).trigger("change");
    });
  }

  /**
   * Initialize WordPress color pickers on admin screens.
   */
  function initColorPickers() {
    if (typeof $.fn.wpColorPicker !== "undefined") {
      $(".ppfx-color-picker").wpColorPicker();
    }
  }

  // Initialize components on layout load
  $(document).ready(function () {
    initSortable();
    initToggles();
    initCollapsibleDependency();
    initTypeSelects();
    initColorPickers();

    // Initial order calculation to ensure inputs are correct on load
    updateOrderInputs();

    // Mobile drawer breakpoint and mode radio dependencies
    const cb = document.getElementById("ppfx_mobile_drawer_enabled");
    const bp = document.getElementById("ppfx_mobile_drawer_breakpoint");
    if (cb && bp) {
      cb.addEventListener("change", function () {
        bp.disabled = !this.checked;
        document
          .querySelectorAll(".ppfx-drawer-mode-radio")
          .forEach(function (r) {
            r.disabled = !cb.checked;
          });
      });
    }
  });
})(jQuery);
