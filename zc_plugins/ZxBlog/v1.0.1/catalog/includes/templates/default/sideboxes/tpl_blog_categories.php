<?php

declare(strict_types=1);

$content = '<div id="' . str_replace('_', '-', $box_id . 'Content') . '" class="sideBoxContent">';
$content .= '<ul class="list-links zx-blog-categories-list">';
$content .= '<li><a href="' . zen_href_link(FILENAME_ZX_BLOG) . '">' . zen_output_string_protected(TEXT_BLOG_ALL_POSTS) . '</a></li>';

foreach ($blogCategoriesSidebox as $catRow) {
    $link = zen_href_link(FILENAME_ZX_BLOG, 'bPath=' . (int)$catRow['category_id']);
    $content .= '<li><a href="' . $link . '">' . zen_output_string_protected($catRow['category_name']) . ' (' . (int)$catRow['post_count'] . ')</a></li>';
}

$content .= '</ul></div>';