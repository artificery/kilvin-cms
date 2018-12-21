<?php

namespace Kilvin\Libraries\Twig\Templates;

use Illuminate\Database\Eloquent\Builder as IlluminateBuilder;

class Builder extends IlluminateBuilder implements \IteratorAggregate
{
    /**
     * Required by the IteratorAggregate interface.
     * Returns a Illuminate Collection which has the necessary array implementations for Twig
     *
     * @return \Illuminate\Support\Collection
     */
    public function getIterator()
    {
        return $this->get();
    }
}
