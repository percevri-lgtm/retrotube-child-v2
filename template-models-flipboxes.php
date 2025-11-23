<?php
/**
 * Template Name: Models Flipboxes (with Sidebar)
 * Description: Displays an Actors flipbox grid with pagination, sidebar, and a banner slot.
 */
get_header(); ?>
<main id="primary" class="site-main">
  <div class="tmw-layout container">
    <section class="tmw-content" data-mobile-guard="true">
      <h1 class="section-title">Models</h1>
      <?php
      // Edit banner file at /assets/models-banner.html or pass banner_* via shortcode below.
      $tmw_flipbox_link_filter = tmw_get_flipbox_link_guard([
        'disable_mobile' => true,
      ]);

      add_filter('tmw_model_flipbox_link', $tmw_flipbox_link_filter, 10, 2);
      echo tmw_models_flipboxes_cb([
        'per_page'        => 16,
        'cols'            => 4,
        'show_pagination' => true,
      ]);
      remove_filter('tmw_model_flipbox_link', $tmw_flipbox_link_filter, 10);
      ?>
    </section>
    <aside class="tmw-sidebar">
      <?php get_sidebar(); ?>
    </aside>
  </div>
</main>
<?php get_footer(); ?>
