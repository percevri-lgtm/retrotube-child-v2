<?php
/**
 * Template Name: Videos Page
 */
get_header();

$filter   = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : '';
$instance = array();

if ( $filter ) {
    if ( 'latest' === $filter ) {
        $instance = array(
            'title'          => __( 'Latest videos', 'retrotube-child' ),
            'video_type'     => 'latest',
            'video_number'   => 12,
            'video_category' => 0,
        );
    } elseif ( 'random' === $filter ) {
        $instance = array(
            'title'          => __( 'Random videos', 'retrotube-child' ),
            'video_type'     => 'random',
            'video_number'   => 12,
            'video_category' => 0,
        );
    } elseif ( 'longest' === $filter ) {
        $instance = array(
            'title'          => __( 'Longest videos', 'retrotube-child' ),
            'video_type'     => 'longest',
            'video_number'   => 12,
            'video_category' => 0,
        );
    } elseif ( 'popular' === $filter ) {
        $instance = array(
            'title'          => __( 'Most popular videos', 'retrotube-child' ),
            'video_type'     => 'popular',
            'video_number'   => 12,
            'video_category' => 0,
        );
    } elseif ( is_numeric( $filter ) ) {
        $category_id = absint( $filter );
        $term        = get_term( $category_id, 'category' );

        if ( $term && ! is_wp_error( $term ) ) {
            /* translators: %s: video category name. */
            $title     = sprintf( __( '%s videos', 'retrotube-child' ), $term->name );
            $instance  = array(
                'title'          => $title,
                'video_type'     => 'category',
                'video_number'   => 12,
                'video_category' => $category_id,
            );
        }
    }
}

tmw_render_sidebar_layout('', function () use ( $filter, $instance ) {
    ?>
      <header class="entry-header">
        <h1 class="entry-title"><i class="fa fa-video-camera"></i> Videos</h1>
      </header>

      <?php if ( $filter ) : ?>

        <?php if ( ! empty( $instance ) ) : ?>
          <?php
          the_widget(
              'wpst_WP_Widget_Videos_Block',
              $instance,
              array(
                  'before_widget' => '<section class="widget widget_videos_block">',
                  'after_widget'  => '</section>',
                  'before_title'  => '<h2 class="widget-title">',
                  'after_title'   => '</h2>',
              )
          );
          ?>
        <?php else : ?>
          <p><?php esc_html_e( 'No videos found for this filter.', 'retrotube-child' ); ?></p>
        <?php endif; ?>

      <?php elseif ( is_page( 'videos' ) ) : ?>

        <?php
        the_widget(
            'wpst_WP_Widget_Videos_Block',
            array(
                'title'          => 'Videos being watched',
                'video_type'     => 'random',
                'video_number'   => 8,
                'video_category' => 0,
            ),
            array(
                'before_widget' => '<section class="widget widget_videos_block">',
                'after_widget'  => '</section>',
                'before_title'  => '<h2 class="widget-title">',
                'after_title'   => '</h2>',
            )
        );

        the_widget(
            'wpst_WP_Widget_Videos_Block',
            array(
                'title'          => 'Latest videos',
                'video_type'     => 'latest',
                'video_number'   => 6,
                'video_category' => 0,
            ),
            array(
                'before_widget' => '<section class="widget widget_videos_block">',
                'after_widget'  => '</section>',
                'before_title'  => '<h2 class="widget-title">',
                'after_title'   => '</h2>',
            )
        );

        the_widget(
            'wpst_WP_Widget_Videos_Block',
            array(
                'title'          => 'Longest videos',
                'video_type'     => 'longest',
                'video_number'   => 12,
                'video_category' => 0,
            ),
            array(
                'before_widget' => '<section class="widget widget_videos_block">',
                'after_widget'  => '</section>',
                'before_title'  => '<h2 class="widget-title">',
                'after_title'   => '</h2>',
            )
        );
        ?>

      <?php endif; ?>
    <?php
});

get_footer();
