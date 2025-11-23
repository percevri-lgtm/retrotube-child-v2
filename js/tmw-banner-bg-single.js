/*!
 * v4.0.1 — Single renderer: frame background (cover) driven by --offset-y.
 * Keeps 1:1 slider. Hides inner <img> so there is no duplicate layer.
 */
(function () {
  function enable(frame) {
    if (!frame || frame.classList.contains('tmw-bg-mode')) return;
    var img = frame.querySelector('img');
    if (!img) return;

    function syncBg() {
      var src = (img.currentSrc || img.src || '').trim();
      if (!src) return;
      frame.style.setProperty('background-image', 'url("' + src.replace(/"/g, '\\"') + '")', 'important');
      frame.style.setProperty('background-size', 'cover', 'important');
      frame.style.setProperty('background-repeat', 'no-repeat', 'important');
      // vertical position is handled in CSS via --offset-y
    }

    syncBg();
    frame.classList.add('tmw-bg-mode');

    // Track responsive swaps
    var mo = new MutationObserver(function (list) {
      for (var m of list) {
        if (m.type === 'attributes' && (m.attributeName === 'src' || m.attributeName === 'srcset')) {
          syncBg();
        }
      }
    });
    mo.observe(img, { attributes: true, attributeFilter: ['src', 'srcset'] });
  }

  function init() {
    document.querySelectorAll('.single-model .tmw-banner-frame, .wp-admin .tmw-banner-frame')
      .forEach(enable);
  }

  if (document.readyState !== 'loading') init();
  else document.addEventListener('DOMContentLoaded', init);
})();
