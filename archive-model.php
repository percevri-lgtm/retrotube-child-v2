<?php
/**
 * Archive template for Models CPT
 * Safely reuses the Models Flipboxes template with archive header.
 */

get_header();
?>
<main id="primary" class="site-main">
  <div class="tmw-layout container">
    <section class="tmw-content" data-mobile-guard="true">
      <header class="entry-header">
        <h1 class="widget-title"><span class="tmw-star">★</span> Models</h1>
      </header>
      <?php
      // Edit banner file at /assets/models-banner.html or pass banner_* via shortcode below.
      $tmw_flipbox_link_filter = tmw_get_flipbox_link_guard();

      add_filter('tmw_model_flipbox_link', $tmw_flipbox_link_filter, 10, 2);
      echo do_shortcode('[actors_flipboxes per_page="16" cols="4" show_pagination="true"]');
      remove_filter('tmw_model_flipbox_link', $tmw_flipbox_link_filter, 10);
      ?>
    </section>
    <aside class="tmw-sidebar">
      <?php get_sidebar(); ?>
    </aside>
  </div>
</main>
<?php get_footer(); ?>
