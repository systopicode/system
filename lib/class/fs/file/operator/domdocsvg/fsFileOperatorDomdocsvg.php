<?php

class fsFileOperatorDomdocsvg {

	public $operator;
	public $file;

	/** the logo group, set by importLogoGroup() */
	public ?DOMNode $g = null;
	/** background rect, set by setBackground() */
	public ?DOMNode $bg = null;
	public int|float $padding = 0;

	/** guards createDocument() against re-entering through the doc/svg getters */
	private bool $documentBuilt = false;

	function __construct($operator) {
		$this->operator = $operator;
		$this->file = $operator->file;
	}

	/**
	 * doc and svg build the document on first access, as __get used to.
	 * createDocument() writes both and reads $this->doc while doing so — the
	 * guard flag is what keeps that read from calling the getter again.
	 */
	public ?DOMDocument $doc {
		get { $this->documentBuilt || $this->createDocument($this->file->contents); return $this->doc; }
	}

	public ?DOMNode $svg {
		get { $this->documentBuilt || $this->createDocument($this->file->contents); return $this->svg; }
	}

	public ?object $viewbox {
		get => $this->viewbox ??= $this->getViewbox();
	}

	public mixed $width    { get => $this->viewbox->width; }
	public mixed $height   { get => $this->viewbox->height; }
	public mixed $sizeUnit { get => $this->viewbox->sizeUnit; }

	function getViewbox() {
		if (preg_match('~^([^\s]+) ([^\s]+) ([^\s]+) ([^\s]+)$~', $this->svg->getAttribute('viewBox'), $match)) {
			// parse viewBox
			$sizeUnit = 'px';
			if ($this->svg->getAttribute('width') && preg_match('~^([^a-z]+)([a-z]*)~i', $this->svg->getAttribute('width'), $match2)) {
				// parse width attribute if exists
				if ($match2[2]) {
					$sizeUnit = $match2[2];
				}
			}
			return (object) [
						'minX' => $match[3],
						'minY' => $match[4],
						'width' => $match[3],
						'height' => $match[4],
						'sizeUnit' => $sizeUnit
			];
		}
		d('svg viewBox error');
	}

	function getLogoGroup($srcSvgNode) {
		foreach ($srcSvgNode->childNodes AS $childNode) {
			if ($childNode->nodeType !== XML_ELEMENT_NODE) {
				continue;
			}
			if ($childNode->getAttribute('id') === 'logos') {
				p('found logos Layer');
				return $childNode;
			}
		}
		return $srcSvgNode->getElementsByTagName('g')->item(0);
	}

	function importLogoGroup($src) {
		$srcLogoGroup = $this->getLogoGroup($srcSvgNode);
		$this->g = $this->doc->importNode($srcLogoGroup, TRUE);
		$this->svg->appendChild($this->g);
	}

	function importSvgNode($src) {
		$srcSvgNode = $src->getElementsByTagName('svg')->item(0);
		$this->svg = $this->doc->importNode($srcSvgNode, TRUE);
		$this->doc->appendChild($this->svg);
	}

	function createDocument($srcSvg) {
		$this->documentBuilt = true; // before the first $this->doc read below
		if (empty($srcSvg)) {
			t('EMPTY');
			$srcSvg = EMPTY_SVG;
		}
		$src = new DOMDocument('1.0', 'utf-8');
		libxml_use_internal_errors(TRUE);
		$src->loadXML($srcSvg, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$this->doc = new DOMDocument('1.0', 'utf-8');
		$this->importSvgNode($src);
		$this->doc->formatOutput = TRUE;
		$errors = libxml_get_errors();
		if (count($errors)) {
			p($errors);
		}
		return $this;
	}

	function allNodes($callback, $parent = NULL) {
		if (is_null($parent)) {
			$parent = $this->svg;
		}
		foreach ($parent->childNodes AS $childNode) {
			if ($childNode->nodeType !== XML_ELEMENT_NODE) {
				continue;
			}
			$callback($childNode);
			$this->allNodes($callback, $childNode,);
		}
	}

	function removeAttributes($parent = NULL, $attributes = ['class', 'fill', 'id', 'style']) {
		if (is_null($parent)) {
			$parent = $this->svg;
		}
		foreach ($parent->childNodes AS $childNode) {
			if ($childNode->nodeType !== XML_ELEMENT_NODE) {
				continue;
			}
			foreach ($attributes AS $attribute) {
				$childNode->removeAttribute($attribute);
			}
			$this->removeAttributes($childNode, $attributes);
		}
		return $this;
	}

	function setPadding($padding) {
		$this->padding = $padding;
		return $this->setCanvas();
	}

	function setCanvas() {
		$width = 1000 + 2 * $this->padding;
		$height = $this->viewbox->height + 2 * $this->padding;
		$this->svg->removeAttribute('viewbox'); // must be lowercase -> dom doc bug
		$this->svg->setAttribute('viewBox', "0 0 $width $height");
		$this->g->setAttribute('transform', "translate($this->padding $this->padding)");
		return $this;
	}

	function setColor($color) {
		if (!$this->g) {
			$this->createDocument($this->file->contents);
		}
		$this->g->setAttribute('fill', $color);
		return $this;
	}

	function setRootFillColor($color) {
		$g = $this->doc->createElementNS($this->svg->namespaceURI, 'g');
		$g->setAttribute('fill', $color);
		while ($child = $this->svg->firstChild) {
			if ($child instanceof DOMElement) {
				$tag = strtolower($child->tagName);
				if (in_array($tag, ['defs', 'metadata', 'title', 'desc'], true)) {
					// keep at root
					$this->svg->removeChild($child);
					$this->svg->appendChild($child); // re-append so order is preserved
					continue;
				}
			}
			$g->appendChild($child);
		}
		$this->svg->appendChild($g);
	}

	function setBackground($color = FALSE) {
		if (!isset($this->bg)) {
			$this->bg = $this->doc->createElement('rect');
		}
		$this->bg->setAttribute('x', 0);
		$this->bg->setAttribute('y', 0);
		$this->bg->setAttribute('width', $this->viewbox->width + 2 * $this->padding);
		$this->bg->setAttribute('height', $this->viewbox->height + 2 * $this->padding);
		if ($color) {
			$this->bg->setAttribute('fill', $color);
			$this->bg->removeAttribute('visibility');
		} else {
			$this->bg->setAttribute('visibility', 'hidden');
		}
		$this->svg->insertBefore($this->bg, $this->g);
		return $this;
	}

	function upload($srcSvg) {
		$this->createDocument($srcSvg)->removeAttributes();
		$this->logo->storeFile();
	}

	function toHtml() {
		return (string) $this;
	}

	function toSvg() {
		return (string) $this;
	}

	function __toString() {
		return $this->doc->saveXML();
	}
}
