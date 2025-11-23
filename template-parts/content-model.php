<?php
$model_id   = get_the_ID();
$model_name = get_the_title();
$tmw_debug_enabled = defined('TMW_DEBUG') && TMW_DEBUG;

if ($tmw_debug_enabled) {
        error_log('[TMW-MODEL-AUDIT] template-parts/content-model.php loaded for ' . $model_name);
}

$banner_url      = tmw_resolve_model_banner_url( $model_id );

$cta_url   = function_exists( 'get_field' ) ? get_field( 'model_link', $model_id ) : '';
$cta_label = function_exists( 'get_field' ) ? get_field( 'model_link_label', $model_id ) : '';
$cta_note  = function_exists( 'get_field' ) ? get_field( 'model_link_note', $model_id ) : '';

if ( empty( $cta_label ) ) {
        $cta_label = __( 'Watch Live', 'retrotube' );
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?> itemprop="performer" itemscope itemtype="http://schema.org/Person">
        <header class="entry-header">

                <div class="video-player box-shadow model-banner">
                        <?php
                        if ( defined( 'TMW_BANNER_DEBUG' ) && TMW_BANNER_DEBUG ) {
                                echo "\n<!-- TMW Banner URL: " . esc_html( $banner_url ? $banner_url : 'EMPTY' ) . " -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                        <?php if ( ! tmw_render_model_banner( $model_id, 'frontend' ) ) : ?>
                                <div class="tmw-banner-container">
                                        <div class="tmw-banner-frame frontend">
                                                <div class="no-banner-placeholder">
                                                        <p><?php esc_html_e( 'No banner image uploaded yet.', 'retrotube' ); ?></p>
                                                </div>
                                        </div>
                                </div>
                        <?php endif; ?>

                        <?php if ( $cta_url ) : ?>
                                <a class="button model-cta" id="model-cta" href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="nofollow noopener">
                                        <i class="fa fa-video-camera"></i>
                                        <?php echo esc_html( $cta_label ); ?>
                                </a>
                        <?php endif; ?>

                        <?php if ( $cta_note ) : ?>
                                <p class="model-cta-note"><?php echo wp_kses_post( $cta_note ); ?></p>
                        <?php endif; ?>
                </div>

                <div class="title-block box-shadow">
                        <div class="tmw-model-header">
                                <?php the_title( '<h1 class="entry-title model-name" itemprop="name">', '</h1>' ); ?>
                                <?php if ( xbox_get_field_value( 'wpst-options', 'enable-rating-system' ) == 'on' ) : ?>
                                        <div class="tmw-model-actions">
                                                <span id="video-rate"><?php echo wpst_get_post_like_link( get_the_ID() ); ?></span>
                                        </div>
                                <?php endif; ?>
                        </div>
                        <div id="video-tabs" class="tabs">
                                <button class="tab-link active about" data-tab-id="video-about">
                                        <i class="fa fa-info-circle"></i> <?php esc_html_e( 'About', 'wpst' ); ?>
                                </button>
                                <?php if ( xbox_get_field_value( 'wpst-options', 'enable-video-share' ) == 'on' ) : ?>
                                        <button class="tab-link share" data-tab-id="video-share">
                                                <i class="fa fa-share"></i> <?php esc_html_e( 'Share', 'wpst' ); ?>
                                        </button>
                                <?php endif; ?>
                        </div>
                </div>

                               <div class="clear"></div>


        </header><!-- .entry-header -->

        <div class="entry-content">
                <div class="tab-content">
                        <div id="video-about" class="width100">
                                <div class="video-description">
                                        <?php if ( xbox_get_field_value( 'wpst-options', 'show-description-video-about' ) == 'on' ) : ?>
                                                <div class="desc <?php echo ( xbox_get_field_value( 'wpst-options', 'truncate-description' ) == 'on' ) ? 'more' : ''; ?>">
                                                        <?php the_content(); ?>
                                                </div>
                                        <?php endif; ?>
                                </div>

                                <?php
                                // [TMW-SLOT] v4.1.2 — Render slot machine under description (inside #video-about)
                                $__exists = shortcode_exists('tmw_slot_machine') ? 'yes' : 'no';
                                $__out    = do_shortcode('[tmw_slot_machine]');
                                $__len    = strlen( trim( wp_strip_all_tags( $__out ) ) );
                                if ($tmw_debug_enabled) {
                                        error_log(
                                                '[TMW-SLOT-AUDIT] under #video-about model="' . get_the_title() . '" ' .
                                                'post_type=' . get_post_type() . ' shortcode_exists=' . $__exists . ' output_len=' . $__len
                                        );
                                }
                                ?>
                                <div class="tmw-slot-banner" style="margin:15px 0 20px;text-align:center;">
                                        <?php if ($__len === 0): ?>
                                                <!-- [TMW-SLOT-AUDIT] shortcode returned empty -->
                                                <div class="tmw-slot-audit-placeholder"
                                                     style="display:inline-block;padding:8px 12px;border:1px dashed #db011a;color:#db011a;border-radius:4px;">
                                                        [Slot shortcode returned empty]
                                                </div>
                                        <?php else: ?>
                                                <?php echo $__out; ?>
                                        <?php endif; ?>
                                </div>

                                <?php if ( xbox_get_field_value( 'wpst-options', 'show-categories-video-about' ) == 'on' || xbox_get_field_value( 'wpst-options', 'show-tags-video-about' ) == 'on' ) : ?>
                                        <div class="tags"><?php wpst_entry_footer(); ?></div>
                                <?php endif; ?>
                        </div>

                        <?php
                        $model_slug = get_post_field('post_name', get_the_ID());
                        if (!is_string($model_slug) || $model_slug === '') {
                                if ($tmw_debug_enabled) {
                                        error_log('[TMW-MODEL-AUDIT] Unable to determine model slug in content-model.php');
                                }
                        } elseif (function_exists('tmw_get_videos_for_model')) {
                                $videos = tmw_get_videos_for_model($model_slug);
                                if (!is_array($videos)) {
                                        $video_count = 0;
                                        if ($tmw_debug_enabled) {
                                                error_log('[TMW-MODEL-AUDIT] Unexpected result from tmw_get_videos_for_model for ' . $model_slug);
                                        }
                                } else {
                                        $video_count = count($videos);
                                }
                                set_query_var('tmw_model_videos', $videos);
                                if ($tmw_debug_enabled) {
                                        error_log('[TMW-MODEL-AUDIT] ' . $model_slug . ' video count: ' . $video_count);
                                }
                        } else {
                                if ($tmw_debug_enabled) {
                                        error_log('[TMW-MODEL-AUDIT] tmw_get_videos_for_model unavailable in content-model.php');
                                }
                        }

                        $tmw_model_tags_count = get_query_var('tmw_model_tags_count', null);
                        $tmw_model_tags       = get_query_var('tmw_model_tags_data', []);
                        ?>

                        <?php if ( $tmw_model_tags_count !== null ) : ?>
                                <!-- === TMW-TAGS-BULLETPROOF-RESTORE === -->
                                <div class="post-tags entry-tags tmw-model-tags<?php echo $tmw_model_tags_count === 0 ? ' no-tags' : ''; ?>">
                                        <span class="tag-title">
                                                <i class="fa fa-tags" aria-hidden="true"></i>
                                                <?php
                                                echo $tmw_model_tags_count === 0
                                                        ? esc_html__('(No tags linked — audit mode)', 'retrotube')
                                                        : esc_html__('Tags:', 'retrotube');
                                                ?>
                                        </span>
                                        <?php if ($tmw_model_tags_count > 0 && is_array($tmw_model_tags)) : ?>
                                                <?php foreach ($tmw_model_tags as $tag) : ?>
                                                        <a href="<?php echo get_tag_link( $tag->term_id ); ?>"
                                                                class="label"
                                                                title="<?php echo esc_attr( $tag->name ); ?>">
                                                                <i class="fa fa-tag"></i><?php echo esc_html( $tag->name ); ?>
                                                        </a>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                </div>
                                <!-- === END TMW-TAGS-BULLETPROOF-RESTORE === -->
                                <?php endif; ?>

                        <?php get_template_part( 'template-parts/model-videos' ); ?>

                        <?php if ( xbox_get_field_value( 'wpst-options', 'enable-video-share' ) == 'on' ) : ?>
                                <?php get_template_part( 'template-parts/content', 'share-buttons' ); ?>
                        <?php endif; ?>
                </div>
        </div><!-- .entry-content -->

        <?php if ( xbox_get_field_value( 'wpst-options', 'display-related-videos' ) == 'on' ) : ?>
                <?php get_template_part( 'template-parts/content', 'related' ); ?>
        <?php endif; ?>

        <?php
        if ( xbox_get_field_value( 'wpst-options', 'enable-comments' ) == 'on' ) {
                if ( comments_open() || get_comments_number() ) :
                        comments_template();
                endif;
        }
        ?>
</article><!-- #post-## -->
