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

$get_max_execution_time = ini_get('max_execution_time');

if ($get_max_execution_time >= '60') {
    $max_execution_time['label'] = 'Your Script Time is: <b>' . $get_max_execution_time . ' sec</b>';
    $max_execution_time['status'] = 'confirm';
} else {
    $max_execution_time['label'] = 'Your Script Time is max: <b>' . $get_max_execution_time . ' sec.</b> We recommend at least <b>120 sec.</b>';
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
$get_log_errors_path = ini_get('error_log');
$get_log_errors_path = trim($get_log_errors_path);

if ($get_log_errors && $get_log_errors_path === 'var/log/PHP_errors.log') {
    $log_errors['label'] = 'Log Errors is <b>on</b> and Error Log Path is <b>' . 'var/log/PHP_errors.log</b>';
    $log_errors['status'] = 'confirm';
} else if ($get_log_errors && $get_log_errors_path !== 'var/log/PHP_errors.log') {
    $log_errors['label'] = "Log Errors is <b>on</b> but Error Log Path is <b>{$get_log_errors_path}</b>. <br>We recommend to change it in <b>var/log/PHP_errors.log</b>";
} else if (!$get_log_errors && $get_log_errors_path == 'var/log/PHP_errors.log') {
    $log_errors['label'] = 'Log Errors off <b>on</b> but Error Log Path <b>is</b> <b>' . 'var/log/PHP_errors.log</b>';
} else {
    $log_errors['label'] = 'Log Errors <b>off</b>. <br>We recommend to turn them <b>on</b>.';
}



if ($get_log_errors_path === 'var/log/PHP_errors.log') {
    $log_errors_path['label'] = 'Your Error Log Path is <b>' . 'var/log/PHP_errors.log</b>';
    $log_errors_path['status'] = 'confirm';
} else {
    $log_errors_path['label'] = 'Error Log Path is <b>$get_log_errors_path</b>. We recommend to change it to <b>' . 'var/log/PHP_errors.log</b>.';
}

$upload_max_size = array(
    'label' => 'Upload Size',
    'status' => 'warning'
);

$getUploadMaxSize = $getBytes((string) ini_get('upload_max_filesize'));

if ($getUploadMaxSize >= '128') {
    $upload_max_size['label'] = 'Your Upload Size: <b>' . str::formatbytes((int) $getUploadMaxSize) . '</b>';
    $upload_max_size['status'] = 'confirm';
} else {
    $upload_max_size['label'] = 'Your Upload Size: <b>' . str::formatbytes((int) $getUploadMaxSize) . '</b>. We recommend at least <b>128MB</b>';
}


$php_version = array(
    'label' => 'PHP Version',
    'status' => 'error'
);

$get_php_version = phpversion();
$get_php_version = preg_match_all("/^[0-9]\.[0-9]/", $get_php_version, $php_matches);
$php_matches = $php_matches[0];
$php_matches = $php_matches[0];

if ($php_matches >= '8.3') {
    $php_version['label'] = 'Your PHP Version: <b>' . $php_matches . '</b>';
    $php_version['status'] = 'confirm';
} else {
    $php_version['label'] = 'Your PHP Version: <b>' . $php_matches . '</b>. You need at least <b>8.3</b>';
}


$var_writeableFolders = array(
    'var' => array(
        'label' => 'Upload folder <b>var</b> <b>is not</b> writeable',
        'status' => 'error'
    ),
    'var/backups' => array(
        'label' => 'Upload folder <b>' . 'var/backups</b> <b>is not</b> writeable',
        'status' => 'error'
    ),
    'var/formats' => array(
        'label' => 'Upload folder <b>' . 'var/formats</b> <b>is not</b> writeable',
        'status' => 'error'
    ),
    'var/log' => array(
        'label' => 'Upload folder <b>' . 'var/log</b> <b>is not</b> writeable',
        'status' => 'error'
    ),
    'var/original' => array(
        'label' => 'Upload folder <b>' . 'var/original</b> <b>is not</b> writeable',
        'status' => 'error'
    ),
);

foreach ($var_writeableFolders as $folder => $status) {
    $folderPath = (string) $folder;
    if (fs::isDir(fs::$root . $folderPath) && is_writeable(fs::$root . $folderPath)) {
        $var_writeableFolders[$folderPath]['label'] = "Upload folder <b>{$folderPath}</b> is writeable";
        $var_writeableFolders[$folderPath]['status'] = 'confirm';
    } else {
        $var_writeableFolders[$folderPath]['label'] = "Upload folder <b>{$folderPath}</b> <b>is not</b> writeable";
        $var_writeableFolders[$folderPath]['status'] = 'error';
    }
}

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
        $var_extensions['imagickPDF']['label'] = "ImageMagick <b>PDF</b> reading is not allowed";
        $var_extensions['imagickPDF']['status'] = 'confirm';
    }

    if (!empty(Imagick::queryFormats('WEBP'))) {
        $var_extensions['imagickWEBP']['label'] = "ImageMagick <b>WEBP</b> reading/writing is allowed";
        $var_extensions['imagickWEBP']['status'] = 'confirm';
    } else {
        $var_extensions['imagickWEBP']['label'] = "ImageMagick <b>WEBP</b> reading/writing is not allowed";
        $var_extensions['imagickWEBP']['status'] = 'confirm';
    }
} else {
    $var_extensions['imagickVersion']['label'] = "Imagick class does not exist.";
    $var_extensions['imagickVersion']['status'] = 'error';
}

if (function_exists('exec')) {
    $var_extensions['exec']['label'] = "exec function enabled";
    $var_extensions['exec']['status'] = 'confirm';
} else {
    $var_extensions['exec']['label'] = "exec function disabld";
    $var_extensions['exec']['status'] = 'error';
}


$output = shell_exec('mysql -V');
preg_match('@[0-9]+\.[0-9]+\.[0-9]+@', $output, $mysqlVersion);
$var_extensions['mysqlVersion']['label'] = "Your MYSQL Version: <b>$mysqlVersion[0]</b>. We recommend at least <b>8.0.0</b>";
$var_extensions['mysqlVersion']['status'] = 'error';
if (version_compare($mysqlVersion[0], '8.0.0') >= 0) {
    $var_extensions['mysqlVersion']['label'] = "Your MYSQL Version: <b>$mysqlVersion[0] </b>";
    $var_extensions['mysqlVersion']['status'] = 'confirm';
}
