- The `.ppf-primary-filter` class is added by PHP only to the primary (main) filter container, so secondary filters are never accidentally targeted.

---

## 10. `src/scss/_type-checkbox.scss`

**Why:** Split checkbox and radio SCSS into separate blocks. There was a `column-gap` rule applying incorrectly to radio groups.

**What changed:**
- Separated `.ppf-checkbox-list` and `.ppf-radio-list` into distinct SCSS blocks.
- Removed the column-gap rule that was creating visual spacing glitches in radio filter groups.

---

## 11. `src/scss/_variables.scss`

**Why:** Pro CSS custom properties (`--ppf-dropdown-border-color`, `--ppf-slider-track-color`, etc.) had been added to the Free version's `:root`. These are only used by the Pro Appearance Customizer, which does not exist in Free.

**What changed:**
- Stripped all Pro-only `:root` custom property declarations.
- Restored a clean, minimal token file with only SCSS variables used by the Free plugin's CSS.

---

## 12. `templates/filter-layout.php`

**Why:** The shortcode `sections` attribute needed to be consumed in the template. Also, the primary filter container needed a CSS class to scope mobile drawer CSS.

**What changed:**
- Added `ppf-primary-filter` class to the main `.ppf-filter-container` div (used by `_mobile-drawer.scss`).
- Added conditional panel rendering: if `$requested_sections` is non-empty, only render panels matching those section keys.
- Added conditional panel title header display (hides `<h3>` title when title is empty).

---

## 13. `templates/types/type-image-swatch.php`

**Why:** Bug fix from Pro commit `231d662` (Jul 19). The fallback pill button `title` attribute only showed the term name, not the count, even when `show_count` was enabled.

**What changed (Line 27):**
- `title` attribute updated from `esc_attr($term->name)` to `esc_attr($term->name . (!empty($show_count) ? ' (' . $term->count . ')' : ''))`.

---

## 14. `templates/types/type-swatch.php`

**Why:** Two bug fixes from Pro commit `231d662` (Jul 19):
1. Color values from term meta were output raw without hex sanitization.
2. Same fallback title count issue as `type-image-swatch.php`.

**What changed (Lines 15–16, 26):**
- Color meta now fetched into `$raw_color`, then sanitized: `$color = sanitize_hex_color($raw_color) ?: $raw_color`.
- `title` attribute on fallback pill button updated to include count when `show_count` is active.

---

## Build Output

All changes compiled successfully with `npm run build`:

| Asset | Size |
|---|---|
| `build/css/app-*.css` | 17.80 kB (gzip: 3.20 kB) |
| `build/js/app-*.js` | 31.07 kB (gzip: 9.35 kB) |

> CSS bundle reduced from **21.04 kB → 17.80 kB** (–3.2 kB) due to removal of Pro CSS custom properties.
 