<?php

namespace Kilvin\Libraries\Twig\Loaders;

use Illuminate\Support\Facades\File;
use Kilvin\Facades\Plugins;
use Twig_Function;

/**
 * Extension to expose defined functions to the Twig templates.
 *
 * See the `extensions.php` config file, specifically the `functions` key
 * to configure those that are loaded.
 */
class Functions extends Loader
{
    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'Cms_Twig_Extension_Loader_Functions';
    }

    /**
     * Get unctions in Plugins
     *
     * @return array
     */
    public function getPluginFunctions()
    {
        if (KILVIN_REQUEST === 'INSTALL') {
            return [];
        }

        $functions = [];
        $plugins = Plugins::installedPlugins();
        $registered = Plugins::twig()['function'];

        foreach($plugins as $plugin_name => $plugin_details) {

            if (!isset($registered[$plugin_name])) {
                continue;
            }

            foreach ($registered[$plugin_name] as $class) {
                if ($details = $this->getFunctionDetails($class)) {
                    $functions = array_merge($functions, $details);
                }
            }
        }

        return array_filter($functions);
    }

    /**
     * Get Functions for a Plugin
     *
     * @param string
     * @return array|boolean
     */
    private function getFunctionDetails($class)
    {
        if (class_exists($class)) {
            $object = app($class);
            $name = $object->name();
            $options = $object->options();
            $options['callback'] = $class.'@run';

            return [$name => $options];
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctions()
    {
        $load      = array_merge($this->getPluginFunctions(), config('twig.functions', []));
        $functions = [];

        foreach ($load as $method => $callable) {
            list($method, $callable, $options) = $this->parseCallable($method, $callable);

            $function = new Twig_Function(
                $method,
                function () use ($callable) {

                    // Allows Dependency Injection via Laravel
                    if (is_array($callable) && isset($callable[0])) {
                        if (class_exists($callable[0])) {
                            $callable[0] = app($callable[0]);
                        }
                    }

                    return call_user_func_array($callable, func_get_args());
                },
                $options
            );

            $functions[] = $function;
        }

        return $functions;
    }
}
