<?php

namespace Kilvin\Http\Controllers\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\Auth;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Kilvin\Core\Url;
use Kilvin\Core\Session;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use Illuminate\Container\Container;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\RouteDependencyResolverTrait;

class Controller extends BaseController
{
    // Leaving these here for the time being, may remove.
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, RouteDependencyResolverTrait;

    /**
     * The container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $container;

    /**
     * Create a new controller dispatcher instance.
     *
     * @param  \Illuminate\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

   /**
     * Loads up the CP Files
     *
     * Conversion is in progress!
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function all()
    {
        // ------------------------------------
        //  Class/Method Matrix
        // ------------------------------------

        // Map available classes against the first segment and
        // instantiate the class and/or method associated with it

        $class_map = [
            'default'                => ['Home'],
            'login_form'             => ['LoginController', 'loginForm'],
            'login'                  => ['LoginController', 'login'],
            'forgot_password_form'   => ['ForgotPasswordController', 'showForgotPasswordForm'],
            'forgot_password_send'   => ['ForgotPasswordController', 'sendResetLinkEmail'],
            'reset_password_form'    => ['ResetPasswordController', 'showResetForm'], // Will include token
            'reset_password_send'    => ['ResetPasswordController', 'reset'],
            'logout'                 => ['LoginController', 'logout'],
            'content'                => ['Content'],
            'templates'              => ['Templates'],
            'plugins'                => ['Plugins'],
            'members'                => ['Members'],
            'account'                => ['Account'],
            'administration'         => ['Administration'],
            'weblogs-administration' => ['WeblogAdministration'],
            'sites'                  => ['Sites'],
            'sites-administration'   => ['SitesAdministration'],
        ];

        // ------------------------------------
        //  Access
        // ------------------------------------

        if ( ! Session::access('can_access_content')) {
            unset($class_map['content']);
        }

        if ( ! Session::access('can_access_templates')) {
            unset($class_map['templates']);
        }

        if ( ! Session::access('can_access_plugins')) {
            unset($class_map['plugins']);
        }

        if ( ! Session::access('can_access_admin')) {
            unset($class_map['members']);
            unset($class_map['administration']);
            unset($class_map['weblogs-administration']);
            unset($class_map['sites-administration']);
        }

        // ------------------------------------
        //  Determine Which Class to Use
        // ------------------------------------

        $forgot_related = [
            'login',
            'forgot_password_form',
            'forgot_password_send',
            'reset_password_form',
            'reset_password_send'
        ];

        $C = Cp::segment(1);
        $M = Cp::segment(2);

        // No admin session exists?  Show login screen
        if (!Auth::check() and !in_array($C, $forgot_related)) {
            $class  = $class_map['login_form'][0];
            $method = $class_map['login_form'][1];
        } elseif (!isset($class_map[$C])) {
            $class  = $class_map['default'][0];
            $method = ( ! isset($class_map['default'][1])) ? '' : $class_map['default'][1];
        } else {
            $class  = $class_map[$C][0];
            $method = ( ! isset($class_map[$C][1])) ? '' : $class_map[$C][1];
        }

        $class = ucfirst($class);

        // ------------------------------------
        //  Instantiate the Requested CP Class
        // ------------------------------------

        $full_class = (stristr($class, 'Controller')) ? 'Kilvin\\Http\\Controllers\\Cp\\'.$class : 'Kilvin\Cp\\'.$class;

        // Classes with 'Controller' is the new Laravel way
        if (stristr($class, 'Controller')) {
            $object = resolve($full_class);
            $route  = Route::current();

            $parameters = $this->resolveClassMethodDependencies(
                $route->parametersWithoutNulls(), $object, $method
            );

            return $object->{$method}(...array_values($parameters));
        }

        $object = new $full_class;

        // If there is a method, call it.
        if ($method != '' AND method_exists($object, $method)) {
            $result = $object->$method();
        } else {
            $result = $object->run();
        }

        // ------------------------------------
        //  Display the Control Panel
        //  - Z represents simple CP (usually a popup)
        // ------------------------------------

        if (Cp::pathVar('Z')) {
            Cp::buildSimpleControlPanel();
        } else {
            Cp::buildFullControlPanel();
        }

        // Prevent robots from indexing/following the page
        // (see https://developers.google.com/webmasters/control-crawl-index/docs/robots_meta_tag)
        $headers = [
            'X-Robots-Tag' => 'none',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => 'text/html; charset=utf-8',
            'Last-Modified' => gmdate("D, d M Y H:i:s")." GMT",
            'Pragma' => 'no-cache'
        ];

        $elapsed_time = number_format(microtime(true) - LARAVEL_START, 4);

        $vars = ['{elapsed_time}' => $elapsed_time];

        // @todo
        // This allows us to return a View or RedirectResponse object and send it back
        // to the router allowing it to be rendered. This means
        // we can have new View based CP pages AND keep the old Display
        // class based stuff for a while longer.
        if (!empty($result) && is_object($result)) {
            if ($result instanceof \Illuminate\View\View) {
                return $result;
            }

            if ($result instanceof \Illuminate\Http\RedirectResponse) {
                return $result;
            }
        }

        return str_replace(
            array_keys($vars),
            array_values($vars),
            Cp::output()
        );
    }

    /**
     * Loads up the Javascript
     *
     * @return \Illuminate\Http\Response
     */
    public function javascript()
    {
        return outputThemeFile(KILVIN_CP_THEMES.'cp.js', 'application/javascript');
    }

    /**
     * Loads up the CSS
     *
     * @return \Illuminate\Http\Response
     */
    public function css()
    {
        $cp_theme = (!Session::userdata('cp_theme') or Session::userdata('cp_theme') == '') ?
            Site::config('cp_theme') :
            Session::userdata('cp_theme');

        if (empty($cp_theme)) {
            $cp_theme = 'default';
        }

        $paths = [
            KILVIN_CP_THEMES.$cp_theme.'.css',
            KILVIN_CP_THEMES.'default.css'
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return outputThemeFile($path, 'text/css');
            }
        }

        abort(404);
    }
}
