<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Header–H1 Gap — FULL AUDIT (report only)
 * Admin-only. Runs on single model pages when URL contains ?tmw_probe=header-gap-full
 * Collects:
 *  - Runtime layout metrics (hero/header/between/ancestors, pseudo elements, shadows, radii)
 *  - Final computed styles and potential cascade winners
 *  - Stylesheet order and matching rules (same-origin only)
 *  - "Lock" detection: do margins revert when we nudge them briefly?
 *  - Theme file scan for suspicious patterns (MutationObserver, !important, wp_head/wp_footer injectors, CSS vars, offset guards)
 * Saves JSON + Markdown into /wp-content/codex-reports and logs a [TMW-HDR-GAP-FULL] line.
 */

add_action('wp_footer', function () {
    if (!is_singular('model')) return;
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    if (!isset($_GET['tmw_probe']) || $_GET['tmw_probe'] !== 'header-gap-full') return;

    $ajax  = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('tmw_header_gap_full');
    ?>
    <script id="tmw-header-gap-full">
    (function(){
      const SEL = {
        hero: '.single-model .tmw-banner-frame, .single-model .tmw-model-hero, .single-model .model-hero, .single-model .entry-hero, .single-model .post-thumbnail',
        header: '.single-model .entry-header',
        article: '.single-model article, .single-model .hentry'
      };

      function q(s){ return document.querySelector(s); }
      function cs(el){ return el? getComputedStyle(el) : null; }
      function rc(el){ return el? el.getBoundingClientRect() : null; }
      function px(v){ const n=parseFloat(v||0); return isFinite(n)?Math.round(n):0; }
      function clipRect(r){ return r?{top:Math.round(r.top),right:Math.round(r.right),bottom:Math.round(r.bottom),left:Math.round(r.left),width:Math.round(r.width),height:Math.round(r.height)}:null; }
      function pick(s,keys){ const o={}; if(!s) return o; keys.forEach(k=>o[k]=s[k]); return o; }
      function selHint(el){ if(!el) return ''; const c=(el.className||'').trim().split(/\s+/)[0]||''; return (el.tagName||'div').toLowerCase()+(c?'.'+c:''); }
      function pseudo(el,which){ try{ return getComputedStyle(el, which); }catch(e){ return null; } }

      const hero   = q(SEL.hero);
      const header = q(SEL.header);
      const art    = q(SEL.article);

      if (!hero || !header) {
        return send({ ok:false, reason:'hero/header not found', found:{hero:!!hero, header:!!header} });
      }

      // Between-nodes listing
      const between = [];
      let node = hero.nextElementSibling;
      while (node && node !== header) {
        const r = rc(node); const s = cs(node);
        between.push({
          tag: (node.tagName||'div').toLowerCase(),
          class: node.className||'',
          rect: clipRect(r),
          mt: s?s.marginTop:null, mb: s?s.marginBottom:null, pt: s?s.paddingTop:null, pb: s?s.paddingBottom:null, bt: s?s.borderTopWidth:null, bb: s?s.borderBottomWidth:null
        });
        node = node.nextElementSibling;
      }

      // Ancestor checks (up to 3 levels)
      function ancestry(el){
        const out=[]; let p=el?el.parentElement:null; let d=0;
        while (p && d<3) {
          const s=cs(p);
          out.push({sel:selHint(p), rect:clipRect(rc(p)), styles: pick(s,['marginTop','marginBottom','paddingTop','paddingBottom','borderTopWidth','borderBottomWidth','boxShadow','backgroundColor'])});
          p=p.parentElement; d++;
        }
        return out;
      }

      const hr = rc(hero), hs = cs(hero);
      const hd = rc(header), es = cs(header);
      const ar = rc(art),    as = cs(art);
      const beforeHeader = pseudo(header,'::before'), afterHeader = pseudo(header,'::after');
      const beforeHero   = pseudo(hero,'::before'),   afterHero   = pseudo(hero,'::after');

      const gap = Math.round(hd.top - hr.bottom);

      // Stylesheet order + matching rules (best-effort; same-origin only)
      function collectRules(){
        const files=[], matches=[];
        const props = ['margin-top','margin-bottom','padding-top','padding-bottom','border-top','border-bottom','box-shadow'];
        try {
          Array.from(document.styleSheets).forEach((sheet,idx)=>{
            const href = sheet.href || '[inline-'+idx+']';
            files.push(href);
            let rules;
            try { rules = sheet.cssRules; } catch(e) { return; } // Cross-origin blocked
            if (!rules) return;
            Array.from(rules).forEach(r=>{
              if (!r.selectorText) return;
              if (/(\.tmw-banner-frame|\.tmw-model-hero|\.model-hero|\.entry-hero|\.post-thumbnail)\b/.test(r.selectorText) ||
                  /\.entry-header\b/.test(r.selectorText)) {
                const text = r.cssText.toLowerCase();
                if (props.some(p=>text.includes(p))) {
                  matches.push({sheet:href, selector:r.selectorText, css:r.cssText});
                }
              }
            });
          });
        } catch(e){}
        return {files, matches};
      }

      // "Lock" detection: nudge then revert, see if something reverts us
      async function lockTest(){
        const res = { header_top:false, hero_mb:false };
        const origHT = header.style.marginTop;
        const origHM = hero.style.marginBottom;

        header.style.marginTop = '10px';
        hero.style.marginBottom = '10px';
        await new Promise(r=>setTimeout(r,120));
        const hc = cs(header), h2 = cs(hero);
        res.header_top = (px(hc.marginTop) !== 10); // if not 10, something forced another value
        res.hero_mb    = (px(h2.marginBottom) !== 10);
        // revert
        header.style.marginTop = origHT;
        hero.style.marginBottom = origHM;
        return res;
      }

      (async function(){
        const rules = collectRules();
        const locks = await lockTest();

        const payload = {
          ok:true,
          ts_gmt: new Date().toISOString(),
          url: location.href,
          viewport:{w:innerWidth,h:innerHeight,dpr:window.devicePixelRatio||1},
          elems:{
            hero:{ sel: selHint(hero), class: hero.className||'', rect: clipRect(hr),
                   styles: pick(hs,['marginBottom','paddingBottom','borderBottomWidth','boxShadow','borderBottomLeftRadius','borderBottomRightRadius','backgroundColor']) },
            header:{ sel: selHint(header), class: header.className||'', rect: clipRect(hd),
                     styles: pick(es,['marginTop','paddingTop','borderTopWidth','boxShadow','borderTopLeftRadius','borderTopRightRadius','backgroundColor']) },
            article:{ sel: selHint(art), class: art?art.className:'', rect: clipRect(ar),
                      styles: pick(as,['paddingTop','marginTop','backgroundColor']) }
          },
          pseudo:{
            hero_before:  beforeHero ? pick(beforeHero, ['content','borderBottomWidth','boxShadow']) : null,
            hero_after:   afterHero  ? pick(afterHero,  ['content','borderTopWidth','boxShadow']) : null,
            header_before:beforeHeader? pick(beforeHeader,['content','borderTopWidth','boxShadow']): null,
            header_after: afterHeader ? pick(afterHeader, ['content','borderBottomWidth','boxShadow']) : null
          },
          ancestors:{
            hero: ancestry(hero),
            header: ancestry(header)
          },
          between,
          metrics:{
            gap_px: gap,
            hero_margin_bottom_px: px(hs.marginBottom),
            header_margin_top_px:   px(es.marginTop),
            article_padding_top_px: px(as.paddingTop)
          },
          stylesheets: rules.files,
          matching_rules: rules.matches,
          lock_test: locks
        };

        send(payload);
      })();

      function send(obj){
        const data = new URLSearchParams();
        data.set('action','tmw_header_gap_full');
        data.set('_ajax_nonce','<?php echo esc_js($nonce); ?>');
        data.set('payload', JSON.stringify(obj));
        fetch('<?php echo esc_url($ajax); ?>', {
          method:'POST', credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:String(data)
        }).then(()=>console.log('[TMW-HDR-GAP-FULL] sent')).catch(e=>console.warn('send failed',e));
      }
    })();
    </script>
    <?php
}, 9999);

