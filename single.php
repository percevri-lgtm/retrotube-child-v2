<?php
/**
 * Single post template override for Retrotube Child theme.
 */

if (defined('TMW_DEBUG') && TMW_DEBUG) {
    error_log('[TMW-MODEL-AUDIT] single.php loaded for ' . get_the_title());
}

$parent_template = '';
$parent_dir      = trailingslashit(get_template_directory());

foreach (['single.php', 'singular.php', 'index.php'] as $candidate) {
    $path = $parent_dir . $candidate;
    if (file_exists($path)) {
        $parent_template = $path;
        break;
    }
}

if ($parent_template) {
    ob_start();
    include $parent_template;
    $parent_output = ob_get_clean();

    if ($parent_output !== false) {
        echo $parent_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }
}

get_header();
?>
<div id="content" class="site-content row">
  <div id="primary" class="content-area with-sidebar-right single-post">
    <main id="main" class="site-main with-sidebar-right" role="main">
      <?php while (have_posts()) : the_post(); ?>
        <?php get_template_part('template-parts/content', get_post_type()); ?>
        <?php the_post_navigation(); ?>

        <?php if (comments_open() || get_comments_number()) : ?>
          <?php comments_template(); ?>
        <?php endif; ?>
      <?php endwhile; ?>
    </main>
  </div>
  <aside id="sidebar" class="widget-area with-sidebar-right" role="complementary">
    <?php get_sidebar(); ?>
  </aside>
</div>
<?php
get_footer();
