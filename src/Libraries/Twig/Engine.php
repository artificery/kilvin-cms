<?php

namespace Kilvin\Libraries\Twig;

use Twig_Error;
use Twig_Error_Loader;
use ErrorException;
use Kilvin\Libraries\Twig\Loader;
use Illuminate\View\Engines\CompilerEngine;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Kilvin\Exceptions\CmsTemplateException;

/**
 * View engine for Twig files.
 */
class Engine extends CompilerEngine
{
    /**
     * Data that is passed to all templates.
     *
     * @var array
     */
    protected $global_data = [];

    /**
     * Used to find the file that has failed.
     *
     * @var \Kilvin\Libraries\Twig\Loader
     */
    protected $loader = [];

    /**
     * Create a new Twig view engine instance.
     *
     * @param \Kilvin\Libraries\Twig\Compiler        $compiler
     * @param \Kilvin\Libraries\Twig\Loader            $loader
     * @param array                              $global_data
     */
    public function __construct(Compiler $compiler, Loader $loader, array $global_data = [])
    {
        parent::__construct($compiler);

        $this->loader      = $loader;
        $this->global_data = $global_data;
    }

    /**
     * Get the global data.
     *
     * @return array
     */
    public function getGlobalData()
    {
        return $this->global_data;
    }

    /**
     * Set global data sent to the view.
     *
     * @param array $global_data Global data.
     *
     * @return void
     */
    public function setGlobalData(array $global_data)
    {
        $this->global_data = $global_data;
    }

    /**
     * Append global data that is sent to the view.
     *
     * @param array $data Global data.
     * @return void
     */
    public function appendGlobalData(array $data)
    {
        $this->global_data = array_merge($this->global_data, $data);
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @param string $path Full file path to Twig template.
     * @param array  $data
     *
     * @throws \Twig_Error|\ErrorException When unable to load the requested path.
     *
     * @return string
     */
    public function get($path, array $data = [])
    {
        $data = array_merge($this->global_data, $data);

        // This captures a fair amount of debugging info and suppresses it
        // May want a way to switch this off. -PB
        try {
            $content = $this->compiler->load($path)->render($data);
        } catch (Twig_Error $ex) {
            $this->handleTwigError($ex);
        }

        return $content;
    }

    /**
     * Handle a TwigError exception.
     *
     * @param \Twig_Error $ex
     *
     * @throws \Twig_Error|\ErrorException
     */
    protected function handleTwigError(Twig_Error $ex)
    {
        $previous = $ex->getPrevious();

        // 404, push it through and send to Laravel Handler
        if ($previous && $previous instanceof NotFoundHttpException) {
            throw $previous;
        }

        // HTTP exception, push it through and send to Laravel Handler
        if ($previous && $previous instanceof HttpException) {
            throw $previous;
        }

        $context = $ex->getSourceContext();

        if (null === $context) {
            throw $ex;
        }

        $templateFile = $context->getPath();
        $templateLine = $ex->getTemplateLine();

        if ($templateFile && file_exists($templateFile)) {
            $file = $templateFile;
        } elseif ($templateFile) {
            // Attempt to locate full path to file
            try {
                $file = $this->loader->findTemplate($templateFile);
            } catch (Twig_Error_Loader $exception) {
                // Unable to load template
            }
        }

        if (isset($file)) {
            $ex = new CmsTemplateException($ex->getMessage(), 0, 1, $file, $templateLine, $ex);
        }

        throw $ex;
    }
}
