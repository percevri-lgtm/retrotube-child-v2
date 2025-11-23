<?php
/**
 * Template Name: Single Model
 * Description: Displays single model banner and related videos.
 */

$tmw_debug_enabled = defined('TMW_DEBUG') && TMW_DEBUG;

if ($tmw_debug_enabled) {
  error_log('[TMW-MODEL] single-model.php loaded for ' . get_the_title());
}

// Keep tags area visible (audit mode) so we can verify.
$tmw_tags_audit_css = '.tmw-model-tags{display:block!important;visibility:visible!important;opacity:1!important}'
    . '.tmw-model-tags:empty::before{content:"(No tags linked — audit mode)";color:#999;font-size:12px;}';
wp_add_inline_style('retrotube-child-style', $tmw_tags_audit_css);

get_header(); ?>

<div id="primary" class="content-area with-sidebar-right">
  <main id="main" class="site-main with-sidebar-right" role="main">
    <?php
    if ( have_posts() ) :
      while ( have_posts() ) :
        the_post();

        // === Video & Tag Resolution ===
        $post_id    = get_the_ID();
        $model_slug = get_post_field( 'post_name', $post_id );
        $video_tags = array();

        if ( function_exists( 'tmw_get_videos_for_model' ) ) {
          $videos = tmw_get_videos_for_model( $model_slug, -1 );
          $video_count = is_array( $videos ) ? count( $videos ) : 0;
          if ( $tmw_debug_enabled ) {
            error_log( '[TMW-MODEL] Found ' . $video_count . ' videos for model: ' . get_the_title() );
          }

          if ( ! empty( $videos ) ) {
            foreach ( $videos as $video_post ) {
              $tags_for_video = wp_get_post_terms( $video_post->ID, 'post_tag' );
              if ( ! is_wp_error( $tags_for_video ) && ! empty( $tags_for_video ) ) {
                foreach ( $tags_for_video as $tag_term ) {
                  $video_tags[ $tag_term->term_id ] = $tag_term;
                }
              }
            }
          }
        }

        $tag_count = count( $video_tags );
        if ( $tmw_debug_enabled ) {
          error_log( '[TMW-MODEL] Found ' . $tag_count . ' tags for model: ' . get_the_title() );
        }

        if ( $tag_count > 0 ) {
          uasort( $video_tags, static function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
          } );
        }

        set_query_var( 'tmw_model_tags_data', array_values( $video_tags ) );
        set_query_var( 'tmw_model_tags_count', $tag_count );
        if ( $tmw_debug_enabled ) {
          error_log( '[TMW-MODEL-TAGS-AUDIT] Model tags fully synchronized with video tags (v3.3.1).' );
        }

        // Render the model content template.
        get_template_part( 'template-parts/content', 'model' );

        // Cleanup query vars.
        set_query_var( 'tmw_model_tags_data', array() );
        set_query_var( 'tmw_model_tags_count', null );

      endwhile;
    endif;
    ?>
  </main>
</div>

<?php get_sidebar(); ?>

<?php
// Removed side-wide injected comments block to prevent duplicate forms.
// The normal comment form (if any) should be rendered by content-model.php / theme.
get_footer();
