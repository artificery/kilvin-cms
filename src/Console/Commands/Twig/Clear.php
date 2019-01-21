<?php

namespace Kilvin\Console\Commands\Twig;

use Illuminate\Console\Command;
use Twig_Environment;
use Illuminate\Filesystem\Filesystem;

/**
 * Artisan command to clear the Twig cache.
 */
class Clear extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $name = 'twig:clear';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Clear the Twig Cache';

    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        $twig     = $this->laravel['cms.twig'];
        $files    = $this->laravel['files'];
        $cacheDir = $twig->getCache();

        $files->deleteDirectory($cacheDir);

        if ($files->exists($cacheDir)) {
            $this->error('Twig cache failed to be cleared');
        } else {
            $this->info('Twig cache cleared');
        }
    }
}
