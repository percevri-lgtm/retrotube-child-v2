# RetroTube Child v2 – Final Cleanup Audit

## ✅ Dead Code Inventory
- [TMW-AUDIT] `_legacy/single-model.php.bak` and `_legacy/single-model_bio.php` are backup templates that duplicate the live model view handled by `single-model.php` (lines 1-76) and `template-parts/content-model.php` (lines 1-178); safe to delete once history confirmed. 【F:single-model.php†L1-L76】【F:template-parts/content-model.php†L1-L178】
- [TMW-AUDIT] `backups/` contains archived `archive-model*.php` and `single-model_bio.v1.1.5.php` variants superseded by the active flipbox archive (`archive-model.php`, lines 1-60) and taxonomy templates. 【F:archive-model.php†L1-L60】
- [TMW-AUDIT] Root-level `CODEX_*.php/.md` files were one-off audits/tooling; none are loaded from `functions.php` beyond guarded includes (lines 6-78). Remove or archive externally. 【F:functions.php†L6-L78】
- [TMW-AUDIT] Unreferenced assets: `assets/gold-black-bg.webp` (507 KB) and `assets/gold-black-hero.webp` (128 KB) are not referenced anywhere (no matches in repo) and can be culled to trim the package. 【F:assets/flipboxes.css†L1-L200】
- [TMW-AUDIT] Legacy JS never enqueued: `js/tmw-flipbox-mobile-fix.js` still targets `.flip-box` markup (lines 1-40) no longer emitted by `tmw_models_flipboxes_cb` (lines 280-396); delete along with `js/tmw-offset-guard.js` / `.min.js`, superseded by the inline guard injected in `inc/frontend/model-banner.php` (lines 83-148). 【F:js/tmw-flipbox-mobile-fix.js†L1-L40】【F:inc/frontend/model-banner.php†L83-L148】
- [TMW-AUDIT] `js/tmw-resetpass-popup.js` and `js/tmw-tml-popup.js` are unused—no `wp_enqueue_script` reference outside of dormant debug loaders. Confirm with Theme My Login flow before removal.

## ✅ Duplicate / Redundant Enqueues
- [TMW-AUDIT] Core assets already handled in `inc/enqueue.php` (`retrotube-parent`, `retrotube-child-style`, `rt-child-flip`; lines 29-78). Secondary re-queues in `inc/tmw-admin-tools.php` (lines 255-281 and 796-804) re-enqueue or dequeue/requeue the same handles at `9999`, causing double work and cache busting; consolidate into the main enqueue helper. 【F:inc/enqueue.php†L29-L78】【F:inc/tmw-admin-tools.php†L255-L304】【F:inc/tmw-admin-tools.php†L796-L804】
- [TMW-AUDIT] Admin banner helpers (`tmw-admin-banner-style`, `tmw-banner-admin-align`) are enqueued in both `inc/tmw-admin-tools.php` (lines 272-295) and `inc/admin/model-banner-meta-box.php` (lines 10-36). Keep a single source (prefer the scoped `/inc/admin` module). 【F:inc/tmw-admin-tools.php†L272-L295】【F:inc/admin/model-banner-meta-box.php†L10-L36】
- [TMW-AUDIT] Flipbox debug scripts enqueue twice inside the same debug block (lines 735-783). Collapse into one guarded loader tied to `TMW_DEBUG` and avoid duplicate Ajax bindings. 【F:inc/tmw-admin-tools.php†L735-L783】
- [TMW-AUDIT] `wp_enqueue_style('retrotube-child-style')` is called in admin enqueue hooks (`inc/tmw-admin-tools.php` lines 265-269, `inc/admin/model-banner-meta-box.php` lines 28-33, `inc/tmw-model-hooks.php` lines 1378-1404). Verify whether admin actually needs the front-end stylesheet; if not, drop to reduce editor payload. 【F:inc/tmw-model-hooks.php†L1378-L1404】

