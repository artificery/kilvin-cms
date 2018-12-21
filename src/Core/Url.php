<?php

namespace Kilvin\Core;

use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

class Url
{
    public static $URI			= '';       // The full URI query string: /weblog/entry/124/
    public static $QSTR     	= '';       // Only the query segment of the URI: 124

    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    // --------------------------------------------------------------------

    /**
    * Remove problematic characters from string
    *
    * @param string $str
    * @return string
    */
	public static function sanitize($str)
	{
		$bad	= ['$', 	'(', 		')',	 	'%28', 		'%29'];
		$good	= ['&#36;',	'&#40;',	'&#41;',	'&#40;',	'&#41;'];

		return str_replace($bad, $good, $str);
	}

    // --------------------------------------------------------------------

    /**
    * Parse Segments from URI and build Complete URI var
    *
    * @param string $uri
    * @return void
    */
    public static function parseUri($uri = '')
    {
        if (trim($uri) == '') {
            return;
        }

        $uri = static::sanitize(static::trimSlashes($uri));

        if (trim($uri) == '') {
            return;
        }

        $x = 0;

        $ex = explode("/", $uri);

        // ------------------------------------
        //  Maximum Number of Segments Check
        // - If the URL contains more than 10 segments, error out
        // ------------------------------------

        if (count($ex) > 10) {
        	exit("Error: The URL contains too many segments.");
        }

        // ------------------------------------
        //  Parse URI segments
        // ------------------------------------

        $n = 1;

        $uri = '';

        for ($i = $x; $i < count($ex); $i++) {
			// nothing naughty
			if (strpos($ex[$i], '=') !== FALSE && preg_match('#.*(\042|\047).+\s*=.*#i', $ex[$i]))
			{
				$ex[$i] = str_replace(['"', "'", ' ', '='], '', $ex[$i]);
			}

            $uri .= $ex[$i].'/';

            $n++;
        }

        $uri = substr($uri, 0, -1);

        // Reassign the full URI
        static::$URI = '/'.$uri.'/';
    }

    // --------------------------------------------------------------------

    /**
    * Parse out the Query String from Segments
    *
    * @return void
    */
	public static function parseQueryString()
	{
		if ( ! request()->segment(2))
		{
			$QSTR = 'index';
		}
		elseif ( ! request()->segment(3))
		{
			$QSTR = request()->segment(2);
		}
		else
		{
			$QSTR = preg_replace("|".'/'.preg_quote(request()->segment(1)).'/'.preg_quote(request()->segment(2))."|", '', static::$URI);
		}

		static::$QSTR = static::trimSlashes($QSTR);
	}

    // --------------------------------------------------------------------

    /**
    * Trim Slashes from String
    *
    * @param string $str
    * @return string
    */
    public static function trimSlashes($str)
    {
        if (substr($str, 0, 1) == '/') {
            $str = substr($str, 1);
        }

        if (substr($str, 0, 5) == "&#47;") {
            $str = substr($str, 5);
        }

        if (substr($str, -1) == '/') {
            $str = substr($str, 0, -1);
        }

        if (substr($str, -5) == "&#47;") {
            $str = substr($str, 0, -5);
        }

        return $str;
    }
}
