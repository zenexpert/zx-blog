<?php

declare(strict_types=1);

require 'includes/application_top.php';

$languages = zen_get_languages();
$action = $_GET['action'] ?? '';
$tab = $_GET['tab'] ?? 'posts';
$currentYearMonth = date('Ym');
$allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

if (!function_exists('zx_blog_slugify')) {
    function zx_blog_slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9-]+/', '-', $text) ?? '';
        return trim($text, '-') ?: 'post-' . time();
    }
}

if (!function_exists('zx_blog_ensure_dir')) {
    function zx_blog_ensure_dir(string $relativeDir): string
    {
        $fullPath = DIR_FS_CATALOG_IMAGES . rtrim($relativeDir, '/') . '/';
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        return $fullPath;
    }
}

if (!function_exists('zx_blog_handle_upload')) {
    function zx_blog_handle_upload(string $inputName, string $relativeSubdir, array $allowedExtensions): ?string
    {
        if (empty($_FILES[$inputName]['name'])) {
            return null;
        }
        $destFs = zx_blog_ensure_dir($relativeSubdir);
        $upload = new upload($inputName);
        $upload->set_extensions($allowedExtensions);
        $upload->set_destination($destFs);
        $upload->set_output_messages('session');

        if ($upload->parse() && $upload->save()) {
            return rtrim($relativeSubdir, '/') . '/' . $upload->filename;
        }
        return null;
    }
}

