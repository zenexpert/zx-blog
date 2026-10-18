<?php

declare(strict_types=1);

if (!defined('ZX_BLOG_STATUS') || ZX_BLOG_STATUS !== 'true') {
    return;
}

$limit = defined('ZX_BLOG_TAGS_SIDEBOX_LIMIT') ? max(1, (int)ZX_BLOG_TAGS_SIDEBOX_LIMIT) : 20;

$sql = "SELECT t.tag_name, t.tag_slug, COUNT(p2t.post_id) AS tag_usage
        FROM " . TABLE_ZX_BLOG_TAGS . " t
        INNER JOIN " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " p2t ON t.tag_id = p2t.tag_id
        INNER JOIN " . TABLE_ZX_BLOG_POSTS . " p
          ON p2t.post_id = p.post_id AND p.status = 1 AND (p.date_scheduled IS NULL OR p.date_scheduled <= NOW())
        WHERE t.language_id = " . (int)$_SESSION['languages_id'] . "
        GROUP BY t.tag_id, t.tag_name, t.tag_slug
        ORDER BY tag_usage DESC, t.tag_name ASC
        LIMIT " . $limit;

$blogTagsSidebox = $db->Execute($sql);

if ($blogTagsSidebox->RecordCount() > 0) {
    require $template->get_template_dir('tpl_blog_tags.php', DIR_WS_TEMPLATE, $current_page_base, 'sideboxes') . '/tpl_blog_tags.php';
    $title = BOX_HEADING_BLOG_TAGS;
    $title_link = false;
    require $template->get_template_dir($column_box_default, DIR_WS_TEMPLATE, $current_page_base, 'common') . '/' . $column_box_default;
}