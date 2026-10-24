<?php

declare(strict_types=1);

use Zencart\PluginSupport\ScriptedInstaller as BaseScriptedInstaller;

class ScriptedInstaller extends BaseScriptedInstaller
{
    protected string $configGroupTitle = 'ZX Blog Configuration';

    protected function executeInstall(): bool
    {
        zen_define_default('TABLE_ZX_BLOG_CATEGORIES', DB_PREFIX . 'zx_blog_categories');
        zen_define_default('TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION', DB_PREFIX . 'zx_blog_categories_description');
        zen_define_default('TABLE_ZX_BLOG_AVATARS', DB_PREFIX . 'zx_blog_avatars');
        zen_define_default('TABLE_ZX_BLOG_POSTS', DB_PREFIX . 'zx_blog_posts');
        zen_define_default('TABLE_ZX_BLOG_POSTS_DESCRIPTION', DB_PREFIX . 'zx_blog_posts_description');
        zen_define_default('TABLE_ZX_BLOG_POSTS_TO_CATEGORIES', DB_PREFIX . 'zx_blog_posts_to_categories');
        zen_define_default('TABLE_ZX_BLOG_TAGS', DB_PREFIX . 'zx_blog_tags');
        zen_define_default('TABLE_ZX_BLOG_POSTS_TO_TAGS', DB_PREFIX . 'zx_blog_posts_to_tags');
        zen_define_default('TABLE_ZX_BLOG_POSTS_TO_PRODUCTS', DB_PREFIX . 'zx_blog_posts_to_products');

        $this->createDirectories();
        $this->createTables();
        $this->installConfiguration();
        $this->registerAdminPages();
        $this->registerSideboxes();

        return true;
    }

    protected function executeUninstall(): bool
    {
        if (function_exists('zen_deregister_admin_pages')) {
            zen_deregister_admin_pages([
                'toolsZxBlog',
                'extrasZxBlogWpImport',
                'configZxBlog',
            ]);
        }

        $sideboxes = [
            'blog_categories.php',
            'blog_recent_posts.php',
            'blog_tags.php',
        ];
        foreach ($sideboxes as $box) {
            $this->executeInstallerSql(
                "DELETE FROM " . TABLE_LAYOUT_BOXES . " WHERE layout_box_name = '" . zen_db_input($box) . "'"
            );
        }

        $this->deleteConfigurationKeys([
            'ZX_BLOG_STATUS',
            'ZX_BLOG_POSTS_PER_PAGE',
            'ZX_BLOG_RECENT_SIDEBOX_LIMIT',
            'ZX_BLOG_TAGS_SIDEBOX_LIMIT',
            'ZX_BLOG_SHOW_READING_TIME',
        ]);

        $group = $this->getConfigurationGroupDetails($this->configGroupTitle);
        if (is_array($group)) {
            $cgi = (int)$group['configuration_group_id'];
            $remaining = $this->executeInstallerSelectQuery(
                "SELECT configuration_id FROM " . TABLE_CONFIGURATION . "
                 WHERE configuration_group_id = " . $cgi . "
                 LIMIT 1"
            );
            if ($remaining !== false && $remaining->EOF) {
                $this->deleteConfigurationGroup($cgi, false);
            }
        }

        return true;
    }

