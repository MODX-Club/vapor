<?php
/*
 * MODX Revolution
 *
 * Copyright 2006-2013 by MODX, LLC. All rights reserved.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 */
/**
 * Instantiates the setup program.
 *
 * @package modx
 * @subpackage setup
 */
/* do a little bit of environment cleanup if possible */
 

@ error_reporting(E_ALL ^ E_NOTICE);
@ ini_set('display_errors',1);

@ ignore_user_abort(true);
@ ini_set('max_execution_time', 3600);

@ ini_set('magic_quotes_runtime', 0);
@ ini_set('magic_quotes_sybase', 0);

define('MODX_API_MODE', true);

/* start session */
session_start();
$isCommandLine = php_sapi_name() == 'cli';
if ($isCommandLine) {
    foreach ($argv as $idx => $argv) {
        $p = explode('=',ltrim($argv,'--'));
        if (isset($p[1])) {
            $_REQUEST[$p[0]] = $p[1];
        }
    }
    if (!empty($_REQUEST['core_path']) && is_dir($_REQUEST['core_path'])) {
        define('MODX_CORE_PATH',$_REQUEST['core_path']);
    }
    if (!empty($_REQUEST['config_key'])) {
        define('MODX_CONFIG_KEY',$_REQUEST['config_key']);
    }
}
else{
	die('Denied! Execution must be via command line.');
}

/* check for compatible PHP version */
define('MODX_SETUP_PHP_VERSION', phpversion());
$php_ver_comp = version_compare(MODX_SETUP_PHP_VERSION, '5.1.1');
if ($php_ver_comp < 0) {
    die('<html><head><title></title></head><body><h1>FATAL ERROR: MODX Setup cannot continue.</h1><p>Wrong PHP version! You\'re using PHP version '.MODX_SETUP_PHP_VERSION.', and MODX requires version 5.1.1 or higher.</p></body></html>');
}

/* make sure json extension is available */
if (!function_exists('json_encode')) {
    die('<html><head><title></title></head><body><h1>FATAL ERROR: MODX Setup cannot continue.</h1><p>MODX requires the PHP JSON extension! You\'re PHP configuration at version '.MODX_SETUP_PHP_VERSION.' does not appear to have this extension enabled. This should be a standard extension on PHP 5.2+; it is available as a PECL extension in 5.1.</p></body></html>');
}

/* make sure date.timezone is set for PHP 5.3.0+ users */
if (version_compare(MODX_SETUP_PHP_VERSION,'5.3.0') >= 0) {
    $phptz = @ini_get('date.timezone');
    if (empty($phptz)) {
        die('<html><head><title></title></head><body><h1>FATAL ERROR: MODX Setup cannot continue.</h1><p>To use PHP 5.3.0+, you must set the date.timezone setting in your php.ini. Please do set it to a proper timezone before proceeding. A list can be found <a href="http://us.php.net/manual/en/timezones.php">here</a>.</p></body></html>');
    }
}
if (!$isCommandLine) {
    $https = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : false;
    $installBaseUrl= (!$https || strtolower($https) != 'on') ? 'http://' : 'https://';
    $installBaseUrl .= $_SERVER['HTTP_HOST'];
    if ($_SERVER['SERVER_PORT'] != 80) $installBaseUrl= str_replace(':' . $_SERVER['SERVER_PORT'], '', $installBaseUrl);
    $installBaseUrl .= ($_SERVER['SERVER_PORT'] == 80 || ($https !== false || strtolower($https) == 'on')) ? '' : ':' . $_SERVER['SERVER_PORT'];
    $installBaseUrl .= $_SERVER['PHP_SELF'];
    define('MODX_SETUP_URL', $installBaseUrl);
} else {
    define('MODX_SETUP_URL','/');
}

