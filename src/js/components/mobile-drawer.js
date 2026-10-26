export const initMobileDrawer = (
  filterContainer,
  mobileToggle,
  backdrop,
  filterForm,
) => {
  if (!mobileToggle || !filterContainer) return null;
  if (!window.ppfxFilterData?.mobileDrawer) return null;

  // Set initial ARIA accessibility attributes
  mobileToggle.setAttribute("aria-expanded", "false");
  if (filterForm?.id) {
    mobileToggle.setAttribute("aria-controls", filterForm.id);
  }

  const rememberHome = (node) => ({
    parent: node.parentNode,
    next: node.nextSibling,
  });

  const restoreNode = (node, home) => {
    if (!node || !home?.parent) return;

    if (home.next && home.next.parentNode === home.parent) {
      home.parent.insertBefore(node, home.next);
    } else {
      home.parent.appendChild(node);
    }
  };

  // In dom_inject mode, the toggle button lives inside a slot div rendered by
  // filter-layout.php. We move the slot as a whole unit rather than just the
  // button, so the slot is also tracked as a "home".
  const toggleSlot =
    mobileToggle.closest("[data-ppfx-mobile-toggle-slot]") || null;

  const homes = {
    filterContainer: rememberHome(filterContainer),
    mobileToggle: toggleSlot ? null : rememberHome(mobileToggle),
    toggleSlot: toggleSlot ? rememberHome(toggleSlot) : null,
    backdrop: backdrop ? rememberHome(backdrop) : null,
  };

  const breakpoint = window.ppfxFilterData?.mobileDrawerBreakpoint ?? 768;
  const mq = window.matchMedia(`(max-width: ${breakpoint}px)`);
  const drawerMode = window.ppfxFilterData?.mobileDrawerMode ?? "dom_inject";
  const isSimpleToggle = drawerMode === "simple_toggle";

  // ----------------------------------------------------------------
  // Drawer open / close helpers
  // ----------------------------------------------------------------

  const closeDrawer = () => {
    filterContainer.classList.remove("ppfx-open");
    backdrop?.classList.remove("ppfx-visible");
    document.body.classList.remove("ppfx-drawer-open");
    mobileToggle.setAttribute("aria-expanded", "false");
  };

  const openDrawer = () => {
    document
      .querySelectorAll(".ppfx-filter-container.ppfx-open")
      .forEach((openContainer) => {
        if (openContainer !== filterContainer) {
          openContainer.classList.remove("ppfx-open");
        }
      });
    filterContainer.classList.add("ppfx-open");
    backdrop?.classList.add("ppfx-visible");
    document.body.classList.add("ppfx-drawer-open");
    mobileToggle.setAttribute("aria-expanded", "true");

    // Shift focus into the filter panel
    const firstFocusable = filterContainer.querySelector(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );
    if (firstFocusable) firstFocusable.focus();
  };

  const findToolbarTarget = () => {
    const toolbar = document.querySelector(
      ".rocket-shop-toolbar, .storefront-sorting, .woocommerce-shop-toolbar, .woocommerce-products-toolbar, .shop-toolbar, .products-toolbar, .catalog-toolbar",
    );
    if (toolbar) return { node: toolbar, mode: "before" };

    const toolbarItem = document.querySelector(
      ".woocommerce-result-count, .woocommerce-ordering",
    );
    if (
      toolbarItem?.parentElement &&
      toolbarItem.parentElement !== document.body
    ) {
      return { node: toolbarItem.parentElement, mode: "before" };
    }

    const products = document.querySelector(
      ".ppfx-products-wrapper, .products",
    );
    return products ? { node: products, mode: "before" } : null;
  };

  const moveToMobileLayer = () => {
    // The node to place above the products: the slot div if present, else the bare button.
    const toggleNode = toggleSlot || mobileToggle;

    const target = findToolbarTarget();
    if (target?.mode === "before" && target?.node?.parentNode) {
      target.node.parentNode.insertBefore(toggleNode, target.node);
    } else if (target?.node) {
      target.node.appendChild(toggleNode);
    }

    if (backdrop) {
      document.body.appendChild(backdrop);
    }
    document.body.appendChild(filterContainer);
  };

  const restoreDesktopLayer = () => {
    // Restore the slot (or bare toggle) to its original position.
    if (toggleSlot && homes.toggleSlot) {
      restoreNode(toggleSlot, homes.toggleSlot);
    } else if (homes.mobileToggle) {
      restoreNode(mobileToggle, homes.mobileToggle);
    }
    if (backdrop && homes.backdrop) {
      restoreNode(backdrop, homes.backdrop);
    }
    restoreNode(filterContainer, homes.filterContainer);
  };

  // ----------------------------------------------------------------
  // Breakpoint management
  // ----------------------------------------------------------------

  const applyBreakpoint = (e) => {
    const isMobile = e.matches;
    document.documentElement.classList.toggle(
      "ppfx-mobile-drawer-view",
      isMobile,
    );

    if (isMobile) {
      if (isSimpleToggle) {
        filterContainer.classList.add("ppfx-simple-toggle-mode");
      } else {
        moveToMobileLayer();
        filterContainer.classList.add("ppfx-drawer-mode");
      }
    } else {
      closeDrawer();
      if (isSimpleToggle) {
        filterContainer.classList.remove("ppfx-simple-toggle-mode");
      } else {
        filterContainer.classList.remove("ppfx-drawer-mode");
        restoreDesktopLayer();
      }
    }
  };

  applyBreakpoint(mq);
  if (mq.addEventListener) {
    mq.addEventListener("change", applyBreakpoint);
  } else if (mq.addListener) {
    mq.addListener(applyBreakpoint);
  }

  const handleToolbarInserted = () => {
    if (mq.matches && !isSimpleToggle) {
      moveToMobileLayer();
    }
  };
  document.body.addEventListener(
    "ppfx:toolbar_inserted",
    handleToolbarInserted,
  );

  // ----------------------------------------------------------------
  // Named Event Handlers (Allows proper cleanup in destroy)
  // ----------------------------------------------------------------

  const handleKeydown = (e) => {
    if (e.key === "Escape" && filterContainer.classList.contains("ppfx-open")) {
      closeDrawer();
      mobileToggle.focus();
    }
  };

  const handleToggleClick = () => {
    if (filterContainer.classList.contains("ppfx-open")) {
      closeDrawer();
      mobileToggle.focus();
    } else {
      openDrawer();
    }
  };

  const handleBackdropClick = () => {
    closeDrawer();
    mobileToggle.focus();
  };

  mobileToggle.addEventListener("click", handleToggleClick);
  backdrop?.addEventListener("click", handleBackdropClick);
  document.addEventListener("keydown", handleKeydown);

  return {
    closeDrawer,
    openDrawer,
    destroy: () => {
      document.removeEventListener("keydown", handleKeydown);
      mobileToggle.removeEventListener("click", handleToggleClick);
      backdrop?.removeEventListener("click", handleBackdropClick);

      if (mq.removeEventListener) {
        mq.removeEventListener("change", applyBreakpoint);
      } else if (mq.removeListener) {
        mq.removeListener(applyBreakpoint);
      }

      document.body.removeEventListener(
        "ppfx:toolbar_inserted",
        handleToolbarInserted,
      );
    },
  };
};
