/**
 * pgds — entry. Launch only 3 modules (proposal §3.4).
 * Bootstrapped via the data-pgds attribute; do not bind events by CSS class.
 */

import { initNavMobile } from './modules/nav-mobile.js';
import { initYouTubeFacade } from './modules/youtube-facade.js';
import { initMediaTabs } from './modules/media-tabs.js';

function boot() {
  initNavMobile(document);
  initYouTubeFacade(document);
  initMediaTabs(document);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
