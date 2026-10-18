<?php

declare(strict_types=1);

$plugin_folder = basename(dirname(__DIR__));
$version_folder = basename(__DIR__);

$plugin_readme = (defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/') . 'zc_plugins/' . $plugin_folder . '/' . $version_folder . '/readme.html';
$plugin_github = 'https://github.com/zenexpert/zx-blog';
$plugin_forum = '';

$button_readme = '<a class="btn btn-primary btn-sm" href="' . $plugin_readme . '" target="_blank"><i class="fa fa-book"></i> Readme</a>';
$button_github = '<a class="btn btn-info btn-sm" href="' . $plugin_github . '" target="_blank"><i class="fa-solid fa-code-branch"></i> GitHub</a>';
$button_forum  = '<a class="btn btn-success btn-sm" href="' . $plugin_forum . '" target="_blank"><i class="fa fa-comments"></i> Forum</a>';

$buttons = '<div class="buttons my-2">' . $button_readme . ' ' . $button_github . ' ' . $button_forum . '</div>';

return [
    'pluginVersion' => 'v1.0.0',
    'pluginName' => 'ZX Blog',
    'pluginDescription' => 'Encapsulated Blog plugin for Zen Cart 2.1.0+ with categories, tags, product linking, and WordPress migration.' . $buttons,
    'pluginAuthor' => 'ZenExpert (Leonard)',
    'pluginId' => 0,
    'zcVersions' => ['v2.1.0', 'v2.2.0', 'v2.2.1', 'v2.2.2', 'v2.3.0'],
    'changelog' => '',
    'github_repo' => 'https://github.com/zenexpert/zx-blog',
    'pluginGroups' => [],
];
