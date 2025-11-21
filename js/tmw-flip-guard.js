(function () {
  try {
    var noop = function () {};
    var DBG = (window.TMW_FLIP_DBG && window.TMW_FLIP_DBG.debug) ? console : { log: noop, warn: noop, info: noop };

    var isTouch = function () {
      try {
        return matchMedia('(hover: none)').matches || 'ontouchstart' in window;
      } catch (_) {
        return 'ontouchstart' in window;
      }
    };

    if (!isTouch()) {
      return;
    }

    var inBackFace = function (el) {
      return !!(el && el.closest('.tmw-face.back, .tmw-back, .tmw-flip-back'));
    };

    var inCard = function (el) {
      return el ? el.closest('.tmw-flip') : null;
    };

    var debugDump = function (card, event, phase) {
      if (!card) {
        return;
      }

      try {
        var front = card.querySelector('.tmw-face.front, .tmw-front, .tmw-flip-front');
        var back = card.querySelector('.tmw-face.back, .tmw-back, .tmw-flip-back');
        DBG.log('[FLIP-GUARD]', phase, {
          flipped: card.classList.contains('flipped'),
          target: event && event.target && event.target.outerHTML ? event.target.outerHTML.slice(0, 120) : null,
          frontPE: front ? getComputedStyle(front).pointerEvents : null,
          backPE: back ? getComputedStyle(back).pointerEvents : null,
          frontZ: front ? getComputedStyle(front).zIndex : null,
          backZ: back ? getComputedStyle(back).zIndex : null
        });
      } catch (_) {}
    };

    var onPointer = function (e) {
      var card = inCard(e.target);
      if (!card) {
        return;
      }

      var flipped = card.classList.contains('flipped');
      var targetIsBack = inBackFace(e.target);

      debugDump(card, e, 'pointerdown');

      if (flipped || targetIsBack) {
        return;
      }

      var anchor = e.target.closest('a');

      if (anchor) {
        e.preventDefault();
        e.stopPropagation();
      }

      card.classList.add('flipped');
      card.classList.add('tmw-flip-armed');
      setTimeout(function () {
        card.classList.remove('tmw-flip-armed');
      }, 1500);
    };

    document.addEventListener('pointerdown', onPointer, { capture: true, passive: false });
  } catch (_) {}
})();
