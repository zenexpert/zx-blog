<?php

declare(strict_types=1);

if (!defined('ZX_BLOG_STATUS') || ZX_BLOG_STATUS !== 'true') {
    return;
}

$sql = "SELECT c.category_id, cd.category_name, COUNT(p.post_id) AS post_count
        FROM " . TABLE_ZX_BLOG_CATEGORIES . " c
        INNER JOIN " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . " cd
          ON c.category_id = cd.category_id AND cd.language_id = " . (int)$_SESSION['languages_id'] . "
        LEFT JOIN " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " p2c ON c.category_id = p2c.category_id
        LEFT JOIN " . TABLE_ZX_BLOG_POSTS . " p
          ON p2c.post_id = p.post_id AND p.status = 1 AND (p.date_scheduled IS NULL OR p.date_scheduled <= NOW())
        WHERE c.status = 1
        GROUP BY c.category_id, cd.category_name, c.sort_order
        ORDER BY c.sort_order, cd.category_name";

$blogCategoriesSidebox = $db->Execute($sql);

if ($blogCategoriesSidebox->RecordCount() > 0) {
    require $template->get_template_dir('tpl_blog_categories.php', DIR_WS_TEMPLATE, $current_page_base, 'sideboxes') . '/tpl_blog_categories.php';
    $title = BOX_HEADING_BLOG_CATEGORIES;
    $title_link = FILENAME_ZX_BLOG;
    require $template->get_template_dir($column_box_default, DIR_WS_TEMPLATE, $current_page_base, 'common') . '/' . $column_box_default;
}