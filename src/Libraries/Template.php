<?php

namespace Kilvin\Libraries;

use Illuminate\Support\Facades\Auth;
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

        $path = removeDoubleSlashes($this->find($template));

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
            'kilvin' => [
                'version' => KILVIN_VERSION
            ],
            'currentSite' => [
                'name' => Site::config('site_name'),
                'handle' => Site::config('site_handle')
            ],
            'currentUser' => $this->currentUser()
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
     * Get currentUser data
     *
     * @return array
     */
    public function currentUser()
    {
        if (!Auth::check()) {
            return null;
        }

        $data = [
            'member_id' => null,
            'email' => null,
            'screen_name' => null,
            'url' => null,
            'photo_filename' => null,
            'language' => 'en_US',
            'timezone' => null,
            'date_format' => null,
            'time_format' => null,
        ];

        $user = Auth::user();

        foreach ($data as $key => &$val) {
            $val = $user->{$key} ?? $val;
        }

        return $data;
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

        // No segments? Homepage!
        if (empty($segments[0])) {
            return $this->render('index');
        }

        // Homepage with pagination
        // Pagination is the only segment we allow for a homepage request
        // i.e. One cannot do https://site.com/entry-url-title
        // However, one can do this:  https://site.com/page3
        if(count($segments) == 2 && preg_match("#^(\/page\/\d+\/)$#", $uri, $match)) {
            Url::$QSTR = $match[1];
            return $this->render('index');
        }

        // ------------------------------------
        //  Two Options for Name, Template within Folder or Folder with Index
        //  - Template within Folder is PRIMARY
        // ------------------------------------

        $original_segments = $segments;
        $last              = array_pop($segments);

        // We allow certain suffixes in the segments of a request indicating the template type
        // ex: http://site.com/my_folder/index.html
        // ex: http://site.com/my_folder/index.css
        // HOWEVER, if you have an index.html AND index.css template, both of the above
        // requests will load the 'html' template as that type is the primary view.
        // No way currently around this without futzing with how Views are loaded by Laravel
        $suffixes = array_map(function($val) {
            return str_replace('twig.', '', $val);
        }, app('cms.twig.suffixes'));

        if(stristr($last, '.')) {
            $x = explode('.', $last);
            // Only allow one period
            if (sizeof($x) == 2) {
                $suffix = array_pop($x);

                if (in_array($suffix, $suffixes)) {
                    $valid_suffix = $suffix;
                    $last = $x[0];
                }
            }
        }

        // ------------------------------------
        //  Folder within Template
        //  - https://site.com/examples/doom => ./templates/default-site/examples/doom.twig.html
        // ------------------------------------

        $check  =
            rtrim(empty($segments) ? '' : implode('/', $segments), '/').
            '/'.
            $last;

        if ($this->exists($check)) {
            return $this->render($check);
        }

        // ------------------------------------
        //  Folder Request, so look for index file
        //  - https://site.com/examples/doom => ./templates/default-site/examples/doom/index.twig.html
        // ------------------------------------

        $check =
            rtrim(implode('/', $original_segments), '/').
            '/'.
            'index';

        if($this->exists($check)) {
            return $this->render($check);
        }

        // ------------------------------------
        //  No Results? Only a Single Segment in URI?
        //  - Send a 404!
        //  - Dynamic URIs only allowed when there is a template specified in URI
        //  - ex: https://site.com/template-name/my-dynamic-url-segment
        //  - Yes, this means the site's primary index template does not allow dynamic segments
        // ------------------------------------

        if (sizeof($original_segments) == 1) {
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
