<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Header→H1 Gap Probe (read-only)
 * - Admin-only, single-model, runs only with ?tmw_probe=header-gap
 * - Injects a small inline script that measures real layout gap in the browser
 * - Posts the metrics via admin-ajax and writes JSON+MD into /wp-content/codex-reports
 */

add_action('wp_footer', function () {
    if (!is_singular('model')) return;
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    if (!isset($_GET['tmw_probe']) || $_GET['tmw_probe'] !== 'header-gap') return;

    $ajax = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('tmw_header_gap_report');

    ?>
    <script id="tmw-header-gap-probe">
    (function(){
      function css(el){ return el ? getComputedStyle(el) : null; }
      function rect(el){ return el ? el.getBoundingClientRect() : null; }

      // Candidate hero nodes (first hit wins)
      var hero = document.querySelector(
        // Prefer the real hero frame/container first
        '.single-model .tmw-banner-frame, ' +
        '.single-model .tmw-model-hero, ' +
        '.single-model .model-hero, ' +
        '.single-model .entry-hero, ' +
        '.single-model .post-thumbnail'
      );
      var header = document.querySelector('.single-model .entry-header');
      var article = document.querySelector('.single-model article, .single-model .hentry');

      var info = {
        url: location.href,
        ts_gmt: new Date().toISOString(),
        ua: navigator.userAgent,
        viewport: { w: innerWidth, h: innerHeight, dpr: devicePixelRatio || 1 },
        found: { hero: !!hero, header: !!header, article: !!article }
      };

      if (!hero || !header) {
        send({ok:false, reason:'Missing nodes', info:info});
        return;
      }

      var hr = rect(hero), hd = rect(header), ar = rect(article);
      var hs = css(hero);
      var es = css(header);
      var as = css(article);

      var gap = Math.round(hd.top - hr.bottom); // px (can be negative)
      var hints = [];

      // Heuristics: find the dominant contributor
      if (approx(gap, px(hs.marginBottom))) hints.push('hero margin-bottom ≈ gap');
      if (approx(gap, px(es.marginTop)))     hints.push('entry-header margin-top ≈ gap');
      if (approx(gap, px(as.paddingTop)))    hints.push('article padding-top ≈ gap');

      // Build a minimal selector hint
      function selHint(el){ if(!el) return '';
        var c = (el.className || '').trim().split(/\s+/).filter(Boolean)[0] || '';
        return (el.tagName ? el.tagName.toLowerCase() : 'div') + (c ? '.'+c : '');
      }

      var payload = {
        ok: true,
        page_type: 'single-model',
        url: info.url,
        viewport: info.viewport,
        elems: {
          hero:   { sel: selHint(hero), class: hero.className, rect: pickRect(hr),  styles: pickStyles(hs, ['marginBottom','paddingBottom','borderBottomWidth']) },
          header: { sel: selHint(header), class: header.className, rect: pickRect(hd), styles: pickStyles(es, ['marginTop','paddingTop','borderTopWidth']) },
          article:{ sel: selHint(article), class: article?article.className:'', rect: pickRect(ar), styles: pickStyles(as, ['paddingTop','marginTop']) }
        },
        metrics: {
          gap_px: gap,
          hero_margin_bottom_px: px(hs.marginBottom),
          header_margin_top_px:   px(es.marginTop),
          article_padding_top_px: px(as.paddingTop)
        },
        hints: hints
      };

      function px(v){ var n=parseFloat(v||0); return isFinite(n)?Math.round(n):0; }
      function approx(a,b){ return Math.abs(a-b) <= 1; }
      function pickRect(r){ return r?{top:Math.round(r.top), right:Math.round(r.right), bottom:Math.round(r.bottom), left:Math.round(r.left), width:Math.round(r.width), height:Math.round(r.height)}:null; }
      function pickStyles(s, keys){ var o={}; if(!s) return o; keys.forEach(k=>o[k]=s[k]); return o; }

      function send(obj){
        var data = new URLSearchParams();
        data.set('action','tmw_header_gap_report');
        data.set('_ajax_nonce','<?php echo esc_js($nonce); ?>');
        data.set('payload', JSON.stringify(obj));
        fetch('<?php echo esc_url($ajax); ?>', {
          method:'POST', credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:String(data)
        }).then(function(){ console.log('[TMW-HDR-GAP] sent'); })
          .catch(function(e){ console.warn('[TMW-HDR-GAP] send failed', e); });
      }

      send(payload);
    })();
    </script>
    <?php
}, 100);