/* session loop-back tester */
if (!$isCommandLine && (!isset($_GET['s']) || $_GET['s'] != 'set') && !isset($_SESSION['session_test'])) {
    $_SESSION['session_test']= 1;
    echo "<html><head><title>Loading...</title><script>window.location.href='" . MODX_SETUP_URL . "?s=set';</script></head><body></body></html>";
    exit ();
} elseif (!$isCommandLine && isset($_GET['s']) && $_GET['s'] == 'set' && !isset($_SESSION['session_test'])) {
    die('<html><head><title></title></head><body><h1>FATAL ERROR: MODX Setup cannot continue.</h1><p>Make sure your PHP session configuration is valid and working.</p></body></html>');
}


$basePath= strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/';
// print $basePath;


@include(  $basePath . '/config.core.php');
if (!defined('MODX_CORE_PATH')) define('MODX_CORE_PATH', $basePath . '/core/');



/* include the modX class */
if (!@include_once (MODX_CORE_PATH . "model/modx/modx.class.php")) {
    $errorMessage = 'Site temporarily unavailable';
    @include(MODX_CORE_PATH . 'error/unavailable.include.php');
    header('HTTP/1.1 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error 503</h1><p>{$errorMessage}</p></body></html>";
    exit();
}

/* Create an instance of the modX class */
$modx= new modX();
if (!is_object($modx) || !($modx instanceof modX)) {
    @ob_end_flush();
    $errorMessage = '<a href="setup/">MODX not installed. Install now?</a>';
    @include(MODX_CORE_PATH . 'error/unavailable.include.php');
    header('HTTP/1.1 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error 503</h1><p>{$errorMessage}</p></body></html>";
    exit();
}



/*print "\n\n". MODX_PROCESSORS_PATH;
print "\n\n";

exit;*/

/* Set the actual start time */
$modx->startTime= $tstart;


$modx->initialize("mgr", array(
    "new_file_permissions"	=> "0664",
    "new_folder_permissions"	=> "0775",
));
 

$modx->loadClass("error.modError", "", false, true);
//exit;
$modx->setLogLevel(xPDO::LOG_LEVEL_DEBUG);
$modx->setLogTarget("HTML");

if(empty($_REQUEST['package'])){
	@ob_end_flush();
	$errorMessage = "Package was not set";
    header('HTTP/1.1 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error</h1><p>{$errorMessage}</p></body></html>";
    exit();
}
$package = trim($_REQUEST['package']);
 
if( $pi = pathinfo($package) 
	AND $filename = $pi['filename']
	AND $signature = strtolower(preg_replace("/\.transport$/i", "", $filename))
){
	// Scan local packages
	$response = $modx->runProcessor("workspace/packages/scanlocal", array());
	$modx->error->reset();


	// print $signature;
	
	$response = $modx->runProcessor("workspace/packages/install", array(
		"signature" => $signature,
	));
	if($response){
		print_r($response->getResponse());
	}
	else{
		echo "Error while install package\n\n";
	}
}
else{
	@ob_end_flush();
	$errorMessage = "Error installed";
    header('HTTP/1.1 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error</h1><p>{$errorMessage}</p></body></html>";
    exit();
}
 
print '<br /><br /><hr />';

$memory = round(memory_get_usage(true)/1024/1024, 4).' Mb';

print "<div>Memory: {$memory}</div>";

$totalTime= ($modx->getMicroTime() - $modx->startTime);
$queryTime= $modx->queryTime;
$queryTime= sprintf("%2.4f s", $queryTime);
$queries= isset ($modx->executedQueries) ? $modx->executedQueries : 0;
$totalTime= sprintf("%2.4f s", $totalTime);
$phpTime= $totalTime - $queryTime;
$phpTime= sprintf("%2.4f s", $phpTime);
print "<div>TotalTime: {$totalTime}</div>"; 

print "\n\n"; exit;

/*$modInstall = new modInstall();
if ($modInstall->getService('lexicon','modInstallLexicon')) {
    $modInstall->lexicon->load('default');
}
$modInstall->findCore();
$modInstall->doPreloadChecks();
$requestClass = $isCommandLine ? 'request.modInstallCLIRequest' : 'request.modInstallRequest';
$modInstall->getService('request',$requestClass);
echo $modInstall->request->handle();*/
exit();