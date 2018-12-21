<?php

namespace Kilvin\Support\Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Foundation\Application;
use Kilvin\Support\Plugins\PluginMigrator;
use Kilvin\Support\Plugins\PluginMigrationRepository;
use Kilvin\Exceptions\PluginFailureException;

abstract class Manager implements ManagerInterface
{
    protected $version;
    protected $name;
    protected $description;
    protected $developer;
    protected $developer_url;
    protected $documentation_url;
    protected $has_cp;

    protected $icon_url; // Not implemented but a future thing for Plugins CP page

   /**
     * Name of Plugin
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

   /**
     * Name of Developer
     *
     * @return string
     */
    public function description()
    {
        return $this->description;
    }

   /**
     * Current version of plugin files
     *
     * @return string
     */
    public function version()
    {
        return $this->version;
    }

   /**
     * Name of Developer
     *
     * @return string
     */
    public function developer()
    {
        return $this->developer;
    }

   /**
     * URL to website of developer
     *
     * @return string
     */
    public function developerUrl()
    {
        return $this->developer_url;
    }

   /**
     * URL for Plugin Documentation
     *
     * @return string
     */
    public function documentationUrl()
    {
        return $this->documentation_url;
    }

   /**
     * Has CP?
     *
     * @return boolean
     */
    public function hasCp()
    {
        return $this->has_cp;
    }

   /**
     * Install Plugin
     *
     * Method called after the Plugins CP adds the plugin to the DB
     * and runs any migrations.
     *
     * @return bool
     */
    public function install()
    {
        return true;
    }

   /**
     * Uninstall Plugin
     *
     * Method called after the Plugins CP removes the plugin to the DB
     * and resets any migrations.
     *
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

   /**
     * Updates Plugin
     *
     * @param string $database_version Current version of plugin according to 'plugins' table
     * @return bool
     */
    public function updates($database_version)
    {
        return true;
    }

    /**
    * Run Migrations for Plugin
    *
    * @param string $path The path to the directory of migration files
    * @param string $namespace The namespace for the files so we can autoload them
    * @param string $direction Up or down, yo ho!
    * @return bool
    * @throws \Kilvin\Exceptions\PluginFailureException
    */
    protected function runMigrations($path, $namespace = '', $direction = 'up')
    {
        if (empty($path)) {
            throw new PluginFailureException('No migrations path given for plugin.');
        }

        if (!File::isDirectory($path)) {
            throw new PluginFailureException('Invalid migrations path given.');
        }

        $repo = new PluginMigrationRepository(app('db'), 'plugin_migrations');
        $repo->setPlugin($this->name);

        $migrator = new PluginMigrator($repo, app('db'), app('files'));
        $migrator->setNamespace($namespace);

        if ($direction == 'down') {
            $migrator->rollback([$path]);
        } else {
            $migrator->run([$path]);
        }

        return true;
    }

    /**
     * Register a view file namespace.
     *
     * @param  string|array  $path
     * @param  string  $namespace
     * @return void
     */
    protected function loadViewsFrom($path, $namespace)
    {
        if (is_array(config('view.paths'))) {
            foreach (config('view.paths') as $viewPath) {
                if (is_dir($appPath = $viewPath.'/vendor/'.$namespace)) {
                    app('view')->addNamespace($namespace, $appPath);
                }
            }
        }

        app('view')->addNamespace($namespace, $path);
    }

    /**
     * Register a translation file namespace.
     *
     * @param  string  $path
     * @param  string  $namespace
     * @return void
     */
    protected function loadTranslationsFrom($path, $namespace)
    {
        app('translator')->addNamespace($namespace, $path);
    }
}
