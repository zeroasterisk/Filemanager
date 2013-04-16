<?php
/**
 *	Filemanager Filesystem PHP class
 *
 *	filemanager.class.php
 *	class for the filemanager.php connector
 *
 *	@license	MIT License
 *	@author		Riaan Los <mail (at) riaanlos (dot) nl>
 *	@author		Simon Georget <simon (at) linea21 (dot) com>
 *	@copyright	Authors
 */

class FilemanagerEngineFilesystem extends FilemanagerEngine {

	protected $fm = null;
	protected $config = array();
	protected $get = array();
	protected $post = array();
	protected $item = array();
	protected $root = '';
	protected $doc_root = '';

	/**
	 * Does the file exist?
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function file_exists($path) {
		return file_exists($path);
	}

	/**
	 * Is the path a directory?
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function is_dir($path) {
		return is_dir($path);
	}

	/**
	 * Is the path a directory?
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function filemtime($path) {
		return filemtime($path);
	}

	/**
	 * Is the path a directory?
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function filesize($path) {
		return filesize($path);
	}

	/**
	 * Returns a $item info array for a path
	 * needs to work for both a file and a directory
	 * if invalid resource, return false
	 *
	 * @param string $path (file or directory)
	 * @return mixed false or array $item info array
	 */
	public function info($path) {
		$path = $this->pathFull($path);
		$is_dir = $this->is_dir($path);
		$type = ($is_dir ? 'directory' : $this->type($path));
		return array(
			'Path' => $this->path($path),
			'FullPath' => $path,
			'Filename' => $this->name($path),
			'File Type' => $type,
			'Preview' => $this->previewUrl($path, $type),
			'Properties' => $this->properties($path, $type),
			'Error' => '',
			'Code' => 0,
		);
	}

	/**
	 * Lists a directory's files/folders
	 *
	 * @param string $path
	 * @return array $items
	 */
	public function listdir($path) {
		/*
			foreach ($items as $item) {
				$items[$k] = $this->info($path . $item);
			}
		 */
		return array();
	}

	/**
	 * Returns a human Readable name for the path
	 *
	 * @param string $path
	 * @return string $name
	 */
	public function name($path) {
		$path = $this->path($path);
		return basename(trim($path, '/'));
	}

	/**
	 * Determine the mimetype of a file
	 *
	 * @param string $path
	 * @return string $mimetype
	 */
	public function type($path) {
		$path = $this->pathFull($path);
		if (substr($path, -1) == '/') {
			// is dir
			return 'directory';
		}
		if (!file_exists($path)) {
			// is dir
			return 'missing';
		}
		if (class_exists('finfo')) {
			$file_info = new finfo(FILEINFO_MIME);  // object oriented approach!
			$mime = $file_info->buffer(file_get_contents($path));  // e.g. gives "image/jpeg"
			$mimeParts = explode(';', $mime);
			return array_shift($mimeParts);
		}
		// in PHP 4, we can do:
		$fhandle = finfo_open(FILEINFO_MIME);
		$mime = finfo_file($fhandle, $path); // e.g. gives "image/jpeg"
		$mimeParts = explode(';', $mime);
		return array_shift($mimeParts);
		// Alternative approaches:
		// get from file ext
		// get from unix 'file' command
	}

	/**
	 *
	 *
	 */
	public function previewUrl($path, $type=null) {
		$path = $this->path($path);
		if (empty($type)) {
			$type = $this->type($path);
		}
		if ($type == 'directory') {
			return $this->fm->config['icons']['path'] . $this->fm->config['icons']['directory'];
		}

		if (in_array(strtolower($type), $this->fm->config['images']['imagesExt'])) {
			// image previews are actually streamed through preview
			return 'connectors/php/filemanager.php?mode=preview&path='. rawurlencode($current_path);
		}

	}

	/**
	 *
	 *
	 */
	public function properties($path, $type=null) {
		$path = $this->pathFull($path);
		if (empty($type)) {
			$type = $this->type($path);
		}
		if ($type == 'directory') {
			$properties = array(
				'Size' => 0,
				'Date Modified' => 0,
			);
			return $properties;
		}
		$properties = array(
			'Size' => $this->filesize($path),
			'Date Modified' => date($this->fm->config['options']['dateFormat'], $this->filemtime($path)),
		);
		if (in_array(strtolower($type), $this->fm->config['images']['imagesExt'])) {
			list($width, $height, $type, $attr) = getimagesize($path);
			$properties['Height'] = intval($height);
			$properties['Width'] = intval($width);
		}
		return $properties;
	}

