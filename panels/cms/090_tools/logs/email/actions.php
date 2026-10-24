<?php

/** @var tools $this */

// Define EMAILLOGFILE constant if not already defined
if (!defined('EMAILLOGFILE')) {
    define('EMAILLOGFILE', fs::$root . 'var/log/email.log');
}

switch ($this->action) {
    case 'downloadLog':
	if (is_file(EMAILLOGFILE)) {
	    header('Content-Type: text/plain; charset=utf-8');
	    header('Content-Disposition: attachment; filename="email.log"');
	    header('Content-Length: ' . (string) filesize(EMAILLOGFILE));
	    readfile(EMAILLOGFILE);
	    exit;
	}
	break;
    case 'clearLog':
	fs::file_put_contents(EMAILLOGFILE, '');
	$ajaxresponse['functionName'] = 'reload';
	$ajaxresponse['args'] = array(1);
	break;
}