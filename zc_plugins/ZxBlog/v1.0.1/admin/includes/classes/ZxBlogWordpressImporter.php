<?php

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class ZxBlogWordpressImporter
{
    private ?mysqli $wpDb = null;
    private string $prefix = 'wp_';
    private string $wpUploadsFsPath = '';
    private string $wpSiteUrl = '';
    private bool $copyMedia = true;
    private bool $fetchGravatars = false;
    private bool $importDrafts = true;
    private bool $stripGutenberg = true;
    private int $targetLanguageId = 0;
    private string $dbHost = '';
    private string $dbName = '';
    private string $dbUser = '';
    private string $dbPass = '';

    public function __construct(
        string $dbHost,
        string $dbName,
        string $dbUser,
        string $dbPass,
        string $tablePrefix = 'wp_',
        string $wpFsPath = '',
        bool $copyMedia = true,
        bool $fetchGravatars = false,
        bool $importDrafts = true,
        bool $stripGutenberg = true,
        int $targetLanguageId = 0
    ) {
        $this->dbHost = $dbHost;
        $this->dbName = $dbName;
        $this->dbUser = $dbUser;
        $this->dbPass = $dbPass;

        $cleanPrefix = preg_replace('/[^a-zA-Z0-9_]/', '', $tablePrefix) ?? 'wp_';
        $this->prefix = $cleanPrefix !== '' ? $cleanPrefix : 'wp_';

        $wpFsPath = rtrim(str_replace('\\', '/', trim($wpFsPath)), '/');
        if ($wpFsPath !== '') {
            if (str_ends_with($wpFsPath, '/wp-content/uploads')) {
                $this->wpUploadsFsPath = $wpFsPath;
            } elseif (is_dir($wpFsPath . '/wp-content/uploads')) {
                $this->wpUploadsFsPath = $wpFsPath . '/wp-content/uploads';
            } else {
                $this->wpUploadsFsPath = $wpFsPath;
            }
        }

        $this->copyMedia = $copyMedia;
        $this->fetchGravatars = $fetchGravatars;
        $this->importDrafts = $importDrafts;
        $this->stripGutenberg = $stripGutenberg;
        $this->targetLanguageId = $targetLanguageId;
    }

    public function connect(): bool
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
        if ($conn->connect_errno) {
            throw new RuntimeException(
                sprintf('MySQL Connection Error (%d): %s', $conn->connect_errno, $conn->connect_error)
            );
        }
        $conn->set_charset('utf8mb4');
        $this->wpDb = $conn;

        $required = ['posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'users', 'usermeta', 'options'];
        foreach ($required as $table) {
            $fullTable = $this->prefix . $table;
            $res = $this->wpDb->query("SHOW TABLES LIKE '" . $this->wpDb->real_escape_string($fullTable) . "'");
            if (!$res || $res->num_rows === 0) {
                throw new RuntimeException(sprintf('WordPress table "%s" not found in database "%s".', $fullTable, $this->dbName));
            }
        }

        $this->wpSiteUrl = rtrim($this->getOptionValue('siteurl'), '/');

        return true;
    }

    public function getPreviewCounts(): array
    {
        if ($this->wpDb === null) {
            $this->connect();
        }

        $statuses = $this->importDrafts
            ? "'publish', 'future', 'draft', 'pending', 'private'"
            : "'publish', 'future'";

        return [
            'posts' => $this->scalarCount(
                "SELECT COUNT(*) FROM {$this->prefix}posts WHERE post_type = 'post' AND post_status IN ({$statuses})"
            ),
            'categories' => $this->scalarCount(
                "SELECT COUNT(*) FROM {$this->prefix}term_taxonomy WHERE taxonomy = 'category'"
            ),
            'tags' => $this->scalarCount(
                "SELECT COUNT(*) FROM {$this->prefix}term_taxonomy WHERE taxonomy = 'post_tag'"
            ),
            'authors' => $this->scalarCount(
                "SELECT COUNT(DISTINCT post_author) FROM {$this->prefix}posts WHERE post_type = 'post' AND post_status IN ({$statuses})"
            ),
        ];
    }

    public function runImport(): array
    {
        global $db;

        if ($this->wpDb === null) {
            $this->connect();
        }

        $languages = $this->getTargetLanguages();
        $stats = [
            'authors_imported' => 0,
            'categories_imported' => 0,
            'tags_imported' => 0,
            'posts_imported' => 0,
            'posts_updated' => 0,
            'images_copied' => 0,
        ];

        $authorMap = $this->importAuthors($stats);
        $categoryMap = $this->importCategories($languages, $stats);
        $tagMap = $this->importTags($languages, $stats);
        $stickyPostIds = $this->getStickyPostIds();

        $statuses = $this->importDrafts
            ? "'publish', 'future', 'draft', 'pending', 'private'"
            : "'publish', 'future'";

        $postsSql = "SELECT ID, post_author, post_date, post_content, post_title, post_excerpt,
                            post_status, post_name, post_modified
                     FROM {$this->prefix}posts
                     WHERE post_type = 'post'
                       AND post_status IN ({$statuses})
                     ORDER BY post_date ASC";

        $postsRes = $this->wpDb->query($postsSql);
        if (!$postsRes) {
            return $stats;
        }

        while ($wpPost = $postsRes->fetch_assoc()) {
            $wpPostId = (int)$wpPost['ID'];
            $wpAuthorId = (int)$wpPost['post_author'];
            $zcAvatarId = $authorMap[$wpAuthorId] ?? 0;

            $postMeta = $this->getPostMeta($wpPostId);
            $wpStatus = (string)$wpPost['post_status'];

            $zcStatus = in_array($wpStatus, ['publish', 'future'], true) ? 1 : 0;
            $postDate = $this->normalizeDate((string)$wpPost['post_date']);
            $postModified = $this->normalizeDate((string)$wpPost['post_modified']);
            $dateScheduled = ($wpStatus === 'future') ? $postDate : 'null';
            $isPinned = in_array($wpPostId, $stickyPostIds, true) ? 1 : 0;

            $viewsCount = (int)($postMeta['post_views_count'] ?? $postMeta['views'] ?? $postMeta['_post_views'] ?? 0);

            $featuredImageRel = '';
            if (!empty($postMeta['_thumbnail_id'])) {
                $featuredImageRel = $this->resolveAndCopyAttachment((int)$postMeta['_thumbnail_id'], $postDate, $stats);
            }

            $title = html_entity_decode((string)$wpPost['post_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rawSlug = urldecode((string)$wpPost['post_name']);
            $slug = $this->slugify($rawSlug !== '' ? $rawSlug : $title);

            $content = (string)$wpPost['post_content'];
            if ($this->stripGutenberg) {
                $content = $this->cleanGutenbergBlocks($content);
            }
            $content = $this->rewriteAndCopyInlineImages($content, $postDate, $stats);
            $content = $this->applyAutopIfNeeded($content);

            $summary = trim((string)$wpPost['post_excerpt']);
            if ($summary !== '') {
                $summary = $this->applyAutopIfNeeded($summary);
            }

            $metaTitleRaw = $postMeta['_yoast_wpseo_title']
                ?? $postMeta['rank_math_title']
                ?? $postMeta['_aioseo_title']
                ?? '';
            $metaDescRaw = $postMeta['_yoast_wpseo_metadesc']
                ?? $postMeta['rank_math_description']
                ?? $postMeta['_aioseo_description']
                ?? '';

            $metaTitle = $this->cleanSeoVariables($metaTitleRaw, $title);
            $metaDesc = $this->cleanSeoVariables($metaDescRaw, $title);

            $existingPost = $db->Execute(
                "SELECT post_id FROM " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . "
                 WHERE post_slug = '" . zen_db_input($slug) . "'
                 LIMIT 1"
            );

            $postTableData = [
                'avatar_id' => $zcAvatarId,
                'status' => $zcStatus,
                'is_pinned' => $isPinned,
                'views_count' => $viewsCount,
                'date_added' => $postDate,
                'date_scheduled' => $dateScheduled,
                'last_modified' => $postModified,
            ];
            if ($featuredImageRel !== '') {
                $postTableData['featured_image'] = $featuredImageRel;
            }

            if ($existingPost->EOF) {
                if (!isset($postTableData['featured_image'])) {
                    $postTableData['featured_image'] = '';
                }
                zen_db_perform(TABLE_ZX_BLOG_POSTS, $postTableData);
                $zcPostId = (int)$db->insert_ID();
                $stats['posts_imported']++;
            } else {
                $zcPostId = (int)$existingPost->fields['post_id'];
                zen_db_perform(TABLE_ZX_BLOG_POSTS, $postTableData, 'update', 'post_id = ' . $zcPostId);
                $stats['posts_updated']++;
            }

            foreach ($languages as $langId) {
                $descData = [
                    'post_id' => $zcPostId,
                    'language_id' => $langId,
                    'post_title' => $title,
                    'post_slug' => $slug,
                    'post_summary' => $summary,
                    'post_content' => $content,
                    'meta_title' => substr($metaTitle, 0, 255),
                    'meta_description' => substr($metaDesc, 0, 512),
                ];

                $checkDesc = $db->Execute(
                    "SELECT post_id FROM " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . "
                     WHERE post_id = " . $zcPostId . " AND language_id = " . $langId
                );
                if ($checkDesc->EOF) {
                    zen_db_perform(TABLE_ZX_BLOG_POSTS_DESCRIPTION, $descData);
                } else {
                    zen_db_perform(
                        TABLE_ZX_BLOG_POSTS_DESCRIPTION,
                        $descData,
                        'update',
                        'post_id = ' . $zcPostId . ' AND language_id = ' . $langId
                    );
                }
            }

            $this->linkPostTerms($wpPostId, $zcPostId, $categoryMap, $tagMap);
        }

        $postsRes->free();
        return $stats;
    }

    private function resolveAndCopyAttachment(int $attachmentPostId, string $fallbackPostDate, array &$stats): string
    {
        if ($attachmentPostId <= 0) {
            return '';
        }

        $attachedFile = $this->getPostMetaValue($attachmentPostId, '_wp_attached_file');
        if ($attachedFile === '') {
            return '';
        }

        return $this->copyWpUploadToBlogMonthFolder($attachedFile, $fallbackPostDate, $stats);
    }

    private function copyWpUploadToBlogMonthFolder(
        string $wpRelativeUploadPath,
        string $fallbackPostDate,
        array &$stats,
        string $explicitRemoteUrl = ''
    ): string {
        $cleanRel = ltrim(str_replace('\\', '/', $wpRelativeUploadPath), '/');
        if (!$this->isAllowedImageRelativePath($cleanRel)) {
            return '';
        }

        $filename = basename($cleanRel);

        if (preg_match('#^(\d{4})/(\d{2})/(.+)$#', $cleanRel, $m)) {
            $yyyymm = $m[1] . $m[2];
            $filename = basename($m[3]);
        } else {
            $ts = strtotime($fallbackPostDate);
            $yyyymm = ($ts !== false && $ts > 0) ? date('Ym', $ts) : date('Ym');
        }

        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename) ?? $filename;
        $zcRelativePath = DIR_WS_ZX_BLOG_IMAGES . $yyyymm . '/' . $filename;
        $zcFullDestDir = DIR_FS_CATALOG_IMAGES . DIR_WS_ZX_BLOG_IMAGES . $yyyymm . '/';
        $zcFullDestFile = $zcFullDestDir . $filename;

        if (!$this->copyMedia) {
            return $zcRelativePath;
        }

        if (!is_dir($zcFullDestDir)) {
            mkdir($zcFullDestDir, 0755, true);
        }

        if (is_file($zcFullDestFile) && filesize($zcFullDestFile) > 0) {
            return $zcRelativePath;
        }

        if ($this->wpUploadsFsPath !== '') {
            $sourceFile = $this->wpUploadsFsPath . '/' . $cleanRel;
            if (is_file($sourceFile) && @copy($sourceFile, $zcFullDestFile)) {
                $stats['images_copied']++;
                return $zcRelativePath;
            }
        }

        $remoteUrl = $explicitRemoteUrl !== ''
            ? $explicitRemoteUrl
            : ($this->wpSiteUrl !== '' ? $this->wpSiteUrl . '/wp-content/uploads/' . $cleanRel : '');

        if ($remoteUrl !== '' && $this->downloadRemoteFile($remoteUrl, $zcFullDestFile)) {
            $stats['images_copied']++;
            return $zcRelativePath;
        }

        return '';
    }

    private function isAllowedImageRelativePath(string $relativePath): bool
    {
        if ($relativePath === '' || strpos($relativePath, "\0") !== false || strpos($relativePath, '..') !== false) {
            return false;
        }
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true);
    }

    private function rewriteAndCopyInlineImages(string $content, string $postDate, array &$stats): string
    {
        if (!$this->copyMedia) {
            return $content;
        }

        $pattern = '#((?:https?:)?//[^"\'\s>]+/wp-content/uploads/((?:\d{4}/\d{2}/)?[^"\'\s>?\#]+\.(?:jpg|jpeg|png|gif|webp|avif)))#i';

        $migratedCount = 0;
        $updatedContent = (string)preg_replace_callback(
            $pattern,
            function (array $matches) use ($postDate, &$stats, &$migratedCount): string {
                $fullUrl = $matches[1];
                if (str_starts_with($fullUrl, '//')) {
                    $fullUrl = 'https:' . $fullUrl;
                }
                $wpRelative = $matches[2];
                $zcRel = $this->copyWpUploadToBlogMonthFolder($wpRelative, $postDate, $stats, $fullUrl);
                if ($zcRel !== '') {
                    $migratedCount++;
                    return 'images/' . $zcRel;
                }
                return $matches[0];
            },
            $content
        );

        if ($migratedCount > 0) {
            $updatedContent = (string)preg_replace('/\s+(?:data-)?srcset=(["\']).*?\1/is', '', $updatedContent);
            $updatedContent = (string)preg_replace('/\s+(?:data-)?sizes=(["\']).*?\1/is', '', $updatedContent);
        }

        return $updatedContent;
    }

    private function downloadRemoteFile(string $url, string $destFile): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, \CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, \CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, \CURLOPT_USERAGENT, 'ZenCart-ZxBlog-Migrator/1.0');

        $data = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data !== false && $httpCode >= 200 && $httpCode < 300 && strlen((string)$data) > 64) {
            return file_put_contents($destFile, $data) !== false;
        }

        return false;
    }

    private function importAuthors(array &$stats): array
    {
        global $db;

        $map = [];
        $sql = "SELECT DISTINCT u.ID, u.user_login, u.display_name, u.user_email
                FROM {$this->prefix}users u
                INNER JOIN {$this->prefix}posts p ON u.ID = p.post_author
                WHERE p.post_type = 'post'";
        $res = $this->wpDb->query($sql);
        if (!$res) {
            return $map;
        }

        while ($user = $res->fetch_assoc()) {
            $wpUserId = (int)$user['ID'];
            $authorName = trim((string)($user['display_name'] ?: $user['user_login']));
            if ($authorName === '') {
                $authorName = 'Author #' . $wpUserId;
            }

            $bio = $this->getUserMetaValue($wpUserId, 'description');
            $avatarRelPath = $this->resolveUserAvatar($wpUserId, (string)$user['user_email'], $stats);

            $check = $db->Execute(
                "SELECT avatar_id, avatar_image FROM " . TABLE_ZX_BLOG_AVATARS . "
                 WHERE author_name = '" . zen_db_input($authorName) . "'
                 LIMIT 1"
            );

            $data = [
                'author_name' => substr($authorName, 0, 128),
                'author_bio' => substr($bio, 0, 512),
            ];
            if ($avatarRelPath !== '') {
                $data['avatar_image'] = $avatarRelPath;
            }

            if ($check->EOF) {
                if (!isset($data['avatar_image'])) {
                    $data['avatar_image'] = '';
                }
                $data['date_added'] = 'now()';
                zen_db_perform(TABLE_ZX_BLOG_AVATARS, $data);
                $zcAvatarId = (int)$db->insert_ID();
                $stats['authors_imported']++;
            } else {
                $zcAvatarId = (int)$check->fields['avatar_id'];
                zen_db_perform(TABLE_ZX_BLOG_AVATARS, $data, 'update', 'avatar_id = ' . $zcAvatarId);
            }

            $map[$wpUserId] = $zcAvatarId;
        }
        $res->free();

        return $map;
    }

    private function importCategories(array $languages, array &$stats): array
    {
        global $db;

        $map = [];
        $sql = "SELECT tt.term_taxonomy_id, t.name, t.slug, tt.description
                FROM {$this->prefix}term_taxonomy tt
                INNER JOIN {$this->prefix}terms t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'category'";
        $res = $this->wpDb->query($sql);
        if (!$res) {
            return $map;
        }

        while ($row = $res->fetch_assoc()) {
            $ttId = (int)$row['term_taxonomy_id'];
            $name = html_entity_decode((string)$row['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = $this->slugify(urldecode((string)$row['slug']) ?: $name);
            $desc = (string)$row['description'];

            $existing = $db->Execute(
                "SELECT category_id FROM " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . "
                 WHERE category_slug = '" . zen_db_input($slug) . "'
                 LIMIT 1"
            );

            if ($existing->EOF) {
                zen_db_perform(TABLE_ZX_BLOG_CATEGORIES, [
                    'sort_order' => 0,
                    'status' => 1,
                    'date_added' => 'now()',
                ]);
                $zcCatId = (int)$db->insert_ID();
                $stats['categories_imported']++;
            } else {
                $zcCatId = (int)$existing->fields['category_id'];
            }

            foreach ($languages as $langId) {
                $descData = [
                    'category_id' => $zcCatId,
                    'language_id' => $langId,
                    'category_name' => substr($name, 0, 128),
                    'category_slug' => substr($slug, 0, 160),
                    'category_description' => $desc,
                ];
                $checkLang = $db->Execute(
                    "SELECT category_id FROM " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . "
                     WHERE category_id = " . $zcCatId . " AND language_id = " . $langId
                );
                if ($checkLang->EOF) {
                    zen_db_perform(TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION, $descData);
                } else {
                    zen_db_perform(
                        TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION,
                        $descData,
                        'update',
                        'category_id = ' . $zcCatId . ' AND language_id = ' . $langId
                    );
                }
            }

            $map[$ttId] = $zcCatId;
        }
        $res->free();

        return $map;
    }

    private function importTags(array $languages, array &$stats): array
    {
        global $db;

        $map = [];
        $sql = "SELECT tt.term_taxonomy_id, t.name, t.slug
                FROM {$this->prefix}term_taxonomy tt
                INNER JOIN {$this->prefix}terms t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'post_tag'";
        $res = $this->wpDb->query($sql);
        if (!$res) {
            return $map;
        }

        while ($row = $res->fetch_assoc()) {
            $ttId = (int)$row['term_taxonomy_id'];
            $name = html_entity_decode((string)$row['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = substr($this->slugify(urldecode((string)$row['slug']) ?: $name), 0, 80);

            $map[$ttId] = [];
            $importedNew = false;

            foreach ($languages as $langId) {
                $existing = $db->Execute(
                    "SELECT tag_id FROM " . TABLE_ZX_BLOG_TAGS . "
                     WHERE language_id = " . $langId . "
                       AND tag_slug = '" . zen_db_input($slug) . "'
                     LIMIT 1"
                );
                if ($existing->EOF) {
                    zen_db_perform(TABLE_ZX_BLOG_TAGS, [
                        'language_id' => $langId,
                        'tag_name' => substr($name, 0, 64),
                        'tag_slug' => $slug,
                    ]);
                    $tagId = (int)$db->insert_ID();
                    $importedNew = true;
                } else {
                    $tagId = (int)$existing->fields['tag_id'];
                }
                $map[$ttId][] = $tagId;
            }

            if ($importedNew) {
                $stats['tags_imported']++;
            }
        }
        $res->free();

        return $map;
    }

    private function linkPostTerms(int $wpPostId, int $zcPostId, array $categoryMap, array $tagMap): void
    {
        global $db;

        $sql = "SELECT term_taxonomy_id FROM {$this->prefix}term_relationships WHERE object_id = " . $wpPostId;
        $res = $this->wpDb->query($sql);
        if (!$res) {
            return;
        }

        while ($row = $res->fetch_assoc()) {
            $ttId = (int)$row['term_taxonomy_id'];

            if (isset($categoryMap[$ttId])) {
                $zcCatId = (int)$categoryMap[$ttId];
                $db->Execute(
                    "INSERT IGNORE INTO " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " (post_id, category_id)
                     VALUES (" . $zcPostId . ", " . $zcCatId . ")"
                );
            }

            if (isset($tagMap[$ttId])) {
                foreach ($tagMap[$ttId] as $zcTagId) {
                    $db->Execute(
                        "INSERT IGNORE INTO " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " (post_id, tag_id)
                         VALUES (" . $zcPostId . ", " . (int)$zcTagId . ")"
                    );
                }
            }
        }
        $res->free();
    }

    private function resolveUserAvatar(int $wpUserId, string $email, array &$stats): string
    {
        $destDir = DIR_FS_CATALOG_IMAGES . DIR_WS_ZX_BLOG_AVATARS;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $avatarAttachmentId = (int)$this->getUserMetaValue($wpUserId, $this->prefix . 'user_avatar');
        if ($avatarAttachmentId > 0 && $this->copyMedia) {
            $attachedFile = $this->getPostMetaValue($avatarAttachmentId, '_wp_attached_file');
            $cleanRel = ltrim(str_replace('\\', '/', $attachedFile), '/');
            if ($this->isAllowedImageRelativePath($cleanRel)) {
                $filename = 'wp-avatar-' . $wpUserId . '-' . basename($cleanRel);
                $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename) ?? $filename;
                $destFile = $destDir . $filename;

                if ($this->wpUploadsFsPath !== '') {
                    $src = $this->wpUploadsFsPath . '/' . $cleanRel;
                    if (is_file($src) && @copy($src, $destFile)) {
                        $stats['images_copied']++;
                        return DIR_WS_ZX_BLOG_AVATARS . $filename;
                    }
                }

                if ($this->wpSiteUrl !== '' && $this->downloadRemoteFile($this->wpSiteUrl . '/wp-content/uploads/' . $cleanRel, $destFile)) {
                    $stats['images_copied']++;
                    return DIR_WS_ZX_BLOG_AVATARS . $filename;
                }
            }
        }

        if ($this->fetchGravatars && $email !== '') {
            $hash = md5(strtolower(trim($email)));
            $filename = 'wp-gravatar-' . $wpUserId . '.jpg';
            $destFile = $destDir . $filename;
            if (!is_file($destFile)) {
                $gravatarUrl = 'https://www.gravatar.com/avatar/' . $hash . '?s=160&d=404';
                if ($this->downloadRemoteFile($gravatarUrl, $destFile)) {
                    $stats['images_copied']++;
                    return DIR_WS_ZX_BLOG_AVATARS . $filename;
                }
            } else {
                return DIR_WS_ZX_BLOG_AVATARS . $filename;
            }
        }

        return '';
    }

    private function getOptionValue(string $optionName): string
    {
        $escaped = $this->wpDb->real_escape_string($optionName);
        $res = $this->wpDb->query(
            "SELECT option_value FROM {$this->prefix}options WHERE option_name = '{$escaped}' LIMIT 1"
        );
        if (!$res || $res->num_rows === 0) {
            return '';
        }
        $row = $res->fetch_assoc();
        $res->free();
        return trim((string)($row['option_value'] ?? ''));
    }

    private function getStickyPostIds(): array
    {
        $val = $this->getOptionValue('sticky_posts');
        if ($val === '') {
            return [];
        }

        $unserialized = @unserialize($val, ['allowed_classes' => false]);
        if (!is_array($unserialized)) {
            return [];
        }

        return array_map('intval', array_values($unserialized));
    }

    private function getPostMeta(int $wpPostId): array
    {
        $meta = [];
        $res = $this->wpDb->query(
            "SELECT meta_key, meta_value FROM {$this->prefix}postmeta WHERE post_id = " . $wpPostId
        );
        if (!$res) {
            return $meta;
        }
        while ($row = $res->fetch_assoc()) {
            $key = (string)$row['meta_key'];
            if ($key !== '' && !isset($meta[$key])) {
                $meta[$key] = (string)$row['meta_value'];
            }
        }
        $res->free();
        return $meta;
    }

    private function getPostMetaValue(int $wpPostId, string $metaKey): string
    {
        $escapedKey = $this->wpDb->real_escape_string($metaKey);
        $res = $this->wpDb->query(
            "SELECT meta_value FROM {$this->prefix}postmeta
             WHERE post_id = " . $wpPostId . " AND meta_key = '{$escapedKey}'
             LIMIT 1"
        );
        if (!$res || $res->num_rows === 0) {
            return '';
        }
        $row = $res->fetch_assoc();
        $res->free();
        return (string)($row['meta_value'] ?? '');
    }

    private function getUserMetaValue(int $wpUserId, string $metaKey): string
    {
        $escapedKey = $this->wpDb->real_escape_string($metaKey);
        $res = $this->wpDb->query(
            "SELECT meta_value FROM {$this->prefix}usermeta
             WHERE user_id = " . $wpUserId . " AND meta_key = '{$escapedKey}'
             LIMIT 1"
        );
        if (!$res || $res->num_rows === 0) {
            return '';
        }
        $row = $res->fetch_assoc();
        $res->free();
        return (string)($row['meta_value'] ?? '');
    }

    private function cleanGutenbergBlocks(string $content): string
    {
        return trim((string)preg_replace('/<!--\s*\/?wp:.*?-->/s', '', $content));
    }

    private function applyAutopIfNeeded(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '' || stripos($trimmed, '<p') !== false || stripos($trimmed, '<div') !== false) {
            return $trimmed;
        }
        $blocks = preg_split('/\n\s*\n/', str_replace(["\r\n", "\r"], "\n", $trimmed)) ?: [];
        $out = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block !== '') {
                $out[] = '<p>' . nl2br($block) . '</p>';
            }
        }
        return implode("\n\n", $out);
    }

    private function cleanSeoVariables(string $rawSeo, string $postTitle): string
    {
        if ($rawSeo === '') {
            return '';
        }
        $replacements = [
            '%%title%%' => $postTitle,
            '%title%' => $postTitle,
            '%%sep%%' => '-',
            '%sep%' => '-',
            '%%sitename%%' => defined('STORE_NAME') ? STORE_NAME : '',
            '%sitename%' => defined('STORE_NAME') ? STORE_NAME : '',
        ];
        $cleaned = str_ireplace(array_keys($replacements), array_values($replacements), $rawSeo);
        $cleaned = preg_replace('/%%[a-z0-9_]+%%/i', '', $cleaned) ?? $cleaned;
        return trim((string)preg_replace('/\s+/', ' ', $cleaned));
    }

    private function normalizeDate(string $wpDate): string
    {
        if ($wpDate === '' || str_starts_with($wpDate, '0000-00-00')) {
            return date('Y-m-d H:i:s');
        }
        return $wpDate;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9-]+/', '-', $text) ?? '';
        return trim($text, '-') ?: 'post-' . time();
    }

    private function getTargetLanguages(): array
    {
        if ($this->targetLanguageId > 0) {
            return [$this->targetLanguageId];
        }
        $langs = zen_get_languages();
        $ids = [];
        foreach ($langs as $lang) {
            $ids[] = (int)$lang['id'];
        }
        return $ids ?: [(int)$_SESSION['languages_id']];
    }

    private function scalarCount(string $sql): int
    {
        $res = $this->wpDb->query($sql);
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_row();
        $res->free();
        return (int)($row[0] ?? 0);
    }
}