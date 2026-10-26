<?php

/** @var \Systopic\System\Panels\PanelNode $this */

/** An ini size ("128M", "2G", "512K", "-1") in bytes. */
$getBytes = static function (string $value): int {
	$value = trim($value);
	$number = (int) $value;
	return match (strtoupper(substr($value, -1))) {
		'G' => $number * 1024 ** 3,
		'M' => $number * 1024 ** 2,
		'K' => $number * 1024,
		default => $number,
	};
};

$post_max_size = array(
    'label' => 'Post Size',
    'status' => 'warning'
);

$getPostMaxSize = $getBytes((string) ini_get('post_max_size'));

if ($getPostMaxSize >= '262144000') {
    $post_max_size['label'] = 'Your Post Size: <b>' . str::formatbytes((int) $getPostMaxSize) . '</b>';
    $post_max_size['status'] = 'confirm';
} else {
    $post_max_size['label'] = 'Your Post Size: <b>' . str::formatbytes((int) $getPostMaxSize) . '</b>. We recommend at least <b>250 MB</b>';
}

$max_execution_time = array(
    'label' => 'Execution Time',
    'status' => 'warning'
);

$get_max_execution_time = (int) ini_get('max_execution_time');

if ($get_max_execution_time === 0 || $get_max_execution_time >= 60) {
    $max_execution_time['label'] = 'Your Script Time is: <b>' . ($get_max_execution_time === 0 ? 'unlimited' : $get_max_execution_time . ' sec') . '</b>';
    $max_execution_time['status'] = 'confirm';
} else {
    $max_execution_time['label'] = 'Your Script Time is max: <b>' . $get_max_execution_time . ' sec.</b> We recommend at least <b>60 sec.</b>';
}


$memory_limit = array(
    'label' => 'Execution Time',
    'status' => 'warning'
);

$get_memory_limit = $getBytes((string) ini_get('memory_limit'));

if ($get_memory_limit >= '262144000') {
    $memory_limit['label'] = 'Your Memory Limit is max: <b>' . str::formatbytes((int) $get_memory_limit) . '</b>';
    $memory_limit['status'] = 'confirm';
} else {
    $memory_limit['label'] = 'Your Memory Limit is max: <b>' . str::formatbytes((int) $get_memory_limit) . '.</b> We recommend at least <b>250 MB.</b>';
}

$display_errors = array(
    'label' => 'Display Errors',
    'status' => 'warning'
);

$get_display_errors = ini_get('display_errors');

if (!$get_display_errors) {
    $display_errors['label'] = 'Display Errors is <b>off</b>';
    $display_errors['status'] = 'confirm';
} else {
    $display_errors['label'] = 'Display Errors is <b>on</b>. We recommend to turn them <b>off</b>.';
}

$log_errors = array(
    'label' => 'Log Errors',
    'status' => 'warning'
);

$get_log_errors = ini_get('log_errors');
$get_log_errors_path = trim((string) (defined('PHPLOGFILE') ? PHPLOGFILE : ini_get('error_log')));
$log_errors_writable = $get_log_errors_path !== '' && is_file($get_log_errors_path) && is_writable($get_log_errors_path);

if ($get_log_errors && $log_errors_writable) {
    $log_errors['label'] = 'Log Errors is <b>on</b>. File <b>' . htmlspecialchars($get_log_errors_path) . '</b>';
    $log_errors['status'] = 'confirm';
} else if ($get_log_errors) {
    $log_errors['label'] = 'Log Errors is <b>on</b> but <b>' . htmlspecialchars($get_log_errors_path !== '' ? $get_log_errors_path : 'no file') . '</b> is not writable.';
} else {
    $log_errors['label'] = 'Log Errors <b>off</b>. We recommend to turn them <b>on</b>.';
}

$upload_max_size = array(
    'label' => 'Upload Size',
    'status' => 'warning'
);

$getUploadMaxSize = $getBytes((string) ini_get('upload_max_filesize'));

if ($getUploadMaxSize >= 128 * 1024 * 1024) {
    $upload_max_size['label'] = 'Your Upload Size: <b>' . str::formatbytes((int) $getUploadMaxSize) . '</b>';
    $upload_max_size['status'] = 'confirm';
} elseif ($getUploadMaxSize >= 50 * 1024 * 1024) {
    $upload_max_size['label'] = 'Your Upload Size: <b>' . str::formatbytes((int) $getUploadMaxSize) . '</b>. Fine for most files, <b>128 MB</b> is better for video.';
} else {
    $upload_max_size['label'] = 'Your Upload Size: <b>' . str::formatbytes((int) $getUploadMaxSize) . '</b>. We recommend at least <b>50 MB</b>, better <b>128 MB</b>.';
}

