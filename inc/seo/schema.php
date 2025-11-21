<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_schema_itemlist')) {
    /**
     * Output lightweight ItemList schema for flipbox archives/grids.
     *
     * @param array<int, array<string, string>> $items
     */
    function tmw_schema_itemlist(array $items): void {
        if (empty($items)) {
            return;
        }

        $elements = [];
        $position = 1;

        foreach ($items as $item) {
            $url  = isset($item['url']) ? trim((string) $item['url']) : '';
            $name = isset($item['name']) ? trim((string) $item['name']) : '';

            if ($url === '' || $name === '') {
                continue;
            }

            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'url'      => $url,
                'name'     => $name,
            ];
        }

        if (empty($elements)) {
            return;
        }

        $data = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'itemListElement' => $elements,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}
