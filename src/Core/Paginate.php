<?php

namespace Kilvin\Core;

class Paginate
{
        public $base_url        = ''; // The page we are linking to (when using this class in the CP)
        public $path            = ''; // The page we are linking to (when using this class in a public page)
        public $qstr_var        = ''; // The name of the query string variable containing current page number
        public $cur_page        = ''; // The current page being viewed
        public $total_count     = ''; // Total number of items (database results)
        public $per_page        = ''; // Max number of items you want shown per page
        public $max_links       = 3; // Number of "digit" links to show before/after the currently viewed page

        public $first_page      = '';
        public $last_page       = '';
        public $next_link       = '&gt;';
        public $prev_link       = '&lt;';
        public $first_marker    = '&laquo;';
        public $last_marker     = '&raquo;';
        public $first_url       = ''; // Alternative URL for the First Page.
        public $first_div_o     = '';
        public $first_div_c     = '';
        public $next_div_o      = '';
        public $next_div_c      = '';
        public $prev_div_o      = '';
        public $prev_div_c      = '';
        public $num_div_o       = '';
        public $num_div_c       = '';
        public $cur_div_o       = '';
        public $cur_div_c       = '';
        public $last_div_o      = '';
        public $last_div_c      = '';

    // --------------------------------------------------------------------

    /**
    * Constructor
    *
    * @return  void
    */
    public function __construct()
    {
        $this->first_page = $this->first_marker.' '.__('kilvin::core.first');
        $this->last_page  = __('kilvin::core.last').' '.$this->last_marker;
        $this->next_div_o = '&nbsp;';
        $this->num_div_o = '&nbsp;';
        $this->cur_div_o = '&nbsp;';
        $this->last_div_o = '&nbsp;&nbsp;';
        $this->prev_div_o = '&nbsp;';
        $this->prev_div_c = '&nbsp;'.$this->prev_div_c;
        $this->first_div_c = '&nbsp;'.$this->first_div_c;
    }

    // --------------------------------------------------------------------

    /**
    * Show Links
    *
    * @return  string
    */
    public function showLinks()
    {
        // ------------------------------------
        //  Pagination?
        //  - If item count or per-page total is zero, no pagination
        // ------------------------------------

        if ($this->total_count == 0 or $this->per_page == 0) {
           return '';
        }

        // ------------------------------------
        //  Define the base pagination link path
        // ------------------------------------

        $path  = $this->base_url.'&amp;'.$this->qstr_var.'=';

        // ------------------------------------
        //  Determine the total number of pages
        // ------------------------------------

        $num_pages = intval($this->total_count / $this->per_page);

        // ------------------------------------
        //  Do we have an odd number of pages?
        // ------------------------------------

        if ($this->total_count % $this->per_page) {
            $num_pages++;
        }

        // ------------------------------------
        //  Bail out if we only have one page
        // ------------------------------------

        if ($num_pages == 1) {
            return '';
        }

        // ------------------------------------
        //  Determine current page number
        // ------------------------------------

        $uri_page_number = $this->cur_page;
        $this->cur_page = floor(($this->cur_page/$this->per_page) + 1);

        // ------------------------------------
        //  Calculate the start and end numbers
        // ------------------------------------

        $start =
            (($this->cur_page - $this->max_links) > 0) ?
            $this->cur_page - ($this->max_links - 1) :
            1;

        $end =
            (($this->cur_page + $this->max_links) < $num_pages) ?
            $this->cur_page + $this->max_links :
            $num_pages;

        $output = '';

        // ------------------------------------
        //  Render the "First" link
        // ------------------------------------

        if  ($this->cur_page > ($this->max_links + 1)) {
            $first_link = ($this->first_url == '') ? $path.'0' : $this->first_url;
            $output .= $this->first_div_o.'<a href="'.$first_link.'">'.$this->first_page.'</a>'.$this->first_div_c;
        }

        // ------------------------------------
        //  Render the "previous" link
        // ------------------------------------

        if  (($this->cur_page - $this->max_links) >= 0) {

            $i = $uri_page_number - $this->per_page;

            $output .=
                $this->prev_div_o.
                    '<a href="'.$path.$i.'">'.
                        $this->prev_link.
                    '</a>'.
                $this->prev_div_c;
        }

        // ------------------------------------
        //  Integer Links 1 2 3 4
        // ------------------------------------

        for ($loop = $start; $loop <= $end; $loop++) {
            $i = ($loop * $this->per_page) - $this->per_page;

            if ($this->cur_page == $loop)
            {
                $output .=
                    $this->cur_div_o.
                        '<strong>'.$loop.'</strong>'.
                    $this->cur_div_c; // Current page
            }
            else
            {
                $output .=
                    $this->num_div_o.
                        '<a href="'.$path.$i.'">'.
                            $loop.
                        '</a>'.
                    $this->num_div_c;
            }
        }

        // ------------------------------------
        //  Render the "next" link
        // ------------------------------------

        if ($this->cur_page < $num_pages)
        {
            $output .=
                $this->next_div_o.
                    '<a href="'.$path.($this->cur_page * $this->per_page).'">'.
                        $this->next_link.
                    '</a>'.
                $this->next_div_c;
        }

        // ------------------------------------
        //  Render the "Last" link
        // ------------------------------------

        if (($this->cur_page + $this->max_links) < $num_pages)
        {
            $i = (($num_pages * $this->per_page) - $this->per_page);

            $output .=
                $this->last_div_o.
                    '<a href="'.$path.$i.'">'.
                        $this->last_page.
                    '</a>'.
                $this->last_div_c;
        }

        // ------------------------------------
        //  Return the result
        // ------------------------------------
        return removeDoubleSlashes($output);
    }
}
