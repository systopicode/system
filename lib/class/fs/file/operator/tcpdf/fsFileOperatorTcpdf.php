<?php

class fsFileOperatorTcpdf extends TCPDF {

	public $colorMode = 'rgba';
	protected $svgCurrentAttribs = [];
	protected $svgCurrentTagname = '';

	// CMYK COLORS from SVG graphics using:
	// data-fill="cmyk(0,0,0,100)" // black
	// data-stroke="cmyk(100,100,0,0)" // blue
	// compatibility must be checked on update

	protected function startSVGElementHandler(...$args) {
		// cache attrs for the element currently being processed
		$this->svgCurrentTagname = $args[1];
		$this->svgCurrentAttribs = $args[2];
		parent::startSVGElementHandler(...$args);
	}

	protected function setSVGStyles(...$args) {
		$obstyle = parent::setSVGStyles(...$args);
		if ($this->colorMode === 'cmyk') {
			$this->setSVGCMYKStyles();
		}
		return $obstyle;
	}

	protected function setSVGCMYKStyles() {
		if (key_exists('data-fill', $this->svgCurrentAttribs)) {
			$fill_color_str = $this->svgCurrentAttribs['data-fill'];
			$fill_color = TCPDF_COLORS::convertHTMLColorToDec($fill_color_str, $this->spot_colors);
			$this->SetFillColorArray($fill_color);
			$this->SetTextColorArray($fill_color);
		}
		if (key_exists('data-stroke', $this->svgCurrentAttribs)) {
			$stroke_color_str = $this->svgCurrentAttribs['data-stroke'];
			$stroke_color = TCPDF_COLORS::convertHTMLColorToDec($stroke_color_str, $this->spot_colors);
			$this->setDrawColorArray($stroke_color);
		}
	}
}
