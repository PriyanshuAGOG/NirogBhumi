/**
 * Store hero carousel. One slide per product category (built in
 * page-store.php). Plain vanilla JS: translateX sliding, arrow + dot
 * navigation, autoplay that pauses on hover/touch, basic swipe support.
 * A no-op with hidden controls when there is only one slide.
 */
(function () {
  document.querySelectorAll('[data-nb-carousel]').forEach(function (root) {
    var track = root.querySelector('[data-nb-carousel-track]');
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-nb-carousel-slide]'));
    var dotsWrap = root.querySelector('[data-nb-carousel-dots]');
    var dots = dotsWrap ? Array.prototype.slice.call(dotsWrap.querySelectorAll('button')) : [];
    var prevBtn = root.querySelector('[data-nb-carousel-prev]');
    var nextBtn = root.querySelector('[data-nb-carousel-next]');

    if (!track || slides.length < 2) {
      if (prevBtn) prevBtn.hidden = true;
      if (nextBtn) nextBtn.hidden = true;
      if (dotsWrap) dotsWrap.hidden = true;
      return;
    }

    var index = 0;
    var timer = null;
    var autoplayMs = 6000;

    function goTo(i) {
      index = (i + slides.length) % slides.length;
      track.style.transform = 'translateX(-' + (index * 100) + '%)';
      dots.forEach(function (dot, di) {
        dot.classList.toggle('is-active', di === index);
      });
    }

    function next() { goTo(index + 1); }
    function prev() { goTo(index - 1); }

    function startAutoplay() {
      stopAutoplay();
      timer = setInterval(next, autoplayMs);
    }

    function stopAutoplay() {
      if (timer) clearInterval(timer);
    }

    if (nextBtn) nextBtn.addEventListener('click', function () { next(); startAutoplay(); });
    if (prevBtn) prevBtn.addEventListener('click', function () { prev(); startAutoplay(); });
    dots.forEach(function (dot, di) {
      dot.addEventListener('click', function () { goTo(di); startAutoplay(); });
    });

    var touchStartX = null;
    track.addEventListener('touchstart', function (e) {
      touchStartX = e.touches[0].clientX;
      stopAutoplay();
    }, { passive: true });
    track.addEventListener('touchend', function (e) {
      if (touchStartX === null) return;
      var delta = e.changedTouches[0].clientX - touchStartX;
      if (delta > 40) prev();
      else if (delta < -40) next();
      touchStartX = null;
      startAutoplay();
    }, { passive: true });

    root.addEventListener('mouseenter', stopAutoplay);
    root.addEventListener('mouseleave', startAutoplay);

    goTo(0);
    startAutoplay();
  });
})();
