export const initMobileDrawer = (filterContainer, mobileToggle, backdrop, filterForm) => {
  if (!mobileToggle || !filterContainer) return;

  if (filterForm?.id) {
      mobileToggle.setAttribute('aria-controls', filterForm.id);
  }

  const rememberHome = (node) => ({
      parent: node.parentNode,
      next: node.nextSibling,
  });

  const restoreNode = (node, home) => {
      if (!node || !home.parent) return;

      if (home.next && home.next.parentNode === home.parent) {
          home.parent.insertBefore(node, home.next);
      } else {
          home.parent.appendChild(node);
      }
  };

  const homes = {
      filterContainer: rememberHome(filterContainer),
      mobileToggle: rememberHome(mobileToggle),
      backdrop: backdrop ? rememberHome(backdrop) : null,
  };
  const breakpoint = 768;
  const mq = window.matchMedia(`(max-width: ${breakpoint}px)`);

  // ----------------------------------------------------------------
  // Drawer open / close helpers
  // ----------------------------------------------------------------

  const closeDrawer = () => {
      filterContainer.classList.remove('ppfx-open');
      backdrop?.classList.remove('ppfx-visible');
      document.body.classList.remove('ppfx-drawer-open');
      mobileToggle.setAttribute('aria-expanded', 'false');
  };

  const openDrawer = () => {
      // Close any other open drawers
      document.querySelectorAll('.ppfx-filter-container.ppfx-open').forEach(openContainer => {
          if (openContainer !== filterContainer) {
              openContainer.classList.remove('ppfx-open');
          }
      });
      filterContainer.classList.add('ppfx-open');
      backdrop?.classList.add('ppfx-visible');
      document.body.classList.add('ppfx-drawer-open');
      mobileToggle.setAttribute('aria-expanded', 'true');

      // Shift focus into the drawer
      const firstFocusable = filterContainer.querySelector(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      if (firstFocusable) firstFocusable.focus();
  };

  const findMobileToggleTarget = () => {
      const slot = document.querySelector('[data-ppfx-mobile-toggle-slot]');
      if (slot) {
          return { node: slot, mode: 'append' };
      }

      const toolbar = document.querySelector(
          '.rocket-shop-toolbar, .storefront-sorting, .woocommerce-shop-toolbar, .woocommerce-products-toolbar, .shop-toolbar, .products-toolbar, .catalog-toolbar'
      );
      if (toolbar) return { node: toolbar, mode: 'before' };

      const toolbarItem = document.querySelector('.woocommerce-result-count, .woocommerce-ordering');
      if (toolbarItem?.parentElement && toolbarItem.parentElement !== document.body) {
          return { node: toolbarItem.parentElement, mode: 'before' };
      }

      const products = document.querySelector('.ppfx-products-wrapper, .products');
      return products ? { node: products, mode: 'before' } : null;
  };

  const moveToMobileLayer = () => {
      const toggleTarget = findMobileToggleTarget();
      if (toggleTarget?.mode === 'append') {
          toggleTarget.node.appendChild(mobileToggle);
      } else if (toggleTarget?.node?.parentNode) {
          toggleTarget.node.parentNode.insertBefore(mobileToggle, toggleTarget.node);
      }

      if (backdrop) {
          document.body.appendChild(backdrop);
      }
      document.body.appendChild(filterContainer);
  };

  const restoreDesktopLayer = () => {
      restoreNode(mobileToggle, homes.mobileToggle);
      if (backdrop && homes.backdrop) {
          restoreNode(backdrop, homes.backdrop);
      }
      restoreNode(filterContainer, homes.filterContainer);
  };

  // ----------------------------------------------------------------
  // Breakpoint management - add/remove .ppfx-drawer-mode and
  // show/hide the toggle button based on the current viewport width.
  // ----------------------------------------------------------------

  const applyBreakpoint = (e) => {
      const isMobile = e.matches;

      // Toggle mobile drawer mode via CSS class
      document.documentElement.classList.toggle('ppfx-mobile-drawer-view', isMobile);

      if (isMobile) {
          // Keep the trigger in normal page flow above the product toolbar,
          // while the drawer panel itself escapes theme sidebar clipping.
          moveToMobileLayer();
          filterContainer.classList.add('ppfx-drawer-mode');
      } else {
          // Leave drawer mode: close any open drawer and restore inline layout.
          closeDrawer();
          filterContainer.classList.remove('ppfx-drawer-mode');
          restoreDesktopLayer();
      }
  };

  // Run immediately on init, then listen for viewport changes.
  applyBreakpoint(mq);
  mq.addEventListener('change', applyBreakpoint);

  // ----------------------------------------------------------------
  // Event listeners
  // ----------------------------------------------------------------

  const handleKeydown = (e) => {
      if (e.key === 'Escape' && filterContainer.classList.contains('ppfx-open')) {
          closeDrawer();
          mobileToggle.focus();
      }
  };

  mobileToggle.addEventListener('click', () => {
      if (filterContainer.classList.contains('ppfx-open')) {
          closeDrawer();
          mobileToggle.focus();
      } else {
          openDrawer();
      }
  });

  backdrop?.addEventListener('click', () => {
      closeDrawer();
      mobileToggle.focus();
  });

  document.addEventListener('keydown', handleKeydown);

  return {
      closeDrawer,
      openDrawer,
      destroy: () => {
          document.removeEventListener('keydown', handleKeydown);
          mq.removeEventListener('change', applyBreakpoint);
      },
  };
};