switch ($action) {
    case 'set_editor':
        // Handled by init_html_editor.php; redirect back to current view
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, zen_get_all_get_params(['action', 'reset_editor'])));
        break;

    case 'setflag_post':
        $postId = (int)($_GET['pID'] ?? 0);
        $flag = ((int)($_GET['flag'] ?? 0) === 1) ? 1 : 0;
        if ($postId > 0) {
            $db->Execute(
                "UPDATE " . TABLE_ZX_BLOG_POSTS . "
                 SET status = " . $flag . ", last_modified = NOW()
                 WHERE post_id = " . $postId
            );
        }
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=posts&pID=' . $postId));
        break;

    case 'save_post':
        $postId = (int)($_POST['post_id'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $avatarId = (int)($_POST['avatar_id'] ?? 0);
        $dateScheduledRaw = trim($_POST['date_scheduled'] ?? '');
        $dateScheduled = 'null';
        if ($dateScheduledRaw !== '') {
            $ts = strtotime($dateScheduledRaw);
            if ($ts !== false) {
                $dateScheduled = date('Y-m-d H:i:s', $ts);
            }
        }

        $uploadedFeatured = zx_blog_handle_upload(
            'featured_image_file',
            DIR_WS_ZX_BLOG_IMAGES . $currentYearMonth,
            $allowedImageExtensions
        );

        $sqlData = [
            'avatar_id' => $avatarId,
            'status' => $status,
            'is_pinned' => $isPinned,
            'date_scheduled' => $dateScheduled,
            'last_modified' => 'now()',
        ];

        if ($uploadedFeatured !== null) {
            $sqlData['featured_image'] = $uploadedFeatured;
        } elseif (!empty($_POST['delete_featured_image']) && $postId > 0) {
            $oldImg = $db->Execute(
                "SELECT featured_image FROM " . TABLE_ZX_BLOG_POSTS . " WHERE post_id = " . $postId
            );
            if (!$oldImg->EOF && !empty($oldImg->fields['featured_image'])) {
                $fullOld = DIR_FS_CATALOG_IMAGES . $oldImg->fields['featured_image'];
                if (is_file($fullOld)) {
                    @unlink($fullOld);
                }
            }
            $sqlData['featured_image'] = '';
        }

        if ($postId > 0) {
            zen_db_perform(TABLE_ZX_BLOG_POSTS, $sqlData, 'update', 'post_id = ' . $postId);
        } else {
            $sqlData['date_added'] = 'now()';
            zen_db_perform(TABLE_ZX_BLOG_POSTS, $sqlData);
            $postId = (int)$db->insert_ID();
        }

        foreach ($languages as $lang) {
            $langId = (int)$lang['id'];
            $title = trim($_POST['post_title'][$langId] ?? '');
            $slugInput = trim($_POST['post_slug'][$langId] ?? '');
            $slug = ($slugInput !== '') ? zx_blog_slugify($slugInput) : zx_blog_slugify($title);

            $descData = [
                'post_id' => $postId,
                'language_id' => $langId,
                'post_title' => $title,
                'post_slug' => $slug,
                'post_summary' => $_POST['post_summary'][$langId] ?? '',
                'post_content' => $_POST['post_content'][$langId] ?? '',
                'meta_title' => trim($_POST['meta_title'][$langId] ?? ''),
                'meta_description' => trim($_POST['meta_description'][$langId] ?? ''),
            ];

            $check = $db->Execute(
                "SELECT post_id FROM " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . "
                 WHERE post_id = " . $postId . " AND language_id = " . $langId
            );
            if ($check->EOF) {
                zen_db_perform(TABLE_ZX_BLOG_POSTS_DESCRIPTION, $descData);
            } else {
                zen_db_perform(
                    TABLE_ZX_BLOG_POSTS_DESCRIPTION,
                    $descData,
                    'update',
                    'post_id = ' . $postId . ' AND language_id = ' . $langId
                );
            }

            $tagsRaw = $_POST['post_tags'][$langId] ?? '';
            $tagNames = array_filter(array_map('trim', explode(',', $tagsRaw)));
            $db->Execute(
                "DELETE pt FROM " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " pt
                 INNER JOIN " . TABLE_ZX_BLOG_TAGS . " t ON pt.tag_id = t.tag_id
                 WHERE pt.post_id = " . $postId . " AND t.language_id = " . $langId
            );
            foreach ($tagNames as $tagName) {
                $tagSlug = zx_blog_slugify($tagName);
                $existingTag = $db->Execute(
                    "SELECT tag_id FROM " . TABLE_ZX_BLOG_TAGS . "
                     WHERE language_id = " . $langId . " AND tag_slug = '" . zen_db_input($tagSlug) . "'"
                );
                if ($existingTag->EOF) {
                    zen_db_perform(TABLE_ZX_BLOG_TAGS, [
                        'language_id' => $langId,
                        'tag_name' => $tagName,
                        'tag_slug' => $tagSlug,
                    ]);
                    $tagId = (int)$db->insert_ID();
                } else {
                    $tagId = (int)$existingTag->fields['tag_id'];
                }
                $db->Execute(
                    "INSERT IGNORE INTO " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " (post_id, tag_id)
                     VALUES (" . $postId . ", " . $tagId . ")"
                );
            }
        }

        $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " WHERE post_id = " . $postId);
        $selectedCats = $_POST['categories'] ?? [];
        if (is_array($selectedCats)) {
            foreach ($selectedCats as $catId) {
                $catId = (int)$catId;
                if ($catId > 0) {
                    $db->Execute(
                        "INSERT IGNORE INTO " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " (post_id, category_id)
                         VALUES (" . $postId . ", " . $catId . ")"
                    );
                }
            }
        }

        $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_POSTS_TO_PRODUCTS . " WHERE post_id = " . $postId);
        $relatedRaw = $_POST['related_products'] ?? '';
        $prodIds = array_filter(array_map('intval', explode(',', $relatedRaw)));
        foreach ($prodIds as $prodId) {
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_ZX_BLOG_POSTS_TO_PRODUCTS . " (post_id, products_id)
                 VALUES (" . $postId . ", " . $prodId . ")"
            );
        }

        $messageStack->add_session(SUCCESS_POST_SAVED, 'success');
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=posts&pID=' . $postId));
        break;

    case 'delete_post_confirm':
        $postId = (int)($_POST['post_id'] ?? 0);
        if ($postId > 0) {
            $imgQuery = $db->Execute(
                "SELECT featured_image FROM " . TABLE_ZX_BLOG_POSTS . " WHERE post_id = " . $postId
            );
            if (!$imgQuery->EOF && !empty($imgQuery->fields['featured_image'])) {
                $file = DIR_FS_CATALOG_IMAGES . $imgQuery->fields['featured_image'];
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_POSTS . " WHERE post_id = " . $postId);
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . " WHERE post_id = " . $postId);
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " WHERE post_id = " . $postId);
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " WHERE post_id = " . $postId);
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_POSTS_TO_PRODUCTS . " WHERE post_id = " . $postId);
            $messageStack->add_session(SUCCESS_POST_DELETED, 'success');
        }
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=posts'));
        break;

    case 'save_category':
        $catId = (int)($_POST['category_id'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);

        $catData = [
            'sort_order' => $sortOrder,
            'status' => $status,
        ];
        if ($catId > 0) {
            zen_db_perform(TABLE_ZX_BLOG_CATEGORIES, $catData, 'update', 'category_id = ' . $catId);
        } else {
            $catData['date_added'] = 'now()';
            zen_db_perform(TABLE_ZX_BLOG_CATEGORIES, $catData);
            $catId = (int)$db->insert_ID();
        }

        foreach ($languages as $lang) {
            $langId = (int)$lang['id'];
            $name = trim($_POST['category_name'][$langId] ?? '');
            $slugInput = trim($_POST['category_slug'][$langId] ?? '');
            $slug = ($slugInput !== '') ? zx_blog_slugify($slugInput) : zx_blog_slugify($name);

            $descData = [
                'category_id' => $catId,
                'language_id' => $langId,
                'category_name' => $name,
                'category_slug' => $slug,
                'category_description' => $_POST['category_description'][$langId] ?? '',
            ];
            $check = $db->Execute(
                "SELECT category_id FROM " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . "
                 WHERE category_id = " . $catId . " AND language_id = " . $langId
            );
            if ($check->EOF) {
                zen_db_perform(TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION, $descData);
            } else {
                zen_db_perform(
                    TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION,
                    $descData,
                    'update',
                    'category_id = ' . $catId . ' AND language_id = ' . $langId
                );
            }
        }
        $messageStack->add_session(SUCCESS_CATEGORY_SAVED, 'success');
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=categories&cID=' . $catId));
        break;

    case 'delete_category_confirm':
        $catId = (int)($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_CATEGORIES . " WHERE category_id = " . $catId);
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . " WHERE category_id = " . $catId);
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " WHERE category_id = " . $catId);
            $messageStack->add_session(SUCCESS_CATEGORY_DELETED, 'success');
        }
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=categories'));
        break;

    case 'save_avatar':
        $avatarId = (int)($_POST['avatar_id'] ?? 0);
        $authorName = trim($_POST['author_name'] ?? '');
        $authorBio = trim($_POST['author_bio'] ?? '');

        $uploadedAvatar = zx_blog_handle_upload(
            'avatar_image_file',
            DIR_WS_ZX_BLOG_AVATARS,
            $allowedImageExtensions
        );

        $avatarData = [
            'author_name' => $authorName,
            'author_bio' => $authorBio,
        ];

        if ($uploadedAvatar !== null) {
            if ($avatarId > 0) {
                $old = $db->Execute(
                    "SELECT avatar_image FROM " . TABLE_ZX_BLOG_AVATARS . " WHERE avatar_id = " . $avatarId
                );
                if (!$old->EOF && !empty($old->fields['avatar_image'])) {
                    $oldPath = DIR_FS_CATALOG_IMAGES . $old->fields['avatar_image'];
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            }
            $avatarData['avatar_image'] = $uploadedAvatar;
        } elseif (!empty($_POST['delete_avatar_file']) && $avatarId > 0) {
            $old = $db->Execute(
                "SELECT avatar_image FROM " . TABLE_ZX_BLOG_AVATARS . " WHERE avatar_id = " . $avatarId
            );
            if (!$old->EOF && !empty($old->fields['avatar_image'])) {
                $oldPath = DIR_FS_CATALOG_IMAGES . $old->fields['avatar_image'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $avatarData['avatar_image'] = '';
        }

        if ($avatarId > 0) {
            zen_db_perform(TABLE_ZX_BLOG_AVATARS, $avatarData, 'update', 'avatar_id = ' . $avatarId);
        } else {
            $avatarData['date_added'] = 'now()';
            zen_db_perform(TABLE_ZX_BLOG_AVATARS, $avatarData);
            $avatarId = (int)$db->insert_ID();
        }

        $messageStack->add_session(SUCCESS_AVATAR_SAVED, 'success');
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=avatars&aID=' . $avatarId));
        break;

    case 'delete_avatar_confirm':
        $avatarId = (int)($_POST['avatar_id'] ?? 0);
        if ($avatarId > 0) {
            $old = $db->Execute(
                "SELECT avatar_image FROM " . TABLE_ZX_BLOG_AVATARS . " WHERE avatar_id = " . $avatarId
            );
            if (!$old->EOF && !empty($old->fields['avatar_image'])) {
                $oldPath = DIR_FS_CATALOG_IMAGES . $old->fields['avatar_image'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $db->Execute("DELETE FROM " . TABLE_ZX_BLOG_AVATARS . " WHERE avatar_id = " . $avatarId);
            $db->Execute("UPDATE " . TABLE_ZX_BLOG_POSTS . " SET avatar_id = 0 WHERE avatar_id = " . $avatarId);
            $messageStack->add_session(SUCCESS_AVATAR_DELETED, 'success');
        }
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=avatars'));
        break;

    case 'upload_content_image':
        $uploadedContentImg = zx_blog_handle_upload(
            'content_image_file',
            DIR_WS_ZX_BLOG_IMAGES . $currentYearMonth,
            $allowedImageExtensions
        );
        if ($uploadedContentImg !== null) {
            $messageStack->add_session(
                sprintf(SUCCESS_CONTENT_IMAGE_UPLOADED, 'images/' . $uploadedContentImg),
                'success'
            );
        } else {
            $messageStack->add_session(ERROR_UPLOAD_FAILED, 'error');
        }
        zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=media'));
        break;
}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php
    require DIR_WS_INCLUDES . 'admin_html_head.php';
    if (!empty($editor_handler)) {
        include $editor_handler;
    }
    ?>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-8">
            <h1><?php echo HEADING_TITLE; ?></h1>
        </div>
        <div class="col-sm-4 text-right" style="margin-top: 20px;">
            <?php
            echo zen_draw_form('editor_form', FILENAME_ZX_BLOG, '', 'get', 'class="form-inline"');
            echo zen_hide_session_id();
            echo zen_draw_hidden_field('action', 'set_editor');
            echo zen_draw_hidden_field('tab', $tab);
            echo zen_draw_label(TEXT_EDITOR_INFO, 'reset_editor', 'class="control-label" style="margin-right:6px;"');
            echo zen_draw_pull_down_menu('reset_editor', $editors_pulldown, $current_editor_key, 'onChange="this.form.submit();" class="form-control"');
            echo '</form>';
            ?>
        </div>
    </div>

    <ul class="nav nav-tabs" style="margin-bottom: 20px;">
        <li class="<?php echo ($tab === 'posts') ? 'active' : ''; ?>">
            <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=posts'); ?>"><?php echo TAB_POSTS; ?></a>
        </li>
        <li class="<?php echo ($tab === 'categories') ? 'active' : ''; ?>">
            <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=categories'); ?>"><?php echo TAB_CATEGORIES; ?></a>
        </li>
        <li class="<?php echo ($tab === 'avatars') ? 'active' : ''; ?>">
            <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=avatars'); ?>"><?php echo TAB_AVATARS; ?></a>
        </li>
        <li class="<?php echo ($tab === 'media') ? 'active' : ''; ?>">
            <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=media'); ?>"><?php echo TAB_MEDIA; ?></a>
        </li>
    </ul>

    <?php
    if ($tab === 'posts') {
        if ($action === 'new_post' || $action === 'edit_post') {
            $pID = (int)($_GET['pID'] ?? 0);
            $post = [
                'post_id' => 0,
                'avatar_id' => 0,
                'featured_image' => '',
                'status' => 1,
                'is_pinned' => 0,
                'date_scheduled' => '',
            ];
            $translations = [];
            $selectedCategories = [];
            $relatedProductsStr = '';

            if ($pID > 0) {
                $pRes = $db->Execute("SELECT * FROM " . TABLE_ZX_BLOG_POSTS . " WHERE post_id = " . $pID);
                if (!$pRes->EOF) {
                    $post = $pRes->fields;
                }
                $dRes = $db->Execute("SELECT * FROM " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . " WHERE post_id = " . $pID);
                foreach ($dRes as $row) {
                    $lId = (int)$row['language_id'];
                    $translations[$lId] = $row;
                }
                $cRes = $db->Execute("SELECT category_id FROM " . TABLE_ZX_BLOG_POSTS_TO_CATEGORIES . " WHERE post_id = " . $pID);
                foreach ($cRes as $cRow) {
                    $selectedCategories[] = (int)$cRow['category_id'];
                }
                $prRes = $db->Execute("SELECT products_id FROM " . TABLE_ZX_BLOG_POSTS_TO_PRODUCTS . " WHERE post_id = " . $pID);
                $prodArr = [];
                foreach ($prRes as $prRow) {
                    $prodArr[] = (int)$prRow['products_id'];
                }
                $relatedProductsStr = implode(',', $prodArr);
            }

            $scheduledHtmlValue = '';
            if (!empty($post['date_scheduled'])) {
                $schedTs = strtotime((string)$post['date_scheduled']);
                if ($schedTs !== false) {
                    $scheduledHtmlValue = date('Y-m-d\TH:i', $schedTs);
                }
            }

            $avatarsList = [['id' => '0', 'text' => TEXT_NONE_SELECTED]];
            $avQuery = $db->Execute("SELECT avatar_id, author_name FROM " . TABLE_ZX_BLOG_AVATARS . " ORDER BY author_name");
            foreach ($avQuery as $av) {
                $avatarsList[] = ['id' => $av['avatar_id'], 'text' => $av['author_name']];
            }

            $allCategories = $db->Execute(
                "SELECT c.category_id, cd.category_name
                 FROM " . TABLE_ZX_BLOG_CATEGORIES . " c
                 LEFT JOIN " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . " cd
                   ON c.category_id = cd.category_id AND cd.language_id = " . (int)$_SESSION['languages_id'] . "
                 ORDER BY c.sort_order, cd.category_name"
            );

            echo zen_draw_form('blog_post', FILENAME_ZX_BLOG, 'tab=posts&action=save_post', 'post', 'enctype="multipart/form-data" class="form-horizontal"');
            echo zen_draw_hidden_field('post_id', (string)$post['post_id']);
            ?>
            <h3><?php echo ($pID > 0) ? TEXT_HEADING_EDIT_POST : TEXT_HEADING_NEW_POST; ?></h3>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_POST_STATUS; ?></label>
                <div class="col-sm-9">
                    <label class="radio-inline">
                        <?php echo zen_draw_radio_field('status', '1', (int)$post['status'] === 1) . ' ' . TEXT_ENABLED; ?>
                    </label>
                    <label class="radio-inline">
                        <?php echo zen_draw_radio_field('status', '0', (int)$post['status'] === 0) . ' ' . TEXT_DISABLED; ?>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_POST_PINNED; ?></label>
                <div class="col-sm-9">
                    <label class="checkbox-inline">
                        <?php echo zen_draw_checkbox_field('is_pinned', '1', (int)$post['is_pinned'] === 1); ?>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_POST_AUTHOR_AVATAR; ?></label>
                <div class="col-sm-4">
                    <?php echo zen_draw_pull_down_menu('avatar_id', $avatarsList, (string)$post['avatar_id'], 'class="form-control"'); ?>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_POST_DATE_SCHEDULED; ?></label>
                <div class="col-sm-4">
                    <input type="datetime-local" name="date_scheduled" value="<?php echo zen_output_string_protected($scheduledHtmlValue); ?>" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo sprintf(TEXT_POST_FEATURED_IMAGE, $currentYearMonth); ?></label>
                <div class="col-sm-6">
                    <?php echo zen_draw_file_field('featured_image_file', false, 'class="form-control"'); ?>
                    <?php if (!empty($post['featured_image'])) { ?>
                        <div style="margin-top:8px;">
                            <strong><?php echo TEXT_POST_EXISTING_IMAGE; ?></strong>
                            <code>images/<?php echo zen_output_string_protected($post['featured_image']); ?></code><br>
                            <label>
                                <?php echo zen_draw_checkbox_field('delete_featured_image', '1', false) . ' ' . TEXT_POST_DELETE_IMAGE; ?>
                            </label>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_POST_CATEGORIES; ?></label>
                <div class="col-sm-6">
                    <?php foreach ($allCategories as $cat) { ?>
                        <div class="checkbox">
                            <label>
                                <?php
                                echo zen_draw_checkbox_field(
                                    'categories[]',
                                    (string)$cat['category_id'],
                                    in_array((int)$cat['category_id'], $selectedCategories, true)
                                ) . ' ' . zen_output_string_protected($cat['category_name']);
                                ?>
                            </label>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_POST_RELATED_PRODUCTS; ?></label>
                <div class="col-sm-6">
                    <?php echo zen_draw_input_field('related_products', zen_output_string_protected($relatedProductsStr), 'class="form-control"'); ?>
                </div>
            </div>

            <hr>
            <?php
            foreach ($languages as $lang) {
                $lId = (int)$lang['id'];
                $tr = $translations[$lId] ?? [];

                $tagList = [];
                if ($pID > 0) {
                    $tQuery = $db->Execute(
                        "SELECT t.tag_name
                         FROM " . TABLE_ZX_BLOG_TAGS . " t
                         INNER JOIN " . TABLE_ZX_BLOG_POSTS_TO_TAGS . " pt ON t.tag_id = pt.tag_id
                         WHERE pt.post_id = " . $pID . " AND t.language_id = " . $lId
                    );
                    foreach ($tQuery as $tRow) {
                        $tagList[] = $tRow['tag_name'];
                    }
                }
                $tagsString = implode(', ', $tagList);
                ?>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <strong><?php echo zen_image(DIR_WS_CATALOG_LANGUAGES . $lang['directory'] . '/images/' . $lang['image'], $lang['name']) . ' ' . $lang['name']; ?></strong>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_POST_TITLE; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_input_field('post_title[' . $lId . ']', zen_output_string_protected($tr['post_title'] ?? ''), 'class="form-control" required'); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_POST_SLUG; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_input_field('post_slug[' . $lId . ']', zen_output_string_protected($tr['post_slug'] ?? ''), 'class="form-control"'); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_POST_SUMMARY; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_textarea_field('post_summary[' . $lId . ']', 'soft', '100', '3', htmlspecialchars($tr['post_summary'] ?? '', ENT_COMPAT, CHARSET, true), 'class="form-control"'); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_POST_CONTENT; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_textarea_field('post_content[' . $lId . ']', 'soft', '100', '14', htmlspecialchars($tr['post_content'] ?? '', ENT_COMPAT, CHARSET, true), 'class="editorHook form-control"'); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_POST_TAGS; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_input_field('post_tags[' . $lId . ']', zen_output_string_protected($tagsString), 'class="form-control"'); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_POST_META_TITLE; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_input_field('meta_title[' . $lId . ']', zen_output_string_protected($tr['meta_title'] ?? ''), 'class="form-control"'); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_POST_META_DESCRIPTION; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_textarea_field('meta_description[' . $lId . ']', 'soft', '100', '2', htmlspecialchars($tr['meta_description'] ?? '', ENT_COMPAT, CHARSET, true), 'class="form-control"'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary"><?php echo IMAGE_SAVE; ?></button>
                    <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=posts'); ?>" class="btn btn-default"><?php echo IMAGE_CANCEL; ?></a>
                </div>
            </div>
            <?php
            echo '</form>';
        } else {
            $postsSql = "SELECT p.*, pd.post_title, a.author_name
                         FROM " . TABLE_ZX_BLOG_POSTS . " p
                         LEFT JOIN " . TABLE_ZX_BLOG_POSTS_DESCRIPTION . " pd
                           ON p.post_id = pd.post_id AND pd.language_id = " . (int)$_SESSION['languages_id'] . "
                         LEFT JOIN " . TABLE_ZX_BLOG_AVATARS . " a
                           ON p.avatar_id = a.avatar_id
                         ORDER BY p.is_pinned DESC, p.date_added DESC";
            $postsResults = $db->Execute($postsSql);
            ?>
            <div class="row" style="margin-bottom:15px;">
                <div class="col-sm-12 text-right">
                    <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=posts&action=new_post'); ?>" class="btn btn-primary">
                        <?php echo BUTTON_NEW_POST; ?>
                    </a>
                </div>
            </div>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th><?php echo TABLE_HEADING_ID; ?></th>
                        <th><?php echo TABLE_HEADING_TITLE; ?></th>
                        <th><?php echo TABLE_HEADING_AUTHOR; ?></th>
                        <th><?php echo TABLE_HEADING_PINNED; ?></th>
                        <th><?php echo TABLE_HEADING_STATUS; ?></th>
                        <th><?php echo TABLE_HEADING_VIEWS; ?></th>
                        <th><?php echo TABLE_HEADING_DATE_ADDED; ?></th>
                        <th class="text-right"><?php echo TABLE_HEADING_ACTION; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postsResults as $row) {
                        $isFutureScheduled = !empty($row['date_scheduled']) && strtotime((string)$row['date_scheduled']) > time();
                        ?>
                        <tr>
                            <td><?php echo (int)$row['post_id']; ?></td>
                            <td><?php echo zen_output_string_protected($row['post_title'] ?? ''); ?></td>
                            <td><?php echo zen_output_string_protected($row['author_name'] ?? '-'); ?></td>
                            <td><?php echo ((int)$row['is_pinned'] === 1) ? TEXT_YES : TEXT_NO; ?></td>
                            <td>
                                <?php if ((int)$row['status'] === 1) { ?>
                                    <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=posts&action=setflag_post&flag=0&pID=' . (int)$row['post_id']); ?>">
                                        <?php echo $isFutureScheduled ? zen_icon('status-yellow') : zen_icon('status-green'); ?>
                                    </a>
                                    <?php if ($isFutureScheduled) { ?>
                                        <span class="label label-warning"><?php echo TEXT_SCHEDULED_BADGE; ?></span>
                                    <?php } ?>
                                <?php } else { ?>
                                    <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=posts&action=setflag_post&flag=1&pID=' . (int)$row['post_id']); ?>">
                                        <?php echo zen_icon('status-red'); ?>
                                    </a>
                                <?php } ?>
                            </td>
                            <td><?php echo (int)$row['views_count']; ?></td>
                            <td><?php echo zen_output_string_protected($row['date_scheduled'] ?: $row['date_added']); ?></td>
                            <td class="text-right">
                                <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=posts&action=edit_post&pID=' . (int)$row['post_id']); ?>" class="btn btn-xs btn-default">
                                    <?php echo IMAGE_EDIT; ?>
                                </a>
                                <?php
                                echo zen_draw_form('del_post_' . (int)$row['post_id'], FILENAME_ZX_BLOG, 'tab=posts&action=delete_post_confirm', 'post', 'style="display:inline;" onsubmit="return confirm(\'' . zen_output_string_protected(TEXT_DELETE_POST_INTRO) . '\');"');
                                echo zen_draw_hidden_field('post_id', (string)$row['post_id']);
                                ?>
                                <button type="submit" class="btn btn-xs btn-danger"><?php echo IMAGE_DELETE; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php
        }
    }

    if ($tab === 'categories') {
        if ($action === 'new_category' || $action === 'edit_category') {
            $cID = (int)($_GET['cID'] ?? 0);
            $cat = ['category_id' => 0, 'sort_order' => 0, 'status' => 1];
            $catTrans = [];
            if ($cID > 0) {
                $cQuery = $db->Execute("SELECT * FROM " . TABLE_ZX_BLOG_CATEGORIES . " WHERE category_id = " . $cID);
                if (!$cQuery->EOF) {
                    $cat = $cQuery->fields;
                }
                $cdQuery = $db->Execute("SELECT * FROM " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . " WHERE category_id = " . $cID);
                foreach ($cdQuery as $cdRow) {
                    $catTrans[(int)$cdRow['language_id']] = $cdRow;
                }
            }

            echo zen_draw_form('blog_category', FILENAME_ZX_BLOG, 'tab=categories&action=save_category', 'post', 'class="form-horizontal"');
            echo zen_draw_hidden_field('category_id', (string)$cat['category_id']);
            ?>
            <h3><?php echo ($cID > 0) ? TEXT_HEADING_EDIT_CATEGORY : TEXT_HEADING_NEW_CATEGORY; ?></h3>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_CATEGORY_STATUS; ?></label>
                <div class="col-sm-9">
                    <label class="radio-inline">
                        <?php echo zen_draw_radio_field('status', '1', (int)$cat['status'] === 1) . ' ' . TEXT_ENABLED; ?>
                    </label>
                    <label class="radio-inline">
                        <?php echo zen_draw_radio_field('status', '0', (int)$cat['status'] === 0) . ' ' . TEXT_DISABLED; ?>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_CATEGORY_SORT_ORDER; ?></label>
                <div class="col-sm-3">
                    <?php echo zen_draw_input_field('sort_order', (string)$cat['sort_order'], 'class="form-control"'); ?>
                </div>
            </div>

            <?php foreach ($languages as $lang) {
                $lId = (int)$lang['id'];
                $ct = $catTrans[$lId] ?? [];
                ?>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <strong><?php echo zen_image(DIR_WS_CATALOG_LANGUAGES . $lang['directory'] . '/images/' . $lang['image'], $lang['name']) . ' ' . $lang['name']; ?></strong>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_CATEGORY_NAME; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_input_field('category_name[' . $lId . ']', zen_output_string_protected($ct['category_name'] ?? ''), 'class="form-control" required'); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_CATEGORY_SLUG; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_input_field('category_slug[' . $lId . ']', zen_output_string_protected($ct['category_slug'] ?? ''), 'class="form-control"'); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo TEXT_CATEGORY_DESCRIPTION; ?></label>
                            <div class="col-sm-9">
                                <?php echo zen_draw_textarea_field('category_description[' . $lId . ']', 'soft', '80', '3', htmlspecialchars($ct['category_description'] ?? '', ENT_COMPAT, CHARSET, true), 'class="form-control"'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary"><?php echo IMAGE_SAVE; ?></button>
                    <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=categories'); ?>" class="btn btn-default"><?php echo IMAGE_CANCEL; ?></a>
                </div>
            </div>
            </form>
            <?php
        } else {
            $cats = $db->Execute(
                "SELECT c.*, cd.category_name
                 FROM " . TABLE_ZX_BLOG_CATEGORIES . " c
                 LEFT JOIN " . TABLE_ZX_BLOG_CATEGORIES_DESCRIPTION . " cd
                   ON c.category_id = cd.category_id AND cd.language_id = " . (int)$_SESSION['languages_id'] . "
                 ORDER BY c.sort_order, cd.category_name"
            );
            ?>
            <div class="row" style="margin-bottom:15px;">
                <div class="col-sm-12 text-right">
                    <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=categories&action=new_category'); ?>" class="btn btn-primary">
                        <?php echo BUTTON_NEW_CATEGORY; ?>
                    </a>
                </div>
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo TABLE_HEADING_ID; ?></th>
                        <th><?php echo TABLE_HEADING_TITLE; ?></th>
                        <th><?php echo TABLE_HEADING_SORT_ORDER; ?></th>
                        <th><?php echo TABLE_HEADING_STATUS; ?></th>
                        <th class="text-right"><?php echo TABLE_HEADING_ACTION; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cats as $cRow) { ?>
                        <tr>
                            <td><?php echo (int)$cRow['category_id']; ?></td>
                            <td><?php echo zen_output_string_protected($cRow['category_name'] ?? ''); ?></td>
                            <td><?php echo (int)$cRow['sort_order']; ?></td>
                            <td><?php echo ((int)$cRow['status'] === 1) ? TEXT_ENABLED : TEXT_DISABLED; ?></td>
                            <td class="text-right">
                                <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=categories&action=edit_category&cID=' . (int)$cRow['category_id']); ?>" class="btn btn-xs btn-default">
                                    <?php echo IMAGE_EDIT; ?>
                                </a>
                                <?php
                                echo zen_draw_form('del_cat_' . (int)$cRow['category_id'], FILENAME_ZX_BLOG, 'tab=categories&action=delete_category_confirm', 'post', 'style="display:inline;" onsubmit="return confirm(\'' . zen_output_string_protected(TEXT_DELETE_CATEGORY_INTRO) . '\');"');
                                echo zen_draw_hidden_field('category_id', (string)$cRow['category_id']);
                                ?>
                                <button type="submit" class="btn btn-xs btn-danger"><?php echo IMAGE_DELETE; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php
        }
    }

    if ($tab === 'avatars') {
        if ($action === 'new_avatar' || $action === 'edit_avatar') {
            $aID = (int)($_GET['aID'] ?? 0);
            $av = ['avatar_id' => 0, 'author_name' => '', 'author_bio' => '', 'avatar_image' => ''];
            if ($aID > 0) {
                $aQuery = $db->Execute("SELECT * FROM " . TABLE_ZX_BLOG_AVATARS . " WHERE avatar_id = " . $aID);
                if (!$aQuery->EOF) {
                    $av = $aQuery->fields;
                }
            }
            echo zen_draw_form('blog_avatar', FILENAME_ZX_BLOG, 'tab=avatars&action=save_avatar', 'post', 'enctype="multipart/form-data" class="form-horizontal"');
            echo zen_draw_hidden_field('avatar_id', (string)$av['avatar_id']);
            ?>
            <h3><?php echo ($aID > 0) ? TEXT_HEADING_EDIT_AVATAR : TEXT_HEADING_NEW_AVATAR; ?></h3>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_AVATAR_AUTHOR_NAME; ?></label>
                <div class="col-sm-6">
                    <?php echo zen_draw_input_field('author_name', zen_output_string_protected($av['author_name']), 'class="form-control" required'); ?>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_AVATAR_AUTHOR_BIO; ?></label>
                <div class="col-sm-6">
                    <?php echo zen_draw_textarea_field('author_bio', 'soft', '60', '3', htmlspecialchars($av['author_bio'], ENT_COMPAT, CHARSET, true), 'class="form-control"'); ?>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo TEXT_AVATAR_IMAGE; ?></label>
                <div class="col-sm-6">
                    <?php echo zen_draw_file_field('avatar_image_file', false, 'class="form-control"'); ?>
                    <?php if (!empty($av['avatar_image'])) { ?>
                        <div style="margin-top:10px;">
                            <strong><?php echo TEXT_AVATAR_CURRENT_IMAGE; ?></strong><br>
                            <?php echo zen_image(DIR_WS_CATALOG_IMAGES . $av['avatar_image'], $av['author_name'], 64, 64); ?>
                            <code>images/<?php echo zen_output_string_protected($av['avatar_image']); ?></code><br>
                            <label>
                                <?php echo zen_draw_checkbox_field('delete_avatar_file', '1', false) . ' ' . TEXT_AVATAR_DELETE_FILE; ?>
                            </label>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary"><?php echo IMAGE_SAVE; ?></button>
                    <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=avatars'); ?>" class="btn btn-default"><?php echo IMAGE_CANCEL; ?></a>
                </div>
            </div>
            </form>
            <?php
        } else {
            $avatars = $db->Execute("SELECT * FROM " . TABLE_ZX_BLOG_AVATARS . " ORDER BY author_name");
            ?>
            <div class="row" style="margin-bottom:15px;">
                <div class="col-sm-12 text-right">
                    <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=avatars&action=new_avatar'); ?>" class="btn btn-primary">
                        <?php echo BUTTON_NEW_AVATAR; ?>
                    </a>
                </div>
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo TABLE_HEADING_ID; ?></th>
                        <th><?php echo TEXT_AVATAR_CURRENT_IMAGE; ?></th>
                        <th><?php echo TABLE_HEADING_AUTHOR; ?></th>
                        <th class="text-right"><?php echo TABLE_HEADING_ACTION; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($avatars as $avRow) { ?>
                        <tr>
                            <td><?php echo (int)$avRow['avatar_id']; ?></td>
                            <td>
                                <?php if (!empty($avRow['avatar_image'])) {
                                    echo zen_image(DIR_WS_CATALOG_IMAGES . $avRow['avatar_image'], $avRow['author_name'], 48, 48);
                                } ?>
                            </td>
                            <td>
                                <strong><?php echo zen_output_string_protected($avRow['author_name']); ?></strong><br>
                                <small><?php echo zen_output_string_protected($avRow['author_bio']); ?></small>
                            </td>
                            <td class="text-right">
                                <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tab=avatars&action=edit_avatar&aID=' . (int)$avRow['avatar_id']); ?>" class="btn btn-xs btn-default">
                                    <?php echo IMAGE_EDIT; ?>
                                </a>
                                <?php
                                echo zen_draw_form('del_av_' . (int)$avRow['avatar_id'], FILENAME_ZX_BLOG, 'tab=avatars&action=delete_avatar_confirm', 'post', 'style="display:inline;" onsubmit="return confirm(\'' . zen_output_string_protected(TEXT_DELETE_AVATAR_INTRO) . '\');"');
                                echo zen_draw_hidden_field('avatar_id', (string)$avRow['avatar_id']);
                                ?>
                                <button type="submit" class="btn btn-xs btn-danger"><?php echo IMAGE_DELETE; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php
        }
    }

    if ($tab === 'media') {
        $monthDirFs = zx_blog_ensure_dir(DIR_WS_ZX_BLOG_IMAGES . $currentYearMonth);
        $files = glob($monthDirFs . '*.{jpg,jpeg,png,gif,webp,avif}', GLOB_BRACE) ?: [];
        ?>
        <p><?php echo sprintf(TEXT_MEDIA_UPLOAD_HELP, $currentYearMonth); ?></p>
        <?php echo zen_draw_form('media_upload', FILENAME_ZX_BLOG, 'tab=media&action=upload_content_image', 'post', 'enctype="multipart/form-data" class="form-inline" style="margin-bottom:25px;"'); ?>
            <div class="form-group">
                <label><?php echo TEXT_MEDIA_FILE_LABEL; ?></label>
                <?php echo zen_draw_file_field('content_image_file', false, 'class="form-control" required'); ?>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo BUTTON_UPLOAD_CONTENT_IMAGE; ?></button>
        </form>

        <h4><?php echo sprintf(TEXT_MEDIA_RECENT_FILES, 'images/blog/' . $currentYearMonth . '/'); ?></h4>
        <table class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th><?php echo TEXT_MEDIA_COPY_PATH; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $filePath) {
                    $relPath = 'images/blog/' . $currentYearMonth . '/' . basename($filePath);
                    ?>
                    <tr>
                        <td><code><?php echo zen_output_string_protected($relPath); ?></code></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
    }
    ?>

    <div class="text-center" style="margin: 40px 0 20px; padding: 15px 0; border-top: 1px solid #e5e5e5; color: #777; font-size: 0.9em; background-color: #fafafa; border-radius: 4px;">
        Powered by <a href="https://zenexpert.com" target="_blank" rel="noreferrer" style="color: #3498db; text-decoration: none;">ZenExpert</a>
    </div>
</div>

<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
