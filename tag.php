<?php
/**
 * Tag archive template override for Retrotube Child theme.
 *
 * Delegates rendering to the parent template; the Featured Models block is now injected globally.
 */

if (tmw_try_parent_template(['tag.php', 'archive.php', 'index.php'])) {
    return;
}

get_header();

tmw_render_sidebar_layout('tag-archive', function () {
    ?>
      <?php if (have_posts()) : ?>
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
