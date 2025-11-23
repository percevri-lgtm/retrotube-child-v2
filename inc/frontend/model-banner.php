<?php
if (!defined('ABSPATH')) { exit; }

// Ensure legacy banner helpers remain loaded (background renderer, admin CSS).
$banner_autoload = TMW_CHILD_PATH . '/inc/_autoload_tmw_banner_bg.php';
if (is_readable($banner_autoload)) {
    require_once $banner_autoload;
}

if (defined('TMW_DEBUG') && TMW_DEBUG) {
    add_action('init', function () {
        error_log('[TMW-V410] cleanup: removed legacy background assets');
    }, 2);
}

// v3.5.4 — Flip CTA offset loaded sentinel.
add_action('wp_head', function () {
    if (defined('TMW_DEBUG') && TMW_DEBUG) {
        error_log('[TMW-FLIP-CTA] v3.5.4 style.css CTA offset rules active');
    }
}, 1);

/**
 * Admin: capture the preview's actual hero height and store it as _tmw_offset_base
 * so the front-end can scale the saved px offset perfectly at any viewport.
 */
add_action('admin_footer-post.php', 'tmw_model_offset_admin_probe');
add_action('admin_footer-post-new.php', 'tmw_model_offset_admin_probe');
if (!function_exists('tmw_model_offset_admin_probe')) {
    function tmw_model_offset_admin_probe() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'model') {
            return;
        }
        ?>
        <input type="hidden" id="tmw_offset_base" name="tmw_offset_base" value="" />
        <script>
        (function(){
          function frame(){ return document.querySelector('.tmw-banner-frame'); }
          function writeBase(){
            var el = frame();
            var h  = el ? Math.round(el.getBoundingClientRect().height || 0) : 0;
            var inp = document.getElementById('tmw_offset_base');
            if (inp && h) inp.value = String(h);
          }
          document.addEventListener('DOMContentLoaded', writeBase, {once:true});
          window.addEventListener('resize', writeBase, {passive:true});
          document.addEventListener('input', writeBase, true);
          document.addEventListener('click', function(e){
            var id = (e.target && e.target.id) || '';
            if (/publish|save-post|save/i.test(id)) writeBase();
          }, true);
        })();
        </script>
        <?php
    }
}

add_action('save_post_model', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['tmw_offset_base'])) {
        update_post_meta($post_id, '_tmw_offset_base', (int) $_POST['tmw_offset_base']);
    }
}, 10, 1);

/**
 * Front-end: compute --offset-scale = (heroHeight / baseHeight)
 * We read --offset-base (injected with the banner) or fall back to --tmw-hero-base-h.
 */
add_action('wp_footer', function () {
    ?>
    <script>
    (function () {
      function setScale(el) {
        if (!el) return;
        var cs   = getComputedStyle(el);
        var base = parseFloat(cs.getPropertyValue('--offset-base')) ||
                   parseFloat(cs.getPropertyValue('--tmw-hero-base-h')) || 350;
        var h    = el.getBoundingClientRect().height || 0;
        if (base > 0 && h > 0) {
          el.style.setProperty('--offset-scale', (h / base).toFixed(6));
        }
      }
      function init() {
        var frames = document.querySelectorAll('.tmw-banner-frame');
        frames.forEach(setScale);
        if ('ResizeObserver' in window) {
          var ro = new ResizeObserver(function(entries){ entries.forEach(function(e){ setScale(e.target); }); });
          frames.forEach(function(f){ ro.observe(f); });
        } else {
          window.addEventListener('resize', function(){ frames.forEach(setScale); }, {passive:true});
        }
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once:true});
      } else {
        init();
      }
    })();
    </script>
    <?php
}, 90); // after paint, before other late scripts

// === TMW offset guard QA bypass (cookie + html flag) ===
add_action('init', function () {
    if (!isset($_GET['tmw-no-guard'])) {
        return;
    }

    $value = $_GET['tmw-no-guard'];
    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

    if ($value === '1') {
        // 24h cookie to persist bypass on phones
        $ttl = time() + DAY_IN_SECONDS;
        setcookie('tmw_no_guard', '1', $ttl, $path, $domain, is_ssl(), true);
        $_COOKIE['tmw_no_guard'] = '1';
    } elseif ($value === '0') {
        setcookie('tmw_no_guard', '', time() - DAY_IN_SECONDS, $path, $domain, is_ssl(), true);
        unset($_COOKIE['tmw_no_guard']);
    }
});

