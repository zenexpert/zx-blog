<?php

declare(strict_types=1);

if (!defined('ZX_BLOG_STATUS') || ZX_BLOG_STATUS !== 'true') {
    return;
}

$limit = defined('ZX_BLOG_RECENT_SIDEBOX_LIMIT') ? max(1, (int)ZX_BLOG_RECENT_SIDEBOX_LIMIT) : 5;

$sql = "SELECT p.post_id, p.featured_image, COALESCE(p.date_scheduled, p.date_added) AS publish_date, pd.post_title
        FROM " . TABLE_ZX_BLOG_POSTS . " p
        INNER JOIN " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . " pd
          ON p.post_id = pd.post_id AND pd.language_id = " . (int)$_SESSION['languages_id'] . "
        WHERE p.status = 1
          AND (p.date_scheduled IS NULL OR p.date_scheduled <= NOW())
        ORDER BY publish_date DESC
        LIMIT " . $limit;

$blogRecentSidebox = $db->Execute($sql);

if ($blogRecentSidebox->RecordCount() > 0) {
    require $template->get_template_dir('tpl_blog_recent_posts.php', DIR_WS_TEMPLATE, $current_page_base, 'sideboxes') . '/tpl_blog_recent_posts.php';
    $title = BOX_HEADING_BLOG_RECENT_POSTS;
    $title_link = FILENAME_ZX_BLOG;
    require $template->get_template_dir($column_box_default, DIR_WS_TEMPLATE, $current_page_base, 'common') . '/' . $column_box_default;
}