	/**
	 *
	 * @return array $info
	 */
	public function getinfo() {
		$this->item = array();
		$this->item['properties'] = $this->properties;
		$this->get_file_info();
		$array = array(
			'Path'=> $this->get['path'],
			'Filename'=>$this->item['filename'],
			'File Type'=>$this->item['filetype'],
			'Preview'=>$this->item['preview'],
			'Properties'=>$this->item['properties'],
			'Error'=>"",
			'Code'=>0
		);
		return $array;
	}

	/**
	 * get a folder's information and list contents
	 *
	 * @return array $folderList
	 */
	public function getfolder() {
		$array = array();
		$filesDir = array();
		$current_path = $this->path();
		if (empty($current_path)) {
			$this->fm->error("The path is missing.");
		}
		$full_path = $this->pathFull($this->pathDir($current_path));
		if (!$this->isValidPath($full_path)) {
			$this->error('Security Failure in Path');
		}
		if (!is_dir($full_path)) {
			$this->error(sprintf($this->lang('DIRECTORY_NOT_EXIST'), $this->get['path']));
		}
		if (!$handle = opendir($full_path)) {
			$this->error(sprintf($this->lang('UNABLE_TO_OPEN_DIRECTORY'), $this->get['path']));
		}
		// collect files/folders
		while (false !== ($file = readdir($handle))) {
			if($file != "." && $file != "..") {
				array_push($filesDir, $file);
			}
		}
		closedir($handle);
		// sorting by names
		natcasesort($filesDir);
		// prep config to exclude records
		$exclude = (empty($this->fm->config['exclude']) ? array() : $this->fm->config['exclude']);
		foreach($filesDir as $file) {
			$node = $full_path . $file;
			if (is_dir($node)) {
				if (!empty($exclude['unallowed_dirs']) && in_array($file, $exclude['unallowed_dirs'])) {
					$this->fm->__log("getfolder() skipped {$file} (unallowed_dirs)");
					continue;
				}
				if (!empty($exclude['unallowed_dirs_REGEXP']) && preg_match($exclude['unallowed_dirs_REGEXP'], $file)) {
					$this->fm->__log("getfolder() skipped {$file} (unallowed_dirs_REGEXP)");
					continue;
				}
				$array[$node] = $this->info($node);
			} else {
				if (!empty($exclude['unallowed_files']) && in_array($file, $exclude['unallowed_files'])) {
					$this->fm->__log("getfolder() skipped {$file} (unallowed_files)");
					continue;
				}
				if (!empty($exclude['unallowed_files_REGEXP']) && preg_match($exclude['unallowed_files_REGEXP'], $file)) {
					$this->fm->__log("getfolder() skipped {$file} (unallowed_files_REGEXP)");
					continue;
				}
				$array[$node] = $this->info($node);
			}
		}
		// return nodes
		return $array;
	}

	/**
	 * Rename a path (move file)
	 *
	 * @return array $response
	 */
	public function rename() {
		$suffix='';
		if(substr($this->get['old'],-1,1)=='/') {
			$this->get['old'] = substr($this->get['old'],0,(strlen($this->get['old'])-1));
			$suffix='/';
		}
		$tmp = explode('/',$this->get['old']);
		$filename = $tmp[(sizeof($tmp)-1)];
		$path = str_replace('/' . $filename,'',$this->get['old']);

		$new_file = $this->path($path . '/' . $this->get['new']). $suffix;
		$old_file = $this->path($this->get['old']) . $suffix;

		if(!$this->isValidPath($old_file)) {
			$this->error("No way.");
			return array();
		}

		$this->__log(__METHOD__ . ' - renaming '. $old_file. ' to ' . $new_file);

		if(file_exists ($new_file)) {
			if($suffix=='/' && is_dir($new_file)) {
				$this->error(sprintf($this->lang('DIRECTORY_ALREADY_EXISTS'),$this->get['new']));
			}
			if($suffix=='' && is_file($new_file)) {
				$this->error(sprintf($this->lang('FILE_ALREADY_EXISTS'),$this->get['new']));
			}
		}

		if(!rename($old_file,$new_file)) {
			if(is_dir($old_file)) {
				$this->error(sprintf($this->lang('ERROR_RENAMING_DIRECTORY'),$filename,$this->get['new']));
			} else {
				$this->error(sprintf($this->lang('ERROR_RENAMING_FILE'),$filename,$this->get['new']));
			}
		}
		$array = array(
			'Error'=>"",
			'Code'=>0,
			'Old Path'=>$this->get['old'],
			'Old Name'=>$filename,
			'New Path'=>$path . '/' . $this->get['new'].$suffix,
			'New Name'=>$this->get['new']
		);
		return $array;
	}

