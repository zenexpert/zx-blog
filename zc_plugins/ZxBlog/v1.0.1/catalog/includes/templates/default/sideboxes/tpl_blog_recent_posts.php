<?php

declare(strict_types=1);

$content = '<div id="' . str_replace('_', '-', $box_id . 'Content') . '" class="sideBoxContent">';
$content .= '<ul class="list-links zx-blog-recent-list">';

foreach ($blogRecentSidebox as $recentRow) {
    $link = zen_href_link(FILENAME_ZX_BLOG, 'post_id=' . (int)$recentRow['post_id']);
    $content .= '<li class="zx-blog-recent-item">';
    $content .= '<a href="' . $link . '">' . zen_output_string_protected($recentRow['post_title']) . '</a>';
    $content .= '<small class="zx-blog-recent-date" style="display:block;">' . zen_date_short($recentRow['publish_date']) . '</small>';
    $content .= '</li>';
}

$content .= '</ul></div>';