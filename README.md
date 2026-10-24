# ZX Blog - Zen Cart Blog plugin

**Platform:** Zen Cart 2.1.0 or newer  

> **Disclaimer:** *Please note that this plugin is installed and used at your own risk. If you are unsure of how to complete the installation procedure documented below, please seek support in the official Forum section (keep in mind Forums are public and work on voluntary basis) or contact me for paid assistance. ZenExpert takes no responsibility for problems related to the installation or use of this plugin.*

ZX Blog is a full-featured, multi-language content management and blogging suite built exclusively for Zen Cart 2.1.0 and newer. Engineered as a strict **Encapsulated Plugin**, it provides categories, tags, author avatar management, automatic monthly media folder partitioning (`images/blog/{yyyymm}/`), scheduled publishing, related store product linking, storefront sideboxes, and a one-click WordPress database and media migration tool with zero core file modifications.

## Table of Contents
- [Installation](#installation)
- [Features & Usage](#features--usage)
  - [Global Configuration Settings](#global-configuration-settings)
  - [Blog Posts Manager & HTML Editor](#blog-posts-manager--html-editor)
  - [Scheduled Publishing & Pinned Posts](#scheduled-publishing--pinned-posts)
  - [Categories Management](#categories-management)
  - [Author Avatars Manager (`images/blog/assets/`)](#author-avatars-manager-imagesblogassets)
  - [Monthly Content Image Partitioning (`images/blog/{yyyymm}/`)](#monthly-content-image-partitioning-imagesblogyyyymm)
  - [Storefront Sideboxes & Layout Boxes Controller](#storefront-sideboxes--layout-boxes-controller)
  - [Storefront Features, SEO & Security](#storefront-features-seo--security)
  - [WordPress Database & Media Migration Tool](#wordpress-database--media-migration-tool)
- [Uninstallation](#uninstallation)
- [Common Issues](#common-issues)
- [Sources and Credits](#sources-and-credits)
- [Support](#support)
- [License](#license)

## Installation
ZX Blog is built as a strict **Encapsulated Plugin** for Zen Cart 2.1.0+. All admin pages, storefront controllers, sideboxes, language arrays, and installers live inside `zc_plugins/ZxBlog/v1.0.1/`, requiring zero core file overwrites.

1. Backup your Zen Cart database and store files.
2. Upload the `ZxBlog` directory into your store's `zc_plugins/` directory so that the path matches `zc_plugins/ZxBlog/v1.0.1/manifest.php`.
3. Log into your Zen Cart Admin panel and navigate to **Modules > Plugin Manager**.
4. Locate **ZX Blog** in the list and click **Install**. The scripted installer will automatically:
    *   Create the image directories `images/blog/`, `images/blog/assets/`, and the current month directory `images/blog/YYYYMM/`.
    *   Create the nine required database tables: `zx_blog_posts`, `zx_blog_posts_description`, `zx_blog_categories`, `zx_blog_categories_description`, `zx_blog_avatars`, `zx_blog_posts_to_categories`, `zx_blog_tags`, `zx_blog_posts_to_tags`, and `zx_blog_posts_to_products`.
    *   Register the configuration group under **Configuration > ZX Blog Configuration**.
    *   Register the main management interface under **Tools > ZX Blog Manager**.
    *   Register the WordPress migration utility under **Extras > ZX Blog WordPress Import**.
    *   Register all three storefront sideboxes (`blog_categories.php`, `blog_recent_posts.php`, `blog_tags.php`) in `layout_boxes` with encapsulated `plugin_details`.
5. Navigate to **Tools > Layout Boxes Controller** to adjust the left/right column placement and sort order of the three new blog sideboxes for your active template.
6. Access your storefront blog at `index.php?main_page=blog`.

## Features & Usage

### Global Configuration Settings
Navigate to **Configuration > ZX Blog Configuration** to control global storefront behavior:

| Configuration Title | Key | Default | Description |
| :--- | :--- | :--- | :--- |
| Enable ZX Blog | `ZX_BLOG_STATUS` | `true` | Master switch for the storefront blog page and all three blog sideboxes. |
| Posts Per Page | `ZX_BLOG_POSTS_PER_PAGE` | `6` | Number of articles displayed per page on main blog, category, tag, and search listings. |
| Sidebox: Recent Posts Limit | `ZX_BLOG_RECENT_SIDEBOX_LIMIT` | `5` | Maximum number of recent articles displayed in the Recent Blog Posts sidebox. |
| Sidebox: Tags Limit | `ZX_BLOG_TAGS_SIDEBOX_LIMIT` | `20` | Maximum number of popular tags displayed in the Blog Tags sidebox cloud. |
| Display Estimated Reading Time | `ZX_BLOG_SHOW_READING_TIME` | `true` | Automatically calculates and displays estimated reading time (at 200 words per minute) on listing cards and single articles. |

### Blog Posts Manager & HTML Editor
Navigate to **Tools > ZX Blog Manager** and select the **Posts** tab to create, edit, toggle status, or delete blog articles.
*   **HTML Editor Integration:** The top-right corner of the Blog Manager includes Zen Cart's native **Text Editor** selector dropdown. Selecting CKEditor or TinyMCE automatically hooks into the `.editorHook` Post Content textarea across all installed store languages.
*   **Multi-Language Support:** Post Title, URL Slug, Excerpt/Summary, Post Content, Tags, SEO Meta Title, and SEO Meta Description are stored per language in `zx_blog_posts_description` and `zx_blog_tags`.
*   **Related Store Products:** Enter comma-separated Zen Cart Product IDs (e.g., `12,45,89`) in the **Related Product IDs** field. Active store products linked here automatically render in a shoppable product grid at the bottom of the blog post.
*   **SEO Meta Tags & URL Slugs:** Each post supports custom `META_TAG_TITLE` and `META_TAG_DESCRIPTION` overrides. If the URL Slug is left blank, it is automatically generated from the Post Title.

### Scheduled Publishing & Pinned Posts
ZX Blog supports native scheduled publishing without requiring a server cron job:
*   **Immediate Publishing:** Leave the **Publish Date** field blank and set **Status** to *Enabled*. The post goes live immediately using its creation timestamp.
*   **Scheduled Future Publishing:** Set **Status** to *Enabled* and select a future date and time in the **Publish Date** picker. In the Admin Posts table, the post will display a yellow status icon with a `Scheduled` badge.
*   **Real-Time Trigger:** Storefront queries evaluate `(p.date_scheduled IS NULL OR p.date_scheduled <= NOW())` on every request. The exact second the server clock reaches the scheduled timestamp, the article automatically appears on the blog listing, single post page, and sideboxes.
*   **Pin to Top:** Checking **Pin to Top** marks the post as *Featured* and forces it to the top of storefront listings ahead of chronological posts.

### Categories Management
Open the **Categories** tab inside **Tools > ZX Blog Manager** to organize your articles into topic hubs:
*   Create unlimited blog categories with custom sort orders, enable/disable toggles, localized names, URL slugs, and rich HTML category descriptions.
*   Posts can be assigned to multiple categories simultaneously via checkboxes on the Post edit screen.
*   When a visitor filters the blog by category (`index.php?main_page=blog&bPath=X`), the category's title and description are rendered at the top of the listing and added to the breadcrumb trail.

### Author Avatars Manager (`images/blog/assets/`)
Open the **Author Avatars** tab inside **Tools > ZX Blog Manager** to manage blog authors and their profile images:
*   **Dedicated Asset Storage:** All author avatar images are uploaded directly to `images/blog/assets/`.
*   **Full Lifecycle Management:** Create author profiles with an Author Name, Short Bio, and Avatar Image. Replacing an avatar or deleting an author profile automatically removes the old image file from `images/blog/assets/` and safely resets any linked posts to `avatar_id = 0`.

### Monthly Content Image Partitioning (`images/blog/{yyyymm}/`)
To prevent a single image folder from accumulating thousands of unorganized files over time, ZX Blog automatically partitions all post media by year and month (`YYYYMM`, such as `images/blog/202610/`):
*   **Featured Images:** Uploaded directly from the Post edit screen into `images/blog/{yyyymm}/`. Checking *Remove current featured image* or deleting a post removes the physical file from the server.
*   **Inline Content Image Uploader Tab:** Open the **Content Image Uploader** tab in **Tools > ZX Blog Manager** to upload inline images directly to the current month's folder (`images/blog/{yyyymm}/`) and copy the ready-to-use relative path (`images/blog/202610/your-image.jpg`) into CKEditor, TinyMCE, or Plain Text HTML.

### Storefront Sideboxes & Layout Boxes Controller
ZX Blog includes three encapsulated sidebox modules located in `catalog/includes/modules/sideboxes/`:
*   **Blog Categories (`blog_categories.php`):** Lists all active blog categories along with a live count of published posts in each category.
*   **Recent Blog Posts (`blog_recent_posts.php`):** Displays the latest published articles with publication dates.
*   **Blog Tags (`blog_tags.php`):** Renders a tag cloud ordered by tag frequency, linking to `index.php?main_page=blog&tag=slug`.

> **Managing Sidebox Position:** Navigate to **Tools > Layout Boxes Controller** in your Zen Cart Admin to enable/disable each sidebox, assign it to the Left or Right column, and set its sort order.

### Storefront Features, SEO & Security
*   **Schema.org Structured Data:** Single blog posts automatically output `https://schema.org/BlogPosting` microdata (`headline`, `author`, `datePublished`, `image`, and `articleBody`) and set a clean canonical URL via Zen Cart's `$canonicalLink`.
*   **Hardened Frontend Search & Filtering:** Visitors can search articles by keyword (`bsearch`), filter by category (`bPath`), or filter by tag (`tag`). Search inputs are stripped of HTML/control characters, capped at 100 characters, escaped against SQL `LIKE` wildcard DoS attacks (`%` and `_`), and bound via Zen Cart's `$db->bindVars(..., 'string')`.
*   **Admin Request Sanitizer Integration:** Registers complex sanitization rules at autoloader breakpoint `68` (before Zen Cart's `init_sanitize.php` runs at breakpoint `70`) so rich HTML in `post_content`, `post_summary`, and `category_description` is preserved safely.

### WordPress Database & Media Migration Tool
Navigate to **Extras > ZX Blog WordPress Import** (`admin/blog_wp_import.php`) to migrate an existing WordPress blog into Zen Cart.
1. Enter your WordPress MySQL database credentials (**DB Host**, **Database Name**, **Username**, **Password**, and **Table Prefix** such as `wp_`).
2. *Optional:* Enter the **Local WordPress Filesystem Path** (e.g., `/home/user/public_html/wp` or `/var/www/html/wp-content/uploads`). If left blank, the importer automatically reads `siteurl` from WordPress's `options` table and downloads images via cURL fallback.
3. Click **Test Connection & Preview Counts** to verify connectivity and preview the exact number of posts, categories, tags, and authors found before making any changes.
4. Click **Run WordPress Import Now** to execute the migration.

**What the WordPress Importer Transfers:**
*   **Authors & Avatars:** Imports WordPress users who have authored posts into `zx_blog_avatars`, including their biography (`description` meta) and local avatar or optional Gravatar image into `images/blog/assets/`.
*   **Categories & Tags:** Imports all WordPress categories and post tags (with decoded slugs and descriptions) and maps all post-to-term relationships.
*   **Posts, Sticky Status & Scheduled Dates:** Imports published, future-scheduled, and (optionally) draft/private posts. WordPress `sticky_posts` are automatically marked as **Pinned** (`is_pinned = 1`).
*   **Featured & Inline Content Images:** Copies physical image files from WordPress's `wp-content/uploads/YYYY/MM/` directory into `images/blog/YYYYMM/`, rewrites inline `<img src="...">` paths inside `post_content` to `images/blog/YYYYMM/filename.ext`, and strips legacy WordPress `srcset` and `sizes` attributes so images never 404 after WordPress is removed.
*   **SEO Metadata & View Counts:** Automatically migrates custom SEO titles and meta descriptions from **Yoast SEO**, **Rank Math**, or **All in One SEO**, as well as post view counts.
*   **Idempotent Safe Re-Runs:** Posts, categories, tags, and authors are matched by slug/name. Re-running the importer updates existing records rather than creating duplicates.

## Uninstallation
To uninstall the plugin, navigate to **Modules > Plugin Manager**, select **ZX Blog**, and click **Uninstall**. The uninstaller deregisters the Admin menu pages and removes the sidebox entries from `layout_boxes` while leaving your blog database tables and uploaded images in `images/blog/` intact so you never accidentally lose articles during a plugin upgrade.

## Common Issues
*   **Sideboxes listed under "Missing Boxes" in Layout Boxes Controller:** If you installed an earlier build where `plugin_details` was not populated in `layout_boxes`, click **Delete** next to the three blog sideboxes in **Tools > Layout Boxes Controller**. Zen Cart's `SideboxFinder` will immediately re-register them with `plugin_details = 'ZxBlog/v1.0.1'`.
*   **HTML Editor (CKEditor / TinyMCE) not showing on Post Content:** Use the **Text Editor** dropdown in the top-right corner of **Tools > ZX Blog Manager** (or under **Configuration > My Store > HTML Editor**) to switch from *Plain Text* to your installed editor.
*   **Image uploads failing:** Verify that your web server has write permissions (`0755` or `0775`) on the `images/` directory so PHP can create `images/blog/assets/` and monthly `images/blog/YYYYMM/` subdirectories.

## Sources and Credits
* External libraries used in this plugin: none

## Contributing
Found a bug? Feel free to submit an issue or pull request.

## License
GNU Public License V2.0
