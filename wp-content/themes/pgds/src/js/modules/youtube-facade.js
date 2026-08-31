/**
 * youtube-facade — thay poster bang iframe youtube-nocookie khi nguoi dung bam.
 * Khong tai iframe truoc tuong tac (bao ve LCP, giam ~600KB-1.2MB JS cua YouTube).
 * A11y: nut la <button aria-label>, sau khi chen iframe focus vao iframe.
 */

const HOST = (window.PGDS && window.PGDS.ytHost) || 'https://www.youtube-nocookie.com';

function buildIframe(videoId) {
  const iframe = document.createElement('iframe');
  const params = new URLSearchParams({
    autoplay: '1',
    rel: '0',
    modestbranding: '1',
  });
  iframe.src = `${HOST}/embed/${encodeURIComponent(videoId)}?${params.toString()}`;
  iframe.title = 'YouTube video';
  iframe.allow =
    'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
  iframe.setAttribute('allowfullscreen', '');
  iframe.setAttribute('loading', 'eager');
  iframe.width = '100%';
  iframe.height = '100%';
  return iframe;
}

function activate(figure) {
  const videoId = figure.getAttribute('data-video-id');
  if (!videoId || figure.dataset.pgdsActivated === '1') return;
  figure.dataset.pgdsActivated = '1';

  const iframe = buildIframe(videoId);
  // Xoa poster + nut, chen iframe.
  figure.querySelectorAll('.pgds-video__poster, .pgds-video__play, .pgds-video__dur').forEach((el) => el.remove());
  figure.appendChild(iframe);
  iframe.focus();
}

export function initYouTubeFacade(root = document) {
  const figures = root.querySelectorAll('[data-pgds="youtube-facade"]');
  figures.forEach((figure) => {
    const btn = figure.querySelector('.pgds-video__play');
    if (!btn) return;
    btn.addEventListener('click', () => activate(figure));
  });
}