// Print the html data-flag as early as possible
add_action('wp_head', function () {
    $bypass = (is_user_logged_in() || !empty($_COOKIE['tmw_no_guard']));
    if ($bypass) {
        echo "<script>document.documentElement.setAttribute('data-tmw-no-guard','');</script>";
    }
}, 0);

// Disable the inline guard entirely when bypassing
add_filter('tmw/offset_guard/enabled', function ($enabled) {
    if (is_user_logged_in() || !empty($_COOKIE['tmw_no_guard'])) {
        return false;
    }

    return $enabled;
});

// Inline guard to lock the banner offset variable in place.
// Offset guard: default OFF for public; can be enabled via filter if ever needed.
add_action('wp_footer', function () {
    static $injected = false;
    if ($injected) {
        return;
    }

    if (is_admin()) {
        return;
    }

    // QA toggles
    $skip_for_logged_in = true; // allow logged-in users to bypass guard
    $enabled = apply_filters('tmw/offset_guard/enabled', false); // was true

    if (isset($_GET['tmw-no-guard'])) {
        return;
    }

    if (!$enabled) {
        return;
    }

    if ($skip_for_logged_in && is_user_logged_in()) {
        return;
    }

    $injected = true;
    echo '<script id="tmw-offset-fix">(function(){const o="__tmwOffsetGuard__",t="__tmwOffsetGuardInstalled__";if(window[t])return;const e=typeof location==="object"&&typeof location.search==="string"?location.search:"",n=e.indexOf("tmw-no-guard")!==-1,r=document.documentElement&&document.documentElement.hasAttribute("data-tmw-no-guard");if(n||r){window[t]=!0;const e=window[o]&&typeof window[o]==="object"?window[o]:{};e.installed=!0;e.bypassed=!0;window[o]=e;if(typeof window.fixOffset!=="function"){function n(){}Object.defineProperty(n,"name",{value:"fixOffset"});n.__offsetGuardVersion="1.1.0";window.fixOffset=n}return}if(window[o]&&window[o].installed){window[t]=!0;return}const i=".tmw-banner-frame",a=0,d={target:null,observer:null,rafId:0,deadline:0,isSetting:!1};function c(e){if(!e||d.isSetting)return;const n=parseFloat(getComputedStyle(e).getPropertyValue("--offset-y"))||0;if(n>=a)return;d.isSetting=!0;try{e.style.setProperty("--offset-y",a+"px","important")}finally{d.isSetting=!1}}function s(e){d.observer||(d.observer=new MutationObserver(()=>{if(!d.target)return;const e=parseFloat(getComputedStyle(d.target).getPropertyValue("--offset-y"))||0;e<a&&c(d.target)}));d.observer.disconnect();d.observer.observe(e,{attributes:!0,attributeFilter:["style"]})}function l(){const e=document.querySelector(i);if(!e)return!1;d.target!==e&&d.observer&&(d.observer.disconnect(),d.observer=null);d.target=e;c(e);s(e);return!0}function f(){if(l()){d.rafId=0;return}if(performance.now()>=d.deadline){d.rafId=0;return}d.rafId=requestAnimationFrame(f)}function u(){d.rafId&&(cancelAnimationFrame(d.rafId),d.rafId=0);d.deadline=performance.now()+5e3;f()}function m(){d.observer&&(d.observer.disconnect(),d.observer=null);d.target=null}function w(){u()}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",w,{once:!0}):w();window.addEventListener("load",w,{once:!0});function y(){m();u()}Object.defineProperty(y,"name",{value:"fixOffset"});window.fixOffset=y;window.fixOffset.__offsetGuardVersion="1.1.0";const g=window[o]&&typeof window[o]==="object"?window[o]:{};g.installed=!0;g.bypassed=!1;window[o]=g;window[t]=!0})();</script>';
}, 100);
