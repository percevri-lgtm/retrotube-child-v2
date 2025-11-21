(function () {
  function setHref(a, path) {
    if (!a) return;
    try {
      a.setAttribute('href', path);
      a.setAttribute('data-tmw-tml', '1');
      a.removeAttribute('data-toggle');
      a.removeAttribute('data-target');
      a.classList.remove('open-login','open-register','open-lostpass','open-reset');
    } catch(e){}
  }

  function routeTopBar(root) {
    if (!root) return;
    var as = root.querySelectorAll('a');
    as.forEach(function(a){
      var t = (a.textContent || '').trim().toLowerCase();
      // normalize common labels
      if (t === 'login' || t === 'log in') {
        setHref(a, '/login/');
      } else if (t === 'register' || t === 'sign up' || t === 'signup') {
        setHref(a, '/register/');
      } else if (t.indexOf('reset') >= 0) {
        setHref(a, '/resetpass/');
      } else if (t.indexOf('forgot') >= 0 || t.indexOf('lost') >= 0) {
        setHref(a, '/lostpassword/');
      }
      // any hash-based auth links fall back to login
      var href = (a.getAttribute('href') || '');
      if (href.charAt(0) === '#') setHref(a, '/login/');
    });
    try { console.info('[TMW-TML] rewired header auth links to TML'); } catch(e){}
  }

  document.addEventListener('DOMContentLoaded', function(){
    var topbar = document.querySelector('#masthead .top-bar');
    if (topbar) routeTopBar(topbar);
  });
})();
