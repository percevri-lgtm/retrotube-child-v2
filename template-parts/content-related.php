<?php
/**
 * Child override: render parent related-videos markup but remove "Show more" button on model pages.
 */

ob_start();

// Run the parent related template normally
get_template_part('template-parts/related');

$related_markup = ob_get_clean();

// Remove the button if this is a model page
if ( is_singular('model') ) {
    $related_markup = preg_replace(
        '#<div class="show-more">.*?</div>#is',
        '',
        $related_markup
    );
}

echo $related_markup;
