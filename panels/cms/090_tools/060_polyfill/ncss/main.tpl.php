<?php

foreach (fsDir::get((string) fs::$root . 'css/')->files AS $file) {
	echo html::div()->class('layout')
		('div')->h2($file->name)
		('div')->textarea($file->contents)->style([
	    'overflow' => 'auto',
	    'max-height' => '300px',
	])
		('div')->hr()
	;
}