if ($getPostMaxSize < $getUploadMaxSize) {
    $post_max_size['label'] .= '. Post size is smaller than the upload size, so large uploads are rejected.';
    $post_max_size['status'] = 'error';
}


$php_version = array(
    'label' => 'PHP Version',
    'status' => 'error'
);

$php_version_full = PHP_VERSION;

if (version_compare($php_version_full, '8.4.0', '>=')) {
    $php_version['label'] = 'Your PHP Version: <b>' . htmlspecialchars($php_version_full) . '</b>';
    $php_version['status'] = 'confirm';
} elseif (version_compare($php_version_full, '8.3.0', '>=')) {
    $php_version['label'] = 'Your PHP Version: <b>' . htmlspecialchars($php_version_full) . '</b>. The CMS accepts 8.3, Composer asks for <b>8.4</b>.';
    $php_version['status'] = 'warning';
} else {
    $php_version['label'] = 'Your PHP Version: <b>' . htmlspecialchars($php_version_full) . '</b>. You need at least <b>8.4</b>.';
}


$projectRoot = rtrim((string) fs::$root, '/\\');
$siteRoot = rtrim((string) fs::$siteRoot, '/\\');
$var_writeableFolders = array(
    $projectRoot . '/var/log' => 'var/log',
    $siteRoot . '/var' => 'public/var',
    $siteRoot . '/var/backups' => 'public/var/backups',
    $siteRoot . '/var/formats' => 'public/var/formats',
    $siteRoot . '/var/original' => 'public/var/original',
    $siteRoot . '/var/bin' => 'public/var/bin',
);

foreach ($var_writeableFolders as $folderPath => $label) {
    if (is_dir($folderPath) && is_writable($folderPath)) {
        $var_writeableFolders[$folderPath] = array(
            'label' => "<b>{$label}</b> is writeable",
            'status' => 'confirm',
        );
    } else {
        $var_writeableFolders[$folderPath] = array(
            'label' => "<b>{$label}</b> is missing or not writeable",
            'status' => 'error',
        );
    }
}

// Mail and login: from the instance's config (emailSmtp, Auth\AuthConfig)
$isDev = defined('STAGE') && str_starts_with((string) STAGE, 'DEV');
$instanceName = (string) (\Systopic\System\Config\Config::current()?->instance->name ?? '');
$instanceFile = $instanceName !== '' ? $instanceName . '.config.json' : 'the instance config';
$var_access = array(
    'mail' => emailSmtp::config() !== null
        ? array('label' => 'Mail: <b>' . htmlspecialchars((string) emailSmtp::config()['host']) . '</b>', 'status' => 'confirm')
        : array('label' => $isDev
            ? 'Mail: no host in <b>' . htmlspecialchars($instanceFile) . '</b>. On DEV nothing leaves this machine. It is kept by Mailpit at 127.0.0.1:1025.'
            : 'Mail: no host in <b>' . htmlspecialchars($instanceFile) . '</b>. Nothing is sent.',
            'status' => $isDev ? 'warning' : 'error'),
    'auth' => \Systopic\System\Auth\AuthConfig::secret() !== null
        ? array('label' => 'Login codes: <b>auth.secret</b> is set', 'status' => 'confirm')
        : array('label' => 'Login codes: no <b>auth.secret</b> (32+ characters) - no invitations or codes', 'status' => 'error'),
);

$needed_extensions = array(
    "mysqlnd",
    "xml",
    "curl",
    "dom",
    "imagick",
    "json",
    "mbstring",
    "mysqli",
    "pdo_mysql",
    "pgsql",
    "simplexml",
    "xmlreader",
    "xmlwriter",
    "xsl",
    "zip",
    "openssl",
    "fileinfo",
    "session",
    "iconv",
    "ctype",
    "tokenizer",
);

$var_extensions = array();
foreach ($needed_extensions as $needed_ext) {
    $var_extensions[$needed_ext] = array(
        'label' => "<b>$needed_ext</b> is not loaded",
        'status' => 'error'
    );
}

//$var_extensions = array(
//    'imagick' => array(
//	'label' => '<b>imagick</b> is not loaded',
//	'status' => 'error'
//    ),
//    'pdo' => array(
//	'label' => '<b>pdo</b> is not loaded',
//	'status' => 'error'
//    ),
//);

