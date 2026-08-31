/**
 * media-tabs — control the content group in the media block.
 * WAI-ARIA Tabs pattern: role=tablist/tab/tabpanel, Left/Right arrows, Home/End.
 * roving tabindex on tabs.
 */

function activateTab(tabs, panels, index) {
  tabs.forEach((tab, i) => {
    const selected = i === index;
    tab.setAttribute('aria-selected', selected ? 'true' : 'false');
    tab.tabIndex = selected ? 0 : -1;
    if (selected) tab.focus();
  });
  panels.forEach((panel, i) => {
    panel.hidden = i !== index;
  });
}

function initTablist(tablist) {
  const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));
  if (!tabs.length) return;

  // Match panels by aria-controls.
  const panels = tabs.map((t) => {
    const id = t.getAttribute('aria-controls');
    return id ? document.getElementById(id) : null;
  });

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activateTab(tabs, panels, index));
    tab.addEventListener('keydown', (e) => {
      let next = null;
      switch (e.key) {
        case 'ArrowRight':
          next = (index + 1) % tabs.length;
          break;
        case 'ArrowLeft':
          next = (index - 1 + tabs.length) % tabs.length;
          break;
        case 'Home':
          next = 0;
          break;
        case 'End':
          next = tabs.length - 1;
          break;
        default:
          return;
      }
      e.preventDefault();
      activateTab(tabs, panels, next);
    });
  });
}

export function initMediaTabs(root = document) {
  root.querySelectorAll('[role="tablist"]').forEach(initTablist);
}
