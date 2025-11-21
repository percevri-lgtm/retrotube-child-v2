/**
 * 🧩 TMW Flipbox Mobile CSS + ScrollLock (v2.9.5)
 * Fixes: no flip on mobile, page jump, View Profile span detection.
 */
(function ($) {
  $(document).ready(function () {
    console.log('[TMW-FLIPBOX-FIX] v2.9.5 initialized');

    // Flip handler
    $('.tmw-flip-back, .flip-box').each(function () {
      const $el = $(this).closest('.flip-box, .tmw-flip-wrapper');

      // Touch / click flip trigger
      $(this).off('click').on('click touchstart', function (e) {
        const isProfileBtn = $(e.target).closest('.tmw-view, .tmw-flip-view, .view-profile').length > 0;

        if (isProfileBtn) {
          console.log('[TMW-FLIPBOX-FIX] View Profile clicked');
          e.stopPropagation();
          const href = $(e.target).attr('data-href') || $(e.target).closest('[data-href]').attr('data-href');
          if (href) window.location.href = href;
          return;
        }

        // Prevent scroll jump
        e.preventDefault();
        e.stopPropagation();

        // Save current scroll
        const scrollY = window.scrollY;

        // Toggle flip
        $el.toggleClass('flipped');
        console.log('[TMW-FLIPBOX-FIX] Card flipped');

        // Restore scroll position
        setTimeout(() => window.scrollTo(0, scrollY), 10);
      });
    });

    // Touch scroll behavior lock for smoother flip
    $('.flip-box, .tmw-flip-wrapper').css({
      'touch-action': 'manipulation',
      '-webkit-transform-style': 'preserve-3d',
      'transform-style': 'preserve-3d',
      'backface-visibility': 'hidden'
    });
  });
})(jQuery);