// Receiver: writes /wp-content/codex-reports/header-gap-*.{json,md}
add_action('wp_ajax_tmw_header_gap_report', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
    check_ajax_referer('tmw_header_gap_report');

    $payload = isset($_POST['payload']) ? json_decode(stripslashes((string)$_POST['payload']), true) : null;
    if (!$payload) wp_send_json_error('no-payload', 400);

    $dir = WP_CONTENT_DIR . '/codex-reports';
    if (!is_dir($dir)) wp_mkdir_p($dir);

    $stamp = gmdate('Ymd-His');
    $json = $dir . "/header-gap-$stamp.json";
    $md   = $dir . "/header-gap-$stamp.md";

    // Write JSON
    @file_put_contents($json, wp_json_encode($payload, JSON_PRETTY_PRINT));

    // Build Markdown
    $gap = isset($payload['metrics']['gap_px']) ? (int)$payload['metrics']['gap_px'] : null;
    $hints = isset($payload['hints']) ? implode(', ', $payload['hints']) : '';
    $heroSel   = $payload['elems']['hero']['sel']   ?? '.post-thumbnail';
    $headerSel = $payload['elems']['header']['sel'] ?? '.entry-header';

    $md_body  = "# Header→H1 Gap Report (single-model)\n";
    $md_body .= "- URL: **" . esc_url_raw($payload['url'] ?? '') . "**\n";
    $md_body .= "- Generated (GMT): **" . gmdate('c') . "**\n";
    $md_body .= "- Viewport: **" . ($payload['viewport']['w'] ?? '?') . "×" . ($payload['viewport']['h'] ?? '?') . " @ " . ($payload['viewport']['dpr'] ?? '1') . "**\n\n";
    $md_body .= "## Measurements\n";
    $md_body .= "- Gap (header.top − hero.bottom): **" . $gap . "px**\n";
    $md_body .= "- Hero margin-bottom: **" . ($payload['metrics']['hero_margin_bottom_px'] ?? '?') . "px**\n";
    $md_body .= "- Entry-header margin-top: **" . ($payload['metrics']['header_margin_top_px'] ?? '?') . "px**\n";
    $md_body .= "- Article padding-top: **" . ($payload['metrics']['article_padding_top_px'] ?? '?') . "px**\n\n";
    $md_body .= "## Likely Cause(s)\n- " . ($hints ? $hints : "No single dominant culprit detected; combined margins/padding likely.") . "\n\n";
    $md_body .= "## Candidate Fix Patterns (for later PR; not applied here)\n";
    $md_body .= "```css\n/* Option A: zero margins at the join */\n.single-model " . $heroSel . " { margin-bottom: 0 !important; }\n.single-model " . $headerSel . " { margin-top: 0 !important; }\n\n/* Option B: wrap both and collapse the gap */\n/* .single-model .tmw-hero-header-group > " . $heroSel . ",\n   .single-model .tmw-hero-header-group > " . $headerSel . " { margin: 0 !important; } */\n```\n";
    $md_body .= "\n> **Note:** This file is a diagnostic report only. Apply fixes via a separate PR.\n";

    @file_put_contents($md, $md_body);

    error_log(sprintf('[TMW-HDR-GAP] gap=%spx wrote=%s', $gap, wp_normalize_path($md)));
    wp_send_json_success(['ok'=>true]);
});
