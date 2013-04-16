<?php
/**
 *
 *
 *
 *
 * This is the "template" for all "Engines"
 *
 * Every one of these methods must be implemented (extra methods are fine)
 *
 * Any which are not, "bubble up" and use this functionality
 *
 * These methods are used by the "Filemanager" class, and assumed to be
 * functional with the same param/return
 */
abstract class FilemanagerEngine {
	public $settings = array();

	/**
	 * Initialize this engine
	 *
	 * @param object $fm Filemanager passed by refernce
	 */
	public function __construct(&$fm) {
		if (!is_object($fm)) {
			throw new OutOfBoundsException('FilemanagerEngineFilesystem::__construct() invalid $fm passed in');
		}
		$this->fm = $fm;
		$this->init();
	}

	/**
	 * Initialize the Engine
	 *
	 * @param array $settings Associative array of parameters for the engine
	 * @return boolean True if the engine has been successfully initialized, false if noti
	 */
	public function init($settings = array()) {
		$settings += $this->settings + array(
			// defaults
		);
		$this->settings = $settings;
		return true;
	}

	/**
	 * helper for logs
	 *
	 * @param mixed $input
	 */
	public function log($input) {
		return $this->fm->__log($input);
	}

	/**
	 * helper for error
	 *
	 * @param mixed $input
	 */
	public function error($input) {
		return $this->fm->error($input);
	}

	/**
	 * action: getinfo
	 * gets details for a single path
	 *
	 * @return array
	 */
	abstract public function getinfo();

	/**
	 * Cleanup method for paths
	 * ensures it's sanitized
	 * ensures it ends with a '/' if it's a dir
	 * otherwise ensures it doesn't end with a '/'
	 * (assumes it's a file if not dir)
	 *
	 * @param string $path
	 * @return string $path
	 */
	public function path($path) {
		if (empty($path) && !empty($this->fm->get['path'])) {
			$path = $this->fm->get['path'];
		}
		$path = Filemanager::sanitize($path);
		if ($this->is_dir($path)) {
			return $this->pathDir($path);
		}
		return $this->pathFile($path);
	}

	/**
	 * Cleanup method for paths
	 * ensures it's sanitized
	 * ensures it ends with a '/'
	 *
	 * @param string $path
	 * @return string $path
	 */
	public function pathDir($path) {
		$path = Filemanager::sanitize($path);
		return trim(rtrim(trim($path), '/')) . '/';
	}

	/**
	 * Cleanup method for file
	 * ensures it's sanitized
	 * ensures it doesn't end with a '/'
	 *
	 * @param string $path
	 * @return string $path
	 */
	public function pathFile($path) {
		$path = Filemanager::sanitize($path);
		return trim(rtrim(trim($path), '/'));
	}

}
