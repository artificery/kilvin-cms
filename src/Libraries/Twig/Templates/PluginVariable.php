<?php

namespace Kilvin\Libraries\Twig\Templates;

use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Plugins;
use Carbon\Carbon;

class PluginVariable
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
     * Call the method, which is actually a plugin
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
    	$installed = Plugins::installedPlugins();
    	$plugin = $installed[$method] ?? null;

        if ($plugin !== null) {

        	// Load up the class
        	// Maybe have each Plugin have a methods() function to list all available methods?

        	return new class {
				function mostRecent()
				{
					return new class {
						function title()
						{
							return 'Welcome to Kilvin CMS!';
						}
					};
				}

                function recentEntries()
                {
                    return new class {
                        function limit() {
                            return [
                                [
                                    'title' => 'Another Entry',
                                    'slug'  => 'another_entry',
                                    'content' => PluginVariable::latin(),
                                    'entry_date' => Carbon::now()->subHours(rand(1,5))->subMinutes(rand(1,30))
                                ],
                                [
                                    'title' => 'Welcome to Kilvin CMS!',
                                    'slug'  => 'welcome_to_groot_cms',
                                    'content' => PluginVariable::latin(),
                                    'entry_date' => Carbon::now()->subDays(rand(1,5))->subHours(rand(1,5))->subMinutes(rand(1,30))
                                ]
                            ];
                        }
                    };
                }
			};
        }

        throw new \Twig_Error(sprintf('The %s Plugin does not exist or is not installed.', $method));
    }

    /**
     * Temporary Method for testing
     *
     * @return string
     */
    public static function latin()
    {
        return <<<EOT
Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam aliquet vitae dui at faucibus. Morbi faucibus mollis purus, vitae suscipit massa pharetra sit amet. Quisque lacinia sed nulla id efficitur. Mauris pharetra pharetra venenatis. Mauris aliquam nisl ac mi pellentesque, a auctor leo ultrices. Etiam vel metus ante. Vivamus lacinia, augue sed tincidunt ultrices, nisl nisi lobortis ex, sed pulvinar eros elit a tellus. Integer sagittis mi vitae sem iaculis, quis placerat felis sagittis. Vivamus pharetra odio sed felis laoreet, quis rhoncus sem venenatis. Sed id turpis feugiat, auctor tortor dignissim, ornare neque. Donec in diam sapien.

Aliquam mollis pretium ullamcorper. Mauris et finibus mi. Sed viverra eget orci non imperdiet. Duis nec pellentesque orci. Integer quis risus lectus. Proin non urna gravida odio ullamcorper bibendum. Aliquam aliquam tempus risus eu scelerisque. Vivamus aliquam tellus elit, vitae vulputate massa sollicitudin quis. Etiam sagittis molestie tristique. Fusce viverra enim quis eros pulvinar mattis. Cras eleifend nisl vitae ipsum laoreet, quis condimentum enim tincidunt. Ut pretium tincidunt libero sit amet ornare.

Morbi efficitur at augue sit amet vehicula. Ut semper cursus nibh, posuere aliquam metus faucibus eget. Praesent finibus augue in elit consequat accumsan. Donec bibendum arcu ut laoreet cursus. Nullam vitae fermentum sapien. Etiam dictum orci eu risus fringilla placerat. Nulla vitae vestibulum arcu. Praesent pulvinar nulla a dui venenatis, et semper eros semper. Vestibulum quis nibh vel mi mattis porttitor at ut dolor. Donec rutrum porta hendrerit. Praesent vehicula, nisl eget fringilla condimentum, justo orci aliquet urna, molestie suscipit ante nulla quis quam.
EOT;
    }
}