	/**
	 * Delete a path (rm file)
	 *
	 * @return array $response
	 */
	public function delete() {
		$current_path = $this->path();
		if (!$this->isValidPath($current_path)) {
			$this->error("No way.");
			return array();
		}
		if (is_dir($current_path)) {
			$this->unlinkRecursive($current_path);
			$array = array(
				'Error'=>"",
				'Code'=>0,
				'Path'=>$this->get['path']
			);
			$this->__log(__METHOD__ . ' - deleting folder '. $current_path);
			return $array;
		} elseif (file_exists($current_path)) {
			unlink($current_path);
			$array = array(
				'Error'=>"",
				'Code'=>0,
				'Path'=>$this->get['path']
			);
			$this->__log(__METHOD__ . ' - deleting file '. $current_path);
			return $array;
		} else {
			$this->error(sprintf($this->lang('INVALID_DIRECTORY_OR_FILE')));
		}
	}

	/**
	 * Add a file
	 *
	 * @return array $response
	 */
	public function add() {
		$this->setParams();
		if(!isset($_FILES['newfile']) || !is_uploaded_file($_FILES['newfile']['tmp_name'])) {
			$this->error(sprintf($this->lang('INVALID_FILE_UPLOAD')),true);
		}
		// we determine max upload size if not set
		if($this->fm->config['upload']['fileSizeLimit'] == 'auto') {
			$this->fm->config['upload']['fileSizeLimit'] = $this->getMaxUploadFileSize();
		}
		if($_FILES['newfile']['size'] > ($this->fm->config['upload']['fileSizeLimit'] * 1024 * 1024)) {
			$this->error(sprintf($this->lang('UPLOAD_FILES_SMALLER_THAN'),$this->fm->config['upload']['size'] . 'Mb'),true);
		}
		if($this->fm->config['upload']['imagesOnly'] || (isset($this->params['type']) && strtolower($this->params['type'])=='images')) {
			if(!($size = @getimagesize($_FILES['newfile']['tmp_name']))){
				$this->error(sprintf($this->lang('UPLOAD_IMAGES_ONLY')),true);
			}
			if(!in_array($size[2], array(1, 2, 3, 7, 8))) {
				$this->error(sprintf($this->lang('UPLOAD_IMAGES_TYPE_JPEG_GIF_PNG')),true);
			}
		}
		$_FILES['newfile']['name'] = $this->cleanString($_FILES['newfile']['name'],array('.','-'));

		$current_path = $this->path($this->post['currentpath']);

		if(!$this->isValidPath($current_path)) {
			$this->error("No way.");
			return array();
		}

		if(!$this->fm->config['upload']['overwrite']) {
			$_FILES['newfile']['name'] = $this->getFilenameAvoidOverwrite($current_path,$_FILES['newfile']['name']);
		}
		move_uploaded_file($_FILES['newfile']['tmp_name'], $current_path . $_FILES['newfile']['name']);
		chmod($current_path . $_FILES['newfile']['name'], 0644);

		$response = array(
			'Path'=>$this->post['currentpath'],
			'Name'=>$_FILES['newfile']['name'],
			'Error'=>"",
			'Code'=>0
		);

		$this->__log(__METHOD__ . ' - adding file '. $_FILES['newfile']['name']. ' into '. $current_path);

		echo '<textarea>' . json_encode($response) . '</textarea>';
		die();
	}

