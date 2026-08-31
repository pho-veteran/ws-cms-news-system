/**
 * pgds — entry. Chi 3 module launch (proposal §3.4).
 * Khoi dong qua data-pgds attribute, khong gan event theo class CSS.
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
