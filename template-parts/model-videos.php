<?php
/**
 * Template Part: Model Videos Section — Hybrid Scan Query
 */

if (!defined('ABSPATH')) {
    exit;
}

global $post;

if (!$post instanceof WP_Post) {
    return;
}

$model_name = get_the_title($post->ID);
$model_slug = get_post_field('post_name', $post->ID);

if (empty($model_slug)) {
    return;
}

$videos = get_query_var('tmw_model_videos', null);

if (!is_array($videos)) {
    $videos = tmw_get_videos_for_model($model_slug);
}

$video_count = is_array($videos) ? count($videos) : 0;

if ($video_count === 0) {
    return;
}

$original_post = $post;
?>
<section class="tmw-model-videos widget widget_videos_block">
  <h3 class="tmw-section-header widget-title"><?php esc_html_e('Videos Featuring', 'retrotube'); ?> <?php echo esc_html($model_name); ?></h3>
  <div class="video-grid tmw-model-video-grid">
    <?php
    foreach ($videos as $video_post) :
      if (!$video_post instanceof WP_Post) {
        continue;
      }

      $post = $video_post;
      setup_postdata($post);

      $permalink = get_permalink($post);
      $title     = get_the_title($post);
      $title     = is_string($title) ? trim($title) : '';
      if ($title === '' || preg_match('/^\d+$/', $title)) {
        $title = sprintf(
          /* translators: %s: model name. */
          __('Video featuring %s', 'retrotube'),
          $model_name
        );
      }
      $video_id  = get_the_ID();
      $thumb     = get_the_post_thumbnail($post, 'wpst_thumb_large', [
        'class' => 'video-thumb-img',
        'alt'   => $title,
      ]);

      if (empty($thumb)) {
        $thumb_meta = get_post_meta($post->ID, 'thumb', true);
        if (is_string($thumb_meta) && $thumb_meta !== '') {
          $thumb = sprintf(
            '<img class="video-thumb-img" src="%s" alt="%s" loading="lazy" />',
            esc_url($thumb_meta),
            esc_attr($title)
          );
        }
      }

      $duration = get_post_meta($post->ID, 'duration', true);
      $duration = is_string($duration) ? trim($duration) : '';

      ?>
      <article id="post-<?php echo esc_attr($video_id); ?>" <?php post_class('video-item tmw-model-video-item'); ?>>
        <a class="video-thumb" href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
          <?php
          if (!empty($thumb)) {
            echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          }
          ?>
          <?php if ($duration !== '') : ?>
            <span class="video-duration"><?php echo esc_html($duration); ?></span>
          <?php endif; ?>
        </a>
        <h4 class="video-title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h4>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php
wp_reset_postdata();

if ($original_post instanceof WP_Post) {
    $post = $original_post;
}
?>
