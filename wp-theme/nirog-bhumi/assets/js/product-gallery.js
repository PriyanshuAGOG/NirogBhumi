/**
 * Single product page: gallery thumbnail swapping and the share button.
 * Plain vanilla JS, no dependency on the store carousel.
 */
(function () {
  document.querySelectorAll('[data-nb-gallery]').forEach(function (root) {
    var main = root.querySelector('[data-nb-gallery-main] img');
    var thumbs = Array.prototype.slice.call(root.querySelectorAll('[data-nb-gallery-thumb]'));
    if (!main || !thumbs.length) {
      return;
    }
    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        var full = thumb.getAttribute('data-full');
        if (!full) {
          return;
        }
        main.src = full;
        main.alt = thumb.getAttribute('data-alt') || '';
        thumbs.forEach(function (other) { other.classList.remove('is-active'); });
        thumb.classList.add('is-active');
      });
    });
  });

  document.querySelectorAll('[data-nb-share]').forEach(function (button) {
    button.addEventListener('click', function () {
      var url = button.getAttribute('data-url') || window.location.href;
      var title = button.getAttribute('data-title') || document.title;

      if (navigator.share) {
        navigator.share({ title: title, url: url }).catch(function () {});
        return;
      }

      var status = button.parentElement ? button.parentElement.querySelector('[data-nb-share-status]') : null;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () {
          if (!status) return;
          status.hidden = false;
          clearTimeout(status._nbTimer);
          status._nbTimer = setTimeout(function () { status.hidden = true; }, 2200);
        }).catch(function () {
          window.prompt('Copy this link', url);
        });
        return;
      }

      window.prompt('Copy this link', url);
    });
  });
})();
