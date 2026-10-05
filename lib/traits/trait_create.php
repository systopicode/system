<?php

trait trait_create
{

	static function create(...$args): self
	{
		return new self(...$args);
	}

	function __construct(...$args)
	{
		// automatically stores the arguments 
		// in order of the declared class properties
		$vars = array_keys(get_object_vars($this));
		foreach ($args as $index => $value) {
			$this->{$vars[$index]} = $value;
		}
	}
}
