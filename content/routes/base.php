<?php

use TFD\Config;
use TFD\Route;
use TFD\Response;
use TFD\Render;

Route::filter('before', function($request, $method) {
	if (Config::get('site.maintenance') === true) {
		return (string)Response::make(Render::error('maintenance'));
	}
});

Route::filter('after-example', function($result, $request, $method) {
	// 
});

use TFD\DB;

Route::get('/db', function() {
	$db = new DB('posts');
	die(print_p($db->get()));
});

Route::get('/test', function() {
	die(Test::foobar());
});

Route::get('/foo', function() {
	die('foobar!');
});
