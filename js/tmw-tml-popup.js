(function($){
  // Render the current Lost/Reset page content into a modal popup, without altering TML or email flow.
  $(function(){
    if (typeof tmwTml === 'undefined') return;

    // create overlay + modal once
    var $overlay = $('<div class="tmw-tml-overlay tmw-tml-hide" />').appendTo('body');
    var $modal   = $('<div class="tmw-tml-modal tmw-tml-hide" />').appendTo('body');
    var $head    = $('<div class="tmw-tml-head">Reset Password <button class="tmw-tml-close" aria-label="Close">&times;</button></div>').appendTo($modal);
    var $body    = $('<div class="tmw-tml-body" />').appendTo($modal);

    function openModalWith($content){
      $body.empty().append($content);
      $overlay.removeClass('tmw-tml-hide');
      $modal.removeClass('tmw-tml-hide');
      // focus the first input if any
      var $first = $modal.find('input,select,textarea,button').filter(':visible:first');
      if ($first.length) $first.trigger('focus');
    }
    function closeModal(){
      $overlay.addClass('tmw-tml-hide');
      $modal.addClass('tmw-tml-hide');
    }
    $overlay.on('click', closeModal);
    $modal.on('click', '.tmw-tml-close', closeModal);

    // Selectors that work with TML pages (lostpassword/resetpass)
    var $lostForm  = $('#theme-my-login form').filter(function(){ return /lostpassword/i.test(this.action || ''); }).first();
    var $resetForm = $('#theme-my-login form').filter(function(){ return /resetpass|action=rp/i.test(this.action || window.location.href); }).first();

    if (tmwTml.isLost && $lostForm.length) {
      openModalWith($lostForm.detach().show());
      // hide the original page area to avoid duplicate forms
      $('#theme-my-login').closest('main, .content, body').children().not('.tmw-tml-overlay,.tmw-tml-modal').addClass('tmw-tml-hide');
    }

    if (tmwTml.isReset && $resetForm.length) {
      openModalWith($resetForm.detach().show());
      $('#theme-my-login').closest('main, .content, body').children().not('.tmw-tml-overlay,.tmw-tml-modal').addClass('tmw-tml-hide');
    }

    // Keep normal submit behaviour (TML/core handles the nonce + processing).
    // No extra AJAX here to avoid duplicate reset requests (which invalidate previous keys).
  });
})(jQuery);
