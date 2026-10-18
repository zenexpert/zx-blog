<?php

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$sanitizer = AdminRequestSanitizer::getInstance();
$sanitizer->addComplexSanitization([
    'post_content' => [
        'sanitizerType' => 'PRODUCT_DESC_REGEX',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'post_summary' => [
        'sanitizerType' => 'PRODUCT_DESC_REGEX',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'category_description' => [
        'sanitizerType' => 'PRODUCT_DESC_REGEX',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'post_title' => [
        'sanitizerType' => 'PRODUCT_NAME_DEEP_REGEX',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'meta_title' => [
        'sanitizerType' => 'META_TAGS',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'meta_description' => [
        'sanitizerType' => 'META_TAGS',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'wp_db_pass' => [
        'sanitizerType' => 'NULL_ACTION',
        'method' => 'post',
        'pages' => ['blog_wp_import'],
    ],
    'wp_fs_path' => [
        'sanitizerType' => 'FILE_PATH_OR_URL_REGEX',
        'method' => 'post',
        'pages' => ['blog_wp_import'],
    ],
]);