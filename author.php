<?php
/**
 * Author archive template override for Retrotube Child theme.
 */

if (tmw_try_parent_template(['author.php', 'archive.php', 'index.php'])) {
    return;
}

get_header();

tmw_render_sidebar_layout('author-archive', function () {
    ?>
      <?php if (have_posts()) : ?>
        <header class="page-header">
          <h1 class="page-title"><?php the_archive_title(); ?></h1>
          <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
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
