/**
 * Pickory Product Filter — Collapsible Sections ("See More / See Less")
 *
 * Handles the smooth expand/collapse animation for filter sections that have
 * both `max_height` and `collapsible` enabled in admin settings.
 *
 * Animation technique: JS scrollHeight method.
 *   - Animates between a known px value (saved max-height) and the full
 *     natural height (scrollHeight), so CSS transition always interpolates
 *     between two concrete pixel values (never `none`).
 *   - After expand completes, max-height is set to `none` so the container
 *     can grow freely if items are added later.
 *   - overflow-y is managed around transitions to prevent scrollbar flash.
 *
 * Scoped per filter form instance to respect the existing instance ID system.
 * Does not interact with ppfxGlobalState or applyFilterState.
 */

/**
 * Initialize See More / See Less behaviour for all collapsible sections
 * within the given filter form element.
 *
 * Called once per form instance from app.js inside filterForms.forEach().
 *
 * @param {HTMLElement} filterForm - The filter form element ([data-ppfx-filter-form]).
 */
export const initCollapsibleSections = (filterForm) => {
  // Find all sections that have been marked collapsible by PHP.
  const collapsibleSections = filterForm.querySelectorAll(
    ".ppfx-filter-section[data-ppfx-collapsible='true']",
  );

  if (!collapsibleSections.length) return;

  collapsibleSections.forEach((section) => {
    const btn = section.querySelector("[data-ppfx-see-more]");
    const scrollContainer = section.querySelector(".ppfx-scrollable-container");

    // Both elements must exist for this to be a valid collapsible section.
    if (!btn || !scrollContainer) return;

    const collapsedHeight = parseInt(btn.dataset.collapsedHeight, 10);

    if (!collapsedHeight || isNaN(collapsedHeight)) return;

    // -------------------------------------------------------------------
    // Guard: only show the button if the content is actually taller than
    // the collapsed height. If all items fit, the button is unnecessary.
    // -------------------------------------------------------------------
    const naturalHeight = scrollContainer.scrollHeight;
    if (naturalHeight <= collapsedHeight) {
      btn.style.display = "none";
      return;
    }

    // -------------------------------------------------------------------
    // Expand ("See more" → "See less")
    // -------------------------------------------------------------------
    const expand = () => {
      // Read natural scrollHeight first before any style writes.
      const targetHeight = scrollContainer.scrollHeight;

      // Lock overflow during animation to prevent scrollbar flash.
      scrollContainer.style.overflowY = "hidden";

      // Anchor the start value (already at collapsedHeight via inline style,
      // but set explicitly to be safe).
      scrollContainer.style.maxHeight = collapsedHeight + "px";

      // Animate to full natural height.
      requestAnimationFrame(() => {
        scrollContainer.style.maxHeight = targetHeight + "px";
      });

      btn.textContent = btn.dataset.labelLess;
      btn.setAttribute("aria-expanded", "true");

      // After transition, remove max-height constraint entirely so the
      // container can accommodate future DOM changes (e.g. search filtering).
      scrollContainer.addEventListener(
        "transitionend",
        () => {
          scrollContainer.style.maxHeight = "none";
          scrollContainer.style.overflowY = "auto";
        },
        { once: true },
      );
    };

    // -------------------------------------------------------------------
    // Collapse ("See less" → "See more")
    // -------------------------------------------------------------------
    const collapse = () => {
      // Read current scrollHeight first before any style writes.
      const startHeight = scrollContainer.scrollHeight;

      // When max-height is `none`, we must set it to a concrete px value
      // first so the transition has a known start point.
      scrollContainer.style.maxHeight = startHeight + "px";
      scrollContainer.style.overflowY = "hidden";

      // Animate back to the saved collapsed height.
      requestAnimationFrame(() => {
        scrollContainer.style.maxHeight = collapsedHeight + "px";
      });

      btn.textContent = btn.dataset.labelMore;
      btn.setAttribute("aria-expanded", "false");

      // After transition, restore overflow-y to auto so the scrollbar
      // reappears correctly at the clipped height.
      scrollContainer.addEventListener(
        "transitionend",
        () => {
          scrollContainer.style.overflowY = "auto";
        },
        { once: true },
      );
    };

    // -------------------------------------------------------------------
    // Click handler
    // -------------------------------------------------------------------
    btn.addEventListener("click", () => {
      const isExpanded = btn.getAttribute("aria-expanded") === "true";
      if (isExpanded) {
        collapse();
      } else {
        expand();
      }
    });
  });
};
