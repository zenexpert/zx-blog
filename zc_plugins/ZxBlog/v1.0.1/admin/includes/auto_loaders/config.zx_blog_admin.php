<?php

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// Breakpoint 68 runs before init_sanitize.php (Breakpoint 70)
$autoLoadConfig[68][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_zx_blog_admin.php',
];