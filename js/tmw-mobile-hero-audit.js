(function(){
  const q = (sel) => Array.from(document.querySelectorAll(sel));
  const imgSel = [
    '.single-model .entry-header .tmw-banner-frame > img',
    '.single-model .entry-header .tmw-banner-frame picture > img',
    '.single-model .tmw-banner-frame > img',
    '.single-model .tmw-banner-frame picture > img',
    '.single-model .tmw-model-hero > img',
    '.single-model .tmw-model-hero picture > img',
    '.single-model .entry-header .wp-post-image',
    '.single-model .tmw-banner-frame .wp-post-image'
  ].join(',');

  const frameSel = [
    '.single-model .entry-header .tmw-banner-frame',
    '.single-model .tmw-banner-frame',
    '.single-model .tmw-model-hero',
    '.single-model .tmw-banner-container'
  ].join(',');

  function logComputed() {
    console.group('TMW Mobile Hero Auditor');
    const imgs = q(imgSel);
    const frames = q(frameSel);
    imgs.forEach((el, i) => {
      const cs = getComputedStyle(el);
      console.log('[IMG '+i+'] transform:', cs.transform, 'object-fit:', cs.objectFit, 'object-position:', cs.objectPosition, el);
    });
    frames.forEach((el, i) => {
      const cs = getComputedStyle(el);
      console.log('[FRAME '+i+'] --offset-y:', cs.getPropertyValue('--offset-y'), '--offset-base:', cs.getPropertyValue('--offset-base'), '--offset-scale:', cs.getPropertyValue('--offset-scale'));
    });
    console.groupEnd();
  }

  function forceInlineIfRequested() {
    if (!window.TMW_MOBILE_HERO_FORCE_INLINE) return;
    const val = 'translateY(calc(var(--offset-y, 0px) * var(--offset-scale, 1)))';
    q(imgSel).forEach(el => {
      try { el.style.setProperty('transform', val, 'important'); } catch(e){}
    });
  }

  function badge(msg, ok) {
    const b = document.createElement('div');
    b.textContent = msg;
    b.style.cssText = 'position:fixed;left:8px;bottom:8px;z-index:99999;padding:8px 10px;border-radius:6px;font:12px/1.2 system-ui;'
                      +(ok?'background:#0a0;color:#fff;':'background:#a00;color:#fff;');
    document.body.appendChild(b);
    setTimeout(()=>b.remove(), 6000);
  }

  // Run only on small viewports
  const mq = window.matchMedia('(max-width: 840px)');
  if (!mq.matches) { console.info('TMW Auditor: Desktop viewport; nothing to do.'); return; }

  forceInlineIfRequested();
  logComputed();

  // Quick pass/fail badge for humans: if any IMG still shows "50% 50%" it's likely centered
  const mismatch = q(frameSel).some(frame => {
    const cs = getComputedStyle(frame);
    const offsetY = parseFloat(cs.getPropertyValue('--offset-y')) || 0;
    if (Math.abs(offsetY) < 0.5) { return false; }
    const img = frame.querySelector('img, picture > img, .wp-post-image');
    if (!img) { return true; }
    const transform = getComputedStyle(img).transform;
    return !transform || transform === 'none';
  });
  badge(mismatch ? 'Hero parity: NOT OK (transform missing)' : 'Hero parity: OK', !mismatch);
})();