	/**
	 * Add a folder
	 *
	 * @return array $response
	 */
	public function addfolder() {
		$current_path = $this->path();
		if(!$this->isValidPath($current_path)) {
			$this->error("No way.");
			return array();
		}
		if(is_dir($current_path . $this->get['name'])) {
			$this->error(sprintf($this->lang('DIRECTORY_ALREADY_EXISTS'),$this->get['name']));
			return array();
		}
		$newdir = $this->cleanString($this->get['name']);
		if(!mkdir($current_path . $newdir,0755)) {
			$this->error(sprintf($this->lang('UNABLE_TO_CREATE_DIRECTORY'),$newdir));
			return array();
		}
		$array = array(
			'Parent'=>$this->get['path'],
			'Name'=>$this->get['name'],
			'Error'=>"",
			'Code'=>0
		);
		$this->__log(__METHOD__ . ' - adding folder '. $current_path . $newdir);
		return $array;
	}

	/**
	 * Get Current Path (file)
	 * handles security in a standardized way
	 *
	 * @return mixed $current_path or false
	 */
	public function getCurrentPathFile() {
		$current_path = $this->path();
		if(!$this->isValidPath($current_path)) {
			$this->error("No way.");
			return false;
		}
		if(! (isset($this->get['path']) && file_exists($current_path))) {
			$this->error(sprintf($this->lang('FILE_DOES_NOT_EXIST'),$current_path));
			return false;
		}
		return $current_path;
	}

