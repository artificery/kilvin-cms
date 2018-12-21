<?php

namespace Kilvin\Libraries\Twig;

use Illuminate\View\Compilers\CompilerInterface;
use Twig_Environment;
use Twig_Error_Loader;
use Exception;
use InvalidArgumentException;
use Kilvin\Libraries\Twig\Template;

/**
 * Compiles Twig templates.
 */
class Compiler implements CompilerInterface
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * Create a new instance of the Twig compiler.
     *
     * @param \Twig_Environment $twig
     */
    public function __construct(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Returns the instance of Twig used to render the template.
     *
     * @return \Twig_Environment
     */
    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * Twig handles this for us. Here to satisfy interface.
     *
     * {@inheritdoc}
     */
    public function getCompiledPath($path)
    {
        return $this->twig->getCacheFilename($path);
    }

    /**
     * Twig handles this for us. Here to satisfy interface.
     *
     * {@inheritdoc}
     */
    public function isExpired($path)
    {
        $time = filemtime($this->getCompiledPath($path));

        return $this->twig->isTemplateFresh($path, $time);
    }

    /**
     * {@inheritdoc}
     */
    public function compile($path)
    {
        try {
            $this->load($path);
        } catch (Exception $ex) {
            // Unable to compile
            // Try running `php artisan twig:lint`
        }
    }

    /**
     * Compile the view at the given path.
     *
     * @param string $path
     *
     * @throws \InvalidArgumentException
     *
     * @return string \Kilvin\Libraries\Twig\Template
     */
    public function load($path)
    {
        // Load template
        try {
            $template = $this->twig->loadTemplate($path);
        } catch (Twig_Error_Loader $e) {
            throw new InvalidArgumentException("Error loading $path: ". $e->getMessage(), $e->getCode(), $e);
        }

        if ($template instanceof Template) {
            // Events are already fired by the View Environment
            $template->setFiredEvents(true);
        }

        return $template;
    }
}
