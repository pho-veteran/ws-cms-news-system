/**
 * nav-mobile — mo/dong menu chinh tren man hinh hep + disclosure submenu.
 * Keyboard: Esc dong menu, focus quay lai nut toggle.
 * ARIA: aria-expanded tren nut toggle va tung nut submenu.
 */

export function initNavMobile(root = document) {
  // --- Toggle menu chinh ---
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