    protected function createDirectories(): void
    {
        $dirs = [
            DIR_FS_CATALOG_IMAGES . 'blog',
            DIR_FS_CATALOG_IMAGES . 'blog/assets',
            DIR_FS_CATALOG_IMAGES . 'blog/' . date('Ym'),
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $this->errorContainer->addError(
                    0,
                    sprintf(ZC_PLUGIN_ZX_BLOG_INSTALL_ERROR_MKDIR, $dir),
                    false,
                    sprintf(ZC_PLUGIN_ZX_BLOG_INSTALL_ERROR_MKDIR, $dir)
                );
            }
        }
    }

    protected function createTables(): void
    {
        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_CATEGORIES . " (
                category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                sort_order INT NOT NULL DEFAULT 0,
                status TINYINT(1) NOT NULL DEFAULT 1,
                date_added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (category_id),
                KEY idx_status_sort_zen (status, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . " (
                category_id INT UNSIGNED NOT NULL,
                language_id INT NOT NULL DEFAULT 1,
                category_name VARCHAR(128) NOT NULL DEFAULT '',
                category_slug VARCHAR(160) NOT NULL DEFAULT '',
                category_description TEXT,
                PRIMARY KEY (category_id, language_id),
                KEY idx_slug_zen (category_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_AVATARS . " (
                avatar_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                author_name VARCHAR(128) NOT NULL DEFAULT '',
                author_bio VARCHAR(512) NOT NULL DEFAULT '',
                avatar_image VARCHAR(255) NOT NULL DEFAULT '',
                date_added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (avatar_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_POSTS . " (
                post_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                avatar_id INT UNSIGNED NOT NULL DEFAULT 0,
                featured_image VARCHAR(255) NOT NULL DEFAULT '',
                status TINYINT(1) NOT NULL DEFAULT 1,
                is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                views_count INT UNSIGNED NOT NULL DEFAULT 0,
                date_added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_scheduled DATETIME NULL DEFAULT NULL,
                last_modified DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (post_id),
                KEY idx_status_sched_zen (status, is_pinned, date_scheduled, date_added)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . " (
                post_id INT UNSIGNED NOT NULL,
                language_id INT NOT NULL DEFAULT 1,
                post_title VARCHAR(255) NOT NULL DEFAULT '',
                post_slug VARCHAR(255) NOT NULL DEFAULT '',
                post_summary TEXT,
                post_content MEDIUMTEXT,
                meta_title VARCHAR(255) NOT NULL DEFAULT '',
                meta_description VARCHAR(512) NOT NULL DEFAULT '',
                PRIMARY KEY (post_id, language_id),
                KEY idx_post_slug_zen (post_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " (
                post_id INT UNSIGNED NOT NULL,
                category_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (post_id, category_id),
                KEY idx_cat_zen (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_TAGS . " (
                tag_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                language_id INT NOT NULL DEFAULT 1,
                tag_name VARCHAR(64) NOT NULL DEFAULT '',
                tag_slug VARCHAR(80) NOT NULL DEFAULT '',
                PRIMARY KEY (tag_id),
                UNIQUE KEY idx_lang_slug_zen (language_id, tag_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " (
                post_id INT UNSIGNED NOT NULL,
                tag_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (post_id, tag_id),
                KEY idx_tag_zen (tag_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . TABLE_ZX_BLOG_POSTS_TO_PRODUCTS . " (
                post_id INT UNSIGNED NOT NULL,
                products_id INT NOT NULL,
                PRIMARY KEY (post_id, products_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    protected function installConfiguration(): void
    {
        $cgi = $this->getOrCreateConfigGroupId(
            $this->configGroupTitle,
            'Configuration settings for ZX Blog.'
        );

        $configs = [
            [
                'key' => 'ZX_BLOG_STATUS',
                'value' => 'true',
                'title' => 'Enable ZX Blog',
                'desc' => 'Enable the frontend blog and sideboxes?',
                'sort' => 10,
                'set_func' => "zen_cfg_select_option(['true', 'false'], ",
            ],
            [
                'key' => 'ZX_BLOG_POSTS_PER_PAGE',
                'value' => '6',
                'title' => 'Posts Per Page',
                'desc' => 'Number of blog posts displayed per page on listing views.',
                'sort' => 20,
                'set_func' => null,
            ],
            [
                'key' => 'ZX_BLOG_RECENT_SIDEBOX_LIMIT',
                'value' => '5',
                'title' => 'Sidebox: Recent Posts Limit',
                'desc' => 'Maximum number of recent posts shown in the Recent Posts sidebox.',
                'sort' => 30,
                'set_func' => null,
            ],
            [
                'key' => 'ZX_BLOG_TAGS_SIDEBOX_LIMIT',
                'value' => '20',
                'title' => 'Sidebox: Tags Limit',
                'desc' => 'Maximum number of tags shown in the Blog Tags sidebox.',
                'sort' => 40,
                'set_func' => null,
            ],
            [
                'key' => 'ZX_BLOG_SHOW_READING_TIME',
                'value' => 'true',
                'title' => 'Display Estimated Reading Time',
                'desc' => 'Show estimated reading time on posts?',
                'sort' => 50,
                'set_func' => "zen_cfg_select_option(['true', 'false'], ",
            ],
        ];

        foreach ($configs as $cfg) {
            $setFunc = $cfg['set_func'] === null ? 'NULL' : "'" . zen_db_input($cfg['set_func']) . "'";
            $this->executeInstallerSql(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, set_function)
                 VALUES
                    ('" . zen_db_input($cfg['title']) . "',
                     '" . zen_db_input($cfg['key']) . "',
                     '" . zen_db_input($cfg['value']) . "',
                     '" . zen_db_input($cfg['desc']) . "',
                     " . (int)$cgi . ",
                     " . (int)$cfg['sort'] . ",
                     NOW(),
                     " . $setFunc . ")"
            );
        }

        if (!zen_page_key_exists('configZxBlog')) {
            zen_register_admin_page(
                'configZxBlog',
                'BOX_CONFIGURATION_ZX_BLOG',
                'FILENAME_CONFIGURATION',
                'gID=' . (int)$cgi,
                'configuration',
                'Y',
                (int)$cgi
            );
        }
    }

    protected function registerAdminPages(): void
    {
        if (!zen_page_key_exists('toolsZxBlog')) {
            zen_register_admin_page(
                'toolsZxBlog',
                'BOX_TOOLS_ZX_BLOG',
                'FILENAME_ZX_BLOG',
                '',
                'tools',
                'Y',
                25
            );
        }

        if (!zen_page_key_exists('extrasZxBlogWpImport')) {
            zen_register_admin_page(
                'extrasZxBlogWpImport',
                'BOX_EXTRAS_ZX_BLOG_WP_IMPORT',
                'FILENAME_ZX_BLOG_WP_IMPORT',
                '',
                'extras',
                'Y',
                30
            );
        }
    }

    protected function registerSideboxes(): void
    {
        global $db;

        // Resolve 'ZxBlog/v1.0.0' dynamically for TABLE_LAYOUT_BOXES.plugin_details
        $pluginDetails = basename(dirname(__DIR__, 2)) . '/' . basename(dirname(__DIR__));

        $templates = $db->Execute(
            "SELECT DISTINCT layout_template FROM " . TABLE_LAYOUT_BOXES
        );

        $sideboxes = [
            'blog_categories.php' => 60,
            'blog_recent_posts.php' => 61,
            'blog_tags.php' => 62,
        ];

        foreach ($templates as $tpl) {
            $templateDir = $tpl['layout_template'];
            foreach ($sideboxes as $boxFile => $sortOrder) {
                $check = $db->Execute(
                    "SELECT layout_id, plugin_details FROM " . TABLE_LAYOUT_BOXES . "
                     WHERE layout_template = '" . zen_db_input($templateDir) . "'
                       AND layout_box_name = '" . zen_db_input($boxFile) . "'
                     LIMIT 1"
                );
                if ($check->EOF) {
                    $this->executeInstallerSql(
                        "INSERT INTO " . TABLE_LAYOUT_BOXES . "
                            (layout_template, layout_box_name, layout_box_status, layout_box_location, layout_box_sort_order, layout_box_sort_order_single, layout_box_status_single, plugin_details)
                         VALUES
                            ('" . zen_db_input($templateDir) . "',
                             '" . zen_db_input($boxFile) . "',
                             1,
                             1,
                             " . (int)$sortOrder . ",
                             0,
                             0,
                             '" . zen_db_input($pluginDetails) . "')"
                    );
                } elseif (empty($check->fields['plugin_details'])) {
                    $this->executeInstallerSql(
                        "UPDATE " . TABLE_LAYOUT_BOXES . "
                         SET plugin_details = '" . zen_db_input($pluginDetails) . "'
                         WHERE layout_id = " . (int)$check->fields['layout_id']
                    );
                }
            }
        }
    }
}