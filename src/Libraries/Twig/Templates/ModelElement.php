<?php

namespace Kilvin\Libraries\Twig\Templates;

/**
 * Model Element for Templates
 *
 * Allows an Eloquent model to become a Twig Element
 * This means that we can iterate over the results of an Eloquent query essentially.
 */
trait ModelElement
{
	// ALL SEARCH CRITERIA SHOULD BE SCOPES!!!

   /**
	 * Required by the IteratorAggregate interface.
	 * Returns a Illuminate Collection which has the necessary array implementations
     * This is the magic that allows us to use an Eloquent model as an "Element" within Twig
	 *
	 * @return \Illuminate\Support\Collection
	 */
	public function getIterator()
	{
		return $this->find();
	}

   /**
	 * Returns all elements that match the criteria.
	 *
	 * @param array $attributes Any last-minute parameters that should be added.
	 * @return array The matched elements.
	 */
	public function find($attributes = null)
	{
		//$this->setAttributes($attributes);

		// This is the place where we would use something like a Type/Transformer on the results

		return $this->get();
	}

	/**
     * Create a new (modified) Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Automatically called when treated as a Twig function
     *
     * Can override this to set default Eloquent search criteria or even accept function arguments
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function run()
    {
        return $this;
    }
}