## ✅ Move-to-/inc/ (or consolidate) Recommendations
- [TMW-AUDIT] The flipbox link guards are duplicated inline in `archive-model.php` (lines 16-53) and `template-models-flipboxes.php` (lines 12-60). Extract a single helper in `inc/frontend/flipboxes.php` and share it across templates. 【F:archive-model.php†L16-L53】【F:template-models-flipboxes.php†L12-L60】
- [TMW-AUDIT] `taxonomy-models.php` directly logs and renders widgets; shift the `[TMW-MODEL-AUDIT]` logger and widget args into `inc/frontend/taxonomies.php` to keep templates presentation-only. 【F:taxonomy-models.php†L7-L45】
- [TMW-AUDIT] Inline `wp_add_inline_style` audit CSS inside `single-model.php` (lines 9-12) should move to a conditional helper in `inc/frontend/model-banner.php` so templates stay lean when audit mode is disabled. 【F:single-model.php†L7-L56】
- [TMW-AUDIT] Consolidate Ajax endpoints and logging utilities living in `inc/tmw-admin-tools.php` into purpose-specific modules under `/inc/admin/` (e.g., `flipbox-debug`, `model-sync`) to keep `editor-tweaks.php` minimal. 【F:inc/admin/editor-tweaks.php†L1-L7】【F:inc/tmw-admin-tools.php†L700-L792】

## ✅ Debug / Logging Toggles to Disable Before Release
- [TMW-AUDIT] Front-end templates emit `[TMW-*]` logs on every request: `taxonomy-models.php` (line 7), `single-model.php` (lines 7, 31, 46, 56), and `template-parts/content-model.php` (lines 5, 91-129) should wrap logs behind a `WP_DEBUG`/constant check. 【F:taxonomy-models.php†L7-L45】【F:single-model.php†L7-L56】【F:template-parts/content-model.php†L5-L129】
- [TMW-AUDIT] `inc/enqueue.php` reports slot handles on each page load (lines 156-194); guard or remove before production. 【F:inc/enqueue.php†L156-L194】
- [TMW-AUDIT] Persistent audits like `inc/tmw-register-audit.php` (lines 12-105), `inc/tmw-tml-bridge.php` (lines 7-17), and `inc/tmw-mail-transport.php` (lines 22-86) log to `debug.log`. Add master toggles or disable modules when launch-ready. 【F:inc/tmw-register-audit.php†L12-L105】【F:inc/tmw-tml-bridge.php†L7-L17】【F:inc/tmw-mail-transport.php†L22-L86】
- [TMW-AUDIT] `wp-content/mu-plugins/tmw-mail-audit.php` logs every email (`[MAIL]` tag); deactivate for production to avoid leaking addresses. 【F:wp-content/mu-plugins/tmw-mail-audit.php†L1-L3】
- [TMW-AUDIT] Ship with `wp-content/debug.log` removed and ensure `WP_DEBUG_LOG` is false in production.

## ✅ Performance Quick Wins (ranked)
1. [TMW-AUDIT] Stop re-enqueuing core styles in `inc/tmw-admin-tools.php` (lines 255-304, 796-804) — reduces duplicate HTTP requests and cache churn. 【F:inc/tmw-admin-tools.php†L255-L304】【F:inc/tmw-admin-tools.php†L796-L804】
2. [TMW-AUDIT] Remove per-term logging inside `tmw_models_flipboxes_cb` (line 317) to cut slow I/O on large grids. 【F:inc/tmw-video-hooks.php†L280-L396】
3. [TMW-AUDIT] Replace inline `<script>` injection in `inc/frontend/model-banner.php` (lines 83-148) with an external, conditionally enqueued file so caching/CDN can optimize it.
4. [TMW-AUDIT] Audit Autoptimize exclusions in `inc/enqueue.php` (lines 200-208) to ensure only required handles are skipped; over-exclusion prevents CSS/JS aggregation. 【F:inc/enqueue.php†L200-L208】
5. [TMW-AUDIT] Purge unused flipbox/mobile scripts noted above to shrink build size and avoid unnecessary `filemtime` checks.

