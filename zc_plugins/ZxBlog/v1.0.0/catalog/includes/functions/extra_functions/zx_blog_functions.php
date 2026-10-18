<?php

declare(strict_types=1);

function zx_blog_reading_time(string $htmlContent): int
{
    $wordCount = str_word_count(strip_tags($htmlContent));
    return max(1, (int)ceil($wordCount / 200));
}

function zx_blog_get_post_categories(int $postId, int $languageId): array
{
    global $db;

    $sql = "SELECT c.category_id, cd.category_name
            FROM " . TABLE_ZX_BLOG_CATEGORIES . " c
            INNER JOIN " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " p2c ON c.category_id = p2c.category_id
            LEFT JOIN " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . " cd
              ON c.category_id = cd.category_id AND cd.language_id = :langId:
            WHERE p2c.post_id = :postId: AND c.status = 1
            ORDER BY c.sort_order, cd.category_name";
    $sql = $db->bindVars($sql, ':langId:', $languageId, 'integer');
    $sql = $db->bindVars($sql, ':postId:', $postId, 'integer');

    $results = $db->Execute($sql);
    $categories = [];
    foreach ($results as $row) {
        $categories[] = [
            'id' => (int)$row['category_id'],
            'name' => $row['category_name'] ?? '',
        ];
    }
    return $categories;
}

function zx_blog_get_post_tags(int $postId, int $languageId): array
{
    global $db;

    $sql = "SELECT t.tag_id, t.tag_name, t.tag_slug
            FROM " . TABLE_ZX_BLOG_TAGS . " t
            INNER JOIN " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " p2t ON t.tag_id = p2t.tag_id
            WHERE p2t.post_id = :postId: AND t.language_id = :langId:
            ORDER BY t.tag_name";
    $sql = $db->bindVars($sql, ':postId:', $postId, 'integer');
    $sql = $db->bindVars($sql, ':langId:', $languageId, 'integer');

    $results = $db->Execute($sql);
    $tags = [];
    foreach ($results as $row) {
        $tags[] = [
            'id' => (int)$row['tag_id'],
            'name' => $row['tag_name'],
            'slug' => $row['tag_slug'],
        ];
    }
    return $tags;
}