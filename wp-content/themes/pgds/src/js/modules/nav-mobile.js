/**
 * nav-mobile — open/close the main menu on narrow screens + disclosure submenu.
 * Keyboard: Esc closes the menu, focus returns to the toggle button.
 * ARIA: aria-expanded on the toggle button and each submenu button.
 */

export function initNavMobile(root = document) {
  // --- Main menu toggle ---
  const toggle = root.querySelector('[data-pgds="nav-toggle"]');
  const list = root.querySelector('#pgds-primary-menu');

  if (toggle && list) {
    const closeMenu = () => {
      list.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    };
    const openMenu = () => {
      list.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
    };

    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') === 'true';
      open ? closeMenu() : openMenu();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        closeMenu();
        toggle.focus();
      }
    });
  }

  // --- Disclosure submenu (mobile) ---
  const discs = root.querySelectorAll('[data-pgds="submenu-toggle"]');
  discs.forEach((btn) => {
    const id = btn.getAttribute('aria-controls');
    const submenu = id ? root.querySelector('#' + CSS.escape(id)) : null;
    if (!submenu) return;

    btn.addEventListener('click', () => {
      const open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', open ? 'false' : 'true');
      submenu.classList.toggle('is-open', !open);
    });
  });
}
