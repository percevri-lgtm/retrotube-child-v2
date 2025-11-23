(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  ready(function () {
    var path = window.location.pathname || '';
    var params = Object.fromEntries(new URLSearchParams(window.location.search));
    var hasArgs = Object.prototype.hasOwnProperty.call(params, 'login') &&
                  Object.prototype.hasOwnProperty.call(params, 'key');
    if (path.indexOf('/resetpass/') === -1 || !hasArgs) return;

    // Try to find the existing reset form rendered by WP/TML.
    var form = document.getElementById('resetpassform') ||
               document.querySelector('form[action*="action=rp"]') ||
               document.querySelector('form.resetpass');

    if (!form) return;

    // Build a lightweight modal so the reset happens inside a popup.
    var overlay = document.createElement('div');
    overlay.id = 'tmw-rp-overlay';
    overlay.setAttribute('role','dialog');
    overlay.innerHTML = ''
      + '<div class="tmw-rp-backdrop"></div>'
      + '<div class="tmw-rp-card">'
      + '  <button type="button" class="tmw-rp-close" aria-label="Close">×</button>'
      + '  <h3 class="tmw-rp-title">Reset Password</h3>'
      + '  <div class="tmw-rp-body"></div>'
      + '</div>';

    document.body.appendChild(overlay);
    overlay.querySelector('.tmw-rp-body').appendChild(form);

    // Focus first input if present
    var first = form.querySelector('input[type="password"], input[type="text"], input[type="email"]');
    if (first) setTimeout(function(){ first.focus(); }, 60);

    overlay.querySelector('.tmw-rp-close').addEventListener('click', function () {
      // Close -> go back to homepage (or change to your preferred URL)
      window.location.href = '/';
    });

    // Minimal, isolated styles to avoid touching theme CSS.
    var css = document.createElement('style');
    css.textContent = `
      #tmw-rp-overlay{position:fixed;inset:0;z-index:99999;display:block}
      #tmw-rp-overlay .tmw-rp-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.72)}
      #tmw-rp-overlay .tmw-rp-card{position:relative;max-width:560px;margin:5vh auto;background:#1c1c1c;color:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.6);padding:24px}
      #tmw-rp-overlay .tmw-rp-title{margin:0 0 12px;font-size:20px}
      #tmw-rp-overlay .tmw-rp-close{position:absolute;top:8px;right:12px;background:transparent;color:#fff;border:0;font-size:28px;line-height:1;cursor:pointer}
      #tmw-rp-overlay input[type="password"], #tmw-rp-overlay input[type="text"], #tmw-rp-overlay input[type="email"]{width:100%}
      #tmw-rp-overlay p.message{background:#0e2e12;border-left:4px solid #2ecc71;padding:8px 10px;border-radius:6px}
    `;
    document.head.appendChild(css);

    // Trace
    if (window.console && console.log) console.log('[TMW-RP-POPUP] Reset form pulled into popup');
  });
})();

