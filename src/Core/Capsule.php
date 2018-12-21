<?php

namespace Kilvin\Core;

use Illuminate\Database\Capsule\Manager as IlluminateCapsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

class Capsule
{
    public function __construct($config, $name = 'default')
    {
        $capsule = new IlluminateCapsule;

        $capsule->addConnection($config, $name);

        // Set the event dispatcher used by Eloquent models... (optional)
        $capsule->setEventDispatcher(new Dispatcher(new Container));

        // Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();

        // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $capsule->bootEloquent();
    }

}
