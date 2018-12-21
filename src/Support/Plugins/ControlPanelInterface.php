<?php

namespace Kilvin\Support\Plugins;

interface ControlPanelInterface
{
   /**
    * Run the CP Request Engine
    *
    * @return string
    */
    public function run();

   /**
    * The homepage for the plugin
    *
    * @return \Illuminate\Http\Response
    */
    public function homepage();
}
