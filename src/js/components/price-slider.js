/**
 * Component: Price Slider
 * Handles dual-thumb range inputs, track visualization, and label updates.
 */
export const initPriceSlider = (formElement, onFilterChange) => {
  const sliderGroup = formElement.querySelector(".ppfx-price-group");
  if (!sliderGroup) return;

  const minInput = sliderGroup.querySelector('input[name="min_price"]');
  const maxInput = sliderGroup.querySelector('input[name="max_price"]');
  const trackFill = sliderGroup.querySelector(".ppfx-price-track-fill");
  const minLabel = sliderGroup.querySelector(".ppfx-price-min-label");
  const maxLabel = sliderGroup.querySelector(".ppfx-price-max-label");

  if (!minInput || !maxInput || !minLabel || !maxLabel) return;

  const currencySymbol =
    sliderGroup.dataset.currency ||
    minLabel.textContent.replace(/[0-9.,\s]/g, "").trim() ||
    "$";
  const priceFormat =
    sliderGroup.dataset.priceFormat ||
    (minLabel.textContent.trim().startsWith(currencySymbol)
      ? `${currencySymbol}%v`
      : `%v ${currencySymbol}`);
  const locale = document.documentElement.lang || "en-US";
  const step = Number.parseFloat(minInput.step) || 1;
  const precision = Number.isInteger(step)
    ? 0
    : Math.max(0, (String(minInput.step).split(".")[1] || "").length);

  const parseNumber = (value, fallback = 0) => {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  };

  const formatInputValue = (value) => {
    const rounded = Number(value.toFixed(Math.min(precision, 6)));
    return String(rounded);
  };

  const roundToStep = (value) => {
    const min = parseNumber(minInput.min);
    const stepped = Math.round((value - min) / step) * step + min;
    return Number(stepped.toFixed(Math.min(precision, 6)));
  };

  const formatPrice = (value) => {
    const formattedValue = new Intl.NumberFormat(locale, {
      minimumFractionDigits: precision,
      maximumFractionDigits: Math.max(precision, 2),
    }).format(value);

    return priceFormat.replace("%v", formattedValue).trim();
  };

  const getPercent = (value) => {
    const min = parseNumber(minInput.min);
    const max = parseNumber(minInput.max);
    const range = max - min;
    if (range === 0) {
      return 0;
    }
    return ((value - min) / (max - min)) * 100;
  };

  const updateSlider = (activeInput = null, skipLabelUpdate = false) => {
    const min = parseNumber(minInput.min);
    const max = parseNumber(minInput.max);
    let minVal = roundToStep(parseNumber(minInput.value, min));
    let maxVal = roundToStep(parseNumber(maxInput.value, max));

    minVal = Math.min(Math.max(minVal, min), max);
    maxVal = Math.min(Math.max(maxVal, min), max);

    // Clamp the active handle instead of swapping DOM input values mid-drag.
    if (minVal > maxVal) {
      if (activeInput === maxInput) {
        maxVal = minVal;
      } else {
        minVal = maxVal;
      }
    }

    minInput.value = formatInputValue(minVal);
    maxInput.value = formatInputValue(maxVal);

    // Update Labels
    if (!skipLabelUpdate) {
      minLabel.textContent = formatPrice(minVal);
      maxLabel.textContent = formatPrice(maxVal);
    }

    // Update Track Fill
    if (trackFill) {
      const minPercent = getPercent(minVal);
      const maxPercent = getPercent(maxVal);
      trackFill.style.left = minPercent + "%";
      trackFill.style.width = maxPercent - minPercent + "%";
    }
  };

  // Initial update
  updateSlider(null, true);

  // Listen to input for smooth label/track updates
  const handleInput = (event) => updateSlider(event.target);
  const handleChange = () => onFilterChange(true, "slider");

  minInput.addEventListener("input", handleInput);
  maxInput.addEventListener("input", handleInput);

  // Trigger filter update on change (mouse release / touch end)
  minInput.addEventListener("change", handleChange);
  maxInput.addEventListener("change", handleChange);

  return {
    destroy: () => {
      minInput.removeEventListener("input", handleInput);
      maxInput.removeEventListener("input", handleInput);
      minInput.removeEventListener("change", handleChange);
      maxInput.removeEventListener("change", handleChange);
    },
  };
};
