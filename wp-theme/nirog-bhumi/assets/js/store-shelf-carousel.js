/**
 * Featured Products shelf: the same card row, scrollable with arrows
 * instead of wrapping to a second row. Native horizontal scroll with
 * snap, not a translateX track like the hero carousel - arrows just
 * scrollBy one card width. A one-time idle nudge toward the next card
 * (then back) hints that the row scrolls, and is cancelled the moment a
 * shopper actually touches it.
 */
(function () {
  document.querySelectorAll('[data-nb-shelf-carousel]').forEach(function (root) {
    var track = root.querySelector('[data-nb-shelf-track]');
    var prevBtn = root.querySelector('[data-nb-shelf-prev]');
    var nextBtn = root.querySelector('[data-nb-shelf-next]');
    if (!track) {
      return;
    }

    var canScroll = track.scrollWidth > track.clientWidth + 4;
    if (!canScroll) {
      if (prevBtn) prevBtn.hidden = true;
      if (nextBtn) nextBtn.hidden = true;
      return;
    }

    function cardStep() {
      var first = track.children[0];
      if (!first) {
        return track.clientWidth;
      }
      var style = window.getComputedStyle(track);
      var gap = parseFloat(style.columnGap || style.gap || '0') || 0;
      return first.getBoundingClientRect().width + gap;
    }

    function updateArrows() {
      if (!prevBtn || !nextBtn) {
        return;
      }
      var maxScroll = track.scrollWidth - track.clientWidth - 2;
      prevBtn.disabled = track.scrollLeft <= 2;
      nextBtn.disabled = track.scrollLeft >= maxScroll;
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        track.scrollBy({ left: -cardStep(), behavior: 'smooth' });
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        track.scrollBy({ left: cardStep(), behavior: 'smooth' });
      });
    }
    track.addEventListener('scroll', updateArrows, { passive: true });
    updateArrows();

    var interacted = false;
    function markInteracted() {
      interacted = true;
    }
    ['pointerdown', 'wheel', 'touchstart'].forEach(function (evt) {
      track.addEventListener(evt, markInteracted, { passive: true, once: true });
    });
    if (prevBtn) prevBtn.addEventListener('click', markInteracted, { once: true });
    if (nextBtn) nextBtn.addEventListener('click', markInteracted, { once: true });

    setTimeout(function () {
      if (interacted) {
        return;
      }
      track.scrollTo({ left: 46, behavior: 'smooth' });
      setTimeout(function () {
        if (interacted) {
          return;
        }
        track.scrollTo({ left: 0, behavior: 'smooth' });
      }, 700);
    }, 1200);
  });
})();
