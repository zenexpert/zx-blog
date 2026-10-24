<?php

declare(strict_types=1);

$zco_notifier->notify('NOTIFY_HEADER_START_ZX_BLOG');

if (!defined('ZX_BLOG_STATUS') || ZX_BLOG_STATUS !== 'true') {
    zen_redirect(zen_href_link(FILENAME_DEFAULT));
}

require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

$languageId = (int)$_SESSION['languages_id'];
$postId = max(0, (int)($_GET['post_id'] ?? 0));
$categoryId = max(0, (int)($_GET['bPath'] ?? 0));

// Strict sanitization for tag slug and search keyword
$tagSlug = substr(preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['tag'] ?? '')) ?? '', 0, 80);
$rawSearch = trim(strip_tags((string)($_GET['bsearch'] ?? '')));
$rawSearch = preg_replace('/[\x00-\x1F\x7F]/u', '', $rawSearch) ?? '';
$searchKeyword = function_exists('mb_substr') ? mb_substr($rawSearch, 0, 100) : substr($rawSearch, 0, 100);
$_GET['bsearch'] = $searchKeyword;

$breadcrumb->add(NAVBAR_TITLE, zen_href_link(FILENAME_ZX_BLOG));

$isSinglePost = false;
$blogPost = null;
$blogPostCategories = [];
$blogPostTags = [];
$blogRelatedProducts = [];
$blogListingHeading = HEADING_TITLE;
$blogCategoryDescription = '';

if ($postId > 0) {
    $postSql = "SELECT p.*, pd.post_title, pd.post_slug, pd.post_summary, pd.post_content,
                       pd.meta_title, pd.meta_description,
                       a.author_name, a.author_bio, a.avatar_image
                FROM " . TABLE_ZX_BLOG_POSTS . " p
                INNER JOIN " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . " pd
                  ON p.post_id = pd.post_id AND pd.language_id = :langId:
                LEFT JOIN " . TABLE_ZX_BLOG_AVATARS . " a
                  ON p.avatar_id = a.avatar_id
                WHERE p.post_id = :postId:
                  AND p.status = 1
                  AND (p.date_scheduled IS NULL OR p.date_scheduled <= NOW())
                LIMIT 1";
    $postSql = $db->bindVars($postSql, ':langId:', $languageId, 'integer');
    $postSql = $db->bindVars($postSql, ':postId:', $postId, 'integer');
    $postResult = $db->Execute($postSql);

    if (!$postResult->EOF) {
        $isSinglePost = true;
        $blogPost = $postResult->fields;
        $blogPost['reading_time'] = zx_blog_reading_time($blogPost['post_content'] ?? '');

        $db->Execute(
            "UPDATE " . TABLE_ZX_BLOG_POSTS . "
             SET views_count = views_count + 1
             WHERE post_id = " . $postId
        );

        $blogPostCategories = zx_blog_get_post_categories($postId, $languageId);
        $blogPostTags = zx_blog_get_post_tags($postId, $languageId);

        $relSql = "SELECT p.products_id, p.products_image, pd.products_name
                   FROM " . TABLE_ZX_BLOG_POSTS_TO_PRODUCTS . " p2p
                   INNER JOIN " . TABLE_PRODUCTS . " p ON p2p.products_id = p.products_id
                   INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                     ON p.products_id = pd.products_id AND pd.language_id = :langId:
                   WHERE p2p.post_id = :postId: AND p.products_status = 1";
        $relSql = $db->bindVars($relSql, ':langId:', $languageId, 'integer');
        $relSql = $db->bindVars($relSql, ':postId:', $postId, 'integer');
        $relResults = $db->Execute($relSql);
        foreach ($relResults as $relRow) {
            $blogRelatedProducts[] = $relRow;
        }

        $breadcrumb->add($blogPost['post_title'], zen_href_link(FILENAME_ZX_BLOG, 'post_id=' . $postId));
        $canonicalLink = zen_href_link(FILENAME_ZX_BLOG, 'post_id=' . $postId, 'NONSSL', false);

        if (!empty($blogPost['meta_title'])) {
            define('META_TAG_TITLE', zen_output_string_protected($blogPost['meta_title']));
        }
        if (!empty($blogPost['meta_description'])) {
            define('META_TAG_DESCRIPTION', zen_output_string_protected($blogPost['meta_description']));
        }
    }
}

