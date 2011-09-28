<?php namespace TFD;
	
	use Content\Hooks;
	use TFD\Core\Request;
	use TFD\Core\Router;
	use TFD\Core\Render;
		
	class App{
	
		private static $request;
		
		/**
		 * Magic Methods
		 */
		
		function __construct(){
			session_start();
			Hooks::spinup();
			self::bootstrap($_GET['tfd_request']);
		}
		
		function __destruct(){
			Hooks::spindown();
		}
		
		/**
		 * Accessors
		 */
		
		public function request(){
			return (string)self::$request;
		}
		
		public function url($segment = null){
			if($segment == null){
				return $this->request();
			}else{
				$segments = explode('/', $this->request());
				$seg = $segment - 1;
				return $segments[$seg];
			}
		}
		
		/**
		 * Class methods
		 */
		
		private static function bootstrap($request){
			self::$request = new Request($request);
			Flash::bootstrap();
		}
		
		public function site(){
			$do = self::$request->run();
			if($do !== false){
				return $do;
			}
			
			$router = new Router($this->request()); // create a router object
			$route = $router->get();
			
			if(is_array($route)){
				if(($route['auth'] || $route['admin']) && !Admin::loggedin()){
					// need to login
					setcookie('redirect', $this->request(), time() + 3600);
					redirect(LOGIN_PATH);
					exit;
				}
				if($route['admin']){
					return Admin::dashboard($route);
				}
				$render_info = $route;
			}else{
				$render_info = array('view' => $this->request());
			}
			Hooks::www();
			return Render::page($render_info)->render();
		}
		
		function partial($file, $extra=null){
			if(is_array($extra)) extract($extra);
			$file = PARTIALS_DIR.$file.EXT;
			if(file_exists($file)){
				ob_start();
				include($file);
				$partial = ob_get_contents();
				ob_end_clean();
				return $partial;
			}elseif($this->testing){
				$this->error->report("Partial: {$file} doesn't exist");
			}
			return;
		}
		
		protected function render($options){
			if(!$this->is_admin) Hooks::front();
			Hooks::render();
			extract($options);
			// get full path of the file
			if($dir){
				if($dir == 'admin-dashboard' && !$this->admin->loggedin()){
					$this->send_404();
					$master = '404';
				}else{
					$file = CONTENT_DIR . $dir.'/'.$file.EXT;
				}
			}else{
				$file = WEB_DIR . $file . EXT;
			}
			// start the output buffer
			ob_start();
			if(file_exists($file)){
				// include the file
				include($file);
				// save file contents to var
				$content = ob_get_contents();
				// clean the output buffer
				ob_clean();
			}elseif($this->testing && $this->request() !== '404'){
				$this->send_404();
				$this->error->report($file.' not found!');
			}else{
				// if the file wasn't found, 404
				$this->send_404();
				$master = '404';
			}
			// figure out the title
			if(!$options['title'] && $title == ''){
				$title = SITE_TITLE;
			}elseif($options['title']){
				$title = $options['title'];
			}
			if(!empty($replace)){
				foreach($replace as $item => $value){
					$content = str_replace($item, $value, $content);
				}
			}
			// get the full path to the master
			$master = MASTERS_DIR . $master . EXT;
			if(!file_exists($master)){
				// if the master doesn't exist, use the default one
				$master = DEFAULT_MASTER;
			}
			// include master
			include($master);
			// save it to a var
			$page = ob_get_contents();
			// end the output buffer
			ob_end_clean();
			
			// return the page
			return $page;
		}
		
		// General Functions
		
		function send_404(){
			header('HTTP/1.1 404 Not Found');
		}
	
	}