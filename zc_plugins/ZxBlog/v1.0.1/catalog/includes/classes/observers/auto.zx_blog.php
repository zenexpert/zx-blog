<?php

declare(strict_types=1);

use Zencart\Traits\InteractsWithPlugins;

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class zcObserverZxBlog extends base
{
    use InteractsWithPlugins;

    public function __construct()
    {
        $this->attach($this, ['NOTIFY_HTML_HEAD_END']);
        $this->detectZcPluginDetails(__DIR__);
    }

    public function update(&$class, $eventID, $paramsArray = []): void
    {
        if ($eventID !== 'NOTIFY_HTML_HEAD_END') {
            return;
        }
        if (defined('ZX_BLOG_STATUS') && ZX_BLOG_STATUS !== 'true') {
            return;
        }

        $currentPageBase = is_string($paramsArray) ? $paramsArray : ($_GET['main_page'] ?? '');
        $this->linkCatalogStylesheet('zx_blog.css', $currentPageBase);
    }
}
