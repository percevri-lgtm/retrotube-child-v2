# Mobile Hero Offset Audit — v3.6.6

## Conflicting & Duplicate Mobile Rules (Pre-Cleanup)
- Prior to this cleanup, the first `@media (max-width: 840px)` block introduced in v3.6.1 combined the hero height clamp with a global `.tmw-banner-frame img` rule that rewrote the offset on mobile using `!important`. This lived alongside the clamp logic at the top of the Mobile section. The block remains for height control, but its focal override has been removed in v3.6.6. 【F:style.css†L318-L343】
- A second `@media (max-width: 840px)` block added in v3.6.5 targeted the single-model hero with highly specific selectors and the same `!important` offsets for `<img>`, `picture > img`, `.wp-post-image`, and background/overlay pseudos. Because it appeared later in the file and was more specific, it always won the cascade, leaving the earlier override redundant. The consolidated block introduced in v3.6.6 keeps those selectors intact while relocating them to the end of the stylesheet. 【F:style.css†L723-L773】

## "Before" Cascade Map (≤840px)
1. **Clamp + Global Override (v3.6.1)** – Applied to `.tmw-banner-frame img` inside the first mobile breakpoint, forcing `object-position` with `!important`. Lower specificity, but executed first. (Removed in v3.6.6.)
2. **Single-Model Specific Override (v3.6.5)** – Applied to `.single-model` hero selectors for both `<img>` and background layers, also using `!important`. Because it is more specific and was declared later, it determined the computed focal point on mobile. The new consolidated block preserves this behavior while coexisting cleanly with the clamp rules. 【F:style.css†L723-L773】

## Consolidation Plan (Executed)
1. Preserve the existing clamp and CTA spacing logic untouched so mobile heights remain within the 140–200px window. 【F:style.css†L318-L343】【F:style.css†L693-L719】
2. Remove the redundant `object-position` override from the v3.6.1 block and delete the legacy v3.6.5 breakpoint entirely to eliminate duplicate focal declarations.
3. Append a single consolidated mobile block after the clamp section that mirrors desktop `--offset-y` handling for images, backgrounds, and pseudo overlays, adds overflow guarding, and keeps the transform fallback. 【F:style.css†L723-L773】
