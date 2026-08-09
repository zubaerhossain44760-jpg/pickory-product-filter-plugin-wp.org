export const cssEscape = (value) => {
  const stringValue = String(value);

  if (window.CSS && typeof window.CSS.escape === 'function') {
    return window.CSS.escape(stringValue);
  }

  return stringValue.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
};

export const FILTER_KEYS = [
  'category', 'product_tag', 'page', 'min_price',
  'max_price', 'rating', 'on_sale', 'orderby', 'order'
];

const PUBLIC_PARAM_MAP = {
  'category': 'ppfx_category',
  'product_tag': 'ppfx_product_tag',
  'page': 'ppfx_page',
  'orderby': 'ppfx_orderby',
  'order': 'ppfx_order',
  'min_price': 'ppfx_min_price',
  'max_price': 'ppfx_max_price',
  'rating': 'ppfx_rating',
  'on_sale': 'ppfx_on_sale'
};

export const toPublicParams = (params) => {
  const result = {};
  Object.keys(params).forEach(key => {
    if (key === '__proto__' || key === 'constructor' || key === 'prototype') return;
    const publicKey = PUBLIC_PARAM_MAP[key] || (key.startsWith('filter_') ? `ppfx_${key}` : key);
    result[publicKey] = params[key];
  });
  return result;
};

export const toInternalParams = (params) => {
  const INTERNAL_PARAM_MAP = Object.fromEntries(
    Object.entries(PUBLIC_PARAM_MAP).map(([k, v]) => [v, k])
  );
  const result = {};
  params.forEach((value, key) => {
    if (key === '__proto__' || key === 'constructor' || key === 'prototype') return;
    if (INTERNAL_PARAM_MAP[key]) {
      result[INTERNAL_PARAM_MAP[key]] = value;
    } else if (key.startsWith('ppfx_filter_')) {
      result[key.replace('ppfx_', '')] = value;
    } else if (key.startsWith('ppfx_')) {
      result[key.replace('ppfx_', '')] = value;
    } else {
      result[key] = value;
    }
  });
  return result;
};

export const updateUrl = (params, shouldPushState = false) => {
  const newUrl = new URL(window.location);

  const keysToDelete = [];
  const publicFilterKeys = new Set([
    ...Object.values(PUBLIC_PARAM_MAP),
    ...(window.ppfxFilterData?.filterKeys ?? []),
    'ppfx_instance_id'
  ]);

  Array.from(newUrl.searchParams.keys()).forEach(key => {
    if (publicFilterKeys.has(key) || key.startsWith('ppfx_filter_')) {
      keysToDelete.push(key);
    }
  });

  keysToDelete.forEach(key => newUrl.searchParams.delete(key));

  const publicParams = toPublicParams(params);
  
  // Use set() to ensure unique keys
  Object.keys(publicParams).forEach(key => {
    if (key === '__proto__' || key === 'constructor' || key === 'prototype') return;
    if (publicParams[key] !== undefined && publicParams[key] !== null && publicParams[key] !== "") {
      newUrl.searchParams.set(key, publicParams[key]);
    }
  });

  // CRITICAL: Update URL BEFORE fetch to prevent race conditions
  if (shouldPushState && window.location.href !== newUrl.href) {
    window.history.pushState({ ppfx: params }, '', newUrl);
  } else if (!shouldPushState && window.location.href !== newUrl.href) {
    // For popstate/hydration, use replaceState to avoid creating new history entries
    window.history.replaceState({ ppfx: params }, '', newUrl);
  }
};

export const applyFilterState = (formElement, filters) => {
    // CHECKBOX / RADIO:
    formElement.querySelectorAll('input[type="checkbox"], input[type="radio"]')
        .forEach(input => {
            const stateVal = filters[input.name];
            const shouldBeChecked = stateVal
                ? String(stateVal).split(',').includes(input.value)
                : false;
            if (input.checked !== shouldBeChecked) input.checked = shouldBeChecked;
            input.dataset.wasChecked = String(shouldBeChecked);
        });

    // SELECT (exclude orderby):
    formElement.querySelectorAll('select:not([name="orderby"])').forEach(select => {
        const stateVal = filters[select.name] ?? '';
        if (select.value !== String(stateVal)) select.value = String(stateVal);
    });

    // BUTTON GRID / SWATCHES:
    formElement.querySelectorAll('.ppfx-filter-section[data-attr]').forEach(section => {
        const attr = section.dataset.attr;
        const key = attr.startsWith('pa_') ? 'filter_' + attr.replace('pa_', '') : attr;
        const activeVals = new Set(String(filters[key] ?? '').split(',').filter(Boolean));
        section.querySelectorAll('.ppfx-grid-btn, .ppfx-swatch-item').forEach(btn => {
            const shouldBeActive = activeVals.has(btn.dataset.value);
            if (btn.classList.contains('is-active') !== shouldBeActive) {
                btn.classList.toggle('is-active', shouldBeActive);
                btn.setAttribute('aria-pressed', String(shouldBeActive));
            }
        });
        const hidden = section.querySelector('.ppfx-grid-hidden-input, .ppfx-swatch-hidden-input');
        if (hidden) hidden.value = [...activeVals].join(',');
    });

    // TOGGLE:
    const toggle = formElement.querySelector('.ppfx-type-toggle .ppfx-frontend-toggle');
    if (toggle) {
        const shouldBeActive = filters.on_sale === '1';
        if (toggle.classList.contains('is-active') !== shouldBeActive) {
            toggle.classList.toggle('is-active', shouldBeActive);
            toggle.setAttribute('aria-checked', String(shouldBeActive));
            const hidden = toggle.closest('.ppfx-toggle-row')?.querySelector('.ppfx-toggle-input');
            if (hidden) hidden.value = shouldBeActive ? '1' : '';
        }
    }

    // RATING:
    formElement.querySelectorAll('.ppfx-star-row').forEach(row => {
        const shouldBeActive = String(filters.rating ?? '') === String(row.dataset.rating);
        if (row.classList.contains('is-active') !== shouldBeActive) {
            row.classList.toggle('is-active', shouldBeActive);
            row.setAttribute('aria-pressed', String(shouldBeActive));
        }
    });
    const ratingHidden = formElement.querySelector('.ppfx-rating-input');
    if (ratingHidden) ratingHidden.value = filters.rating ?? '';

    // PRICE SLIDER:
    const minInput = formElement.querySelector('input[name="min_price"]');
    const maxInput = formElement.querySelector('input[name="max_price"]');
    if (minInput && maxInput) {
        const newMin = filters.min_price ?? (minInput.min || minInput.dataset.min || '');
        const newMax = filters.max_price ?? (maxInput.max || maxInput.dataset.max || '');
        if (minInput.value !== String(newMin)) {
            minInput.value = newMin;
            minInput.dispatchEvent(new Event('input'));
        }
        if (maxInput.value !== String(newMax)) {
            maxInput.value = newMax;
            maxInput.dispatchEvent(new Event('input'));
        }
    }
};

export const applySortState = (root, sort) => {
    const select = root.querySelector('select[name="orderby"]');
    if (!select) return;
    const target = sort.orderby || '';
    if (select.value !== target) select.value = target;
};
