<?php

namespace Kilvin\Libraries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Kilvin\Models\Plugin;
use Kilvin\Core\Session;
use Kilvin\Exceptions\CmsFailureException;


class Plugins
{
    /**
     * List of currently installed plugins
     *
     * @var array
     */
    private $installed_plugins;

    /**
     * All registered plugins for Kilvin.
     *
     * @var array
     */
    protected $registered_plugins = [];

    /**
     * All registered fieldTypes for Kilvin.
     *
     * @var array
     */
    protected $registered_field_types = [];

    /**
     * All registered Twig filters, extensions, and elements
     *
     * @var array
     */
    protected $twig = [
        'element' => [],
        'filter' => [],
        'variable' => [],
        'function' => [],
    ];

    /**
     * Registers a new plugin.
     *
     * @param string $name
     * @param string $manager Full namespace to manager?
     * @param string $manager Full namespace to CP class?
     */
    public function register($name, $manager, $cp = null)
    {
        $this->registered_plugins[$name] = [
            'manager' => $manager,
            'cp' => $cp
        ];
    }

    /**
     * Register a Field Type
     *
     * @param string $plugin
     * @param string $name
     * @param string $class Full namespace to class
     * @throws \Exception - Can be thrown prior to loading of views used in Exception Handler, so basic exception for now.
     */
    public function registerFieldType($plugin, $name, $class)
    {
        if ($plugin !== 'Kilvin' && !isset($this->registered_plugins[$plugin])) {
            throw new \Exception('This Plugin is not yet registered with Kilvin CMS.');
        }

        $this->registered_field_types[$plugin][$name] = $class;
    }

    /**
     * Register a twig loader
     *
     * @param string $plugin
     * @param string $type
     * @param string $class Full namespace to class
     * @throws \Exception - Can be thrown prior to loading of views used in Exception Handler, so basic exception for now.
     */
    public function registerTwig($plugin, $type, $class)
    {
       if (!isset($this->registered_plugins[$plugin])) {
            throw new \Exception('This Plugin is not yet registered with Kilvin CMS.');
        }

        if (!isset($this->twig[$type])) {
            throw new \Exception('Invalid Twig loader for Kilvin CMS');
        }

        if (!class_exists($class)) {
            throw new \Exception('Invalid Twig loader class for Kilvin CMS: '.$class);
        }

        $this->twig[$type][$plugin][] = $class;
    }

    /**
     * Return Twig Loaders
     *
     * @param array
     */
    public function twig()
    {
        return $this->twig;
    }

    /**
     * All registered plugins
     *
     * @return array
     */
    public function registeredPlugins()
    {
        ksort($this->registered_plugins);

        return $this->registered_plugins;
    }

    /**
     * List of installed plugins
     *
     * @return array
     */
    public function installedPlugins()
    {
        if (!isset($this->installed_plugins)) {
            $this->loadInstalledPlugins();
        }

        return $this->installed_plugins;
    }

    /**
     * Load Plugins
     *
     * @return array
     */
    public function loadInstalledPlugins()
    {
        if (isset($this->installed_plugins)) {
            return $this->installed_plugins;
        }

        $plugins = Plugin::orderBy('plugin_name')
            ->get()
            ->keyBy('plugin_name')
            ->toArray();

        foreach($plugins as $plugin) {
            if ($details = $this->findPluginLoadingDetails($plugin['plugin_name'])) {
                $plugin['details'] = $details;
            }
        }

        return $this->installed_plugins = $plugins;
    }

   /**
    * Find Details for Loading Plugin
    *
    * @param string $plugin
    * @return array
    */
    public function findPluginLoadingDetails($plugin)
    {
        $plugin = filename_security($plugin);

        if (isset($this->registered_plugins[$plugin])) {
            return $this->registered_plugins[$plugin];
        }

        // @todo - When this is thrown, we get a "An exception has been thrown during the compilation of a template"
        // Probably can make that a bit prettier
        throw new CmsFailureException(sprintf(__('kilvin::plugins.plugin_cannot_be_found'), $plugin));
    }

   /**
    * Load Plugin Class
    *
    * @param string $plugin
    * @param string $class cp/manager
    * @return string The full class path
    */
    public function loadPluginClass($plugin, $class)
    {
        if (preg_match('/[^A-Za-z\_]/', $class)) {
            throw new CmsFailureException(__('kilvin::plugins.invalid_class_for_plugins'));
        }

        $details = $this->findPluginLoadingDetails($plugin);

        if ($class == 'cp' && isset($details['cp'])) {
            return app()->make($details['cp']);
        }

        if ($class == 'manager' && isset($details['manager'])) {
            return app()->make($details['manager']);
        }

        throw new CmsFailureException(sprintf(__('kilvin::plugins.plugin_cannot_be_found'), $plugin));
    }

   /**
    * FieldTypes
    * - Returns array of all installed and available field types for CMS
    * - @todo - Consider giving plugins/Kilvin the ability to disable field types?
    *
    * @return array
    */
    public function fieldTypes()
    {
        if (isset($this->field_types)) {
            return $this->field_types;
        }

        $field_types = $this->registered_field_types['Kilvin'] ?? [];

        // Installed Plugins
        $plugins = $this->installedPlugins();

        foreach($plugins as $plugin => $plugin_details) {
            if (isset($this->registered_field_types[$plugin]) && is_array($this->registered_field_types[$plugin])) {
                $field_types = array_merge($this->registered_field_types[$plugin], $field_types);
            }
        }

        ksort($field_types);

        return $this->field_types = $field_types;
    }
}
