<?php

namespace Kilvin\Support\Plugins;

interface ManagerInterface
{
   /**
     * Name of Plugin
     *
     * @return string
     */
    public function name();

   /**
     * Name of Plugin
     *
     * @return string
     */
    public function description();

   /**
     * Current version of plugin files
     *
     * @return string
     */
    public function version();

   /**
     * Name of Developer
     *
     * @return string
     */
    public function developer();

   /**
     * URL to website of developer
     *
     * @return string
     */
    public function developerUrl();

   /**
     * URL for Plugin Documentation
     *
     * @return string
     */
    public function documentationUrl();

   /**
     * Has CP?
     *
     * @return boolean
     */
    public function hasCp();

   /**
     * Install Plugin
     *
     * @return bool
     */
    public function install();

   /**
     * Uninstall Plugin
     *
     * @return bool
     */
    public function uninstall();
}
