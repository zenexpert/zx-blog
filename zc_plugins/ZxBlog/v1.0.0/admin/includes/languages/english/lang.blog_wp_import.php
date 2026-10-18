<?php

declare(strict_types=1);

return [
    'HEADING_TITLE' => 'ZX Blog - WordPress Database & Media Importer',
    'TEXT_WP_IMPORT_INTRO' => 'Connect to a WordPress database on the same MySQL server to import posts, categories, tags, authors/avatars, sticky status, Yoast/RankMath SEO metadata, and media files.',
    'TEXT_WP_DB_HOST' => 'WordPress DB Host:',
    'TEXT_WP_DB_NAME' => 'WordPress Database Name:',
    'TEXT_WP_DB_USER' => 'WordPress DB Username:',
    'TEXT_WP_DB_PASS' => 'WordPress DB Password:',
    'TEXT_WP_TABLE_PREFIX' => 'WordPress Table Prefix:',
    'TEXT_WP_FS_PATH' => 'Local WordPress Filesystem Path (optional):',
    'TEXT_WP_FS_PATH_HELP' => 'e.g. <code>/home/user/public_html/wp</code> or <code>/var/www/html/wp-content/uploads</code>. Used to copy featured and inline images into <code>images/blog/{yyyymm}/</code>. If left blank, the importer will attempt to download images via cURL from the WordPress siteurl.',
    'TEXT_WP_TARGET_LANGUAGE' => 'Target Zen Cart Language:',
    'TEXT_WP_ALL_LANGUAGES' => 'All Store Languages',
    'TEXT_WP_OPT_COPY_MEDIA' => 'Copy Featured & Inline Images to images/blog/{yyyymm}/',
    'TEXT_WP_OPT_GRAVATARS' => 'Download Author Gravatars into images/blog/assets/ when no local avatar exists',
    'TEXT_WP_OPT_IMPORT_DRAFTS' => 'Import Draft / Private / Pending posts (imported as Disabled)',
    'TEXT_WP_OPT_STRIP_GUTENBERG' => 'Strip Gutenberg block comments (<!-- wp:... -->) and auto-format paragraphs',
    'BUTTON_WP_TEST_CONNECTION' => 'Test Connection & Preview Counts',
    'BUTTON_WP_RUN_IMPORT' => 'Run WordPress Import Now',
    'BUTTON_BACK_TO_BLOG_MANAGER' => 'Go to ZX Blog Manager',
    'SUCCESS_WP_CONNECTION_PREVIEW' => 'Connected to WordPress! Found %d posts, %d categories, %d tags, and %d authors ready to migrate.',
    'SUCCESS_WP_IMPORT_SUMMARY' => 'WordPress Import Complete: %d new posts imported (%d existing updated), %d categories, %d tags, %d authors, and %d image files copied.',
    'ERROR_WP_IMPORT_FAILED' => 'WordPress Import Error: %s',
];