<?php

namespace Kilvin\Support\Plugins;

use Kilvin\Facades\Cp;
use \Illuminate\Support\Facades\Request;
use Kilvin\Exceptions\CmsCpPageNotFound;

abstract class ControlPanel implements ControlPanelInterface
{
    protected $plugin_name;

   /**
    * The URL Base for this plugin
    *
    * @return string
    */
    public function urlBase()
    {
        return kilvin_cp_url('plugins/'.$this->plugin_name);
    }

   /**
     * Set the Plugin Details
     *
     * @param string $plugin_name
     * @return void
     */
    public function setPluginName($plugin_name)
    {
        $this->plugin_name = $plugin_name;
    }

   /**
     * Get the Plugin Details
     *
     * @return string
     */
    public function pluginName()
    {
        return $this->plugin_name;
    }

   /**
    * Run the CP Request Engine
    *
    * @return string
    */
    public function run()
    {
        if (Cp::segment(3)) {
            $method = camel_case(Cp::segment(3));
            if (! method_exists($this, $method)) {
                throw new CmsCpPageNotFound;
            }

            return $this->{$method}();
        }

        return $this->homepage();
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
}
