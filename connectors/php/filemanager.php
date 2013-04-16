<?php
// only for debug
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
// ini_set('display_errors', '1');
/**
 *	Filemanager PHP connector
 *
 *	filemanager.php
 *
 *	use for ckeditor filemanager plug-in by Core Five - http://labs.corefive.com/Projects/FileManager/
 *
 *	@license	MIT License
 *	@author		Riaan Los <mail (at) riaanlos (dot) nl>
 *  @author		Simon Georget <simon (at) linea21 (dot) com>
 *	@copyright	Authors
 */

require_once('./inc/filemanager.inc.php');
require_once('filemanager.class.php');
require_once('./engines/filemanager.engine.php');

class MyFilemanager extends Filemanager {

	/**
	 * configuration here
	 * note: we initially parse the ../../../config.js for configuration,
	 * so you don't have to explicitly setup here
	 */
	public $config = array(
		'engine' => 'filesystem',
		//'engine' => 'rsc',
		'engineConfig' => array(
			'user' => 'a',
		),
	);

	/**
	 * Check if user is authorized
	 *
	 * @return boolean true is access granted, false if no access
	 */
	public function auth() {
		// You can insert your own code over here to check if the user is authorized.
		// If you use a session variable, you've got to start the session first (session_start())
		return true;
	}

	/**
	 * Render the response (as a JSON string)
	 * get the responseArray from the parent class
	 *
	 * @return string $responseString;
	 */
	public function response() {
		$responseArray = parent::response();
		return json_encode($responseArray);
	}
}

// now just initialize and render
$fm = new MyFilemanager();
echo $fm->response();
die();

