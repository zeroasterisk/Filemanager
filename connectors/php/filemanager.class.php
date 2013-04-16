<?php
/**
 *	Filemanager PHP class
 *
 *	filemanager.class.php
 *	class for the filemanager.php connector
 *
 *	@license	MIT License
 *	@author		Riaan Los <mail (at) riaanlos (dot) nl>
 *	@author		Simon Georget <simon (at) linea21 (dot) com>
 *	@copyright	Authors
 */

class Filemanager {

	public $config = array(
		'options' => array(
			'culture' => 'en',
			'logger' => true,
			'logfile' => '/tmp/Filemanager.log',
		),
		'exclude' => array(
			'unallowed_files' => array('..', '.'),
			'unallowed_files_REGEXP' => '#(^\.ht.+|\.(cf|php|php3|sh|py|perl|pl|rb|ruby)$)#',
		)
	);
	public $debug = true; // only set if you are debugging raw requests
	public $logger = array();
	public $logfile = array();
	public $log = array();
	public $language = array();
	public $get = array();
	public $post = array();
	public $properties = array();
	public $item = array();
	public $languages = array();
	public $root = '';
	public $doc_root = '';

	/**
	 * initalize and setup Filemanager class
	 *
	 * @param mixed $_config (optional, see config() for formatting)
	 * @return void
	 */
	public function __construct($_config = null) {
		if (is_subclass_of($this, 'Filemanager')) {
			$parentClass = get_parent_class($this);
			$parentVars = get_class_vars($parentClass);
			if (isset($parentVars['config'])) {
				$this->config = array_merge($this->config, $parentVars['config']);
			}
		}
		$this->init($_config);
	}

	/**
	 * (re) setup and initialize class
	 * if you make config changes, you can re-initialize class here
	 *
	 * @param mixed $_config (optional, see config() for formatting)
	 * @return void
	 */
	public function init($_config) {
		$this->setupRoot();
		$this->config($_config);
		if (!empty($this->config['options']['logger'])) {
			$this->enableLog();
		}
		$this->setupBase();
		$this->setupEngine();
		$this->availableLanguages();
		$this->loadLanguageFile();
	}

	/**
	 * config setup
	 *
	 * @param mixed $_config string formatted as json, or an array
	 * @return void;
	 */
	public function config($_config = null) {
		$config = $this->config;
		// default to the configuration from the .js file
		$content = file_get_contents($this->root . 'scripts' . DIRECTORY_SEPARATOR . 'filemanager.config.js');
		if (empty($content)) {
			$this->error('Missing Filemanager/scripts/filemanager.config.js');
		}
		$configFromJS = json_decode($content, true);
		if (!is_array($configFromJS)) {
			$this->error('Malformed Filemanager/scripts/filemanager.config.js');
		}
		$config = array_merge($config, $configFromJS);
		// setup configuration passed into __construct()
		if (is_array($_config)) {
			$config = array_merge($config, $_config);
		} elseif (is_string($_config)) {
			$config = array_merge($config, json_decode($_config, true));
		}
		// set configuration on this class
		$this->config = $config;
	}

	/**
	 * setup engine based on configs
	 *
	 */
	public function setupEngine() {
		if (empty($this->config['engine'])) {
			$this->config['engine'] = 'filesystem';
		}
		$engineName = trim($this->config['engine']);
		$engineClass = 'FilemanagerEngine' . ucfirst($engineName);
		if (!class_exists($engineClass)) {
			require_once dirname(__FILE__) . "/engines/{$engineName}/filemanager.{$engineName}.class.php";
		}
		if (!is_subclass_of($engineClass, 'FilemanagerEngine')) {
			$this->error('Filemanager::setupEngine() failed, Engines must be a subclass of FilemanagerEngine');
		}
		$this->engine = new $engineClass($this);
		// extend the configuration on the engine (convenience)
		$this->config($this->config);
	}

	/**
	 * This sets the root dir for Filemanager
	 * it assumes the file location remains consistant
	 *
	 * @return void;
	 */
	public function setupRoot() {
		$this->root = dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR;
		$this->rootConnector = dirname(__FILE__).DIRECTORY_SEPARATOR;
	}

	/**
	 * if fileRoot is set manually, $this->doc_root takes fileRoot value
	 * for security check in isValidPath() method
	 * else it takes $_SERVER['DOCUMENT_ROOT'] default value
	 *
	 */
	public function setupBase() {
		if (empty($this->config['options']['fileRoot'])) {
			if (empty($this->config['options']['serverRoot'])) {
				$this->doc_root = $this->root . 'userfiles' . DIRECTORY_SEPARATOR;
			} else {
				$this->doc_root = $_SERVER['DOCUMENT_ROOT'];
			}
		} else {
			$this->doc_root = $this->config['options']['fileRoot'];
		}
		$this->__log(__METHOD__ . ' $this->doc_root value ' . $this->doc_root);
	}


