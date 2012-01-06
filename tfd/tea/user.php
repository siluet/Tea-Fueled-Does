<?php namespace TFD\Tea;

	use TFD\Admin;
	use TFD\Crypter;
	use TFD\DB\MySQL;
	use TFD\Config as C;
	
	class User{
	
		public static function __flags(){
			return array(
				'h' => 'help',
				'a' => 'add',
				'p' => 'password',
				'r' => 'remove',
			);
		}
		
		public static function help(){
			echo <<<MAN
Add, update, and remove users.

	Usage: tea user <args>

Arguments:

	-h, --help     This page
	-a, --add      Add a user

TFD Homepage: http://teafueleddoes.com/
Tea Homepage: http://teafueleddoes.com/v2/tea

MAN;
			exit(0);
		}
		
		public static function add($args){
			$username = $args[0];
			while(empty($username)){
				echo "Username: ";
				$username = Tea::response();
			}
			$password = $args[1];
			while(empty($password)){
				echo "Password: ";
				system('stty -echo');
				$password = Tea::response();
				system('stty echo');
				echo "\n";
			}
			// add user
			if(Admin::add_user($username, $password)){
				echo "{$username} added!\n";
			}else{
				echo "Could not add user!\n";
			}
		}
		
		public static function password($args){
			$username = $args[0];
			while(empty($username)){
				echo 'Username: ';
				$username = Tea::response();
			}
			$user = MySQL::table(C::get('admin.table'))->where('username', '=', $username)->limit(1)->get();
			if(empty($user)){
				throw new \Exception("User {$username} is not a valid username.");
			}
			$password = $args[1];
			while(empty($password)){
				echo 'Password: ';
				system('stty -echo');
				$password = Tea::response();
				system('stty echo');
				echo "\n";
			}
			// update password
			if(MySQL::table(C::get('admin.table'))->where('username', '=', $username)->set('hash', Crypter::hash($password))){
				echo "Password updated.\n";
			}else{
				echo "Could not update password.\n";
			}
		}
		
		public static function remove($args){
			$username = $args[0];
			while(empty($username)){
				echo 'Username: ';
				$username = Tea::response();
			}
			$user = MySQL::table(C::get('admin.table'))->where('username', '=', $username)->delete();
			echo "User {$username} removed.\n";
		}
	
	}