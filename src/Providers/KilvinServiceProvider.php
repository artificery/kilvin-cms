<?php

namespace Kilvin\Providers;

// Still need to add Twig Service Provider

use App;
use Kilvin\Facades\Site;
use Kilvin\Facades\Plugins;
use Kilvin\Libraries;
use Parsedown;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\AliasLoader;

class KilvinServiceProvider extends ServiceProvider
{
    protected $aliases = [
        'Stats'          => \Kilvin\Facades\Stats::class,
        'Cp'             => \Kilvin\Facades\Cp::class,
        'Site'           => \Kilvin\Facades\Site::class,
        'Plugins'        => \Kilvin\Facades\Plugins::class,
        'Template'       => \Kilvin\Facades\Template::class,
        'Twig'           => \Kilvin\Facades\Twig::class,
        'PluginVariable' => \Kilvin\Facades\PluginVariable::class,
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // I prefer this over package auto-discovery.
        AliasLoader::getInstance($this->aliases)->register();

        $this->app->singleton('parsedown', function () {
            return Parsedown::instance();
        });

        $this->app->singleton('cms.statistics', function () {
            return new Libraries\Statistics;
        });

        $this->app->singleton('cms.cp', function () {
            return new Libraries\ControlPanel;
        });

        $this->app->singleton('cms.site', function () {
            return new Libraries\Site;
        });

        $this->app->singleton('cms.template', function () {
            return new Libraries\Template;
        });

        $this->app->singleton('cms.plugins', function () {
            return new Libraries\Plugins;
        });

        $this->app->singleton('cms.twig.plugin_variable', function () {
            return new Libraries\Twig\Templates\PluginVariable;
        });

        $this->registerCmsConstants();

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                // InstallCommand::class,
                // UpdateCommand::class,
                // UpdateViewsCommand::class,
                // VersionCommand::class,
            ]);
        }

        $this->app->register(\Kilvin\Providers\TwigServiceProvider::class);

        Plugins::register('Parsedown', \Kilvin\Plugins\Parsedown\Manager::class);
        Plugins::register('Weblogs',\Kilvin\Plugins\Weblogs\Manager::class);

        Plugins::registerTwig('Weblogs', 'element', \Kilvin\Plugins\Weblogs\Templates\Elements\Entries::class);

        Plugins::registerTwig('Parsedown', 'filter', \Kilvin\Plugins\Parsedown\Templates\Filters\Markdown::class);
        Plugins::registerTwig('Parsedown', 'filter', \Kilvin\Plugins\Parsedown\Templates\Filters\Parsedown::class);
        Plugins::registerTwig('Parsedown', 'function', \Kilvin\Plugins\Parsedown\Templates\Functions\Markdown::class);
        Plugins::registerTwig('Parsedown', 'function', \Kilvin\Plugins\Parsedown\Templates\Functions\Parsedown::class);

        Plugins::registerFieldType('Kilvin', 'Date', \Kilvin\FieldTypes\Date::class);
        Plugins::registerFieldType('Kilvin', 'Dropdown', \Kilvin\FieldTypes\Dropdown::class);
        Plugins::registerFieldType('Kilvin', 'Text', \Kilvin\FieldTypes\Text::class);
        Plugins::registerFieldType('Kilvin', 'Textarea', \Kilvin\FieldTypes\Textarea::class);
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->setLocalizationSettings();

        // ----------------------------------------------
        //  App Debugging
        // ----------------------------------------------

        if (config('app.debug') == true) {
            error_reporting(E_ALL);
        }

        // ----------------------------------------------
        //  Installer?
        //  - Stop here, no need to check if system is on
        // ----------------------------------------------

        if (REQUEST == 'INSTALL') {

            $installed_version = config('cms.installed_version');

            // Hide installer after installation
            if (!empty($installed_version) && config('cms.hide_installer') === true) {
                \Log::debug('Kilvin CMS Installer requested but configuration indicates it is already installed.');
                return;
            }

            // Define Installer Routes
            $this->defineInstallerRoutes();
            $this->loadViewsFrom(__DIR__.'/../../resources/views', 'kilvin');

            return;
        }

        // ----------------------------------------------
        //  Check config file is ready
        // ----------------------------------------------

        if ( config()->get('cms.installed_version') === null) {
            exit(CMS_NAME." does not appear to be installed.");
        }

        // Define CMS Routes
        $this->defineCmsRoutes();
        $this->defineResources();

        // Nothing to do for these requests?
        if (in_array(REQUEST, ['INSTALL','CONSOLE'])) {
            return;
        }
    }

   /**
     * Base Localization Settings for CMS
     *
     * @return void
     */
    protected function setLocalizationSettings()
    {
        // --------------------------------------------------
        //  Locale for Carbon - Localizes strings
        //  - @todo: Use cookie value OR use config/app.php's locale value?
        // --------------------------------------------------

        // @todo: Another thing that we might set via middleware
        App::setLocale('en_US');
        Carbon::setLocale('en');

        setlocale(
            LC_CTYPE,
            'C.UTF-8',     // libc >= 2.13
            'C.utf8',      // different spelling
            'en_US.UTF-8', // fallback to lowest common denominator
            'en_US.utf8'   // different spelling for fallback
        );
    }

    /**
     * Define the CMS routes.
     *
     * @return void
     */
    protected function defineCmsRoutes()
    {
        // If the routes have not been cached, we will include them in a route group
        // so that all of the routes will be conveniently registered to the given
        // controller namespace. After that we will load the Spark routes file.
        if (! $this->app->routesAreCached()) {
            Route::group([
                'namespace' => 'Kilvin\Http\Controllers'],
                function ($router) {
                    require __DIR__.'/../../routes/cms-routes.php';
                }
            );
        }
    }

    /**
     * Define the Installer routes.
     *
     * @return void
     */
    protected function defineInstallerRoutes()
    {
        // If the routes have not been cached, we will include them in a route group
        // so that all of the routes will be conveniently registered to the given
        // controller namespace. After that we will load the Spark routes file.
        if (! $this->app->routesAreCached()) {
            Route::group([
                'namespace' => 'Kilvin\Http\Controllers'],
                function ($router) {
                    require __DIR__.'/../../routes/installer-routes.php';
                }
            );
        }
    }

    /**
     * Define the resources for the package.
     *
     * @return void
     */
    protected function defineResources()
    {
        // SITE requests cannot view Kilvin resources, s'il vous plait
        if (defined('REQUEST') && in_array(REQUEST, ['SITE', 'CP', 'CONSOLE'])) {
            $this->loadViewsFrom(__DIR__.'/../../resources/views', 'kilvin');
            $this->loadTranslationsFrom(__DIR__.'/../../resources/language', 'kilvin');
        }

        if ($this->app->runningInConsole()) {
            $this->defineViewPublishing();
            $this->defineAssetPublishing();
            $this->defineLanguagePublishing();
            $this->defineFullPublishing();
        }
    }

    /**
     * Define the view publishing configuration.
     *
     * @return void
     */
    public function defineViewPublishing()
    {
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/kilvin'),
        ], 'kilvin-views');
    }

    /**
     * Define the asset publishing configuration.
     *
     * @return void
     */
    public function defineAssetPublishing()
    {
        $this->publishes([
            __DIR__.'/../../resources/assets/js' => resource_path('assets/js/kilvin'),
        ], 'kilvin-js');

        $this->publishes([
            __DIR__.'/../../resources/assets/sass' => resource_path('assets/sass/kilvin'),
        ], 'kilvin-sass');
    }

    /**
     * Define the language publishing configuration.
     *
     * @return void
     */
    public function defineLanguagePublishing()
    {
        $this->publishes([
            __DIR__.'/../../resources/lang' => resource_path('lang'),
        ], 'kilvin-lang');
    }

    /**
     * Define the "full" publishing configuration.
     *
     * @return void
     */
    public function defineFullPublishing()
    {
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/kilvin'),
            __DIR__.'/../../resources/js' => resource_path('assets/js/vendor/kilvin'),
            __DIR__.'/../../resources/sass' => resource_path('assets/sass/vendor/kilvin'),
            __DIR__.'/../../resources/language' => resource_path('lang/vendor/kilvin'),
        ], 'kilvin');
    }

    /**
     * Define All of the constants currently needed
     * - Many of these will go away when I get a chance.
     *
     * @return void
     */
    protected function registerCmsConstants()
    {
        // @todo - exterminate
        define('SELF', 'index.php');

        if (request()->segment(1) == 'installer') {
            define('REQUEST', 'INSTALL');
        } elseif (request()->segment(1) == config('cms.cp_path')) {
            define('REQUEST', 'CP');
        } elseif (app()->runningInConsole()) {
            define('REQUEST', 'CONSOLE');
        } else {
            define('REQUEST', 'SITE');
        }

        define('KILVIN_THEMES', realpath(__DIR__.'/../../themes').'/');

        if (REQUEST === 'CP') {
            view()->composer('*', 'Kilvin\Http\ViewComposers\Cp');
        }

        if (REQUEST === 'INSTALL') {
            view()->composer('*', 'Kilvin\Http\ViewComposers\Installer');
        }

        // --------------------------------------------------
        //  Determine system path and site name
        // --------------------------------------------------

        $system_path = base_path().DIRECTORY_SEPARATOR;

        // ----------------------------------------------
        //  Set base system constants
        // ----------------------------------------------

        define('CMS_NAME'                , 'Kilvin CMS');
        define('KILVIN_VERSION'          , '0.0.2');

        define('KILVIN_PACKAGE_PATH'     , realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR);
        define('KILVIN_TEMPLATES_PATH'   , $system_path.'templates'.DIRECTORY_SEPARATOR);
        define('KILVIN_THIRD_PARTY_PATH' , $system_path.'plugins'.DIRECTORY_SEPARATOR);
        define('KILVIN_PLUGINS_PATH'     , KILVIN_PACKAGE_PATH.'src/Plugins'.DIRECTORY_SEPARATOR);

        // @todo - Exterminate!
        define('AMP'                     , '&amp;');
        define('BR'                      , '<br />');
        define('NBS'                     , '&nbsp;');

        // ----------------------------------------------
        //  Determine the request type
        // ----------------------------------------------

        // There are FOUR possible request types:
        // 1. INSTALL
        // 2. CP
        // 3. CONSOLE
        // 4. SITE (i.e. template or action) request

        if (REQUEST === 'SITE') {
            if (Request::filled('ACT')) {
                define('ACTION', Request::input('ACT'));
            }
        }

        if (REQUEST === 'CP') {
            define('BASE', SELF);
        }
    }
}
