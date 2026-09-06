/**
 * Primary navigation — responsive drawer and submenus.
 * Keyboard: focus is contained in the drawer; Escape closes the active layer.
 * ARIA: disclosure and modal state stay synchronized with the visible surface.
 */

export function initNavMobile(root = document) {
  const nav = root.querySelector('[data-pgds="primary-nav"]');
  const toggle = root.querySelector('[data-pgds="nav-toggle"]');
  const surface = root.querySelector('[data-pgds="nav-surface"]');
  const close = root.querySelector('[data-pgds="nav-close"]');
  const backdrop = root.querySelector('[data-pgds="nav-backdrop"]');
  const rail = root.querySelector('[data-pgds="nav-rail"]');
  const sentinel = root.querySelector('[data-pgds="nav-sentinel"]');
  const mobileQuery = window.matchMedia('(max-width: 879px)');

  if (!nav || !toggle || !surface) return;

  const pairs = [];
  nav.querySelectorAll('[data-pgds="submenu-toggle"]').forEach((btn) => {
    const id = btn.getAttribute('aria-controls');
    const submenu = id ? root.querySelector('#' + CSS.escape(id)) : null;
    if (submenu) pairs.push({ btn, submenu });
  });

  const closeSubmenu = ({ btn, submenu }) => {
    btn.setAttribute('aria-expanded', 'false');
    submenu.classList.remove('is-open');
  };

  const closeAllSubmenus = () => pairs.forEach(closeSubmenu);

  const closeMobileMenu = ({ restoreFocus = false } = {}) => {
    nav.classList.remove('is-menu-open');
    toggle.setAttribute('aria-expanded', 'false');
    surface.removeAttribute('role');
    surface.removeAttribute('aria-modal');
    surface.removeAttribute('aria-labelledby');
    document.body.classList.remove('pgds-nav-open');
    backdrop?.classList.remove('is-open');
    closeAllSubmenus();
    if (restoreFocus) toggle.focus();
  };

  const openMobileMenu = () => {
    nav.classList.add('is-menu-open');
    toggle.setAttribute('aria-expanded', 'true');
    surface.setAttribute('role', 'dialog');
    surface.setAttribute('aria-modal', 'true');
    surface.setAttribute('aria-labelledby', 'pgds-nav-surface-title');
    document.body.classList.add('pgds-nav-open');
    backdrop?.classList.add('is-open');
    window.setTimeout(() => close?.focus(), 0);
  };

  pairs.forEach((pair) => {
    pair.btn.addEventListener('click', () => {
      const open = pair.btn.getAttribute('aria-expanded') === 'true';
      if (open) {
        closeSubmenu(pair);
        return;
      }
      closeAllSubmenus();
      pair.btn.setAttribute('aria-expanded', 'true');
      pair.submenu.classList.add('is-open');
    });
  });

  toggle.addEventListener('click', () => {
    nav.classList.contains('is-menu-open') ? closeMobileMenu() : openMobileMenu();
  });

  close?.addEventListener('click', () => closeMobileMenu({ restoreFocus: true }));
  backdrop?.addEventListener('click', () => closeMobileMenu({ restoreFocus: true }));


  document.addEventListener('keydown', (event) => {
    if (event.key === 'Tab' && mobileQuery.matches && nav.classList.contains('is-menu-open')) {
      const focusable = Array.from(
        surface.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')
      ).filter((element) => element.offsetParent !== null);
      if (!focusable.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
      return;
    }

    if (event.key !== 'Escape') return;

    const inner = pairs.find(
      (pair) => pair.btn.getAttribute('aria-expanded') === 'true'
        && (pair.submenu.contains(document.activeElement) || pair.btn === document.activeElement)
    );
    if (inner) {
      closeSubmenu(inner);
      inner.btn.focus();
      return;
    }

    if (mobileQuery.matches && nav.classList.contains('is-menu-open')) {
      closeMobileMenu({ restoreFocus: true });
      return;
    }
  });

  const resetResponsiveState = () => {
    closeMobileMenu();
  };
  mobileQuery.addEventListener('change', resetResponsiveState);

  const updateRailOverflow = () => {
    if (!rail || mobileQuery.matches) {
      nav.classList.remove('has-rail-overflow');
      return;
    }
    nav.classList.toggle('has-rail-overflow', rail.scrollWidth > rail.clientWidth + 1);
  };
  updateRailOverflow();
  window.addEventListener('resize', updateRailOverflow);

  if (sentinel && 'IntersectionObserver' in window) {
    const stickyObserver = new window.IntersectionObserver(
      ([entry]) => nav.classList.toggle('is-stuck', !entry.isIntersecting),
      { threshold: 0 }
    );
    stickyObserver.observe(sentinel);
  }
}