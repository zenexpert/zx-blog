<?php

declare(strict_types=1);

require 'includes/application_top.php';
require_once __DIR__ . '/includes/classes/ZxBlogWordpressImporter.php';

$languages = zen_get_languages();
$action = $_GET['action'] ?? '';

if ($action === 'wp_import') {
    $wpHost = trim($_POST['wp_db_host'] ?? 'localhost');
    $wpName = trim($_POST['wp_db_name'] ?? '');
    $wpUser = trim($_POST['wp_db_user'] ?? '');
    $wpPass = (string)($_POST['wp_db_pass'] ?? '');
    $wpPrefix = trim($_POST['wp_table_prefix'] ?? 'wp_');
    $wpFsPath = trim($_POST['wp_fs_path'] ?? '');
    $wpLangId = (int)($_POST['wp_target_language_id'] ?? 0);
    $copyMedia = isset($_POST['wp_copy_media']);
    $fetchGravatars = isset($_POST['wp_fetch_gravatars']);
    $importDrafts = isset($_POST['wp_import_drafts']);
    $stripGutenberg = isset($_POST['wp_strip_gutenberg']);
    $mode = $_POST['wp_mode'] ?? 'test';

    try {
        $importer = new ZxBlogWordpressImporter(
            $wpHost,
            $wpName,
            $wpUser,
            $wpPass,
            $wpPrefix,
            $wpFsPath,
            $copyMedia,
            $fetchGravatars,
            $importDrafts,
            $stripGutenberg,
            $wpLangId
        );

        if ($mode === 'run') {
            @set_time_limit(300);
            $stats = $importer->runImport();
            $messageStack->add_session(
                sprintf(
                    SUCCESS_WP_IMPORT_SUMMARY,
                    $stats['posts_imported'],
                    $stats['posts_updated'],
                    $stats['categories_imported'],
                    $stats['tags_imported'],
                    $stats['authors_imported'],
                    $stats['images_copied']
                ),
                'success'
            );
            zen_redirect(zen_href_link(FILENAME_ZX_BLOG, 'tab=posts'));
        } else {
            $wpPreviewCounts = $importer->getPreviewCounts();
            $messageStack->add(
                sprintf(
                    SUCCESS_WP_CONNECTION_PREVIEW,
                    $wpPreviewCounts['posts'],
                    $wpPreviewCounts['categories'],
                    $wpPreviewCounts['tags'],
                    $wpPreviewCounts['authors']
                ),
                'success'
            );
        }
    } catch (Throwable $e) {
        $messageStack->add(sprintf(ERROR_WP_IMPORT_FAILED, $e->getMessage()), 'error');
    }
}

$langOptions = [['id' => '0', 'text' => TEXT_WP_ALL_LANGUAGES]];
foreach ($languages as $lang) {
    $langOptions[] = ['id' => (string)$lang['id'], 'text' => $lang['name']];
}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-8">
            <h1><?php echo HEADING_TITLE; ?></h1>
        </div>
        <div class="col-sm-4 text-right" style="margin-top:20px;">
            <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG); ?>" class="btn btn-default">
                <?php echo BUTTON_BACK_TO_BLOG_MANAGER; ?>
            </a>
        </div>
    </div>

    <p><?php echo TEXT_WP_IMPORT_INTRO; ?></p>
    <hr>

    <?php
    echo zen_draw_form('wp_import_form', FILENAME_ZX_BLOG_WP_IMPORT, 'action=wp_import', 'post', 'class="form-horizontal"');
    ?>
    <div class="form-group">
        <label class="col-sm-3 control-label"><?php echo TEXT_WP_DB_HOST; ?></label>
        <div class="col-sm-5">
            <?php echo zen_draw_input_field('wp_db_host', zen_output_string_protected($_POST['wp_db_host'] ?? DB_SERVER), 'class="form-control" required'); ?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label"><?php echo TEXT_WP_DB_NAME; ?></label>
        <div class="col-sm-5">
            <?php echo zen_draw_input_field('wp_db_name', zen_output_string_protected($_POST['wp_db_name'] ?? ''), 'class="form-control" required'); ?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label"><?php echo TEXT_WP_DB_USER; ?></label>
        <div class="col-sm-5">
            <?php echo zen_draw_input_field('wp_db_user', zen_output_string_protected($_POST['wp_db_user'] ?? ''), 'class="form-control" required'); ?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label"><?php echo TEXT_WP_DB_PASS; ?></label>
        <div class="col-sm-5">
            <?php echo zen_draw_password_field('wp_db_pass', $_POST['wp_db_pass'] ?? '', false, 'class="form-control"'); ?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label"><?php echo TEXT_WP_TABLE_PREFIX; ?></label>
        <div class="col-sm-3">
            <?php echo zen_draw_input_field('wp_table_prefix', zen_output_string_protected($_POST['wp_table_prefix'] ?? 'wp_'), 'class="form-control" required'); ?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label"><?php echo TEXT_WP_FS_PATH; ?></label>
        <div class="col-sm-6">
            <?php echo zen_draw_input_field('wp_fs_path', zen_output_string_protected($_POST['wp_fs_path'] ?? ''), 'class="form-control" placeholder="' . zen_output_string_protected(DIR_FS_CATALOG) . 'wp"'); ?>
            <span class="help-block"><?php echo TEXT_WP_FS_PATH_HELP; ?></span>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label"><?php echo TEXT_WP_TARGET_LANGUAGE; ?></label>
        <div class="col-sm-4">
            <?php echo zen_draw_pull_down_menu('wp_target_language_id', $langOptions, (string)($_POST['wp_target_language_id'] ?? '0'), 'class="form-control"'); ?>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
            <div class="checkbox">
                <label>
                    <?php echo zen_draw_checkbox_field('wp_copy_media', '1', !isset($_POST['wp_mode']) || isset($_POST['wp_copy_media'])) . ' ' . TEXT_WP_OPT_COPY_MEDIA; ?>
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <?php echo zen_draw_checkbox_field('wp_fetch_gravatars', '1', isset($_POST['wp_fetch_gravatars'])) . ' ' . TEXT_WP_OPT_GRAVATARS; ?>
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <?php echo zen_draw_checkbox_field('wp_import_drafts', '1', !isset($_POST['wp_mode']) || isset($_POST['wp_import_drafts'])) . ' ' . TEXT_WP_OPT_IMPORT_DRAFTS; ?>
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <?php echo zen_draw_checkbox_field('wp_strip_gutenberg', '1', !isset($_POST['wp_mode']) || isset($_POST['wp_strip_gutenberg'])) . ' ' . TEXT_WP_OPT_STRIP_GUTENBERG; ?>
                </label>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
            <button type="submit" name="wp_mode" value="test" class="btn btn-info">
                <?php echo BUTTON_WP_TEST_CONNECTION; ?>
            </button>
            <button type="submit" name="wp_mode" value="run" class="btn btn-primary" onclick="return confirm('Start importing WordPress content into ZX Blog?');">
                <?php echo BUTTON_WP_RUN_IMPORT; ?>
            </button>
        </div>
    </div>
    </form>
</div>

<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>