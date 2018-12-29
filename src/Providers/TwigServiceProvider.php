<?php

namespace Kilvin\Providers;

use Kilvin\Facades\Site;
use Kilvin\Libraries\Twig;
use Kilvin\Libraries\TwigPlugins;
use Illuminate\View\ViewServiceProvider;
use InvalidArgumentException;
use Twig_Loader_Chain;
use Twig_Loader_Array;

/**
 * Kilvin's Laravel Twig Service Provider
 */
class TwigServiceProvider extends ViewServiceProvider
{
    /**
     * File Suffixes that will Trigger Twig templating
     */
    private $suffixes = [
        'twig.html',
        'twig.css',
        'twig.atom',
        'twig.rss',
        'twig.xml',
        'twig.js',
        'twig.json'
    ];

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $this->registerCommands();
        $this->registerOptions();
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $this->registerLoaders();
        $this->registerEngine();
        $this->registerViewExtension();

        $this->app->singleton('cms.twig.plugins', function () {
            return new TwigPlugins;
        });
    }

    /**
     * Register the Twig extension in the Laravel View component.
     *
     * @return void
     */
    protected function registerViewExtension()
    {
        // Array reverse makes sure the search by FileViewFinder
        // is done in the order suffixes are listed
        foreach(array_reverse($this->suffixes) as $suffix) {
            $this->app['view']->addExtension(
                $suffix,
                'cms.twig',
                function () {
                    return $this->app['cms.twig.engine'];
                }
            );
        }
    }

    /**
     * Register console command bindings.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->app->bindIf('command.twig.clean', function () {
            return new \Kilvin\Console\Commands\Twig\Clean;
        });

        $this->commands('command.twig.clean');
    }

    /**
     * Register Twig config option bindings.
     *
     * @return void
     */
    protected function registerOptions()
    {
        $this->app->bindIf('cms.twig.suffixes', function () {
            return $this->suffixes;
        });


        $this->app->bindIf('cms.twig.options', function () {
            $options = config('twig.environment', []);

            // Forced options
            $options['charset'] = 'utf-8';
            $options['debug']   = env('APP_DEBUG', false);

            // If set AND false, disable cache
            if (!isset($options['cache']) or $options['cache'] !== false) {
                $options['cache'] = storage_path('framework/views/twig');
            }

            return $options;
        });

        $this->app->bindIf('cms.twig.extensions', function () {
            $load = config('twig.extensions', []);

            // Is debug enabled?
            // If so enable debug extension
            $options = $this->app['cms.twig.options'];
            $isDebug = (bool) (isset($options['debug'])) ? $options['debug'] : false;

            if ($isDebug) {
                array_unshift($load, 'Twig_Extension_Debug');
            }

            return $load;
        });
    }

    /**
     * Register Twig loader bindings.
     *
     * @return void
     */
    protected function registerLoaders()
    {
        $this->app->bindIf('cms.twig.loader.viewfinder', function () {
            return new Twig\Loader(
                $this->app['files'],
                $this->app['view']->getFinder(),
                $this->suffixes
            );
        });

        $this->app->bindIf(
            'cms.twig.loader',
            function () {
                return new Twig_Loader_Chain([
                    $this->app['cms.twig.loader.viewfinder'],
                ]);
            },
            true
        );
    }

    /**
     * Register Twig engine bindings.
     *
     * @return void
     */
    protected function registerEngine()
    {
        $this->app->bindIf(
            'cms.twig',
            function () {
                $extensions = $this->app['cms.twig.extensions'];
                $twig       = new Twig\Environment(
                    $this->app['cms.twig.loader'],
                    $this->app['cms.twig.options'],
                    $this->app
                );

                // Instantiate and add extensions
                foreach ($extensions as $extension) {
                    // Set up extension
                    if (is_string($extension)) {
                        try {
                            $extension = $this->app->make($extension);
                        } catch (\Exception $e) {
                            throw new InvalidArgumentException(
                                "Cannot instantiate Twig extension '$extension': " . $e->getMessage()
                            );
                        }
                    }

                    $twig->addExtension($extension);
                }

                return $twig;
            },
            true
        );

        $this->app->alias('cms.twig', 'Twig_Environment');
        $this->app->alias('cms.twig', 'Kilvin\Libraries\Twig\Environment');

        $this->app->bindIf('cms.twig.compiler', function () {
            return new Twig\Compiler($this->app['cms.twig']);
        });

        $this->app->bindIf('cms.twig.engine', function () {
            return new Twig\Engine(
                $this->app['cms.twig.compiler'],
                $this->app['cms.twig.loader.viewfinder'],
                config('twig.globals', [])
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.twig.clean',
            'cms.twig.suffixes',
            'cms.twig.options',
            'cms.twig.extensions',
            'cms.twig.templates',
            'cms.twig.loader.viewfinder',
            'cms.twig.loader',
            'cms.twig',
            'cms.twig.compiler',
            'cms.twig.engine',
        ];
    }
}
