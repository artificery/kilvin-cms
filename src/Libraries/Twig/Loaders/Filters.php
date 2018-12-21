<?php

namespace Kilvin\Libraries\Twig\Loaders;

use Illuminate\Support\Facades\File;
use Kilvin\Facades\Plugins;
use Twig_SimpleFilter;

/**
 * Extension to expose defined filters to the Twig templates.
 *
 * See the `extensions.php` config file, specifically the `filters` key
 * to configure those that are loaded.
 */
class Filters extends Loader
{
    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getName()
    {
        return 'Cms_Twig_Extension_Loader_Filters';
    }

    /**
     * Get Filters in Plugins
     *
     * @return array
     */
    public function getPluginFilters()
    {
        $filters = [];
        $plugins = Plugins::installedPlugins();
        $registered = Plugins::twig()['filter'];

        foreach(array_keys($plugins) as $plugin) {

            if (!isset($registered[$plugin])) {
                continue;
            }

            foreach ($registered[$plugin] as $class) {
                if ($details = $this->getFilterDetails($class)) {
                    $filters = array_merge($filters, $details);
                }
            }
        }

        return array_filter($filters);
    }

    /**
     * Get Filters for a Plugin
     *
     * @param string
     * @return array|boolean
     */
    private function getFilterDetails($class)
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
     *
     * @return Twig_Filter[]
     */
    public function getFilters()
    {
        // Our config filters go last as they take priority.
        if (REQUEST === 'INSTALL') {
            $load = config('twig.filters', []);
        } else {
            $load = array_merge($this->getPluginFilters(), config('twig.filters', []));
        }

        $filters = [];

        foreach ($load as $filter_name => $callable) {
            list($filter_name, $callable, $options) = $this->parseCallable($filter_name, $callable);

            $filter = new Twig_SimpleFilter(
                $filter_name,
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

            $filters[] = $filter;
        }

        return $filters;
    }
}
