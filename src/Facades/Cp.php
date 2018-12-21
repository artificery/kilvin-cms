<?php

namespace Kilvin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * CP Functionality
 */
class Cp extends Facade
{
	public static $title;
	public static $body;
	public static $crumb;
    public static $auto_crumb;
	public static $url_append;
	public static $body_props;
	public static $extra_header;

	private static $variables = ['title', 'body', 'crumb', 'auto_crumb', 'url_append', 'body_props', 'extra_header'];

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cms.cp';
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        // Each method call transfers over Facade's static variables to instance
        // This way we keep them in sync and I can have some simpler code in my CP
        // classes for setting things like title or body
        // Note: This should always work since our CP Controller calls the Cp::output method
        foreach(static::$variables as $field) {
        	if(isset(self::$$field)){
				$instance->{$field} = self::$$field;
	        }
        }

        return $instance->$method(...$args);
    }
}
