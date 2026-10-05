<?php

use SVG\SVG;

class fsFileOperator_meyfasvg extends fsFileOperator
{
	private SVG $svg;
	function create()
	{
		$this->svg = new SVG();
	}
}
