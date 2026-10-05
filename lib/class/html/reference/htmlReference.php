<?php

class htmlReference {

	static $tags = [
	    'html', 'head', 'body', // base structure
	    'p', 'h1', 'h2', 'h3', 'h4','h5','h6', 'button','small', // headings paragraphs
	    'a', 'pre', 'label', 'b', 'u', 'i', 's','button', // inline text elements
	    'div', 'section','article', 'header', 'main', 'footer', // blocks structures
	    'table', 'thead', 'th', 'tbody', 'tfoot', 'tr', 'td', // table
	    'form', 'select', 'option', 'textarea', 'button', // form
	    'ul', 'li', // lists
	    'video','iframe', // media
	    'script', // invisible
	];
	static $selfclosingTags = ['br', 'hr', 'img', 'input', 'link'];
	static $attributes = [
	    'action', 'method', 'name', 'accept', 'value', 'type', 'placeholder', 'min', 'max', 'rel',
	    'colspan', 'rowspan',
	    'title', 'hreflang', 'target',
	    'onchange', 'onclick',
	    'src', 'controls',
	    'spellcheck',
	    'download',
	    'contenteditable',
        'width','height',
	    'viewBox', 'text-anchor', 'font-size', 'fill', 'x', 'y',
		'class','id',
	];
	static $emptyAttributes = ['checked', 'autoplay', 'muted', 'loop', 'playsinline', 'disabled', 'readonly'];

	static function parentshipValid($parent, $child) {

		return !in_array("$parent>$child", [
			    "input>input", // resolve abiguous methos input()
			    "textarea>input", // resolve abiguous methos input()
		]);
	}
}
