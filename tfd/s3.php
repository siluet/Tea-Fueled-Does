<?php namespace TFD;

	class S3{
	
		protected static function __signature($string){
			return 'AWS '.Config::get('s3.access_key').':'.self::__hash($string);
		}
		
		private static function __hash($string){
			return base64_encode(hash_hmac('sha1', $string, Config::get('s3.secret_key'), true));
		}
		
		/**
		 * Accessors
		 */
		
		public static function set_bucket($bucket){
			if(!is_string($bucket)){
				$type = gettype($bucket);
				throw new \LogicException("S3::set_bucket() expects a string, {$type} sent");
				return false;
			}
			Config::set('s3.bucket', $bucket);
			return true;
		}
		
		public static function set_acl($acl){
			if(!preg_match('/private|public-read|public-read-write|authenticated-read|bucket-owner-read|bucket-owner-full-control/', strtolower($acl))){
				throw new \LogicException('Not a valid ACL type');
				return false;
			}
			Config::set('s3.acl', $acl);
			return true;
		}
		
		public static function get_bucket(){
			return Config::get('s3.bucket');
		}
		
		public static function get_acl(){
			return Config::get('s3.acl');
		}
		
		/**
		 * Bucket Methods
		 */
		
		public static function list_buckets($detail = false){
			$rest = new S3Rest('GET');
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception("S3::list_buckets(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::list_buckets(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			$results = array();
			
			foreach($response['response']->Buckets->Bucket as $b){
				if($detail){
					$results[] = array(
						'name' => (string)$b->Name,
						'created' => strtotime($b->CreationDate)
					);
				}else{
					$results[] = (string)$b->Name;
				}
			}
			
			return $results;
		}
		
		public static function list_objects($bucket = null, $params = array(), $common_prefixes = false){
			if(!is_null($bucket)) self::set_bucket($bucket);
			$bucket = self::get_bucket();
			
			$rest = new S3Rest('GET', $bucket, '');
			
			if(isset($params['prefix'])) $rest->set_parameter('prefix', $params['prefix']);
			if(isset($params['marker'])) $rest->set_parameter('marker', $params['marker']);
			if(isset($params['max-keys'])) $rest->set_parameter('max-keys', $params['max-keys']);
			if(isset($params['delimiter'])) $rest->set_parameter('delimiter', $params['delimiter']);
			
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception("S3::list_objects(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::list_objects(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			$results = array();
			if($common_prefixes){
				foreach($response['response']->CommonPrefixes as $prefix){
					$results[] = (string)$prefix->Prefix;
				}
			}else{
				foreach($response['response']->Contents as $object){
					$results[] = array(
						'name' => (string)$object->Key,
						'modified' => strtotime($object->LastModified),
						'size' => (string)$object->Size
					);
				}
			}
			
			return $results;
		}
		
		public static function bucket_acl($bucket = null){
			if(is_null($bucket)) $bucket = self::get_bucket();
			
			$rest = new S3Rest('GET', $bucket);
			$rest->set_parameter('acl', null);
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception('S3::bucket_acl(): Unexpected HTTP status');
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::bucket_acl(): {$response['error']['code']}: {$response['error']['message']}");
				return;
			}
			
			$results = array();
			$results['owner'] = (string)$response['response']->Owner->DisplayName;
			foreach($response['response']->AccessControlList->Grant as $grant){
				$results['access'][] = array(
					'name' => (isset($grant->Grantee->DisplayName)) ? (string)$grant->Grantee->DisplayName : (string)$grant->Grantee->URI,
					'permission' => (string)$grant->Permission
				);
			}
			
			return $results;
		}
		
		public static function bucket_location($bucket = null){
			if(is_null($bucket)) $bucket = self::get_bucket();
			
			$rest = new S3Rest('GET', $bucket);
			$rest->set_parameter('location', null);
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception('S3::bucket_location(): Unexpected HTTP status');
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::bucket_location(): {$response['error']['code']}: {$response['error']['message']}");
				return;
			}
			
			return (isset($response['response'][0]) && !empty($response['response'][0])) ? (string)$response['response'][0] : 'US Classic';
		}
		
		public static function create_bucket($bucket = null, $acl = null, $location = null){
			if(!is_null($bucket)) self::set_bucket($bucket);
			if(!is_null($acl)) self::set_acl($acl);
			$bucket = self::get_bucket();
			$acl = self::get_acl();
			
			$rest = new S3Rest('PUT', $bucket, '');
			$rest->set_amz_header('x-amz-acl', $acl);
			
			if(!is_null($location)){
				if(!preg_match('/EU|us-west-1|ap-southeast-1|ap-northeast-1/', $location) || (empty($location) && is_string($location))){
					throw new \LogicException($location.' is not a valid Bucket location');
					return false;
				}else{
					$dom = new \DOMDocument;
					$create_bucket_configuration = $dom->createElement('CreateBucketConfiguration');
					$location_constraint = $dom->createElement('LocationConstraint', $location);
					$create_bucket_configuration->appendChild($location_constraint);
					$dom->appendChild($create_bucket_configuration);
					$data = $dom->saveXML();
					$rest->data($data);
					$rest->size(strlen($data));
					$rest->set_header('Content-Type', 'application/xml');
				}
			}
			
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception("S3::create_bucket(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::create_bucket(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			return true;
		}
		
		public static function delete_bucket($bucket = null){
			if(is_null($bucket)) $bucket = self::get_bucket();
			
			$rest = new S3Rest('DELETE', $bucket);
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 204){
				throw new \Exception("S3::delete_bucket(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::delete_bucket(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			return true;
		}
		
		/**
		 * Object methods
		 */
		
		private static function __put_object($file, $type = 'file', $options = array(), $headers = array(), $meta = array()){
			$bucket = (is_null($options['bucket'])) ? self::get_bucket() : $options['bucket'];
			if(!is_null($options['acl'])) self::set_acl($options['acl']);
			$acl = self::get_acl();
			$uri = (is_null($options['uri'])) ? basename($file) : $options['uri'];
			
			$rest = new S3Rest('PUT', $bucket, $uri);
			
			if($type == 'file' && file_exists($file)){
				$rest->file(fopen($file, 'rb'));
				$rest->size(filesize($file));
				$headers['Content-MD5'] = base64_encode(md5_file($file, true));
				if(!isset($headers['Content-Type'])){
					$finfo = finfo_open(FILEINFO_MIME_TYPE);
					$headers['Content-Type'] = finfo_file($finfo, $file);
					finfo_close($finfo);
				}
			}elseif($type == 'string' && is_string($file)){
				$rest->data($file);
				$rest->size(strlen($file));
				$headers['Content-MD5'] = base64_encode(md5($file, true));
			}else{
				throw new \Exception('Could not upload object');
				return false;
			}
			
			if(!isset($headers['Content-Type'])){
				$headers['Content-Type'] = 'application/octet-stream';
			}
			
			foreach($headers as $header => $value){
				$rest->set_header($header, $value);
			}
			
			$rest->set_amz_header('x-amz-acl', $acl);
			$rest->set_amz_header('x-amz-storage-class', strtoupper($storage));
			foreach($meta as $h => $v){
				$rest->set_amz_header('x-amz-meta-'.$h, $v);
			}
			
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception("S3::put_object(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::put_object(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			return true;
		}
		
		public static function put_object($file, $uri = null, $bucket = null, $acl = null, $storage = 'standard', $headers = array(), $meta = array()){
			return self::__put_object($file, 'file', array('uri' => $uri, 'bucket' => $bucket, 'acl' => $acl, 'storage' => $storage), $headers, $meta);
		}
		
		public static function put_object_from_string($file, $uri, $type = null, $bucket = null, $acl = null, $storage = 'standard', $headers = array(), $meta = array()){
			return self::__put_object($file, 'string', array('uri' => $uri, 'bucket' => $bucket, 'acl' => $acl, 'storage' => $storage), $headers, $meta);
		}
		
		public static function get_object($uri, $bucket = null, $save = false){
			if(is_null($bucket)) $bucket = self::get_bucket();
			
			$rest = new S3Rest('GET', $bucket, $uri);
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception("S3::get_object(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::get_object(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			if(is_resource($save)){
				fwrite($save, $response['response']);
				return true;
			}elseif($save !== false){
				if(($fp = @fopen($save, 'wb')) !== false){
					fwrite($fp, $response['response']);
					@fclose($fp);
					return true;
				}else{
					throw new \Exception('Could not open up file for saving');
					return false;
				}
			}
			
			return $response['response'];
		}
		
		public static function object_info($uri, $bucket = null){
			if(is_null($bucket)) $bucket = self::get_bucket();
			
			$rest = new S3Rest('HEAD', $bucket, $uri);
			$response = $rest->response();
			
			if($response['code'] === 404){
				throw new \Exception("{$uri} does not exist in {$bucket}");
			}elseif(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception("S3::object_info(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::object_info(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			return $response['headers'];
		}
		
		public static function delete_object($uri, $bucket = null){
			if(is_null($bucket)) $bucket = self::get_bucket();
			
			$rest = new S3Rest('DELETE', $bucket, $uri);
			
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 204){
				throw new \Exception("S3::delete_object(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::delete_object(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			return true;
		}
		
		public static function copy_object($bucket, $uri, $acl = null, $src_bucket = null, $scr_uri = null, $headers = array(), $meta = array()){
			if(is_null($src_bucket)) $src_bucket = self::get_bucket();
			if(is_null($src_uri)) $src_uri = $uri;
			if(!is_null($acl)) self::set_acl($acl);
			$acl = self::get_acl();
			
			$rest = new S3Rest('PUT', $bucket, $uri);
			$rest->set_header('Content-Length', 0);
			foreach($headers as $header => $value){
				$rest->set_header($header, $value);
			}
			foreach($meta as $h => $v){
				$rest->set_amz_header('x-amz-meta-'.$h, $v);
			}
			$rest->set_amz_header('x-amz-acl', $acl);
			$rest->set_amz_header('x-amz-copy-source', '/'.$src_bucket.'/'.$src_uri);
			if(count($headers) > 0 || count($meta) > 0){
				$rest->set_amz_header('x-amz-metadata-directive', 'REPLACE');
			}
			
			$response = $rest->response();
			
			if(!isset($response['error']) && $response['code'] !== 200){
				throw new \Exception("S3::copy_object(): Unexpected HTTP status");
				return false;
			}elseif(is_array($response['error'])){
				throw new \Exception("S3::copy_object(): {$response['error']['code']}: {$response['error']['message']}");
				return false;
			}
			
			return true;
		}
		
		public static function authenticated_url($uri, $lifetime = 3600, $bucket = null, $https = false){
			if(is_null($bucket)) $bucket = self::get_bucket();
			
			$expires = time() + $lifetime;
			return sprintf('http%s://%s/%s?AWSAccessKeyId=%s&Expires=%u&Signature=%s',
				$https ? 's' : '',
				$bucket.'.s3.amazonaws.com',
				str_replace('%2F', '/', rawurlencode($uri)),
				Config::get('s3.access_key'),
				$expires,
				urlencode(self::__hash("GET\n\n\n{$expires}\n/{$bucket}/{$uri}"))
			);
		}
	
	}
	
	class S3Rest extends S3{
	
		private static $info = array();
		private static $headers = array();
		private static $amz_headers = array();
		private static $parameters = array();
		private static $resource = '';
		private static $fp = false;
		private static $data = false;
		
		function __construct($verb, $bucket = '', $uri = '', $host = 's3.amazonaws.com'){
			self::$info['verb'] = strtoupper($verb);
			self::$info['bucket'] = strtolower($bucket);
			self::$info['uri'] = (!empty($uri)) ? '/'.str_replace('%2F', '/', rawurlencode($uri)) : '/';
			
			if(!empty(self::$info['bucket'])){
				self::$headers['Host'] = self::$info['bucket'].'.'.$host;
				self::$resource = '/'.self::$info['bucket'].self::$info['uri'];
			}else{
				self::$headers['Host'] = $host;
				self::$resource = self::$info['uri'];
			}
			self::$headers['Date'] = gmdate('D, d M Y H:i:s T');
		}
		
		public function file($fp){
			self::$fp = $fp;
		}
		
		public function data($data){
			self::$data = $data;
		}
		
		public function size($size){
			self::$info['size'] = $size;
		}
		
		public function set_parameter($key, $value){
			self::$parameters[$key] = $value;
		}
		
		public function set_header($key, $value){
			self::$headers[$key] = $value;
		}
		
		public function set_amz_header($key, $value){
			self::$amz_headers[$key] = $value;
		}
		
		public function response(){
			$query = '';
			if(count(self::$parameters) > 0){
				$query = (substr(self::$info['uri'], -1) !== '?') ? '?' : '&';
				foreach(self::$parameters as $key => $value){
					$query .= (is_null($value) || empty($value)) ? $key.'&' : $key.'='.rawurlencode($value).'&';
				}
				$query = substr($query, 0, -1);
				self::$info['uri'] .= $query;
				
				if(array_key_exists('acl', self::$parameters) || array_key_exists('location', self::$parameters) || array_key_exists('torrent', self::$parameters) || array_key_exists('logging', self::$parameters)){
					self::$resource .= $query;
				}
			}
			$url = 'http://'.self::$headers['Host'].self::$info['uri'];
						
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			
			$headers = array();$amz = array();
			foreach(self::$amz_headers as $header => $value){
				if(!empty($value)){
					$headers[] = $header.': '.$value;
					$amz[] = strtolower($header).':'.$value;
				}
				
			}
			foreach(self::$headers as $header => $value){
				if(!empty($value)) $headers[] = $header.': '.$value;
			}
			if(count($amz) > 0){
				sort($amz);
				$amz = "\n".implode("\n", $amz);
			}else{
				$amz = '';
			}
			
			$headers[] = 'Authorization: '.parent::__signature(
				self::$info['verb']."\n".self::$headers['Content-MD5']."\n".self::$headers['Content-Type']."\n".self::$headers['Date'].$amz."\n".self::$resource
			);
			
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, array(&$this, '__write_callback'));
			curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$this, '__header_callback'));
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			
			switch(self::$info['verb']){
				case 'PUT':
					if(self::$fp !== false){
						curl_setopt($ch, CURLOPT_PUT, true);
						curl_setopt($ch, CURLOPT_INFILE, self::$fp);
						if(self::$info['size'] >= 0){
							curl_setopt($ch, CURLOPT_INFILESIZE, self::$info['size']);
						}
					}elseif(self::$data !== false){
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
						curl_setopt($ch, CURLOPT_POSTFIELDS, self::$data);
						if(self::$info['size'] >= 0){
							curl_setopt($ch, CURLOPT_BUFFERSIZE, self::$info['size']);
						}
					}else{
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
					}
					break;
				case 'HEAD':
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
					curl_setopt($ch, CURLOPT_NOBODY, true);
					break;
				case 'DELETE':
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
					break;
				case 'GET':
				default:
					break;
			}
			
			curl_exec($ch);
			self::$info['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			curl_close($ch);
			
			// parse xml
			if(self::$info['headers']['type'] == 'application/xml' && isset(self::$info['response'])){
				self::$info['response'] = simplexml_load_string(self::$info['response']);
			}
			
			$return = array(
				'code' => self::$info['code'],
				'headers' => self::$info['headers'],
				'response' => self::$info['response']
			);
			
			self::$info = array();
			self::$headers = array();
			self::$parameters = array();
			self::$resource = '';
			@fclose(self::$fp);
			
			// check for errors
			if(!in_array($return['code'], array(200, 204)) && isset($return['response']->Code, $return['response']->Message)){
				$return['error'] = array(
					'code' => (string)$return['response']->Code,
					'message' => (string)$return['response']->Message
				);
			}
			
			return $return;
		}
		
		private function __write_callback(&$curl, &$data){
			self::$info['response'] .= $data;
			return strlen($data);
		}
		
		private function __header_callback(&$curl, &$data){
			if(($strlen = strlen($data)) <= 2) return $strlen;
			if(substr($data, 0, 4) == 'HTTP'){
				self::$info['code'] = (int)substr($data, 9, 3);
			}else{
				list($header, $value) = explode(': ', trim($data), 2);
				if($header == 'Last-Modified'){
					self::$info['headers']['time'] = strtotime($value);
				}elseif($header == 'Content-Length'){
					self::$info['headers']['size'] = (int)$value;
				}elseif($header == 'Content-Type'){
					self::$info['headers']['type'] = $value;
				}elseif($header == 'ETag'){
					self::$info['headers']['hash'] = ($value{0} == '"') ? substr($value, 1, -1) : $value;
				}elseif(preg_match('/^x-amz-meta-.*$/', $header)){
					self::$info['headers'][$header] = (is_numeric($value)) ? (int)$value : $value;
				}
			}
			return $strlen;
		}
	
	}