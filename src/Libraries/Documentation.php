<?php

namespace Kilvin\Libraries;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Cache\Repository as Cache;

class Documentation
{
    /**
     * The filesystem implementation.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * The cache implementation.
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Create a new documentation instance.
     *
     * @param  Filesystem  $files
     * @param  Cache  $cache
     * @return void
     */
    public function __construct(Filesystem $files, Cache $cache)
    {
        $this->files = $files;
        $this->cache = $cache;
    }

    /**
     * Get the given documentation page.
     *
     * @param  string  $page
     * @return string
     */
    public function get($page)
    {
        return $this->cache->remember('docs.'.$page, 5, function () use ($page) {
            $path = KILVIN_DOCS_PACKAGE_PATH.$page.'.md';

            if ($this->files->exists($path)) {
                return $this->replaceLinks(parsedown($this->files->get($path)));
            }

            return null;
        });
    }

    /**
     * Replace the path place-holder in links.
     *
     * @param  string  $content
     * @return string
     */
    public static function replaceLinks($content)
    {
        $path = kilvinCpUrl('docs');
        return str_replace('{{path}}', $path, $content);
    }
}