	// allow Filemanager to be used with dynamic folders
	public function setFileRoot($path) {
		if($this->config['options']['serverRoot'] === true) {
			$this->doc_root = $_SERVER['DOCUMENT_ROOT']. '/'.  $path;
		} else {
		$this->doc_root =  $path;
		}

	}

	/**
	 * Render an error and die instantly, no other code should run
	 *
	 * @param mixed $error
	 * @param boolean $asTextarea
	 * @return void - die()s
	 */
	public function error($string, $asTextarea=false) {
		$array = array(
			'Error' => $string,
			'Code' => '-1',
			'Properties' => $this->log,
			'Backtrace' => debug_backtrace(),
		);
		$this->__log( __METHOD__ . ' - error message : ' . $string);
		// debuggging errors
		if (!empty($this->debug)) {
			echo '<h1>Error</h1><pre>' . print_r($array, true) . '</pre>';
			echo '<h1>Log</h1><pre>' . print_r($this->log, true) . '</pre>';
			echo '<h1>Config</h1><pre>' . print_r($this->config, true) . '</pre>';
			die();
		}
		// textarea errors
		if ($asTextarea) {
			echo '<textarea>' . json_encode($array) . '</textarea>';
			die();
		}
		// json errors (normal)
		echo json_encode($array);
		die();
	}

	public function lang($string) {
		if(isset($this->language[$string]) && $this->language[$string]!='') {
			return $this->language[$string];
		} else {
			return 'Language string error on ' . $string;
		}
	}

	/**
	 * get a variable from the $_GET array and set it into $this->get
	 * require the variable to exist and not be empty
	 * also sanitizes the variable
	 *
	 * @param string $var
	 * @return boolean
	 */
	public function getvar($var) {
		if (!isset($_GET[$var]) || empty($_GET[$var])) {
			$this->error(sprintf($this->lang('INVALID_VAR'), $var));
		}
		$this->get[$var] = $this->sanitize($_GET[$var]);
		return true;
	}

	/**
	 * get a variable from the $_POST array and set it into $this->post
	 * require the variable to exist and not be empty
	 * also sanitizes the variable
	 *
	 * @param string $var
	 * @return boolean
	 */
	public function postvar($var) {
		if (!isset($_POST[$var]) || empty($_POST[$var])) {
			$this->error(sprintf($this->lang('INVALID_VAR'), $var));
		}
		$this->post[$var] = $_POST[$var];
		return true;
	}

	/**
	 * we load langCode var passed into URL if present and if exists
	 * else, we use default configuration var
	 *
	 * @return void;
	 */
	private function loadLanguageFile() {
		$lang = $this->config['options']['culture'];
		if (empty($this->config['languages'])) {
			$this->config['languages'] = $this->availableLanguages();
		}
		if(isset($this->params['langCode']) && in_array($this->params['langCode'], $this->config['languages'])) {
			$lang = $this->params['langCode'];
		}

		if (file_exists($this->root. 'scripts/languages/'.$lang.'.js')) {
			$stream =file_get_contents($this->root. 'scripts/languages/'.$lang.'.js');
			$this->language = json_decode($stream, true);
			return;
		}
		// failover to en
		$stream =file_get_contents($this->root. 'scripts/languages/en.js');
		$this->language = json_decode($stream, true);
		return;
	}

