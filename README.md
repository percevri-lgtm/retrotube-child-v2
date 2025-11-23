# Retrotube Child (Flipbox Edition) v2

**What’s new**
- Sidebar on the Models page template.
- Pagination for Actors grid (16 per page by default).
- Banner slot (364×45) injected between the first 8 and next 8 models.
- Trigger button on each flipbox front (“Watch now” by default).
- Actor bio text is now **under** the hero image.
- Optional social links on actor profile (OnlyFans, FanCentro, Reddit, Facebook, Instagram, Twitter/X, TikTok, Website).
- New `[promo_flipboxes]` shortcode for 4 external flipboxes.

## Install / Update
1. Upload and activate the theme: Appearance → Themes → Upload → `retrotube-child-v2.zip`.
2. Make sure the parent theme folder is named **retrotube**.

## Development notes
- When iterating on this child theme, take the time to think through changes holistically—small visual tweaks often impact the shortcode markup, PHP templates, and SCSS partials together.
- Document any non-obvious decisions directly in the template or partial that implements them so future updates retain the original intent.
- Runtime logic now lives in `/inc`. Keep `functions.php` as a bootstrap only.

## Module map (v4.1.0)
- `functions.php` — Defines theme constants and loads the bootstrap.
- `inc/bootstrap.php` — Registers the lightweight autoloader and wires every module.
- `inc/constants.php` — Shared constants (`TMW_CHILD_NS`, asset paths, feature flags).
- `inc/setup.php` — Theme supports, image sizes, and other `after_setup_theme` hooks.
- `inc/enqueue.php` — Styles/scripts for front end + enqueue helpers.
- `inc/frontend/` — Feature-specific front-end modules:
  - `model-banner.php` — Banner math, offset guard, admin capture helpers.
  - `flipboxes.php` — Legacy flipbox shortcodes and helpers.
  - `comments.php` — Comment form hardening.
  - `taxonomies.php` — Model taxonomy wiring and AW helpers.
  - `shortcodes.php` — Reserved for future shortcodes.
  - `template-tags.php` — Navigation/menu helpers and general template glue.
- `inc/admin/` — Admin-only behavior:
  - `metabox-model-banner.php` — Model banner meta box bootstrap.
  - `editor-tweaks.php` — Admin tooling, audits, and enqueue bridges.
- `assets/php/tmw-hybrid-model-scan.php` — WP-CLI commands to sync models/videos.

### Adding new features
- Place new PHP functionality under `/inc/frontend` or `/inc/admin` and include it from `inc/bootstrap.php`.
- Avoid adding executable code to templates; expose helpers via modules so templates stay declarative.
- When adding scripts/styles, register/enqueue them through `inc/enqueue.php` to keep handles organized.

## Models page
Create a page “Models” and choose **Template: Models Flipboxes (with Sidebar)**.  
This uses shortcode: `[actors_flipboxes per_page="16" cols="4" show_pagination="true"]`.

### Banner between 8th and 9th item
Edit the file: `assets/models-banner.html` and paste your ad code (364×45).  
Alternatively pass attributes on the shortcode:
```
[actors_flipboxes per_page="16"
  banner_img="/wp-content/uploads/your-ad.webp"
  banner_url="https://your-link.com"
  banner_alt="Sponsored"]
```

## Shortcodes

### Actors grid (with pagination)
```
[actors_flipboxes per_page="16" cols="4" trigger_text="Watch now" show_pagination="true"]
```
- Pagination uses `?pg=2` on the URL.
- Other params: `orderby`, `order`, `hide_empty` (true/false), `page_var` (default `pg`).

### Promo flipboxes (4 external)
```
[promo_flipboxes
  front1="" back1="" url1="" title1=""]
  front2="" back2="" url2="" title2=""
  front3="" back3="" url3="" title3=""
  front4="" back4="" url4="" title4=""
  cols="4"]
```

## Actor profile (taxonomy-actors.php)
- Hero image: ACF field `hero_image` (optional).  
- Bio (under the image): `short_bio`.  
- Live button: `live_link`.  
- Optional social links (any may be empty): `link_website`, `link_onlyfans`, `link_fancentro`, `link_reddit`, `link_facebook`, `link_instagram`, `link_twitter`, `link_tiktok`.

## ACF fields (add to taxonomy `actors`)
- `actor_card_front` (Image) – Flip front
- `actor_card_back`  (Image) – Flip back
- `hero_image` (Image)
- `short_bio` (Textarea)
- `live_link` (URL)
- `link_website` (URL), `link_onlyfans` (URL), `link_fancentro` (URL), `link_reddit` (URL),
  `link_facebook` (URL), `link_instagram` (URL), `link_twitter` (URL), `link_tiktok` (URL)

Import the provided **acf-actor-fields-v2.json** via ACF → Tools → Import.