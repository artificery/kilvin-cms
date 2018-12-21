<?php

namespace Kilvin\Libraries\Twig;

use Twig_Environment;
use Twig_LoaderInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\View\ViewFinderInterface;
use InvalidArgumentException;
use Twig_Error;

/**
 * Stores the Twig configuration.
 */
class Environment extends Twig_Environment
{
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * {@inheritdoc}
     */
    public function __construct(Twig_LoaderInterface $loader, $options = [], Container $app = null)
    {
        parent::__construct($loader, $options);

        $this->app = $app;
    }

    /**
     * Get the Laravel app.
     *
     * @return \Illuminate\Contracts\Container\Container
     */
    public function getApplication()
    {
        return $this->app;
    }

    /**
     * Set the Laravel app.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     *
     * @return void
     */
    public function setApplication(Container $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function loadTemplate($name, $index = null)
    {
        $template = parent::loadTemplate($name, $index);

        $template->setName($this->normalizeName($name));

        return $template;
    }

    /**
     * Merges a context with the shared variables, same as mergeGlobals()
     *
     * @param array $context An array representing the context
     *
     * @return array The context merged with the globals
     */
    public function mergeShared(array $context)
    {
        // we don't use array_merge as the context being generally
        // bigger than globals, this code is faster.
        foreach ($this->app['view']->getShared() as $key => $value) {
            if (!array_key_exists($key, $context)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    /**
     * Normalize a view name.
     *
     * @param  string $name
     *
     * @return string
     */
    protected function normalizeName($name)
    {
        foreach($this->app['cms.twig.suffixes'] as $suffix) {
            $extension = '.'.$suffix;
            $length = strlen($extension);

            if (substr($name, -$length, $length) === $extension) {
                $name = substr($name, 0, -$length);
                break;
            }
        }

        // Normalize namespace and delimiters
        $delimiter = ViewFinderInterface::HINT_PATH_DELIMITER;
        if (strpos($name, $delimiter) === false) {
            return str_replace('/', '.', $name);
        }

        list($namespace, $name) = explode($delimiter, $name);

        return $namespace.$delimiter.str_replace('/', '.', $name);
    }
}