	/**
	 * lists available languages
	 *
	 * @return array
	 */
	private function availableLanguages() {
		$languages = array();
		if ($handle = opendir($this->root.'/scripts/languages/')) {
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != "..") {
					array_push($languages, pathinfo($file, PATHINFO_FILENAME));
				}
			}
			closedir($handle);
		}
		return $languages;
	}

	/**
	 * log data, for debugging and tracking
	 * file logging for now, could extend with log engines :)
	 *
	 * @param string data to log
	 * @return void
	 */
	public function __log($msg) {
		if (empty($this->logger)) {
			return;
		}
		$this->log[] = $msg;
		if (empty($this->logfile)) {
			return;
		}
		$fp = fopen($this->logfile, "a");
		$str = "[" . date("Y-m-d H:i:s", time()) . "] " . $msg;
		fwrite($fp, $str . PHP_EOL);
		fclose($fp);
		return;
	}

	/**
	 * enable logging to a specific file (if specified)
	 * otherwise, defaults to what was "configured"
	 *
	 * @return boolean
	 */
	public function enableLog() {
		if (!empty($this->config['options']['logger'])) {
			$this->logger = true;
		}
		if (!empty($this->config['options']['logfile'])) {
			$this->logfile = $this->config['options']['logfile'];
		}
		$this->__log(__METHOD__ . ' - Log enabled (in '. $this->logfile. ' file)');
		return ($this->logger);
	}

	/**
	 * disable logging
	 *
	 * @return boolean
	 */
	public function disableLog() {
		$this->logger = false;
		$this->__log(__METHOD__ . ' - Log disabled');
		return (!$this->logger);
	}

	/**
	 * Security: cleanup string, force a normalized charset
	 *
	 * @param string $string
	 * @param array $allowed
	 * @return string $string
	 */
	public static function cleanString($string, $allowed = array()) {
		$allow = null;
		if (!empty($allowed)) {
			foreach ($allowed as $value) {
				$allow .= "\\$value";
			}
		}
		$mapping = array(
			'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
			'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
			'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
			'Õ'=>'O', 'Ö'=>'O', 'Ő'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ű'=>'U', 'Ý'=>'Y',
			'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
			'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n',
			'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ő'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'ű'=>'u',
			'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r', ' '=>'_', "'"=>'_', '/'=>''
		);
		if (is_array($string)) {
			$cleaned = array();
			foreach ($string as $key => $clean) {
				$clean = strtr($clean, $mapping);
				if ($this->config['options']['chars_only_latin'] == true) {
					$clean = preg_replace("/[^{$allow}_a-zA-Z0-9]/u", '', $clean);
					// $clean = preg_replace("/[^{$allow}_a-zA-Z0-9\x{0430}-\x{044F}\x{0410}-\x{042F}]/u", '', $clean); // allow only latin alphabet with cyrillic
				}
				$cleaned[$key] = preg_replace('/[_]+/', '_', $clean); // remove double underscore
			}
		} else {
			$string = strtr($string, $mapping);
			if($this->config['options']['chars_only_latin'] == true) {
				$clean = preg_replace("/[^{$allow}_a-zA-Z0-9]/u", '', $string);
				// $clean = preg_replace("/[^{$allow}_a-zA-Z0-9\x{0430}-\x{044F}\x{0410}-\x{042F}]/u", '', $string); // allow only latin alphabet with cyrillic
			}
			$cleaned = preg_replace('/[_]+/', '_', $string); // remove double underscore
		}
		return $cleaned;
	}

	/**
	 * Security: sanitize a string, remote known 'bad' string parts
	 * this is primarily useful for paths
	 *
	 * @param string $string
	 * @return string $string sanitized
	 */
	public static function sanitize($string) {
		$string = strip_tags($string);
		$string = preg_replace('#//+#', '/', $string);
		$string = preg_replace('#(https?|ftp|rtmp|scp|rsync|git)\://#i', '', $string);
		$string = str_replace('../', '', $string);
		return $string;
	}

	/**
	 * Here's the reponse logic
	 *
	 * @return array $response as array, will be json_encoded when rendered
	 */
	public function response() {
		$response = '';
		$isPOST = false;
		if (!$this->auth()) {
			$this->error($this->lang('AUTHORIZATION_REQUIRED'));
		}
		if (empty($_GET)) {
			$this->error($this->lang('INVALID_ACTION'));
		}
		if (!empty($_POST['mode'])) {
			$mode = strval($_POST['mode']);
			$isPOST = true;
		} elseif (!empty($_GET['mode'])) {
			$mode = strval($_GET['mode']);
		}
		if (empty($mode)) {
			$this->error($this->lang('MODE_ERROR'));
		}
		$mode = strval($_GET['mode']);
		if ($mode == 'getinfo' && $this->getvar('path')) {
			$response = $this->engine->getinfo();
		}
		if ($mode == 'getfolder' && $this->getvar('path')) {
			$response = $this->engine->getfolder();
		}
		if ($mode == 'rename' && $this->getvar('old') && $this->getvar('new')) {
			$response = $this->engine->rename();
		}
		if ($mode == 'delete' && $this->getvar('path')) {
			$response = $this->engine->delete();
		}
		if ($mode == 'add' && $this->getvar('currentpath')) {
			$response = $this->engine->add();
		}
		if ($mode == 'addfolder' && $this->getvar('path') && $this->getvar('name')) {
			$response = $this->engine->addfolder();
		}
		if ($mode == 'download' && $this->getvar('path')) {
			$response = $this->engine->download();
		}
		if ($mode == 'preview' && $this->getvar('path')) {
			$response = $this->engine->preview();
		}
		if ($mode == 'maxuploadfilesize') {
			$response = $this->engine->getMaxUploadFileSize();
		}
		if (empty($response)) {
			$this->error($this->lang('MODE_ERROR'));
		}
		return $response;
	}
}
