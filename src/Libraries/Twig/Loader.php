<?php

namespace Kilvin\Libraries\Twig;

use Twig_LoaderInterface;
use Twig_Error_Loader;
use InvalidArgumentException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ViewFinderInterface;

/**
 * Basic loader using absolute paths.
 */
class Loader implements Twig_LoaderInterface
{
    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * @var \Illuminate\View\ViewFinderInterface
     */
    protected $finder;

    /**
     * @var array Twig file extensions
     */
    protected $extensions;

    /**
     * @var array Template lookup cache.
     */
    protected $cache = [];

    /**
     * @param \Illuminate\Filesystem\Filesystem     $files     The filesystem
     * @param \Illuminate\View\ViewFinderInterface  $finder
     * @param string                                $extension Twig file extension.
     */
    public function __construct(Filesystem $files, ViewFinderInterface $finder, $extensions = ['twig.html'])
    {
        $this->files      = $files;
        $this->finder     = $finder;
        $this->extensions = $extensions;
    }

    /**
     * Return path to template without the need for the extension.
     *
     * @param string $name Template file name or path.
     *
     * @throws \Twig_Error_Loader
     * @return string Path to template
     */
    public function findTemplate($name)
    {
        if ($this->files->exists($name)) {
            return $name;
        }

        $name = $this->normalizeName($name);

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        try {
            $this->cache[$name] = $this->finder->find($name);
        } catch (InvalidArgumentException $ex) {
            throw new Twig_Error_Loader($ex->getMessage());
        }

        return $this->cache[$name];
    }

    /**
     * Normalize the Twig template name to a name the ViewFinder can use
     *
     * @param  string $name Template file name.
     * @return string The parsed name
     */
    protected function normalizeName($name)
    {
        foreach($this->extensions as $extension) {
            if ($this->files->extension($name) === $extension) {
                $name = substr($name, 0, - (strlen($extension) + 1));
                break;
            }
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        try {
            $this->findTemplate($name);
        } catch (Twig_Error_Loader $exception) {
            return false;
        }

        return true;
    }

    /**
     * @param string $name
     *
     * @deprecated Will be dropped with support of 1.x in favour of getSourceContext()
     *
     * @return string
     */
    public function getSource($name)
    {
        $path = $this->findTemplate($name);

        return $this->files->get($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceContext($name)
    {
        $path = $this->findTemplate($name);

        return new \Twig_Source($this->files->get($path), $name, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheKey($name)
    {
        return $this->findTemplate($name);
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($name, $time)
    {
        return $this->files->lastModified($this->findTemplate($name)) <= $time;
    }
}
