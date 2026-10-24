<?php

declare(strict_types=1);

zen_define_default('FILENAME_ZX_BLOG', 'blog');
zen_define_default('FILENAME_ZX_BLOG_WP_IMPORT', 'blog_wp_import');
zen_define_default('DIR_WS_ZX_BLOG_IMAGES', 'blog/');
zen_define_default('DIR_WS_ZX_BLOG_AVATARS', 'blog/assets/');

zen_define_default('TABLE_ZX_BLOG_CATEGORIES', DB_PREFIX . 'zx_blog_categories');
zen_define_default('TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION', DB_PREFIX . 'zx_blog_categories_description');
zen_define_default('TABLE_ZX_BLOG_AVATARS', DB_PREFIX . 'zx_blog_avatars');
zen_define_default('TABLE_ZX_BLOG_POSTS', DB_PREFIX . 'zx_blog_posts');
zen_define_default('TABLE_ZX_BLOG_POSTS_DESCRIPTION', DB_PREFIX . 'zx_blog_posts_description');
zen_define_default('TABLE_ZX_BLOG_POSTS_TO_CATEGORIES', DB_PREFIX . 'zx_blog_posts_to_categories');
zen_define_default('TABLE_ZX_BLOG_TAGS', DB_PREFIX . 'zx_blog_tags');
zen_define_default('TABLE_ZX_BLOG_POSTS_TO_TAGS', DB_PREFIX . 'zx_blog_posts_to_tags');
zen_define_default('TABLE_ZX_BLOG_POSTS_TO_PRODUCTS', DB_PREFIX . 'zx_blog_posts_to_products');