// Receiver: saves JSON+MD and adds server-side file scan section
add_action('wp_ajax_tmw_header_gap_full', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('forbidden',403);
    check_ajax_referer('tmw_header_gap_full');

    $payload = isset($_POST['payload']) ? json_decode(stripslashes((string)$_POST['payload']), true) : null;
    if (!$payload || empty($payload['ok'])) wp_send_json_error('bad-payload',400);

    $dir = WP_CONTENT_DIR . '/codex-reports';
    if (!is_dir($dir)) wp_mkdir_p($dir);
    $stamp = gmdate('Ymd-His');
    $json  = $dir."/header-gap-full-$stamp.json";
    $md    = $dir."/header-gap-full-$stamp.md";

    // --- server-side scan (child theme only) ---
    $root = wp_normalize_path(get_stylesheet_directory());
    $scan = [
      'wp_head_injectors'=>[],
      'wp_footer_injectors'=>[],
      'mutation_observer'=>[],
      'important_usage'=>[],
      'offset_guards'=>[],
      'hero_header_rules'=>[],
    ];
    $patterns = [
      'head'   => '/add_action\s*\(\s*[\'\"]wp_head[\'\"]\s*,\s*[^,]+(?:,\s*(\d+))?/i',
      'foot'   => '/add_action\s*\(\s*[\'\"]wp_footer[\'\"]\s*,\s*[^,]+(?:,\s*(\d+))?/i',
      'mo'     => '/MutationObserver\s*\(/',
      'imp'    => '/!important\b/i',
      'offs'   => '/--offset-y|offset[-_ ]?y|fixOffset|banner[-_ ]?frame/i',
      'hero'   => '/(\.tmw-banner-frame|\.tmw-model-hero|\.model-hero|\.entry-hero|\.entry-header)[^{]*\{[^}]+\}/i'
    ];

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $item) {
        if (!$item->isFile()) continue;
        $ext = strtolower(pathinfo($item->getPathname(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['php','css','js'])) continue;
        $rel = ltrim(str_replace($root,'', wp_normalize_path($item->getPathname())),'/');
        $buf = @file_get_contents($item->getPathname());
        if ($buf === false) continue;

        if (preg_match_all($patterns['head'], $buf, $m))  $scan['wp_head_injectors'][]  = ['file'=>$rel, 'hits'=>count($m[0])];
        if (preg_match_all($patterns['foot'], $buf, $m))  $scan['wp_footer_injectors'][]= ['file'=>$rel, 'hits'=>count($m[0])];
        if (preg_match($patterns['mo'],   $buf))          $scan['mutation_observer'][]  = $rel;
        if ($ext==='css' && preg_match($patterns['imp'],$buf)) $scan['important_usage'][]= $rel;
        if (preg_match($patterns['offs'], $buf))          $scan['offset_guards'][]      = $rel;
        if ($ext==='css' && preg_match_all($patterns['hero'],$buf,$m)) $scan['hero_header_rules'][] = $rel;
    }

    // Write JSON
    @file_put_contents($json, wp_json_encode(['runtime'=>$payload,'scan'=>$scan], JSON_PRETTY_PRINT));

    // Build Markdown
    $gap = $payload['metrics']['gap_px'] ?? '?';
    $locks = $payload['lock_test'] ?? [];
    $md_body  = "# Header–H1 Gap — FULL AUDIT (single-model)\n";
    $md_body .= "- URL: **".esc_url_raw($payload['url'])."**\n";
    $md_body .= "- Generated (GMT): **".gmdate('c')."**\n";
    $md_body .= "- Viewport: **{$payload['viewport']['w']}×{$payload['viewport']['h']} @ {$payload['viewport']['dpr']}**\n\n";
    $md_body .= "## Summary\n";
    $md_body .= "- Computed gap (header.top − hero.bottom): **{$gap}px**\n";
    $md_body .= "- Lock test (runtime rewrites on quick nudge): header.marginTop=**".(!empty($locks['header_top'])?'LOCKED':'free')."**, hero.marginBottom=**".(!empty($locks['hero_mb'])?'LOCKED':'free')."**\n\n";

    $md_body .= "## Runtime Layout\n";
    $md_body .= "### Hero\n```\n".print_r($payload['elems']['hero'], true)."```\n";
    $md_body .= "### Header\n```\n".print_r($payload['elems']['header'], true)."```\n";
    $md_body .= "### Between Elements\n```\n".print_r($payload['between'], true)."```\n";
    $md_body .= "### Ancestors (hero)\n```\n".print_r($payload['ancestors']['hero'], true)."```\n";
    $md_body .= "### Ancestors (header)\n```\n".print_r($payload['ancestors']['header'], true)."```\n";
    $md_body .= "### Pseudo-elements\n```\n".print_r($payload['pseudo'], true)."```\n\n";

    $md_body .= "## Stylesheet Order & Matching Rules (same-origin)\n";
    $md_body .= "- Loaded stylesheets (order):\n";
    foreach (($payload['stylesheets']??[]) as $s) { $md_body .= "  - $s\n"; }
    $md_body .= "\n- Matching rules (showing selectors that touch margins/paddings/borders/box-shadow):\n";
    foreach (($payload['matching_rules']??[]) as $r) {
        $md_body .= "  - **{$r['sheet']}** — `{$r['selector']}`\n";
    }
    $md_body .= "\n";

    $md_body .= "## Theme File Scan (child theme only)\n";
    $md_body .= "- wp_head injectors: `".json_encode($scan['wp_head_injectors'])."`\n";
    $md_body .= "- wp_footer injectors: `".json_encode($scan['wp_footer_injectors'])."`\n";
    $md_body .= "- MutationObserver occurrences: `".json_encode($scan['mutation_observer'])."`\n";
    $md_body .= "- Files with `!important` (CSS): `".json_encode($scan['important_usage'])."`\n";
    $md_body .= "- Potential offset guards / fixers: `".json_encode($scan['offset_guards'])."`\n";
    $md_body .= "- Files containing hero/header CSS blocks: `".json_encode($scan['hero_header_rules'])."`\n\n";

    $md_body .= "## Interpretation Guide (no code applied)\n";
    $md_body .= "- If **Lock test** shows `LOCKED`, a runtime script or late stylesheet is overriding margins. Check the listed `wp_head`/`wp_footer` injectors and any MutationObservers/offset guards.\n";
    $md_body .= "- If **Between Elements** lists any node with height/margins, that node is the *true* spacer, not hero/header margins.\n";
    $md_body .= "- If **Pseudo-elements** show borders/shadows, those may draw the visible line even when margins are zero.\n\n";

    @file_put_contents($md, $md_body);

    error_log(sprintf('[TMW-HDR-GAP-FULL] gap=%spx locked(h=%s,hero=%s) wrote=%s',
        $gap,
        !empty($locks['header_top'])?'yes':'no',
        !empty($locks['hero_mb'])?'yes':'no',
        wp_normalize_path($md)
    ));

    wp_send_json_success(['ok'=>true, 'json'=>basename($json), 'md'=>basename($md)]);
});
