<?php

namespace Kilvin\Http\ViewComposers;

use Illuminate\View\View;

class Installer
{
    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        // Exception was thrown somewhere and CMS is not loaded
        // So, we disable this ViewComposer's loading as it requires
        // that the CMS be loaded and functioning
        if ( ! defined('CMS_NAME')) {
            return;
        }

        $view->with('cms', [
                'name' => CMS_NAME,
                'version' => KILVIN_VERSION,
                'build_date' => KILVIN_BUILD_DATE
            ]
        );

        $view->with(
            'installer',
            [
                'header_elements' => $this->installerJsAndCssHeaderElements()
            ]
        );
    }

    /**
     * Script and Link elements for Installer header
     *
     * @return string
     */
    public function installerJsAndCssHeaderElements()
    {
        $installer_path = '/installer/';
        $manifest_path = KILVIN_THEMES.'/mix-manifest.json';
        $js_suffix = 'v'.KILVIN_VERSION;
        $css_suffix = 'v'.KILVIN_VERSION;

        if (file_exists($manifest_path)) {
            try {
                $manifest = json_decode(file_get_contents($manifest_path), true);
            } catch (\Exception $e) {
                throw new CmsFatalException('The Mix manifest could not be parsed for Kilvin Themes.');
            }

            $js_file = '/installer/installer.js';
            $css_file = '/installer/installer.css';

            if (isset($manifest[$js_file]) && preg_match('/\?id=(.+)$/', $manifest[$js_file], $match)) {
                $js_suffix = $match[1];
            }

            if (isset($manifest[$css_file]) && preg_match('/\?id=(.+)$/', $manifest[$css_file], $match)) {
                $css_suffix = $match[1];
            }
        }

        return
            "<script type='text/javascript' src='{$installer_path}javascript/{$js_suffix}'></script>".PHP_EOL.
            "<link rel='stylesheet' type='text/css' href='{$installer_path}css/{$css_suffix}' />".PHP_EOL;
    }
}
