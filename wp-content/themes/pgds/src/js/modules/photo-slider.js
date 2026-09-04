/**
 * photo-slider — auto-rotating carousel for the photo news panel.
 *
 * @param {Document|Element} root
 */
export function initPhotoSlider(root = document) {
  const panel = root.querySelector('[data-pgds="photo-slider"]');
  if (!panel) return;

  const slides = Array.from(panel.querySelectorAll('.pgds-photo-panel__slide'));
  const dots = Array.from(panel.querySelectorAll('.pgds-photo-panel__dots span'));
  if (slides.length < 2) return;

  let current = 0;
  let timer = null;

  function goTo(index) {
    slides[current].classList.add('is-hidden');
    slides[current].setAttribute('aria-hidden', 'true');
    slides[current].setAttribute('tabindex', '-1');

    current = ((index % slides.length) + slides.length) % slides.length;

    slides[current].classList.remove('is-hidden');
    slides[current].removeAttribute('aria-hidden');
    slides[current].removeAttribute('tabindex');

    dots.forEach((d, i) => d.classList.toggle('is-active', i === current));
  }

  dots.forEach((dot, i) => {
    dot.addEventListener('click', () => {
      goTo(i);
      resetTimer();
    });
  });

  let touchX = 0;
  panel.addEventListener('touchstart', (e) => {
    touchX = e.changedTouches[0].screenX;
  }, { passive: true });

  panel.addEventListener('touchend', (e) => {
    const dx = e.changedTouches[0].screenX - touchX;
    if (Math.abs(dx) > 50) {
      goTo(dx > 0 ? current - 1 : current + 1);
      resetTimer();
    }
  }, { passive: true });

  const mql = window.matchMedia('(prefers-reduced-motion: reduce)');

  function startTimer() {
    if (mql.matches) return;
    timer = setInterval(() => goTo(current + 1), 5000);
  }

  function resetTimer() {
    clearInterval(timer);
    startTimer();
  }

  panel.addEventListener('mouseenter', () => clearInterval(timer));
  panel.addEventListener('mouseleave', () => startTimer());
  panel.addEventListener('focusin', () => clearInterval(timer));
  panel.addEventListener('focusout', () => startTimer());

  startTimer();
}
