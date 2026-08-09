/**
 * Pickory Product Filter — Admin JS
 * Handles: jQuery UI Sortable, toggle switches, and index-based ordering.
 */
(function ($) {
    'use strict';

    const SORTABLE_ID = '#ppfx-sortable';

    /**
     * Update each row's hidden order input to match current DOM position.
     * This relies on the native array saving logic in the backend.
     */
    function updateOrderInputs() {
        $(SORTABLE_ID + ' tr.ppfx-section-row').each(function (index) {
            // Update the hidden input value to the current 1-based index
            $(this).find('.ppfx-order-input').val(index + 1);
        });
    }

    /**
     * Init jQuery UI Sortable on the section rows tbody.
     */
    function initSortable() {
        const $sortable = $(SORTABLE_ID);
        if (!$sortable.length) return;

        $sortable.sortable({
            axis: 'y',
            handle: '.ppfx-drag-handle',
            placeholder: 'ui-sortable-placeholder',
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
     * Handle toggle switch clicks (both Enabled and Count toggles).
     */
    function initToggles() {
        $(document).on('click', '.ppfx-toggle-switch', function () {
            const $toggle = $(this);
            const $row = $toggle.closest('tr');
            const dataKey = $toggle.data('key');
            const isCountToggle = dataKey && dataKey.toString().endsWith('-count');

            if (isCountToggle) {
                // Handle count toggle
                const $input = $row.find('.ppfx-show-count-input');
                const isOn = $toggle.hasClass('ppfx-toggle-on');

                if (isOn) {
                    $toggle.removeClass('ppfx-toggle-on');
                    $toggle.attr('aria-checked', 'false');
                    $input.val('0');
                } else {
                    $toggle.addClass('ppfx-toggle-on');
                    $toggle.attr('aria-checked', 'true');
                    $input.val('1');
                }
            } else {
                // Handle enabled toggle (existing behavior)
                const $input = $row.find('.ppfx-enabled-input');
                const isOn = $toggle.hasClass('ppfx-toggle-on');

                if (isOn) {
                    $toggle.removeClass('ppfx-toggle-on');
                    $toggle.attr('aria-checked', 'false');
                    $toggle.attr('title', 'Disabled — click to enable');
                    $input.val('0');
                    $row.addClass('ppfx-row-disabled');
                } else {
                    $toggle.addClass('ppfx-toggle-on');
                    $toggle.attr('aria-checked', 'true');
                    $toggle.attr('title', 'Enabled — click to disable');
                    $input.val('1');
                    $row.removeClass('ppfx-row-disabled');
                }
            }
        });
    }

    /**
     * Handle type select change — show/hide swatch warning if needed.
     */
    function initTypeSelects() {
        $(document).on('change', '.ppfx-type-select', function () {
            const $select = $(this);
            const $row = $select.closest('tr');
            const $warning = $row.find('.ppfx-swatch-warning');
            const isPa = $row.data('is-pa') === 1;
            const val = $select.val();
            const isSwatch = val === 'swatch' || val === 'image-swatch';

            if (!isPa && isSwatch) {
                $warning.show();
            } else {
                $warning.hide();
            }
        });

        // Initial check for existing values on page load
        $('.ppfx-type-select').each(function () {
            $(this).trigger('change');
        });
    }

    // Initialize components on layout load
    $(document).ready(function () {
        initSortable();
        initToggles();
        initTypeSelects();
        
        // Initial order calculation to ensure inputs are correct on load
        updateOrderInputs();
    });

}(jQuery));