	/**
	 * Force Download a file (through PHP)
	 *
	 * @return void
	 */
	public function download() {
		$current_path = $this->getCurrentPathFile();
		// set headers and return data
		// TODO: stream data for large files faster than file -> php -> response
		header("Content-type: application/force-download");
		header('Content-Disposition: inline; filename="' . basename($current_path) . '"');
		header("Content-Transfer-Encoding: Binary");
		header("Content-Length: ".filesize($current_path));
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($current_path) . '"');
		readfile($current_path);
		$this->__log(__METHOD__ . ' - downloading '. $current_path);
		exit();
	}

	/**
	 * Inline Preview a file (through PHP)
	 *
	 * @return void
	 */
	public function preview() {
		$current_path = $this->getCurrentPathFile();
		// set headers and return data
		// TODO: stream data for large files faster than file -> php -> response
		header("Content-type: image/" . $ext = pathinfo($current_path, PATHINFO_EXTENSION));
		header("Content-Transfer-Encoding: Binary");
		header("Content-Length: ".filesize($current_path));
		header('Content-Disposition: inline; filename="' . basename($current_path) . '"');
		readfile($current_path);
		$this->__log(__METHOD__ . ' - previewing '. $current_path);
		exit();
	}

	/**
	 * configuration helper
	 * gets the ini setting for max file upload information
	 *
	 * return int $upload_mb
	 */
	public function getMaxUploadFileSize() {
		$max_upload = (int) ini_get('upload_max_filesize');
		$max_post = (int) ini_get('post_max_size');
		$memory_limit = (int) ini_get('memory_limit');
		$upload_mb = min($max_upload, $max_post, $memory_limit);
		$this->__log(__METHOD__ . ' - max upload file size is '. $upload_mb. 'Mb');
		return $upload_mb;
	}


	/**
	 * Get the details for a file
	 * sets that data to $this->item
	 *
	 * @param string $path
	 * @param array $return
	 * @return array $this->item
	 */
	private function get_file_info($path='',$return=array()) {
		$current_path = $this->getCurrentPathFile($path);
		$tmp = explode('/',$current_path);
		$this->item['filename'] = $tmp[(sizeof($tmp)-1)];
		$tmp = explode('.',$this->item['filename']);
		$this->item['filetype'] = $tmp[(sizeof($tmp)-1)];
		$this->item['filemtime'] = filemtime($this->path($current_path));
		$this->item['filectime'] = filectime($this->path($current_path));
		$this->item['preview'] = $this->fm->config['icons']['path'] . $this->fm->config['icons']['default'];
		if(is_dir($current_path)) {
			// directory gets default directory icon
			$this->item['preview'] = $this->fm->config['icons']['path'] . $this->fm->config['icons']['directory'];
		} else if(in_array(strtolower($this->item['filetype']),$this->fm->config['images']['imagesExt'])) {
			// image previews are actually streamed through preview
			$this->item['preview'] = 'connectors/php/filemanager.php?mode=preview&path='. rawurlencode($current_path);
			//if(isset($get['getsize']) && $get['getsize']=='true') {
			$this->item['properties']['Size'] = filesize($this->path($current_path));
			if ($this->item['properties']['Size']) {
				list($width, $height, $type, $attr) = getimagesize($this->path($current_path));
			} else {
				$this->item['properties']['Size'] = 0;
				list($width, $height) = array(0, 0);
			}
			$this->item['properties']['Height'] = $height;
			$this->item['properties']['Width'] = $width;
			$this->item['properties']['Size'] = filesize($this->path($current_path));
			//}
		} else if(file_exists($this->root . $this->fm->config['icons']['path'] . strtolower($this->item['filetype']) . '.png')) {
			// special handling for PNGs (should this be above the imagesExt if block?)
			$this->item['preview'] = $this->fm->config['icons']['path'] . strtolower($this->item['filetype']) . '.png';
			$this->item['properties']['Size'] = filesize($this->path($current_path));
			if (!$this->item['properties']['Size']) {
				$this->item['properties']['Size'] = 0;
			}
		}
		$this->item['properties']['Date Modified'] = date($this->fm->config['options']['dateFormat'], $this->item['filemtime']);
		//$return['properties']['Date Created'] = $this->fm->config['options']['dateFormat'], $return['filectime']); // PHP cannot get create timestamp
		return $this->item;
	}

	/**
	 * Get a path (starting at doc_root)
	 *
	 * @return string $path (starting at doc_root)
	 */
	public function path($path = '') {
		$path = parent::path($path);
		if (empty($path)) {
			$this->fm->error('Invalid Path, empty');
		}
		if (strpos($path, $this->fm->doc_root) !== false) {
			// path starts at doc_root;
			return str_replace($this->fm->doc_root, '/', $path);
		}
		return $path;
	}

	/**
	 * Get a full path (starting at root "/")
	 *
	 * @return string $path
	 */
	public function pathFull($path = '') {
		if (strpos($path, $this->fm->doc_root) === false) {
			// path starts at doc_root;
			return rtrim($this->fm->doc_root, '/') . '/' . ltrim($path, '/');
		}
		return $path;
	}


	/**
	 * Security:
	 * verify the path is a part of the root
	 *
	 * @param string $path
	 * @return boolean
	 */
	private function isValidPath($path) {
		// @todo remove debug message
		$path = $this->pathFull($path);
		$this->fm->__log("isValidPath : path=[{$path}] & doc_root=[{$this->fm->doc_root}] ");
		return !strncmp($path, $this->fm->doc_root, strlen($this->fm->doc_root));
	}

	/**
	 * Delete Folder/Files Recursivly
	 *
	 * @param string $dir
	 * @param boolean $deleteRootToo
	 * @return boolean $worked
	 */
	private function unlinkRecursive($dir, $deleteRootToo=true) {
		if(!$dh = @opendir($dir)) {
			return false;
		}
		while (false !== ($obj = readdir($dh))) {
			if($obj == '.' || $obj == '..') {
				continue;
			}
			if (!@unlink($dir . '/' . $obj)) {
				$this->unlinkRecursive($dir . '/' . $obj, true);
			}
		}
		closedir($dh);
		if ($deleteRootToo) {
			@rmdir($dir);
		}
		return true;
	}


	/**
	 * Security: checks a filename to see if it exists
	 * if it does, it appends a suffix automatically until
	 * the filename is unique (avoids overwritting)
	 *
	 * @param string $path
	 * @param string $filename
	 * @return string $filename
	 */
	private function getFilenameAvoidOverwrite($path,$filename) {
		if (!file_exists($path . $filename)) {
			return $filename;
		}
		// avoid overwrite
		$filenameInit = $filename;
		$parts = explode('.', $filename);
		$ext = array_pop();
		$filenameCore = implode('.', $parts);
		$i = 0;
		do {
			$i++;
			$suffix = "{$this->fm->config['upload']['suffix']}{$i}";
			$filename = "{$filenameCore}.{$suffix}.{$ext}";
		} while (file_exists($path . $filename) && $i < 100);
		return $filename;
	}

}
