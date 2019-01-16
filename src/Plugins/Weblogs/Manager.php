<?php
namespace Kilvin\Plugins\Weblogs;

use Kilvin\Support\Plugins\Manager as BaseManager;

class Manager extends BaseManager
{
    protected $version	= '1.0.0';
    protected $name = 'Weblogs';
    protected $description = 'Output weblog entries and related content';
    protected $developer = 'Paul Burdick';
    protected $developer_url = 'https://kilvincms.com';
    protected $documentation_url = 'https://kilvincms.com/docs';
    protected $has_cp = 'n';
}
