<?php

declare(strict_types=1);
?>
<div class="centerColumn" id="zxBlogDefault">
    <div class="zx-blog-search-bar" style="margin-bottom:20px;">
        <?php echo zen_draw_form('blog_search', zen_href_link(FILENAME_ZX_BLOG, '', $request_type ?? 'NONSSL', false), 'get'); ?>
        <?php echo zen_draw_hidden_field('main_page', FILENAME_ZX_BLOG); ?>
        <?php echo zen_hide_session_id(); ?>
        <input type="search" name="bsearch" maxlength="100" value="<?php echo zen_output_string_protected($searchKeyword ?? ''); ?>" placeholder="<?php echo zen_output_string_protected(TEXT_BLOG_SEARCH_PLACEHOLDER); ?>" class="form-control" style="display:inline-block;width:70%;">
        <button type="submit" class="cssButton submit_button button"><?php echo zen_output_string_protected(TEXT_BLOG_SEARCH_BUTTON); ?></button>
        </form>
    </div>

    <?php if (!empty($isSinglePost) && !empty($blogPost)) { ?>
        <article class="zx-blog-single-post" itemscope itemtype="https://schema.org/BlogPosting">
            <h1 itemprop="headline"><?php echo zen_output_string_protected($blogPost['post_title']); ?></h1>

            <div class="zx-blog-meta">
                <?php if (!empty($blogPost['avatar_image'])) { ?>
                    <span class="zx-blog-avatar">
                        <?php echo zen_image(DIR_WS_IMAGES . $blogPost['avatar_image'], $blogPost['author_name'] ?? '', 36, 36); ?>
                    </span>
                <?php } ?>
                <?php if (!empty($blogPost['author_name'])) { ?>
                    <span itemprop="author"><?php echo sprintf(TEXT_BLOG_BY_AUTHOR, zen_output_string_protected($blogPost['author_name'])); ?></span> &bull;
                <?php } ?>
                <time itemprop="datePublished" datetime="<?php echo date('c', strtotime((string)($blogPost['date_scheduled'] ?: $blogPost['date_added']))); ?>">
                    <?php echo sprintf(TEXT_BLOG_PUBLISHED_ON, zen_date_long($blogPost['date_scheduled'] ?: $blogPost['date_added'])); ?>
                </time>
                <?php if (defined('ZX_BLOG_SHOW_READING_TIME') && ZX_BLOG_SHOW_READING_TIME === 'true') { ?>
                    &bull; <span><?php echo sprintf(TEXT_BLOG_READING_TIME, (int)$blogPost['reading_time']); ?></span>
                <?php } ?>
                &bull; <span><?php echo sprintf(TEXT_BLOG_VIEWS, (int)$blogPost['views_count'] + 1); ?></span>
            </div>

            <?php if (!empty($blogPost['featured_image'])) { ?>
                <div class="zx-blog-featured-image" style="margin-bottom:20px;">
                    <?php echo zen_image(DIR_WS_IMAGES . $blogPost['featured_image'], $blogPost['post_title'], '', '', 'class="img-responsive" itemprop="image"'); ?>
                </div>
            <?php } ?>

            <div class="zx-blog-content" itemprop="articleBody">
                <?php echo $blogPost['post_content']; ?>
            </div>

            <?php if (!empty($blogPostCategories)) { ?>
                <div class="zx-blog-post-categories" style="margin-top:20px;">
                    <strong><?php echo TEXT_BLOG_CATEGORIES_LABEL; ?></strong>
                    <?php foreach ($blogPostCategories as $cat) { ?>
                        <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'bPath=' . (int)$cat['id']); ?>">
                            <?php echo zen_output_string_protected($cat['name']); ?>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (!empty($blogPostTags)) { ?>
                <div class="zx-blog-post-tags" style="margin-top:10px;">
                    <strong><?php echo TEXT_BLOG_TAGS_LABEL; ?></strong>
                    <?php foreach ($blogPostTags as $tag) { ?>
                        <a class="zx-blog-tag" href="<?php echo zen_href_link(FILENAME_ZX_BLOG, 'tag=' . urlencode((string)$tag['slug'])); ?>">
                            #<?php echo zen_output_string_protected($tag['name']); ?>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (!empty($blogRelatedProducts)) { ?>
                <section class="zx-blog-related-products" style="margin-top:30px;">
                    <h3><?php echo TEXT_BLOG_RELATED_PRODUCTS; ?></h3>
                    <div class="row">
                        <?php foreach ($blogRelatedProducts as $prod) {
                            $prodLink = zen_href_link(zen_get_info_page((int)$prod['products_id']), 'products_id=' . (int)$prod['products_id']);
                            ?>
                            <div class="col-sm-4 zx-blog-related-product-card">
                                <a href="<?php echo $prodLink; ?>">
                                    <?php echo zen_image(DIR_WS_IMAGES . $prod['products_image'], $prod['products_name'], SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT); ?><br>
                                    <strong><?php echo zen_output_string_protected($prod['products_name']); ?></strong>
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </section>
            <?php } ?>

            <div style="margin-top:25px;">
                <a href="<?php echo zen_href_link(FILENAME_ZX_BLOG); ?>" class="cssButton normal_button button">
                    <?php echo TEXT_BLOG_BACK_TO_LIST; ?>
                </a>
            </div>
        </article>
    <?php } else { ?>
        <h1><?php echo zen_output_string_protected($blogListingHeading ?? HEADING_TITLE); ?></h1>
        <?php if (!empty($blogCategoryDescription)) { ?>
            <div class="zx-blog-category-description" style="margin-bottom:20px;">
                <?php echo $blogCategoryDescription; ?>
            </div>
        <?php } ?>

        <?php if (isset($blogPostsList) && $blogPostsList->RecordCount() > 0) { ?>
            <div class="zx-blog-posts-grid">
                <?php foreach ($blogPostsList as $postItem) {
                    $postUrl = zen_href_link(FILENAME_ZX_BLOG, 'post_id=' . (int)$postItem['post_id']);
                    $readTime = zx_blog_reading_time($postItem['post_content'] ?? '');
                    ?>
                    <article class="zx-blog-card">
                        <?php if (!empty($postItem['featured_image'])) { ?>
                            <div class="zx-blog-card-image" style="margin-bottom:12px;">
                                <a href="<?php echo $postUrl; ?>">
                                    <?php echo zen_image(DIR_WS_IMAGES . $postItem['featured_image'], $postItem['post_title'], MEDIUM_IMAGE_WIDTH, MEDIUM_IMAGE_HEIGHT, 'class="img-responsive"'); ?>
                                </a>
                            </div>
                        <?php } ?>

                        <h2>
                            <?php if ((int)$postItem['is_pinned'] === 1) { ?>
                                <span class="badge"><?php echo TEXT_BLOG_PINNED_BADGE; ?></span>
                            <?php } ?>
                            <a href="<?php echo $postUrl; ?>"><?php echo zen_output_string_protected($postItem['post_title']); ?></a>
                        </h2>

                        <div class="zx-blog-card-meta">
                            <?php if (!empty($postItem['avatar_image'])) {
                                echo zen_image(DIR_WS_IMAGES . $postItem['avatar_image'], $postItem['author_name'] ?? '', 24, 24);
                            } ?>
                            <?php if (!empty($postItem['author_name'])) {
                                echo sprintf(TEXT_BLOG_BY_AUTHOR, zen_output_string_protected($postItem['author_name'])) . ' &bull; ';
                            } ?>
                            <?php echo zen_date_short($postItem['publish_date']); ?>
                            <?php if (defined('ZX_BLOG_SHOW_READING_TIME') && ZX_BLOG_SHOW_READING_TIME === 'true') { ?>
                                &bull; <?php echo sprintf(TEXT_BLOG_READING_TIME, $readTime); ?>
                            <?php } ?>
                        </div>

                        <div class="zx-blog-card-summary" style="margin-bottom:12px;">
                            <?php
                            if (!empty($postItem['post_summary'])) {
                                echo $postItem['post_summary'];
                            } else {
                                echo zen_trunc_string(strip_tags($postItem['post_content'] ?? ''), 220);
                            }
                            ?>
                        </div>

                        <a href="<?php echo $postUrl; ?>" class="cssButton normal_button button">
                            <?php echo TEXT_BLOG_READ_MORE; ?>
                        </a>
                    </article>
                <?php } ?>
            </div>

            <?php if ($blogSplit->number_of_rows > 0 && $blogSplit->number_of_pages > 1) { ?>
                <div class="navSplitPagesResult"><?php echo $blogSplit->display_count(TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></div>
                <div class="navSplitPagesLinks"><?php echo TEXT_RESULT_PAGE . ' ' . $blogSplit->display_links(MAX_DISPLAY_PAGE_LINKS, zen_get_all_get_params(['page', 'info', 'x', 'y', 'main_page'])); ?></div>
            <?php } ?>
        <?php } else { ?>
            <p><?php echo TEXT_BLOG_NO_POSTS_FOUND; ?></p>
        <?php } ?>
    <?php } ?>
</div>
