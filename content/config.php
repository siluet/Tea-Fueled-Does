<?php namespace Content;

/**
 * ENVIRONMENTS
 *
 * TFD allows for your standard development, testing, and production environments.
 * Set the variables (MySQL, error reporting, or anything else you want) for each here
 *  and then for each site, define the environment inside of your .htaccess file (default DEVELOPMENT)
 *
 * TFD includes a custom error reporting class.
 * It is used to report errors that are generated by TFD, not PHP.
 * If "testing mode" is off, it will display, email, or log the error and then try to finish running the script.
 * If testing mode is on, it will display the error (along with logging it or emailing it) and then stop the script.
 *
 * REDIS
 * -----
 * Default Redis port is 6379
 *   If you do not have your Redis server password protected,
 *    leave the password field blank.
 */

	use TFD\Config;
	
	class Environment{
	
		function __construct($env){
			// load some global config options
			$this->general_config();
			$this->api_keys();
			
			// call specific environment settings
			$env = strtolower($env);
			$this->$env();
		}
		
		function general_config(){
			Config::load(array(
				'site.maintenance' => false,
				'site.title' => 'Tea-Fueled Does',
				
				'admin.login' => 'login',
				'admin.logout' => 'logout',
				'admin.path' => 'admin',
				'admin.table' => 'users',
				'admin.auth_key' => '4ea06e01d8e73',
				'admin.login_time' => 3600,
				'admin.cost' => 12, // rounds for hashing passwords
				
				'crypter.rounds' => 10, // default rounds for the crypter class
				
				'application.error_log' => '',
				'application.admin_email' => BASE_DIR.'error.log',
				
				'ajax.path' => 'ajax',
				'ajax.parameter' => 'method'
			));
		}
		
		function api_keys(){
			Config::load(array(
				// ReCAPTCHA - http://www.google.com/recaptcha
				'recaptcha.public_key' => '',
				'recaptcha.private_key' => '',
				
				// Postmark - http://postmarkapp.com/
				'postmark.api_key' => '',
				'postmark.from' => '',
				'postmark.reply_to' => '',
				
				// Amazon S3 - http://aws.amazon.com/s3/
				's3.access_key' => '',
				's3.secret_key' => '',
				's3.bucket' => '',
				's3.acl' => 'private'
			));
		}
		
		/**
		 * ENVIRONMENTS
		 */
		
		function development(){
			// php error reporting
			error_reporting(E_ERROR | E_WARNING | E_PARSE);
			Config::load(array(
				'site.url' => 'http://localhost/', // with trailing slash
				
				'application.mode' => 'testing', // testing or production
				'application.add_user' => true, // ablity to add a user with a url (example.com/?add_user&username=user&password=pass)
				
				'mysql.host' => '127.0.0.1', // do not use "localhost" (use 127.0.0.1 instead)
				'mysql.port' => '8889', // MySQL default is 3306
				'mysql.user' => 'root',
				'mysql.pass' => 'root',
				'mysql.db' => 'tea',
				
				'redis.host' => '',
				'redis.port' => 6379,
				'redis.pass' => ''
			));
		}
		
		function testing(){
			// php error reporting
			error_reporting(E_ERROR | E_WARNING | E_PARSE);
			Config::load(array(
				'site.url' => '',
				
				'application.mode' => 'testing', // testing or production
				'application.add_user' => false,
				
				'mysql.host' => '',
				'mysql.port' => 3306,
				'mysql.user' => '',
				'mysql.pass' => '',
				'mysql.db' => '',
				
				'redis.host' => '',
				'redis.port' => 6379,
				'redis.pass' => '' // blank for none
			));
		}
		
		function production(){
			// php error reporting
			error_reporting(0); // no reporting
			Config::load(array(
				'site.url' => '',
				
				'application.mode' => 'production', // testing or production
				'application.add_user' => false,
				
				'mysql.host' => '',
				'mysql.port' => 3306,
				'mysql.user' => '',
				'mysql.pass' => '',
				'mysql.db' => '',
				
				'redis.host' => '',
				'redis.port' => 6379,
				'redis.pass' => '' // blank for none
			));
		}
	
	}