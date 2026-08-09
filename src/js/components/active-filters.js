import { cssEscape } from "../utils/url";

export const initActiveFilters = (formElement, onFilterChange) => {
    const root = formElement.closest('.ppfx-filter-root') || formElement;
    const container = root.querySelector('[data-ppfx-active-filters]');
    const noChips = root.querySelector('[data-ppfx-no-chips]');
    const minPriceInput = formElement.querySelector('input[name="min_price"]');
    const maxPriceInput = formElement.querySelector('input[name="max_price"]');
    
    if (!container) return;

    const renderChips = () => {
        container.innerHTML = '';
        let hasActive = false;

        // Checkboxes & Radios
        formElement.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked').forEach(input => {
            hasActive = true;
            const labelElement = input.closest('label');
            const labelText = input.dataset.label || labelElement?.textContent.replace(/\(\d+\)/, '').trim() || (window.ppfxFilterData?.i18n?.filter || 'Filter');
            const chip = createChip(labelText, () => {
                input.checked = false;
                const remaining = Array.from(formElement.querySelectorAll(
                    `input[type="checkbox"][name="${cssEscape(input.name)}"]:checked, input[type="radio"][name="${cssEscape(input.name)}"]:checked`
                )).map(i => i.value);
                if (remaining.length > 0) {
                    window.ppfxGlobalState.filters[input.name] = remaining.join(',');
                } else {
                    delete window.ppfxGlobalState.filters[input.name];
                }
                onFilterChange(true);
            });
            container.appendChild(chip);
        });

        // Select Dropdowns (exclude orderby which is a view control, not a filter)
        formElement.querySelectorAll('select:not([name="orderby"])').forEach(select => {
            if (select.value) {
                hasActive = true;
                const selectedOption = select.selectedOptions[0];
                const labelText = selectedOption.textContent.replace(/\(\d+\)/, '').trim() || (window.ppfxFilterData?.i18n?.filter || 'Filter');
                const chip = createChip(labelText, () => {
                    select.value = '';
                    delete window.ppfxGlobalState.filters[select.name];
                    onFilterChange(true);
                });
                container.appendChild(chip);
            }
        });

        // Custom interactive inputs (Button Grid, Swatches, etc.)
        formElement.querySelectorAll(
            '.ppfx-grid-btn.is-active, .ppfx-swatch-item.is-active, .ppfx-star-row.is-active, .ppfx-frontend-toggle.is-active'
        ).forEach(activeEl => {
            hasActive = true;
            // Use data-label if present, otherwise fallback to title/text
            let labelText = activeEl.dataset.label || activeEl.title || activeEl.textContent.trim();
            if (!labelText) {
                // If it's a toggle, use the toggle label span
                const toggleRow = activeEl.closest('.ppfx-toggle-row');
                if (toggleRow) {
                    labelText = toggleRow.querySelector('.ppfx-toggle-label')?.textContent.trim() || (window.ppfxFilterData?.i18n?.onSale || 'On Sale');
                } else if (activeEl.dataset.rating) {
                    labelText = activeEl.dataset.rating + (window.ppfxFilterData?.i18n?.stars || ' Stars');
                }
            }
            if (!labelText) return; // avoid empty chips
            
            const chip = createChip(labelText, () => {
                activeEl.classList.remove('is-active');
                if (activeEl.tagName === 'BUTTON') activeEl.setAttribute('aria-pressed', 'false');
                else if (activeEl.classList.contains('ppfx-frontend-toggle')) activeEl.setAttribute('aria-checked', 'false');

                const list = activeEl.closest('.ppfx-swatch-list, .ppfx-button-grid, .ppfx-star-rating-list, .ppfx-toggle-row');
                if (list) {
                    const hiddenInput = list.querySelector('input[type="hidden"]');
                    
                    // Ensure semantic filters delete from global state even if hidden input is missing
                    if (activeEl.classList.contains('ppfx-star-row')) {
                        delete window.ppfxGlobalState.filters.rating;
                    } else if (activeEl.classList.contains('ppfx-frontend-toggle')) {
                        delete window.ppfxGlobalState.filters.on_sale;
                    }
                    
                    if (hiddenInput && activeEl.dataset.value) {
                        const values = hiddenInput.value.split(',').filter(v => v !== activeEl.dataset.value);
                        hiddenInput.value = values.join(',');
                        if (values.length > 0) {
                            window.ppfxGlobalState.filters[hiddenInput.name] = values.join(',');
                        } else {
                            delete window.ppfxGlobalState.filters[hiddenInput.name];
                        }
                    } else if (hiddenInput) {
                        hiddenInput.value = '';
                        delete window.ppfxGlobalState.filters[hiddenInput.name];
                    }
                }
                onFilterChange(true);
            });
            container.appendChild(chip);
        });

        // Price slider
        if (minPriceInput && maxPriceInput) {
            const min = minPriceInput.value;
            const max = maxPriceInput.value;
            const minBound = minPriceInput.min;
            const maxBound = maxPriceInput.max;
            
            if (Number(min) !== Number(minBound) || Number(max) !== Number(maxBound)) {
                hasActive = true;
                const sliderGroup = formElement.querySelector('.ppfx-price-group');
                const minLabel = sliderGroup?.querySelector('.ppfx-price-min-label');
                const currency = sliderGroup?.dataset.currency || minLabel?.textContent.replace(/[0-9.,]/g, '').trim() || '$';
                const chip = createChip(`${currency}${min} \u2013 ${currency}${max}`, () => {
                    minPriceInput.value = minBound;
                    maxPriceInput.value = maxBound;
                    minPriceInput.dispatchEvent(new Event('input')); // Update labels visually
                    delete window.ppfxGlobalState.filters.min_price;
                    delete window.ppfxGlobalState.filters.max_price;
                    onFilterChange(true);
                });
                container.appendChild(chip);
            }
        }

        if (noChips) {
            noChips.classList.toggle('ppfx-hidden', hasActive);
        }
        container.classList.toggle('ppfx-active', hasActive);
    };

    const createChip = (text, onRemove) => {
        const chip = document.createElement('span');
        chip.className = 'ppfx-chip';

        const label = document.createTextNode(text + ' ');
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'ppfx-chip-x';
        removeBtn.setAttribute('aria-label', `Remove filter: ${text}`);
        removeBtn.textContent = '\u2715';
        removeBtn.addEventListener('click', onRemove);

        chip.appendChild(label);
        chip.appendChild(removeBtn);
        return chip;
    };

    // Initial render
    renderChips();

    // Listen for state updates from filter-form.js to re-render chips after hydration
    document.addEventListener('ppfx-filters-updated', renderChips);

    return { 
        render: renderChips,
        destroy: () => {
            container.innerHTML = '';
            document.removeEventListener('ppfx-filters-updated', renderChips);
        }
    };
};
