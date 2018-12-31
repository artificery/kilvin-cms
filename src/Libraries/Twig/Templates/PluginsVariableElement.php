<?php

namespace Kilvin\Libraries\Twig\Templates;

use Kilvin\Facades\Plugins;

class PluginsVariableElement
{
    private $plugin;

    /**
     * Set plugin
     *
     * @return array
     */
    public function setPlugin($plugin)
    {
        $this->plugin = $plugin;

        return $this;
    }

   /**
     * Call the method, which is actually a plugin's element
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        $elements = Plugins::twig()['element'];
        $plugin_elements = $elements[$this->plugin] ?? [];

        foreach ($plugin_elements as $plugin_element) {
            if ($method == (new \ReflectionClass($plugin_element))->getShortName()) {
                return new $plugin_element;
            }
        }

        throw new \Twig_Error(sprintf('The %s Plugin does not exist or is not installed.', $method));
    }
}
