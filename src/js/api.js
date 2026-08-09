const responseCache = new Map();
const MAX_CACHE_ENTRIES = 10;

const cacheKey = (params) => {
  // Exclude UI-only params like instance_id from cache key
  // eslint-disable-next-line no-unused-vars
  const { instance_id, ...cacheableParams } = params;
  return JSON.stringify({
      version: window.ppfxFilterData?.cacheVersion || '1',
      params: Object.keys(cacheableParams)
          .filter((key) => key !== '__proto__' && key !== 'constructor' && key !== 'prototype')
          .sort()
          .map((key) => [key, cacheableParams[key]]),
  });
};

const CACHE_TTL = 180000; // 3 minutes in milliseconds

const hasDynamicWooSession = () => (
  document.body.classList.contains('logged-in') ||
  document.body.classList.contains('woocommerce-uses-session') ||
  document.cookie.includes('wp_woocommerce_session')
);

const getCachedResponse = (params) => {
  if (hasDynamicWooSession()) {
    return null;
  }
  const cached = responseCache.get(cacheKey(params));
  if (!cached) {
    return null;
  }
  if (Date.now() - cached.time >= CACHE_TTL) {
    responseCache.delete(cacheKey(params));
    return null;
  }
  return cached.data;
};

const setCachedResponse = (params, data) => {
  if (hasDynamicWooSession()) {
    return;
  }

  const key = cacheKey(params);
  if (responseCache.has(key)) {
    responseCache.delete(key);
  }
  responseCache.set(key, { data, time: Date.now() });

  if (responseCache.size > MAX_CACHE_ENTRIES) {
    const oldestKey = responseCache.keys().next().value;
    responseCache.delete(oldestKey);
  }
};

export const fetchProducts = async (params, externalSignal = null) => {
    const cached = getCachedResponse(params);
    if (cached) {
        return cached;
    }

    const restUrl = window.ppfxFilterData
        ? window.ppfxFilterData.restUrl
        : '/wp-json/pickory-filter/v1/products';

    const url = new URL(restUrl, window.location.origin);
    Object.keys(params).forEach((key) => {
        if (key === '__proto__' || key === 'constructor' || key === 'prototype') return;
        if (
            params[key] !== undefined &&
            params[key] !== null &&
            params[key] !== ''
        ) {
            url.searchParams.append(key, String(params[key]));
        }
    });

    const controller = new AbortController();
    const timeoutMs = 15000;
    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

    const abortFromExternal = () => controller.abort();

    if (externalSignal) {
        if (externalSignal.aborted) {
            controller.abort();
        } else {
            externalSignal.addEventListener('abort', abortFromExternal, { once: true });
        }
    }

    try {
        const headers = {
            Accept: 'application/json',
        };
        const nonce = window.ppfxFilterData?.nonce;
        if (nonce) {
            headers['X-WP-Nonce'] = nonce;
        }

        const response = await fetch(url, {
            method: 'GET',
            headers,
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`RequestFailed:${response.status}`);
        }

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('InvalidResponseFormat');
        }

        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            throw new Error('InvalidResponseJson', { cause: parseError });
        }

        setCachedResponse(params, data);
        return data;
    } finally {
        clearTimeout(timeoutId);
        if (externalSignal) {
            externalSignal.removeEventListener('abort', abortFromExternal);
        }
    }
};
