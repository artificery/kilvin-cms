<?php

namespace Kilvin\Support\Plugins;

use Illuminate\Support\Str;
use Illuminate\Database\Migrations\Migrator;

class PluginMigrator extends Migrator
{
	private $namespace = '';

	/**
     * Resolve a migration instance from a file.
     *
     * @param  string  $file
     * @return object
     */
    public function resolve($file)
    {
        $class = $this->namespace.
        	Str::studly(
        		implode('_', array_slice(explode('_', $file), 4)
        		)
        	);

        return new $class;
    }

    /**
     * Set the Plugin Details
     *
     * @param array $details
     * @return void
     */
    public function setNamespace($namespace)
    {
        $this->namespace = rtrim($namespace, '\\').'\\';
    }
}
