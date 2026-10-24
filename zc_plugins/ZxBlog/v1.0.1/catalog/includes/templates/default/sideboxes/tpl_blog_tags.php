<?php

declare(strict_types=1);

$content = '<div id="' . str_replace('_', '-', $box_id . 'Content') . '" class="sideBoxContent zx-blog-tags-cloud">';

foreach ($blogTagsSidebox as $tagRow) {
    $link = zen_href_link(FILENAME_ZX_BLOG, 'tag=' . urlencode($tagRow['tag_slug']));
    $content .= '<a class="zx-blog-tag-badge" href="' . $link . '">'
        . zen_output_string_protected($tagRow['tag_name'])
        . '</a> ';
}

$content .= '</div>';