foreach ($var_extensions as $extension => $status) {
    $extension = (string) $extension;
    if (extension_loaded($extension)) {
        $var_extensions[$extension]['label'] = "<b>{$extension}</b> is loaded";
        $var_extensions[$extension]['status'] = 'confirm';
    }
}

if (class_exists('Imagick', FALSE)) {
    $imagicVersion = Imagick::getVersion();
    preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $imagicVersion['versionString'], $imagicVersion);
    $var_extensions['imagickVersion']['label'] = "Your ImageMagick Version: <b>$imagicVersion[1]</b>. We recommend at least <b>6.9.0</b>";
    $var_extensions['imagickVersion']['status'] = 'error';
    if (version_compare($imagicVersion[1], '6.9.0') >= 0) {
        $var_extensions['imagickVersion']['label'] = "Your ImageMagick Version: <b>$imagicVersion[1] </b>";
        $var_extensions['imagickVersion']['status'] = 'confirm';
    }

    if (!empty(Imagick::queryFormats('PDF'))) {
        $var_extensions['imagickPDF']['label'] = "ImageMagick <b>PDF</b> reading is allowed";
        $var_extensions['imagickPDF']['status'] = 'confirm';
    } else {
        $var_extensions['imagickPDF']['label'] = "ImageMagick <b>PDF</b> reading is not allowed. Only matters for PDF uploads.";
        $var_extensions['imagickPDF']['status'] = 'warning';
    }

    if (!empty(Imagick::queryFormats('WEBP'))) {
        $var_extensions['imagickWEBP']['label'] = "ImageMagick <b>WEBP</b> reading/writing is allowed";
        $var_extensions['imagickWEBP']['status'] = 'confirm';
    } else {
        $var_extensions['imagickWEBP']['label'] = "ImageMagick <b>WEBP</b> reading/writing is not allowed. Image formats will fail.";
        $var_extensions['imagickWEBP']['status'] = 'error';
    }
} else {
    $var_extensions['imagickVersion']['label'] = "Imagick class does not exist.";
    $var_extensions['imagickVersion']['status'] = 'error';
}

$disabledFunctions = array_values(array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions')))));
$var_functions = array();
foreach (array(
    'exec' => 'ffmpeg frames',
    'shell_exec' => 'finding binaries',
    'proc_open' => 'piped ffmpeg',
) as $function => $why) {
    $enabled = function_exists($function) && !in_array($function, $disabledFunctions, true);
    $var_functions[$function] = array(
        'label' => $enabled ? "<b>{$function}()</b> is available ({$why})" : "<b>{$function}()</b> is disabled ({$why})",
        'status' => $enabled ? 'confirm' : 'error',
    );
}
$var_functions['disable_functions'] = array(
    'label' => $disabledFunctions === array()
        ? '<b>disable_functions</b> is empty'
        : '<b>disable_functions</b>: ' . htmlspecialchars(implode(', ', $disabledFunctions)),
    'status' => $disabledFunctions === array() ? 'confirm' : 'warning',
);

$var_binaries = array();
foreach (array('ffmpeg' => 'video frames', 'ffprobe' => 'video info', 'identify' => 'PDF media info') as $binary => $why) {
    $found = \shellBinary::find($binary);
    $local = $siteRoot . '/var/bin/' . $binary;
    if ($found !== null) {
        $var_binaries[$binary] = array(
            'label' => "<b>{$binary}</b> ({$why}): " . htmlspecialchars($found),
            'status' => 'confirm',
        );
    } elseif (is_file($local)) {
        $var_binaries[$binary] = array(
            'label' => "<b>{$binary}</b> is in the project's var/bin but is not executable.",
            'status' => 'warning',
        );
    } else {
        $var_binaries[$binary] = array(
            'label' => "<b>{$binary}</b> was not found ({$why}).",
            'status' => $binary === 'identify' ? 'warning' : 'error',
        );
    }
}

$var_server = array();
$htaccess = $siteRoot . '/.htaccess';
$var_server['htaccess'] = array(
    'label' => is_file($htaccess) ? '<b>public/.htaccess</b> is present' : '<b>public/.htaccess</b> is missing',
    'status' => is_file($htaccess) ? 'confirm' : 'error',
);
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    foreach (array(
        'mod_rewrite' => 'error',
        'mod_headers' => 'warning',
        'mod_expires' => 'warning',
        'mod_deflate' => 'warning',
    ) as $module => $missingStatus) {
        $loaded = in_array($module, $modules, true);
        $var_server[$module] = array(
            'label' => $loaded ? "<b>{$module}</b> is loaded" : "<b>{$module}</b> is not loaded",
            'status' => $loaded ? 'confirm' : $missingStatus,
        );
    }
} else {
    $var_server['apache'] = array(
        'label' => 'Apache module list is not available (PHP-FPM or nginx). Rewrite has to be configured the same way as .htaccess.',
        'status' => 'info',
    );
}

