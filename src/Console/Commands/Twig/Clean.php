<?php

namespace Kilvin\Console\Commands\Twig;

use Illuminate\Console\Command;
use Twig_Environment;
use Illuminate\Filesystem\Filesystem;

/**
 * Artisan command to clear the Twig cache.
 */
class Clean extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $name = 'twig:clean';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Clean the Twig Cache';

    /**
     * {@inheritdoc}
     */
    public function fire()
    {
        $twig     = $this->laravel['cms.twig'];
        $files    = $this->laravel['files'];
        $cacheDir = $twig->getCache();

        $files->deleteDirectory($cacheDir);

        if ($files->exists($cacheDir)) {
            $this->error('Twig cache failed to be cleaned');
        } else {
            $this->info('Twig cache cleaned');
        }
    }
}
