<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_flipbox_a11y_attrs')) {
    /**
     * Build standardized accessibility attributes for flipbox wrappers.
     *
     * @param array $opts {
     *   Optional options to override defaults.
     *
     *   @type string     $role                ARIA role for the wrapper.
     *   @type string     $aria_label          Accessible label for the card.
     *   @type int|null   $tabindex            Tab index applied to the wrapper.
     *   @type string     $aria_roledescription Natural language description for assistive tech.
     * }
     *
     * @return array<string>
     */
    function tmw_flipbox_a11y_attrs(array $opts = []): array {
        $defaults = [
            'role'                 => 'group',
            'aria_label'           => '',
            'tabindex'             => null,
            'aria_roledescription' => 'flip card',
        ];

        $o = array_merge($defaults, $opts);
        $attrs = [];

        $attrs[] = 'role="' . esc_attr($o['role']) . '"';

        if ($o['aria_label'] !== '') {
            $attrs[] = 'aria-label="' . esc_attr($o['aria_label']) . '"';
        }

        $attrs[] = 'aria-roledescription="' . esc_attr($o['aria_roledescription']) . '"';

        if (!is_null($o['tabindex'])) {
            $attrs[] = 'tabindex="' . intval($o['tabindex']) . '"';
        }

        return $attrs;
    }
}

if (!function_exists('tmw_sr_text')) {
    /**
     * Wrap a string in the shared screen-reader only span.
     */
    function tmw_sr_text($text) {
        return '<span class="tmw-sr-only">' . esc_html($text) . '</span>';
    }
}