try {
    $pdo = \Systopic\Db\Connection::default()->pdo();
    $serverVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $shortVersion = explode('-', $serverVersion)[0];
    $var_server['mysql'] = array(
        'label' => 'Database server <b>' . htmlspecialchars($serverVersion) . '</b>',
        'status' => version_compare($shortVersion, '8.0.0', '>=') ? 'confirm' : 'warning',
    );
    $prefix = defined('DEFAULT_TABLE_PREFIX') ? (string) DEFAULT_TABLE_PREFIX : 'cms';
    $like = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $prefix) . '%';
    $tables = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($like))->fetchAll(PDO::FETCH_NUM);
    $count = is_array($tables) ? count($tables) : 0;
    $var_server['tables'] = array(
        'label' => $count > 0 ? "<b>{$count}</b> tables with prefix <b>" . htmlspecialchars($prefix) . "</b>" : 'No tables with prefix <b>' . htmlspecialchars($prefix) . '</b>',
        'status' => $count > 0 ? 'confirm' : 'error',
    );
} catch (Throwable $e) {
    $var_server['mysql'] = array(
        'label' => 'Database connection failed: ' . htmlspecialchars($e->getMessage()),
        'status' => 'error',
    );
}

// Where vendor/systopic/* come from (SYS/.cursor/docs/composer.md): DEV and
// STAGING redirect them to the shared packages/ folder through the global
// Composer config, so they are symlinks (junctions on Windows); RC and LIVE
// install them from the source, so they are real folders. is_link() is false
// for a junction, hence the comparison of the real path with the own one.
$stage = \Systopic\System\Config\Config::current()?->instance->stage ?? '';
$wantLink = match ($stage) {
    'DEV', 'STAGING' => true,
    'RC', 'LIVE' => false,
    default => null,
};
// Every installed package from Composer's own record: version and where it
// came from. Third-party packages are shown, not judged — a DEV machine may
// take them from the mirror (repositories/) or from Packagist, both are fine.
$vendorDir = fs::toNative(fs::$root . 'vendor/composer');
$installed = json_decode((string) @file_get_contents($vendorDir . DIRECTORY_SEPARATOR . 'installed.json'), true);
$devNames = $installed['dev-package-names'] ?? [];
$var_packages = array();
$var_thirdParty = array();
foreach ($installed['packages'] ?? [] as $package) {
    $name = (string) $package['name'];
    $dir = $vendorDir . DIRECTORY_SEPARATOR . (string) ($package['install-path'] ?? '../' . $name);
    $real = (string) realpath($dir);
    $own = (string) realpath(dirname($dir)) . DIRECTORY_SEPARATOR . basename($dir);
    $linked = is_link($dir) || strcasecmp($real, $own) !== 0;
    $source = $package['source']['url'] ?? $package['dist']['url'] ?? '';
    $where = $linked
        ? 'linked to <b>' . htmlspecialchars($real) . '</b>'
        : 'from <b>' . htmlspecialchars((string) $source) . '</b>';
    $label = "<b>$name</b> " . htmlspecialchars((string) ($package['version'] ?? '?'))
        . (in_array($name, $devNames, true) ? ' (dev)' : '') . " $where";

    if (!str_starts_with($name, 'systopic/')) {
        $var_thirdParty[$name] = array('label' => $label, 'status' => 'info');
        continue;
    }
    $var_packages[$name] = array(
        'label' => $label,
        'status' => $wantLink === null || $wantLink === $linked ? 'confirm' : 'warning',
    );
    if ($wantLink === true && !$linked) {
        $var_packages[$name]['label'] .= ". On <b>$stage</b> it should be a link to packages/ — the global Composer config has no repository for packages/*, or composer update has not run since.";
    } elseif ($wantLink === false && $linked) {
        $var_packages[$name]['label'] .= ". On <b>$stage</b> it should come from the source — this server has a global Composer redirect.";
    }
}
if ($var_packages === array()) {
    $var_packages['none'] = array(
        'label' => 'No <b>systopic/*</b> in vendor/composer/installed.json — composer install has not run.',
        'status' => 'error',
    );
}
