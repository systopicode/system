<?php

/**
 * The debug helpers when the project has no debug.php (tools/debug, rolled
 * out by the projects manager) — a fresh project, or a server that never got
 * one. The system calls p(), d(), scripttime(), logroute() and DEBUG()
 * everywhere; without them the first request ends in "undefined function".
 *
 * The same answers as the debug tool's own cloak (tools/debug/cloak.php):
 * every call returns an object that swallows whatever is chained onto it,
 * DEBUG() is FALSE. Each one guarded — a debug.php included later, or a
 * package with its own stand-ins, wins.
 */

if (!class_exists('debugCloak', false)) {
	class debugCloak
	{
		public function __call($name, $arguments)
		{
			return $this;
		}
	}
}

if (!function_exists('p')) {
	function p(...$args)
	{
		return new debugCloak;
	}
}

if (!function_exists('d')) {
	function d(...$args)
	{
		return new debugCloak;
	}
}

if (!function_exists('t')) {
	function t(...$args)
	{
		return new debugCloak;
	}
}

if (!function_exists('scripttime')) {
	function scripttime(...$args)
	{
		return new debugCloak;
	}
}

if (!function_exists('logroute')) {
	function logroute(...$args)
	{
		return new debugCloak;
	}
}

if (!function_exists('DEBUG')) {
	function DEBUG($type = null, $value = null)
	{
		return false;
	}
}
