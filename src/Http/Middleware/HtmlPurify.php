<?php

namespace Kilvin\Http\Middleware;

use File;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

class HtmlPurify extends TransformsRequest
{
	private $settings;
	private $purifier;

    /**
     * The attributes that should not be purified.
     *
     * @var array
     */
    protected $except = [
        'password',
        'password_confirmation',
    ];

   /**
     * Load up Purifier
     *
     * @return void
     */
    public function __construct()
    {
    	$this->loadSettings();

    	$this->purifier = new \HTMLPurifier($this->settings);
    }

   /**
     * Transform the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform($key, $value)
    {
    	if ($this->performTransform($key) === false) {
    		return $value;
    	}

        return is_string($value) ? trim($this->purifier->purify($value)) : $value;
    }

   /**
     * Loads the General Settings
     *
     * @return void
     */
    private function loadSettings()
    {
    	$this->settings = \HTMLPurifier_Config::createDefault();
    	$this->settings->loadArray(config('htmlpurifier.general'));

    	$config = config('htmlpurifier.general');

		// Insure Cache Directory exists
    	if (!empty($config['Cache.SerializerPath'])) {
	        if (!File::isDirectory($config['Cache.SerializerPath'])){
	            File::makeDirectory($config['Cache.SerializerPath']);
	        }
    	}

        if (defined('REQUEST')) {
    		if (!empty(config('htmlpurifier.'.REQUEST)) and is_array(config('htmlpurifier.'.REQUEST))) {
    			$this->settings->loadArray(config('htmlpurifier.'.REQUEST));
    		}
    	}
    }

   /**
     * Determine whether to perform the cleaning
     *
     * @param string
     * @return boolean
     */
    private function performTransform($key)
    {
    	if (in_array($key, $this->except, true)) {
            return false;
        }

    	if (defined('REQUEST') && REQUEST == 'CP' && config('htmlpurifier.CP') === false) {
    		return false;
    	}

    	if (defined('REQUEST') && REQUEST == 'SITE' && config('htmlpurifier.SITE') === false) {
    		return false;
    	}

        return true;
    }

}
