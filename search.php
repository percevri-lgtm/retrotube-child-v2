<?php
/**
 * Search results template override for Retrotube Child theme.
 */

if (tmw_try_parent_template(['search.php', 'archive.php', 'index.php'])) {
    return;
}

get_header();

tmw_render_sidebar_layout('search-results', function () {
    ?>
      <?php if (have_posts()) : ?>
        <header class="page-header">
          <h1 class="page-title"><?php printf(esc_html__('Search Results for: %s', 'retrotube-child'), '<span>' . esc_html(get_search_query()) . '</span>'); ?></h1>
        </header>

        <?php while (have_posts()) : the_post(); ?>
          <?php get_template_part('template-parts/content', get_post_type()); ?>
        <?php endwhile; ?>

        <?php the_posts_navigation(); ?>
      <?php else : ?>
        <?php get_template_part('template-parts/content', 'none'); ?>
      <?php endif; ?>
    <?php
});

get_footer();
