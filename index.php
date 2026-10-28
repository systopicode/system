<?php

// From the first line. A notice, or the debug panel, printed before any
// header is otherwise the start of the response, and Apache reads that as
// a header ("End of script output before headers", "Bad header: sysdebug.update").
ob_start();

/**
 * The front controller of every project — <project>/public/index.php only
 * requires this file (written by bin/scaffold.php on composer install and
 * update), so an improvement here reaches every project with the next
 * composer update instead of being copied around.
 *
 * Runs in the global scope on purpose: loader.php and what it includes
 * (settings.php, the legacy libraries) define globals.
 *
 * Without a config of its own the installation gets the setup page
 * (Setup\Setup): config/ is written from the browser, then the database.
 */

// the entry script - <project>/public/index.php, on the web and on the CLI
$__projectEntry = (string) (get_included_files()[0] ?? '');
$__projectRoot  = dirname($__projectEntry, 2);

$composerAutoload = $__projectRoot . '/vendor/autoload.php';
if (!file_exists($composerAutoload)) {
	throw new RuntimeException('Please run "composer install".');
}
require $composerAutoload;

/* * **************************** CLI (agents, tests) ************************** */
// php public/index.php @request.json — replays a browser request from a JSON
// payload; on the web only OUTPUT_FORMAT is defaulted. See src/Agent/AgentCli.php
// and .cursor/docs/cli-testing.md
\Systopic\System\Agent\AgentCli::boot($__projectEntry);

/* * **************************** INI DEFAULTS ************************** */
// Before the config: "ini" in config.json or an instance file overrides them.
// PHP_INI_PERDIR settings (upload_max_filesize, post_max_size) probably have
// no effect here - see https://www.php.net/manual/en/configuration.changes.modes.php
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', 60);
ini_set('memory_limit', '1G');
ini_set('serialize_precision', 14);
ini_set('log_errors', 1);
ini_set('html_errors', false);
// Absolute, so a later chdir cannot send errors into public/var or nowhere.
// Config may override this afterwards; a relative override is put back below.
$__logDir = $__projectRoot . '/var/log';
if (!is_dir($__logDir)) {
	mkdir($__logDir, 0775, true);
}
$__phpLog = $__logDir . '/PHP_errors.log';
ini_set('error_log', $__phpLog);

/* ***************************** INSTANCE + SITE (config/) ************************** */
// config.json: project constants (and sites without a database);
// <stage>[.<machine>].config.json: one installation (matches, db, debug);
// config.local.json: this machine, never synced. See .cursor/docs/config.md
try {
	$config = \Systopic\System\Config\Config::boot(
		$__projectRoot . '/config',
		$_SERVER,
		$__projectRoot,
		defined('CONFIG_INSTANCE') ? CONFIG_INSTANCE : null,
	);
} catch (\Systopic\System\Config\NotConfigured $__notConfigured) {
	// no config of its own yet: ask for it (exits)
	\Systopic\System\Setup\Setup::run($__projectRoot, $_SERVER, $__notConfigured);
}
// STAGE, INSTANCE, SYS_PATH, DB_*, DEBUG*, PUBLIC_VERSIONAPPENDIX, ini, and the
// project constants from config.json (DEFAULT_TABLE_PREFIX, ACCOUNT_WHITELIST,
// PROJECT_NAMESPACE)
$config->defineConstants();
// The instance or the server php.ini may point at a machine log such as
// ServBay. This project keeps its own file, next to the other logs.
ini_set('log_errors', '1');
ini_set('error_log', $__phpLog);
if (!defined('PHPLOGFILE')) {
	define('PHPLOGFILE', $__phpLog);
}
if (!is_file(PHPLOGFILE)) {
	touch(PHPLOGFILE);
}
umask(0002);
// a config, but no database yet - or the first account still to make: the
// later steps of the setup (exit)
if (!\Systopic\System\Setup\Setup::hasDatabase($config) || \Systopic\System\Setup\Setup::accountPending($__projectRoot)) {
	\Systopic\System\Setup\Setup::run($__projectRoot, $_SERVER, null);
}

/* * **************************** LOADER ************************** */
chdir($__projectRoot);
include SYS_PATH . 'sys.php';
sys::init(SYS_PATH);
if (\clientBrowser::isCrawler() || \http::get('crawler')) {
	define('JSOFF', TRUE);
}

if (\fs::openSysDir()) {
	include 'loader.php';
	\fs::closeDir();
}
