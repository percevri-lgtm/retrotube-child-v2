(function () {
  if (!window || !document) return;
  if (!window.TMW_REG_AUD) return;

  function send(payload) {
    try {
      const body = new FormData();
      body.append('action', 'tmw_reg_audit_ping');
      body.append('nonce', TMW_REG_AUD.nonce);
      body.append('payload', JSON.stringify(payload));
      if (navigator.sendBeacon) {
        navigator.sendBeacon(TMW_REG_AUD.ajaxUrl, body);
      } else {
        fetch(TMW_REG_AUD.ajaxUrl, { method: 'POST', body });
      }
    } catch (e) { /* no-op */ }
  }

  function redact(v) { return typeof v === 'string' ? v.trim().slice(0, 64) : v; }

  function probeOverlays(el) {
    try {
      const r = el.getBoundingClientRect();
      const x = Math.floor(r.left + r.width / 2);
      const y = Math.floor(r.top + r.height / 2);
      const stack = (document.elementsFromPoint ? document.elementsFromPoint(x, y) : [document.elementFromPoint(x, y)]).filter(Boolean);
      const top = stack[0];
      return {
        topNode: top ? (top.id || top.className || top.tagName) : 'n/a',
        topIsInForm: !!(top && el.form && el.form.contains(top)),
        cookieBannerHint: !!document.body.innerText.match(/cookie|cookies|gdpr/i)
      };
    } catch (e) { return { err: true }; }
  }

  const attached = new WeakSet();

  function attachToForm(form) {
    if (!form || attached.has(form)) return;
    attached.add(form);

    form.addEventListener('submit', function () {
      try {
        const fd = new FormData(form);
        const payload = {
          event: 'submit',
          url: location.href,
          action: (form.getAttribute('action') || '').slice(0, 120),
          method: (form.getAttribute('method') || 'GET').toUpperCase(),
          usersCanRegister: !!TMW_REG_AUD.usersCanRegister,
          ua: navigator.userAgent.slice(0, 120)
        };
        fd.forEach((v, k) => {
          const key = String(k).toLowerCase();
          if (key.includes('pass')) return;
          if (key.includes('email')) payload.email = String(v);
          else if (key.includes('user')) payload.username = redact(String(v));
        });
        const btn = form.querySelector('[type="submit"], button');
        if (btn) payload.overlay = probeOverlays(btn);

        console.log('%c[TMW-REG] Submit observed', 'color:#8AC437', payload);
        send(payload);
      } catch (e) { /* noop */ }
    }, { capture: true });

    console.log('%c[TMW-REG] Audit armed on form', 'color:#8AC437', { sel: form.id || form.className || form.tagName });
  }

  function attachToButtons(root) {
    const btns = Array.from(root.querySelectorAll('button, [type="submit"], .btn, .button')).filter(b => {
      const t = (b.textContent || '').toLowerCase();
      return /aanmelden|registreren|register|sign\s*up|create account/.test(t);
    });
    btns.forEach((btn) => {
      if (attached.has(btn)) return;
      attached.add(btn);
      btn.addEventListener('click', function () {
        try {
          const container = btn.closest('form, [role="dialog"], .modal, .popup') || document;
          const email = container.querySelector('input[type="email"], input[name*="email" i]');
          const user  = container.querySelector('input[name*="user" i], input[name*="login" i], input[name*="name" i]');
          const payload = {
            event: 'click-submit-fallback',
            url: location.href,
            usersCanRegister: !!TMW_REG_AUD.usersCanRegister,
            ua: navigator.userAgent.slice(0, 120),
            overlay: probeOverlays(btn)
          };
          if (email && email.value) payload.email = String(email.value);
          if (user && user.value) payload.username = redact(String(user.value));

          console.log('%c[TMW-REG] Fallback submit observed', 'color:#8AC437', payload);
          send(payload);
        } catch (e) { /* noop */ }
      }, { capture: true });
    });
  }

  function scan(root) {
    const forms = Array.from(root.querySelectorAll('form#registerform, form[action*="register"], form[id*="regist" i], form[action*="signup" i]'));
    forms.forEach(attachToForm);
    attachToButtons(root);
  }

  scan(document);

  const mo = new MutationObserver((muts) => {
    muts.forEach((m) => {
      if (!m.addedNodes) return;
      m.addedNodes.forEach((n) => {
        if (n.nodeType !== 1) return;
        if (n.querySelectorAll) scan(n);
      });
    });
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });

  send({ event: 'boot', url: location.href, usersCanRegister: !!TMW_REG_AUD.usersCanRegister });
})();
