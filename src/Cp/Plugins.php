<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Kilvin\Facades\Plugins as PluginsFacade;
use Kilvin\Models\Plugin;
use Kilvin\Core\Session;
use Kilvin\Exceptions\CmsFailureException;
use Illuminate\Database\Migrations\Migrator;

class Plugins
{
    public $result;

   /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if (Cp::segment(2) === null) {
            return $this->homepage();
        }

        switch(Cp::segment(2)) {
            case 'install'	 :   return $this->pluginInstaller('install');
                break;
            case 'uninstall' :   return $this->pluginInstaller('uninstall');
                break;
            default     	 :   return $this->pluginControlPanel();
                break;
        }
    }

   /**
    * Plugins Homepage
    *
    * @return string
    */
    public function homepage()
    {
		// ------------------------------------
		//  Assing page title
		// ------------------------------------

        $title = __('kilvin::cp.homepage');

        Cp::$title = $title;
        Cp::$crumb = $title;

        // ------------------------------------
        //  Fetch all plugins registered with CMS
        // ------------------------------------

        $plugins = PluginsFacade::registeredPlugins();

        // ------------------------------------
        //  Fetch allowed Plugins for a particular user
        // ------------------------------------

        if (!Session::access('can_admin_plugins')) {

            $plugin_ids = array_keys(Session::userdata('assigned_plugins'));

            if (empty($plugin_ids)) {
                return Cp::$body = Cp::quickDiv('', __('kilvin::plugins.plugin_no_access'));
            }

            $allowed_plugins = DB::table('plugins')
                ->whereIn('id', $plugin_ids)
                ->orderBy('plugin_name')
                ->pluck('plugin_name')
                ->all();

            if (sizeof($allowed_plugins) == 0) {
                return Cp::$body = Cp::quickDiv('', __('kilvin::plugins.plugin_no_access'));
            }
        }

        // ------------------------------------
        //  Fetch the installed plugins from DB
        // ------------------------------------

        $installed_plugins = PluginsFacade::installedPlugins();

        // ------------------------------------
        //  Build page output
        // ------------------------------------

        $r = '';

        // -----------------------------------------
        //  CP Message?
        // -----------------------------------------

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        $r .= Cp::table('tableBorderNoTop', '0', '0', '100%').
              '<tr>'.PHP_EOL.
              Cp::tableCell(
                'tableHeading',
                [
                    __('kilvin::plugins.plugin_name'),
                    __('kilvin::plugins.plugin_description'),
                    __('kilvin::plugins.plugin_version'),
                    __('kilvin::plugins.plugin_status'),
                    __('kilvin::plugins.plugin_action')
                ]).
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach ($plugins as $plugin => $details) {
			if (!Session::access('can_admin_plugins') && !in_array($plugin, $allowed_plugins)) {
				continue;
			}

            $manager = $this->loadManager($plugin);

            $r .= '<tr>'.PHP_EOL;

            $name = $manager->name();

            if (isset($installed_plugins[$plugin]) AND $manager->hasCp() == 'y') {
				$name = Cp::anchor('plugins/'.$plugin, $manager->name());
            }

            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', $name), '29%');

            // Plugin Description
            $r .= Cp::tableCell('', $manager->description(), '36%');

            // Plugin Version
            $r .= Cp::tableCell('', $manager->version(), '10%');


            // Plugin Status
            $status = ( ! isset($installed_plugins[$plugin]) ) ? 'not_installed' : 'installed';

			$in_status = str_replace(" ", "&nbsp;", __('kilvin::plugins.'.$status));

            $show_status = ($status == 'not_installed') ?
                Cp::quickSpan('highlight', $in_status) :
                Cp::quickSpan('highlight_alt', $in_status);

            $r .= Cp::tableCell('', $show_status, '12%');

            // Plugin Action
            $action = ($status == 'not_installed') ? 'install' : 'uninstall';

            $show_action =
                (Session::access('can_admin_plugins')) ?
                Cp::anchor('plugins/'.$action.'/'.$plugin, __('kilvin::plugins.'.$action)) :
                '--';

            $r .= Cp::tableCell('', $show_action, '10%');

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$body  = $r;
    }

   /**
    * Load a Plugin's Manager
    *
    * @param string $plugin
    * @return object
    */
    public function loadManager($plugin)
    {
        return PluginsFacade::loadPluginClass($plugin, 'manager');
    }

   /**
    * Load a Plugin's Control Panel class
    *
    * @param string $plugin
    * @return \Kilvin\Support\Plugins\ControlPanelInterface
    */
    public function loadControlPanel($plugin)
    {
        $CP = PluginsFacade::loadPluginClass($plugin, 'cp');
        $CP->setPluginName($plugin);

        return $CP;
    }

   /**
    * Load Plugin's CP Pages
    *
    * @param string $plugin
    * @return object
    */
    function pluginControlPanel()
    {
        if ( ! $plugin = Cp::segment(2)) {
            abort(404);
        }

        // ------------------------------------
        //  Fetch all plugins registered with CMS
        // ------------------------------------

        $plugins = PluginsFacade::registeredPlugins();

        if (!isset($plugins[$plugin])) {
            abort(404);
        }

        // ------------------------------------
        //  Fetch allowed Plugins for a particular user
        // ------------------------------------

        if (!Session::access('can_admin_plugins')) {
            $plugin_ids = array_keys(Session::userdata('assigned_plugins'));

            if (empty($plugin_ids)) {
                return Cp::$body = Cp::quickDiv('', __('kilvin::plugins.plugin_no_access'));
            }

            $allowed_plugins = DB::table('plugins')
                ->whereIn('id', $plugin_ids)
                ->orderBy('plugin_name')
                ->pluck('plugin_name')
                ->all();

            if (sizeof($allowed_plugins) == 0) {
                return Cp::$body = Cp::quickDiv('', __('kilvin::plugins.plugin_no_access'));
            }
        }


        $manager = $this->loadManager($plugin);

        Cp::$auto_crumb = Cp::breadcrumbItem(Cp::anchor('plugins/'.$plugin, $manager->name()));

        return $this->loadControlPanel($plugin)->run();
    }

   /**
    * Plugin Installer and Uninstaller
    *
    * @param string $type
    * @return string
    */
    function pluginInstaller($type)
    {
        if ( ! Session::access('can_admin_plugins')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $plugin = Cp::segment(3)) {
            return false;
        }

        // ------------------------------------------
        //  Load Manager (which checks existence)
        // ------------------------------------------

        $manager = $this->loadManager($plugin);

        // ------------------------------------------
        //  No funny business!
        // ------------------------------------------

        $count = Plugin::where('plugin_name', $plugin)->count();

        if ($count == 0 && $type === 'uninstall') {
        	throw new CmsFailureException(__('kilvin::plugins.plugin_is_not_installed'));
        }

        if ($count > 0 && $type === 'install') {
        	throw new CmsFailureException(__('kilvin::plugins.plugin_is_already_installed'));
        }

        if($type === 'uninstall') {
			if ( ! Request::input('confirmed')) {
				return $this->uninstallConfirmation($plugin);
			}
        }

        // ------------------------------------------
        //  Run the Relevant Methods
        // ------------------------------------------

		if ($type === 'install') {
			$this->installPlugin($plugin, $manager);
		}

		if ($type === 'uninstall') {
            $this->uninstallPlugin($plugin, $manager);
		}

        // ------------------------------------------
        //  Finished, Create Message and Back to Homepage
        // ------------------------------------------

        $line = ($type == 'uninstall') ? __('kilvin::plugins.plugin_has_been_uninstalled') : __('kilvin::plugins.plugin_has_been_installed');

        $message = $line.$manager->name();

        session()->flash(
            'cp-message',
            $message
        );

         return redirect(kilvin_cp_url('plugins'));
    }

   /**
    * Install Plugin
    *
    * @param string $plugin The Plugin's name
    * @param object $manaer The already loaded plugin manager
    * @return bool
    */
    public function installPlugin($plugin, $manager)
    {
        try {
            $manager->install();
        } catch (\Exception $e) {
            // @todo - Proper error via session?
            exit('Failure to install: '.$e->getMessage());
        }

        Plugin::insert(
            [
                'plugin_name' => $plugin,
                'plugin_version' => $manager->version(),
                'has_cp' => $manager->hasCp()
            ]
        );
    }

   /**
    * Uninstall Plugin
    *
    * @param string $plugin The Plugin's name
    * @param object $manaer The already loaded plugin manager
    * @return bool
    */
    public function uninstallPlugin($plugin, $manager)
    {
        try {
            $manager->uninstall();
        } catch (\Exception $e) {
            // @todo - Error?
            exit('Failure to uninstall: '.$e->getMessage());
        }

        DB::table('plugins')->where('plugin_name', ucfirst($plugin))->delete();
    }

   /**
    * Uninstall Confirmation
    *
    * @param string $plugin The Plugin's name
    * @return string
    */
    public function uninstallConfirmation($plugin = '')
    {
        if ( ! Session::access('can_admin_plugins')) {
            return Cp::unauthorizedAccess();
        }

        if ($plugin == '') {
            return Cp::unauthorizedAccess();
        }

        Cp::$title	= __('kilvin::plugins.uninstall_plugin');
		Cp::$crumb	= __('kilvin::plugins.uninstall_plugin');

        Cp::$body	= Cp::formOpen(
            ['action' => '/plugins/uninstall/'.$plugin],
            ['confirmed' => '1']
		);

        $MOD = $this->loadManager($plugin);
		$name = $MOD->name();

		Cp::$body .= Cp::quickDiv('alertHeading', __('kilvin::plugins.uninstall_plugin'));
		Cp::$body .= Cp::div('box');
		Cp::$body .= Cp::quickDiv('defaultBold', __('kilvin::plugins.uninstall_plugin_confirm'));
		Cp::$body .= Cp::quickDiv('defaultBold', BR.$name);
		Cp::$body .= Cp::quickDiv('alert', BR.__('kilvin::plugins.data_will_be_lost')).BR;
		Cp::$body .= '</div>'.PHP_EOL;

		Cp::$body .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::plugins.uninstall_plugin')));
		Cp::$body .= '</form>'.PHP_EOL;
    }
}