## ✅ Accessibility & SEO Gaps
- [TMW-AUDIT] Flipbox cards rely entirely on background images (`tmw_models_flipboxes_cb`, lines 340-369), so screen readers receive no alt text or focus targets. Consider adding hidden `<img>` tags with `alt` text and using `<button>` for flips on mobile.
- [TMW-AUDIT] `page-models-grid.php` surfaces the page title inside a `<div>` (lines 8-19); promote to an `<h1>` for semantic structure. 【F:page-models-grid.php†L1-L19】
- [TMW-AUDIT] `template-models-flipboxes.php` disables anchors on mobile via `wp_is_mobile()` but falls back to `$base_link` (lines 21-34) because `tmw_models_flipboxes_cb` forces `$cta_link = $link ?: $base_link` (lines 320-370). Users may still trigger navigation unexpectedly; align logic and ensure focus management.
- [TMW-AUDIT] Schema: `template-parts/content-model.php` sets `itemtype="Person"` (lines 18-176) but the flipbox listings lack structured data — consider `ItemList` markup for archive/grid templates.
- [TMW-AUDIT] Ensure banner CTA buttons include discernible text for screen readers; audit `template-parts/content-model.php` lines 37-45 for optional labels.

## ✅ Flipbox Desktop/Mobile Parity Notes
- [TMW-AUDIT] Desktop flip relies on CSS hover (`assets/flipboxes.css`, lines 72-119). Mobile fallback depends on JS debug helpers targeting `.flip-box` (legacy) rather than `.tmw-flip`; need a production-ready touch handler aligned with current markup. 【F:assets/flipboxes.css†L72-L119】【F:js/tmw-flipbox-mobile-fix.js†L1-L40】
- [TMW-AUDIT] Templates attempt to disable mobile navigation by returning `false` from `tmw_model_flipbox_link` (template lines 12-60), but `tmw_models_flipboxes_cb` still assigns `$cta_link` (lines 320-370), so tapping may navigate. Reconcile filter contract or add explicit mobile checks in renderer. 【F:template-models-flipboxes.php†L12-L60】【F:inc/tmw-video-hooks.php†L320-L370】
- [TMW-AUDIT] Taxonomy archive (`taxonomy-models.php`, lines 18-41) uses the shortcode without the mobile guard, so category pages behave differently than the dedicated grid page; document whether parity is required.

## ✅ Next-PR Change Plan

### PR 1 — Housekeeping & Code Consolidation
- Remove backup/legacy files and unused assets listed above.
- Merge flipbox link guard logic into `inc/frontend/flipboxes.php`; update templates to consume helpers.
- Migrate admin enqueue responsibilities from `inc/tmw-admin-tools.php` into scoped `/inc/admin/` modules and strip duplicate style loads.
- Disable or gate `[TMW-*]` logging in templates and modules behind environment flags.

### PR 2 — Performance & Asset Hygiene
- Replace inline banner guard JS with an enqueue in `inc/enqueue.php`; delete obsolete `tmw-offset-guard*.js`.
- Trim redundant Autoptimize exclusions and remove re-enqueue/dequeue calls that thrash caches.
- Delete unused flipbox/mobile scripts and heavy gold-black images; re-run `filemtime` dependency checks after cleanup.

### PR 3 — UX, Accessibility, and SEO Polish
- Normalize heading hierarchy in `page-models-grid.php` and related templates.
- Add accessible focus/touch handling for flipboxes (JS + ARIA roles) and ensure mobile behavior matches desktop expectations.
- Inject structured data (`ItemList`, `Person`) for flipbox archives and ensure CTA buttons expose meaningful text to assistive tech.

