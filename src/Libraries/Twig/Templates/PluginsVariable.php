<?php

namespace Kilvin\Libraries\Twig\Templates;

use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Plugins;
use Carbon\Carbon;

/*
The idea behind this was to have a "Plugins" variable
in Twig templates that would have a few helpful methods
available, such as Plugins.pluginsList()

And then the __call method allows us to call a Plugin's name
as an object. This object can then load all of the registered
elements for a Plugin.

ex: Plugins.Element('Entries').with('fields').orderByDesc('entry_date').first()

It will load up the Weblogs plugin's Entries element and get the most recent one's model

*/

class PluginsVariable
{
    /**
     * List all plugins
     *
     * @return array
     */
    public static function pluginsList()
    {
        return Plugins::installedPlugins();
    }

   /**
     * Load a Plugin Element Type, which is a fancy Eloquent Model
     *
     * @return array
     */
    public function Element($element)
    {
        $class = $this->findElementClass($element);

        return new $class;
    }

   /**
     * Find the Element
     *
     * @param string $element
     * @param string|null $plugin_name
     * @return array
     */
    private function findElementClass($element, $plugin_name = null)
    {
        $plugins = Plugins::installedPlugins();
        $registered = Plugins::twig()['element'];

        // Element includes both Plugin name and Element name.
        // ex: Weblogs.Entries
        if(stristr($element, '.')) {
            $x = explode('.', $element);

            if (sizeof($x) > 2) {
                throw new \Twig_Error(sprintf('The %s element name is not allowed to have multiple periods.', $element));
            }

            $plugin_name = $x[0];
            $element     = $x[1];
        }

        // We have a plugin name so let's see if has a matching Element
        if (!empty($plugin_name)) {

            // Plugin not installed
            if(!isset($plugins[$plugin_name])) {
                throw new \Twig_Error(sprintf('The %s Plugin does not exist or is not installed.', $plugin_name));
            }

            if (!isset($registered[$plugin_name])) {
                throw new \Twig_Error(sprintf('The %s Plugin does not have any Elements.', $plugin_name));
            }

            foreach ($registered[$plugin_name] as $plugin_element) {
                if ($element == (new \ReflectionClass($plugin_element))->getShortName()) {
                    return $plugin_element;
                }
            }

            throw new \Twig_Error(sprintf('The %s Plugin does not have an Element named %s.', $plugin_name, $element));
        }

        // Just have an element name, so we need to loop through all registered
        // elements for all plugins and we use the first one we find with a matching class name.
        foreach(array_keys($plugins) as $plugin) {
            if (isset($registered[$plugin])) {
                foreach ($registered[$plugin] as $plugin_element) {
                    if ($element == (new \ReflectionClass($plugin_element))->getShortName()) {
                        return $plugin_element;
                    }
                }
            }
        }

        throw new \Twig_Error(sprintf('Unable to find an Element named %s.', $element));
    }

   /**
     * Call the method, which is actually a PluginsVariableElement object
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
    	$plugins = Plugins::installedPlugins();
        $registered = Plugins::twig()['element'];

        if (!isset($plugins[$method])) {
            throw new \Twig_Error(sprintf('The %s Plugin does not exist or is not installed.', $method));
        }

        if (!isset($registered[$method])) {
            throw new \Twig_Error(sprintf('The %s Plugin does not have any template elements available.', $method));
        }

        return (new PluginsVariableElement)->setPlugin($method);
    }
}
