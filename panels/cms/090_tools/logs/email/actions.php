<?php

/** @var tools $this */

// Define EMAILLOGFILE constant if not already defined
if (!defined('EMAILLOGFILE')) {
    define('EMAILLOGFILE', fs::$root . 'var/log/email.log');
}

switch ($this->action) {
    case 'clearLog':
	fs::file_put_contents(EMAILLOGFILE, '');
	$ajaxresponse['functionName'] = 'reload';
	$ajaxresponse['args'] = array(1);
	break;
}