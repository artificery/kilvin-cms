<?php

namespace Kilvin\Libraries\Twig\Loaders;

use Twig_Error;
use Twig_Markup;

/**
 * Handles calling the method on the called facade.
 */
class FacadeCaller
{
    /**
     * @var string The name of the facade that has to be called
     */
    protected $facade;

    /**
     * @var array Customisation options for the called facade / method.
     */
    protected $options;

    /**
     * Create a new caller for a facade.
     *
     * @param string $facade
     * @param array  $options
     */
    public function __construct($facade, array $options = [])
    {
        $this->facade  = $facade;
        $this->options = array_merge(
            [
                'is_safe' => null,
                'charset' => null,
                'allowed' => true
            ],
            $options
        );
    }

    /**
     * Return facade that will be called.
     *
     * @return string
     */
    public function getFacade()
    {
        return $this->facade;
    }

    /**
     * Return extension options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Call the method on the facade.
     *
     * Supports marking the method as safe, i.e. the returned HTML won't be escaped.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        $is_safe = ($this->options['is_safe'] === true);

        // Allow is_safe option to specify individual methods of the facade that are safe
        if (is_array($this->options['is_safe']) && in_array($method, $this->options['is_safe'])) {
            $is_safe = true;
        }

        // Allow allowed option to specify individual methods of the facade that are permitted to be called
        if (is_array($this->options['allowed']) && ! in_array($method, $this->options['allowed'])) {
            throw new \Twig_Error(sprintf("Method '%s' not permitted on the '%s' facade", $method, $this->facade));
        }

        $result  = forward_static_call_array([$this->facade, $method], $arguments);
        $is_safe = ($is_safe and (is_string($result) or method_exists($result, '__toString')));

        return ($is_safe) ? new Twig_Markup($result, $this->options['charset']) : $result;
    }
}
