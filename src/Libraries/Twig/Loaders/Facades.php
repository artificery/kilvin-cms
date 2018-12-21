<?php

namespace Kilvin\Libraries\Twig\Loaders;

/**
 * Extension to expose defined facades to the Twig templates.
 *
 * See the `extensions.php` config file, specifically the `facades` key
 * to configure those that are loaded.
 *
 * Use the following syntax for using a facade in your application.
 *
 * <code>
 *     {{ Facade.method(param, ...) }}
 *     {{ Config.get('app.timezone') }}
 * </code>
 */
class Facades extends Loader implements \Twig_Extension_GlobalsInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'Cms_Twig_Extension_Loader_Facades';
    }

    /**
     * {@inheritDoc}
     */
    public function getGlobals()
    {
        $load    = config('twig.facades', []);
        $globals = [];

        foreach ($load as $facade => $options) {
            list($facade, $callable, $options) = $this->parseCallable($facade, $options);

            $globals[$facade] = new FacadeCaller($callable, $options);
        }

        return $globals;
    }
}
