<?php

namespace Kilvin\Libraries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Request;
use Kilvin\Facades\Plugins;
use Carbon\Carbon;
use Kilvin\Core\Url;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;
use Illuminate\Http\Response;
use League\Flysystem\Util\MimeType;

/**
 * Templates Functionality
 */
class Template
{
    static $globals_cache = [];

    /**
     * Does the Template Exist?
     *
     * @param  string  $template
     * @return boolean
     */
    public function exists($template)
    {
        if (View::exists($template)) {
            return true;
        }

        return false;
    }

    /**
     * Finds a template on the file system and returns its path.
     *
     * All of the following files will be searched for, in this order:
     *
     * - {folderName}/{templateName}
     * - {folderName}/{templateName}.twig.html
     * - {folderName}/{templateName}.twig.css
     * - {folderName}/{templateName}.twig.xml
     * - {folderName}/{templateName}.twig.atom
     * - {folderName}/{templateName}.twig.rss
     *
     * @param string $uri The uri being used
     *
     * @return string|false The path to the template if it exists, or `false`.
     */
    public function find($view)
    {
        $normalized = \Illuminate\View\ViewName::normalize($view);

        try {
            return View::getFinder()->find($normalized);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Renders a template returns it.
     *
     * @param mixed $template      The full path of the template to load
     * @param array $variables     The variables that should be available to the template.
     *
     * @throws HttpException
     * @return \Illuminate\Http\Response
     */
    public function render($template, $variables = [])
    {
        // ------------------------------------
        //  Meta
        // ------------------------------------

        $path = remove_double_slashes($this->find($template));

        if(empty($path)) {
            return response()->view('_errors.404', [], 404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // ------------------------------------
        //  Site Globals, ex: Template Variables
        // ------------------------------------

        $this->loadGlobals();

        // ------------------------------------
        //  Set the Headers
        // ------------------------------------

        $headers = $this->getHeaders($extension);

        // ------------------------------------
        //  Output
        // ------------------------------------

        return response()->view($template, $variables, 200, $headers);
    }

    /**
     * Sets the Headers for Template Output
     *
     * @param string $type The type of template being outputted (css,html,js,xml,atom)
     * @return void
     */
    private function getHeaders($type, $variables = [])
    {
        $mime = MimeType::detectByFileExtension($type);

        $headers = [
            'X-Powered-By'  => CMS_NAME,
            'Content-Type'  => $mime.'; charset=utf-8'
        ];

        return $headers;
    }

    /**
     * Load Global Variables into Twig Engine
     *
     * @todo - Finish this
     *
     * @return void
     */
    private function loadGlobals()
    {
        View::share(
            array_merge(
                $this->getPluginVariables(),
                $this->buildCoreGlobals()
            )
        );
    }

    /**
     * Load Global Variables into Twig Engine
     *
     * @todo - Finish this
     *
     * @return void
     */
    private function buildCoreGlobals()
    {
        if (isset($this->globals_cache[Site::config('site_handle')])) {
            return $this->globals_cache[Site::config('site_handle')];
        }

        $core_globals = [
            'now' => Localize::createHumanReadableDateTime(),
            'cms' => [
                'version' => KILVIN_VERSION
            ]
        ];

        // Template Variables
        if (REQUEST === 'SITE') {
            $core_globals['tv'] = DB::table('template_variables')
                ->pluck('variable_data', 'variable_name')
                ->toArray();
        }

        return self::$globals_cache[Site::config('site_handle')] = $core_globals;
    }

    /**
     * Get all of the segments for the uri.
     *
     * @return array
     */
    public function segments($uri)
    {
        $segments = explode('/', $uri);

        return array_values(array_filter($segments, function ($v) {
            return $v != '';
        }));
    }

    /**
     * Take a URI string and find the right template to display
     *
     * @param string $uri The URI string to parse
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory
     */
    function discover($uri)
    {
        $segments = $this->segments($uri);

        // Homepage, change this once we handle folders vs template groups
        if (empty($segments[0])) {
            return $this->render('index');
        }

        // Homepage with pagination
        if(count($segments) == 2 && preg_match("#^(\/page\/\d+\/)$#", $uri, $match)) {
            Url::$QSTR = $match[1];
            return $this->render('index');
        }

        // ------------------------------------
        //  Two Options, Folder with Template or Folder with Index
        //  - Folder with Template is PRIMARY
        // ------------------------------------

        $original_segments = $segments;
        $last              = array_pop($segments);

        $suffixes = array_map(function($val) {
            return str_replace('twig.', '', $val);
        }, app('cms.twig.suffixes'));

        if(stristr($last, '.')) {
            $x = explode('.', $last);
            // Only allow one period
            if (sizeof($x) == 2) {
                $suffix = array_pop($x);

                if(in_array($suffix, $suffixes)) {
                    $last = $x[0];
                }
            }
        }

        // ------------------------------------
        //  Template within Folder
        // ------------------------------------

        $check  =
            rtrim(empty($segments) ? '' : implode('/', $segments), '/').
            '/'.
            $last;

        if($this->exists($check)) {
            return $this->render($check);
        }

        // ------------------------------------
        //  Folder Request, so look for index
        // ------------------------------------

        $check =
            rtrim(implode('/', $original_segments), '/').
            '/'.
            'index';

        if($this->exists($check)) {
            return $this->render($check);
        }

        // ------------------------------------
        //  Single Segment? 404
        //  - Otherwise it will go to the site index
        // ------------------------------------

        if(sizeof($original_segments) == 1) {
            return $this->output404();
        }

        // ------------------------------------
        //  No Results? Dynamic URL?
        // ------------------------------------

        $result = $this->withDynamicUri($segments, $last);

        if (!empty($result)) {
            return $result;
        }

        return $this->output404();
    }

    /**
     *  Find Template when we have Dynamic URI
     *
     * @param array $segments The segments (not including last) from our previous search
     * @param string $last The last segment from our previous search
     * @return string|bool
     */
    private function withDynamicUri($segments, $last, $last_uri = '')
    {
        $dynamic_uri = (empty($last_uri)) ? $last : $last.'/'.$last_uri;

        $last = array_pop($segments);

        $check =
            rtrim(empty($segments) ? '/' : implode('/', $segments), '/').
            '/'.
            $last;

        if($this->exists($check)) {
            Url::$QSTR = $dynamic_uri;
            return $this->render($check);
        }

        $check = $last.'/'.'index';

        if($this->exists($check)) {
            Url::$QSTR = $dynamic_uri;
            return $this->render($check);
        }

        if (empty($segments)) {
            return false;
        }

        // This gets a bit repetitive...
        return $this->withDynamicUri($segments, $last, $dynamic_uri);
    }

    /**
     * Respond with 404 error page
     *
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory
     */
    private function output404()
    {
        // if (config('app.debug') !== false) {
        //     throw new \Symfony\Component\HttpKernel\Exception\HttpException(404);
        // }

        return response()->view('_errors.404', [], 404);
    }

    /**
     * Get Variables in Plugins
     *
     * @return array
     */
    public function getPluginVariables()
    {
        $variables = [];

        $registered = Plugins::twig()['variable'];

        foreach(array_keys(Plugins::installedPlugins()) as $plugin) {

            if (isset($registered[$plugin])) {
                foreach ($registered[$plugin] as $class) {
                    $obj = app($class);
                    $variables[$obj->name()] = $obj->run();
                }
            }
        }

        return $variables;
    }
}
