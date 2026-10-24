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
    'category_name' => [
        'sanitizerType' => 'PRODUCT_NAME_DEEP_REGEX',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'post_tags' => [
        'sanitizerType' => 'PRODUCT_NAME_DEEP_REGEX',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'author_name' => [
        'sanitizerType' => 'PRODUCT_NAME_DEEP_REGEX',
        'method' => 'post',
        'pages' => ['blog'],
    ],
    'author_bio' => [
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
        'sanitizerType' => 'ZX_BLOG_FS_PATH',
        'method' => 'post',
        'pages' => ['blog_wp_import'],
    ],
]);

// FILE_PATH_OR_URL is the registered builtin key, but processBuiltIn()
// looks up filterFilePathOrUrl while the method is filterFilePathOrUrlRegex,
// so that builtin never runs. This applies the same character class and
// marks the field sanitized, which also keeps "&" from being entity-encoded.
$sanitizer->addSanitizerTypes([
    'ZX_BLOG_FS_PATH' => [
        'type' => 'custom',
        'function' => static function ($sanitizer, $parameterName): void {
            if (!isset($_POST[$parameterName]) || !is_string($_POST[$parameterName])) {
                return;
            }
            $regex = '~([^0-9a-z' . preg_quote("'.!@#$%&()_-~/;:=?[]`+^ " . '\\', '~') . ']|[><])~i';
            $_POST[$parameterName] = preg_replace($regex, '', $_POST[$parameterName]) ?? '';
            if (is_object($sanitizer) && method_exists($sanitizer, 'setPostKeyAlreadySanitized')) {
                $sanitizer->setPostKeyAlreadySanitized($parameterName);
            }
        },
    ],
]);