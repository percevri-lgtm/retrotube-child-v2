<?php
/**
 * Breadcrumb override for the RetroTube child theme.
 *
 * Ensures Rank Math breadcrumbs replace the default theme breadcrumbs
 * for all Model related templates.
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_model_context = is_singular('model') || is_post_type_archive('model') || is_tax('models');

if (!$is_model_context) {
    $parent_template = trailingslashit(get_template_directory()) . 'template-parts/breadcrumbs.php';
    if (is_readable($parent_template)) {
        load_template($parent_template, false);
    }
    return;
}

$breadcrumb_html = '';

if (function_exists('rank_math_get_breadcrumbs')) {
    $breadcrumb_html = rank_math_get_breadcrumbs([
        'wrap_before' => '',
        'wrap_after'  => '',
        'separator'   => '<span class="separator"><i class="fa fa-caret-right"></i></span>',
    ]);
} elseif (function_exists('rank_math_the_breadcrumbs')) {
    ob_start();
    rank_math_the_breadcrumbs();
    $breadcrumb_html = trim(ob_get_clean());
}

if ($breadcrumb_html === '') {
    if (function_exists('tmw_render_models_breadcrumbs')) {
        $current_label = '';
        if (is_singular('model')) {
            $current_label = single_post_title('', false);
        } elseif (is_tax('models')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $current_label = $term->name;
            }
        }

        tmw_render_models_breadcrumbs([
            'current'      => $current_label,
            'show_current' => !is_post_type_archive('model'),
            'echo'         => true,
        ]);
    }
    return;
}
?>
<div id="breadcrumbs" class="rank-math-breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
    <?php echo $breadcrumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