if (!$isSinglePost) {
    $joins = '';
    $where = " WHERE p.status = 1 AND (p.date_scheduled IS NULL OR p.date_scheduled <= NOW()) ";

    if ($categoryId > 0) {
        $joins .= " INNER JOIN " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " p2c ON p.post_id = p2c.post_id ";
        $where .= $db->bindVars(" AND p2c.category_id = :catId: ", ':catId:', $categoryId, 'integer');

        $catSql = "SELECT category_name, category_description
                   FROM " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . "
                   WHERE category_id = :catId: AND language_id = :langId:";
        $catSql = $db->bindVars($catSql, ':catId:', $categoryId, 'integer');
        $catSql = $db->bindVars($catSql, ':langId:', $languageId, 'integer');
        $catInfo = $db->Execute($catSql);
        if (!$catInfo->EOF) {
            $blogListingHeading = $catInfo->fields['category_name'];
            $blogCategoryDescription = $catInfo->fields['category_description'] ?? '';
            $breadcrumb->add($blogListingHeading, zen_href_link(FILENAME_ZX_BLOG, 'bPath=' . $categoryId));
        }
    }

    if ($tagSlug !== '') {
        $joins .= " INNER JOIN " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " p2t ON p.post_id = p2t.post_id
                    INNER JOIN " . TABLE_ZX_BLOG_TAGS . " t ON p2t.tag_id = t.tag_id ";
        $tagClause = " AND t.language_id = :langId: AND t.tag_slug = :tagSlug: ";
        $tagClause = $db->bindVars($tagClause, ':langId:', $languageId, 'integer');
        $tagClause = $db->bindVars($tagClause, ':tagSlug:', $tagSlug, 'string');
        $where .= $tagClause;
        $blogListingHeading = sprintf(TEXT_BLOG_FILTERED_BY_TAG, $tagSlug);
    }

    if ($searchKeyword !== '') {
        // Escape LIKE wildcards (% and _) to prevent wildcard DoS and bind safely via bindVars
        $escapedLike = addcslashes($searchKeyword, '%_');
        $searchClause = " AND (pd.post_title LIKE :kw: OR pd.post_summary LIKE :kw: OR pd.post_content LIKE :kw:) ";
        $searchClause = $db->bindVars($searchClause, ':kw:', '%' . $escapedLike . '%', 'string');
        $where .= $searchClause;
        $blogListingHeading = sprintf(TEXT_BLOG_SEARCH_RESULTS_FOR, $searchKeyword);
    }

    $listingSql = "SELECT DISTINCT p.post_id, p.featured_image, p.is_pinned, p.views_count,
                          COALESCE(p.date_scheduled, p.date_added) AS publish_date,
                          pd.post_title, pd.post_summary, pd.post_content,
                          a.author_name, a.avatar_image
                   FROM " . TABLE_ZX_BLOG_POSTS . " p
                   INNER JOIN " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . " pd
                     ON p.post_id = pd.post_id AND pd.language_id = " . $languageId . "
                   LEFT JOIN " . TABLE_ZX_BLOG_AVATARS . " a
                     ON p.avatar_id = a.avatar_id " .
                   $joins .
                   $where . "
                   ORDER BY p.is_pinned DESC, publish_date DESC";

    $maxPerPage = defined('ZX_BLOG_POSTS_PER_PAGE') ? max(1, (int)ZX_BLOG_POSTS_PER_PAGE) : 6;
    $blogSplit = new splitPageResults($listingSql, $maxPerPage, 'p.post_id', 'page');
    $blogPostsList = $db->Execute($blogSplit->sql_query);
}

$zco_notifier->notify('NOTIFY_HEADER_END_ZX_BLOG');