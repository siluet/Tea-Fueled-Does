<?php

/**
 * This file gets everything going. Unless you know what your doing, I wouldn't touch this file.
 */

// our file extension
define('EXT', '.php');

// main directories
define('PUBLIC_DIR', $public_dir.'/');
define('BASE_DIR', __DIR__.'/');
define('APP_DIR', realpath($app_dir).'/');
define('CONTENT_DIR', realpath($content_dir).'/');
unset($public_dir, $app_dir, $content_dir);

// app directories
define('FUNCTIONS_DIR', APP_DIR.'functions/');
define('LIBRARY_DIR', APP_DIR.'library/');
define('TEA_DIR', APP_DIR.'tea/');

// public directories
define('MASTERS_DIR', CONTENT_DIR.'masters/');
define('MODELS_DIR', CONTENT_DIR.'models/');
define('PARTIALS_DIR', CONTENT_DIR.'partials/');
define('TEMPLATES_DIR', CONTENT_DIR.'templates/');
define('VIEWS_DIR', CONTENT_DIR.'views/');

// our helper
include_once(FUNCTIONS_DIR.'helpful'.EXT);

// Config class
include_once(APP_DIR.'config'.EXT);

TFD\Config::load(array(
	'application.version' => 'pre-3',
	'application.maintenance_page' => MASTERS_DIR.'maintenance'.EXT,
	'render.default_master' => MASTERS_DIR.'master'.EXT,
	
	'views.admin' => 'admin',
	'views.login' => 'login',
	'views.public' => 'public',
	'views.protected' => 'protected',
	'views.partials' => 'partials',
	'views.error' => 'error'
));

// Autoloader
include_once(APP_DIR.'loader'.EXT);
spl_autoload_register(array('TFD\Loader', 'load'));

// create some class aliases
use TFD\Loader;
Loader::create_aliases(array(
	'CSS' => 'TFD\CSS',
	'JavaScript' => 'TFD\JavaScript',
	'Flash' => 'TFD\Flash',
	'MySQL' => 'TFD\DB\MySQL',
	'ReCAPTCHA' => 'TFD\ReCAPTCHA',
	'Postmark' => 'TFD\Postmark',
	'Image' => 'TFD\Image',
	'Validate' => 'TFD\Validate',
	'Template' => 'TFD\Template',
	'Benchmark' => 'TFD\Benchmark',
	'Render' => 'TFD\Core\Render',
	'Redis' => 'TFD\Redis',
	'Config' => 'TFD\Config',
	'HTML' => 'TFD\HTML',
	'Form' => 'TFD\Form',
	'S3' => 'TFD\S3',
	'Cache' => 'TFD\Cache',
	'Paginator' => 'TFD\Paginator',
	'Request' => 'TFD\Core\Request',
	'File' => 'TFD\File',
	'Model' => 'TFD\Model',
	'RSS' => 'TFD\RSS',
	'Event' => 'TFD\Core\Event',
));
Loader::add_alias('PostmarkBatch', '\TFD\PostmarkBatch', APP_DIR.'postmark'.EXT);
Loader::add_alias('PostmarkBounces', '\TFD\PostmarkBounces', APP_DIR.'postmark'.EXT);
if(APP_DIR !== BASE_DIR.'tfd/') Loader::app_dir(str_replace(BASE_DIR, '', APP_DIR));
if(CONTENT_DIR !== BASE_DIR.'content/') Loader::content_dir(str_replace(BASE_DIR, '', CONTENT_DIR));

// Load app.php
include_once(CONTENT_DIR.'app'.EXT);

// Error Handlers
set_exception_handler(function($e){
	\TFD\Core\Event::fire('exception', $e);
});

set_error_handler(function($number, $error, $file, $line){
	if(error_reporting() === 0) return;
	\TFD\Core\Event::fire('error', $number, $error, $file, $line);
}, E_ALL ^ E_NOTICE);
