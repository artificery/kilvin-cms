<?php

namespace Kilvin\Http\Controllers\Cp;

use Kilvin\Libraries\Documentation;

class DocsController extends Controller
{
    /**
     * The documentation repository.
     *
     * @var Documentation
     */
    protected $docs;

    /**
     * Create a new controller instance.
     *
     * @param  Documentation  $docs
     * @return void
     */
    public function __construct(Documentation $docs)
    {
        $this->docs = $docs;
    }

    /**
     * Show a documentation page.
     *
     * @param  string|null $page
     * @return Response
     */
    public function show($page = null)
    {
        $sectionPage = $page ?: 'index';
        $content = $this->docs->get($sectionPage);

        if (is_null($content)) {
            return response()->view('kilvin::cp.docs.page', [
                'title' => 'Page not found',
                'index' => $this->docs->getIndex(),
                'content' => view('kilvin::cp.docs.missing'),
                'currentVersion' => KILVIN_DOCS_VERSION,
                'currentSection' => '',
                'canonical' => null,
            ], 404);
        }

        $title = $section = '';

        if (preg_match('/<h1>(.+)<\/h1>/is', $content, $match)) {
            $title = $match[1];
        }

        if ($this->docs->sectionExists($page)) {
            $section .= '/'.$page;
        } elseif (! is_null($page)) {
            return redirect(kilvin_cp_url('docs'));
        }

        $canonical = null;

        if ($this->docs->sectionExists($sectionPage)) {
            $canonical = 'docs/'.$sectionPage;
        }

        return view('kilvin::cp.docs.page', [
            'title' => $title,
            'index' => $this->docs->getIndex(),
            'content' => $content,
            'currentVersion' => KILVIN_DOCS_VERSION,
            'currentSection' => $section,
            'canonical' => $canonical,
        ]);
    }
}
