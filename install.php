<?php

/**************************
****** REQUIRES PHP 7 *****
***************************/

namespace MarkNotes;

// -------------------------------------------------
// Constants to initialize
// 		URL to the ZIP to download
define('URL', 'https://github.com/cavo789/marknotes/archive/master.zip');
// 		Name of the application
define('APP_NAME', 'marknotes');
// 		Application's logo
define('LOGO', 'http://marknotes.fr/assets/images/marknotes.png');
// 		Debug mode enabled (1) or not (0)
define('DEBUG', 1);
//		The minimum PHP version on the server
define('PHP_MIN_VERSION', '7.0');
//
// -------------------------------------------------

// Constants below shouldn't be modified
define('DS', DIRECTORY_SEPARATOR);

class Helpers
{
	public static function setDebuggingMode(bool $bOnOff, string $folder = '') : bool
	{
		if ($bOnOff) {
			ini_set("display_errors", "1");
			ini_set("display_startup_errors", "1");
			ini_set("html_errors", "1");
			ini_set("docref_root", "http://www.php.net/");

			// Log php errors in the php_errors.log file
			if (trim($folder) =='') {
				$folder = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
				$folder = str_replace('/', DS, dirname($folder)).DS;
			}

			ini_set('error_log', $folder.'php_errors.log');

			ini_set(
				"error_prepend_string",
				"<div style='background-color:yellow;border:1px solid red;padding:5px;'>"
			);

			ini_set("error_append_string", "</div>");

			error_reporting(E_ALL);
		} else {
			error_reporting(0);
		}

		return true;
	}

	/**
	* Return the current URL
	*
	* @return type string
	*/
	public static function getCurrentURL() : string
	{

		$ssl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
		$protocol = 'http';
		// SERVER_PROTOCOL isn't set when the script is fired through a php-cli
		if (isset($_SERVER['SERVER_PROTOCOL'])) {
			$spt = strtolower($_SERVER['SERVER_PROTOCOL']);
			$protocol = substr($spt, 0, strpos($spt, '/')) . (($ssl)?'s':'');
		}

		$port = '80';
		// SERVER_PORT isn't set when the script is fired through a php-cli
		if (isset($_SERVER['SERVER_PORT'])) {
			$port = $_SERVER['SERVER_PORT'];
			$port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':'.$port;
		}

		$host =
		(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');

		$host = isset($host) ? rtrim(str_replace(DS, '/', $host), '/') : $_SERVER['SERVER_NAME'].$port;

		$return = $protocol.'://'.$host.dirname($_SERVER['PHP_SELF']).'/';

		return $return;
	}
}

class Files
{
	/**
	* Recursively move files from one directory to another
	* @link : https://ben.lobaugh.net/blog/864/php-5-recursively-move-or-copy-files
	*
	* @param String $src - Source of files being moved
	* @param String $dest - Destination of files being moved
	*/
	public function rmove(string $src, string $dest) : bool
	{
		// If source is not a directory stop processing
		if (!is_dir($src)) {
			return false;
		}

		// If the destination directory does not exist create it
		if (!is_dir($dest)) {
			if (!mkdir($dest, 755)) {
				// If the destination directory could not
				// be created stop processing
				return false;
			}
		}

		// Open the source directory to read in files
		$i = new \DirectoryIterator($src);

		foreach ($i as $f) {
			if ($f->isFile()) {
				rename($f->getRealPath(), "$dest/" . $f->getFilename());
			} elseif (!$f->isDot() && $f->isDir()) {
				self::rmove($f->getRealPath(), "$dest/$f");
			}
		}

		rmdir($src);

		return true;
	}

	/**
	* Remove recursively a folder
	*/
	public function rrmdir(string $dir)
	{
		if (is_dir($dir)) {
			$files = scandir($dir);
			foreach ($files as $file) {
				if ($file != "." && $file != "..") {
					self::rrmdir("$dir/$file");
				}
			}
			rmdir($dir);
		} elseif (file_exists($dir)) {
			unlink($dir);
		}
	}

	/**
	* Remove file's extension
	*
	* @param  string $filename 	The filename ("file.zip")
	* @return string			The name without extension ("file")
	*/
	public static function removeExtension(string $filename) : string
	{
		// Correctly handle double extension
		$arr = explode('.', $filename);

		$extension = '';
		if (count($arr) > 0) {
			// Remove the last extension and save it into $extension
			$extension = array_pop($arr);
		}

		return str_replace('.'.$extension, '', $filename);
	}

	/**
	 * Get the number of files in a folder
	 */
	public function countFiles(string $folder) : int
	{
		$wCount = 0;

		if (is_dir($folder)) {
			$fi = new \FilesystemIterator($folder, \FilesystemIterator::SKIP_DOTS);
			$wCount =  iterator_count($fi);
		}

		return $wCount;
	}
}

class Download
{
	const CURL_TIMEOUT = 2; // Timeout delay in seconds

	const ERROR_CURL = 1001;

	private static $sAppName = '';
	private static $sFileName = '';
	private static $sSourceURL = '';
	private static $bDebug = false;
	private static $sDebugFileName = '';

	public function __construct(string $ApplicationName)
	{
		static::$bDebug = false;
		static::$sAppName = $ApplicationName;
	}

	/**
	 * Enable the debug mode for this class
	 */
	public function debugMode(bool $bOnOff)
	{
		static::$bDebug = $bOnOff;

		if ($bOnOff) {
			// A debug.log file will be created in
			// the folder of the calling script
			$folder = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
			$folder = str_replace('/', DS, dirname($folder)).DS;
			static::$sDebugFileName = $folder.'debug.log';
		}
	}

	/*
	 * URL where the script will find a file to download
	 */
	public function setURL(string $sURL)
	{
		static::$sSourceURL = trim($sURL);
	}

	/**
	 * Once download, a file will be created on the disk.
	 * Use this property to specify the name of that file
	 */
	public function setFileName(string $sName)
	{
		static::$sFileName = trim($sName);
	}

	/**
	 * Detect if the CURL library is loaded
	 */
	private function iscURLEnabled() : bool
	{
		return ( (!function_exists("curl_init") && !function_exists("curl_setopt") && !function_exists("curl_exec") && !function_exists("curl_close")) ? false : true);
	}

	/**
	 * If the file is there and has a size of 0 byte,
	 * it's a failure, the file wasn't downloaded
	 */
	private function removeIfNull()
	{
		if (file_exists(static::$sFileName)) {
			if (filesize(static::$sFileName)<1000) {
				unlink(static::$sFileName);
			}
		}
	}

	/**
	* Download the application package ZIP file
	* @param type $url
	* @param type $file
	* @return string
	*/
	public function download() : int
	{
		$wError = 0;

		// Try to use CURL, if installed
		if (self::iscURLEnabled()) {
			// $sFileName is the fullname of the file to create f.i.
			// /home/www/username/rootweb/marknotes-master.zip
			$fp = @fopen(static::$sFileName, 'w');

			if (!$fp) {
				throw new Exception(APP_NAME." - Could not open the file!");
			}

			if (!file_exists(static::$sFileName)) {
				$wError = self::ERROR_CURL;
			} else {
				@fclose($fp);
				@chmod(static::$sFileName, octdec('644'));
			}

			if ($wError===0) {
				// Ok, try to download the file

				$ch = curl_init(static::$sSourceURL);

				if ($ch) {
					// Start the download process
					@set_time_limit(0);

					$fp = @fopen(static::$sFileName, 'w');

					if (!curl_setopt($ch, CURLOPT_URL, static::$sSourceURL)) {
						fclose($fp);
						curl_close($ch);
						$wError=self::ERROR_CURL;
					} else {
						// Download

						curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36 FirePHP/4Chrome');
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CURL_TIMEOUT);

						// Output curl debuging messages into a text file
						if (static::$bDebug) {
							// output debuging info in a txt file
							curl_setopt($ch, CURLOPT_VERBOSE, true);
							$fdebug = fopen(static::$sDebugFileName, 'w');
							curl_setopt($ch, CURLOPT_STDERR, $fdebug);
						}

						curl_setopt($ch, CURLOPT_HEADER, false);
						curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
						curl_setopt($ch, CURLOPT_FILE, $fp);
						curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

						$rc = curl_exec($ch);
						curl_close($ch);
						fclose($fp);

						if (!$rc) {
							$wError = self::ERROR_CURL;
						}

						@chmod(static::$sFileName, octdec('644'));
					} // if (!curl_setopt($ch, CURLOPT_URL, static::$sSourceURL))
				} // if ($ch)
			} // if ($wError===0)
		} // if (self::iscURLEnabled())

		self::removeIfNull();

		if (!file_exists(static::$sFileName)) {
			// Unsuccessfull, try with fopen()

			// Use a context to be able to define a timeout
			$context = stream_context_create(
				array('http'=>array('timeout'=>self::CURL_TIMEOUT))
			);
			// Get the content if fopen() is enabled
			$content = @fopen(static::$sSourceURL, 'r', false, $context);

			if ($content!=='') {
				@file_put_contents(static::$sFileName, $content);
			}

			self::removeIfNull();

			if (file_exists(static::$sFileName)) {
				$wError = 0;
			}
		} // if (!file_exists(static::$sFileName))

		return $wError;
	}

	/**
	 * Return a text for the encountered error
	 */
	public function getErrorMessage(int $code) : string
	{
		$sReturn =
			'<p>Your system configuration doesn\'t allow to '.
			'download the installation package.</p>'.
			'<p>Please click '.
			'<a href="'.static::$sSourceURL.'">here</a> to '.
			'manually download the package, then open your '.
			'FTP client and send the downloaded file to your '.
			'website folder.</p>'.
			'<p>Once this is done, just refresh this page.</p>'.
			'<p><em>Note : the filename should be '.
			static::$sFileName.'</em></p>';

		return $sReturn;
	}
}

class Zip
{
	const ERROR_ZIP_ARCHIVE = 1002;
	const ERROR_UNWRITABLE_FOLDER = 1003;
	const ERROR_MKDIR = 1004;
	const ERROR_ZIP_FAILED = 1005;

	private static $sZIPFileName = '';
	private static $sFolderName = '';

	/**
	 * Name of the ZIP file
	 */
	public function setFileName(string $sName)
	{
		static::$sZIPFileName = trim($sName);

		// Default folder : name of the ZIP (without the extension)
		$tmp = \MarkNotes\Files::removeExtension(basename($sName));
		self::setFolder($tmp);
	}

	/**
	 * Name of the folder that will be created
	 */
	public function setFolder(string $sName)
	{
		static::$sFolderName = trim($sName);
	}

	/**
	 * Unzip an archive
	 */
	public function unZip() : int
	{
		$wReturn = 0;

		if (class_exists('ZipArchive')) {
			// Verify that the parent folder is writable
			if (!is_writable(dirname(static::$sZIPFileName))) {
				@chmod(dirname(static::$sZIPFileName), octdec('755'));
			}

			if (!is_writable(dirname(static::$sZIPFileName))) {
				return self::ERROR_UNWRITABLE_FOLDER;
			}

			// Try to create the destination folder
			if (!(is_dir(static::$sFolderName))) {
				mkdir(static::$sFolderName, 0755);
			}

			if (is_dir(static::$sFolderName)) {
				// Do it, unzip
				$zip = new \ZipArchive;

				try {
					@set_time_limit(0);

					$bReturn = $zip->open(static::$sZIPFileName);

					if ($bReturn) {
						$bZip = $zip->extractTo(static::$sFolderName);
						$zip->close();
					} else {
						self::showErrorPage(ERROR_ZIP_FAILED);
					}
				} catch (Exception $e) {
				}
			} else {
				return self::ERROR_MKDIR;
			}
		} else {
			// Class not loaded
			return self::ERROR_ZIP_ARCHIVE;
		} // if (class_exists('ZipArchive'))

		return $wReturn;
	}

	/**
	 * Return a text for the encountered error
	 */
	public function getErrorMessage(int $code) : string
	{
		$sReturn = '';
		if ($code==self::ERROR_ZIP_ARCHIVE) {
			$sReturn =
			'<p>Sorry but your system configuration has not loaded ' .
			'the PHP ZipArchive library so it\'s impossible to unzip '.
			'the '.static::$sZIPFileName.' file.</p>'.
			'<p>For this reason, you\'ll need to do this yourself by '.
			'unzipping the file on your local machine and send back '.
			'the unzipped folder to your FTP site.</p>'.
			'<p>Finally go back to your browser and refresh this '.
			'so the installation process can continue.</p>';
		} elseif ($code==self::ERROR_UNWRITABLE_FOLDER) {
			$sReturn =
			'<p>Your website root folder is write protected, making impossible to create the '.static::$sFolderName.' folder.</p>'.
			'<p>Please adjust the permissions (chmod) of your root folder.</p>'.
			'<p>Finally go back to your browser and refresh this '.
			'so the installation process can continue.</p>';
		} elseif ($code==self::ERROR_ZIP_FAILED) {
			$sReturn =
			'<p>The file '.static::$sZIPFileName.' seems to be '.
			'corrupt; please remove the file and try again.</p>'.
			'<p>If the problem still occurs after severall tries '.
			'you\'ll need to unzip the file yourself by '.
			'unzipping the file on your local machine and '.
			'send back the unzipped folder to your FTP site.</p>'.
			'<p>Finally go back to your browser and refresh this '.
			'so the installation process can continue.</p>';
		} elseif ($code==self::ERROR_MKDIR) {
			$sReturn = '<p>It\'s impossible to create the '.
			static::$sFolderName.' folder</p>'.
			'<p>Your website root folder is write protected and '.
			'doesn\'t allow the creation of a folder. '.
			'Please create the folder yourself. It\'s name should '.
			'be '.static::$sFolderName.'</p>';
		}

		return $sReturn;
	}
}

class Install
{
	const ERROR_PHP_VERSION = 1000;
	const ERROR_UNKNOWN = 1010;

	// Current folder of the install.php file
	private static $sCurrentFolder = '';
	// Folder where the application should be installed
	private static $sApplicationFolder = '';
	// ZIP filename : based on the URL constant, just the filename
	private static $Zip = '';

	public function __construct()
	{
		// Be sure that the server match the minimum
		// PHP version
		if (version_compare(phpversion(), PHP_MIN_VERSION, '<')) {
			self::ShowErrorPage(self::ERROR_PHP_VERSION);
		}

		Helpers::setDebuggingMode(DEBUG);

		// The zip will be downloaded and stored in the folder
		// where this install.php script is placed.
		static::$sCurrentFolder = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
		static::$sCurrentFolder = str_replace('/', DS, dirname(static::$sCurrentFolder)).DS;

		// Target filename (f.i. /public_html/marknotes/master.zip
		static::$Zip = static::$sCurrentFolder.basename(URL);

		// Folder where the application should be installed
		$tmp = \MarkNotes\Files::removeExtension(basename(URL));
		static::$sApplicationFolder = self::$sCurrentFolder.$tmp.DS;

		return true;
	}

	// Get the HTML page template
	private function getHTMLPage() : string
	{
		$sPage = '<!DOCTYPE html>';
		$sPage .= '<html lang="en">';
		$sPage .= '<head>';
		$sPage .= '<meta charset="utf-8"/>';
		$sPage .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
		$sPage .= '<meta name="robots" content="noindex, nofollow">';
		$sPage .= '<title>'.APP_NAME.' (c) Christophe Avonture</title>';
		$sPage .= '<style>body{font-size:1em;margin:40px auto;padding-top:20px;width:50%;}h2{font-size:0.8em;}p{font-family:Arial;}pre{padding:20px;white-space:pre-line;line-height:1.5em;border-radius:10px;background-color: rgb(194, 139, 137) !important;}.failure{color:red;}.success{color:green;}</style>';
		$sPage .= '</head>';
		$sPage .= '<body>';
		$sPage .= '<img src="'.LOGO.'"/>';
		$sPage .= '<div class="main">';
		$sPage .= '%CONTENT%';
		$sPage .= '</div>';
		$sPage .= '</body>';
		$sPage .= '</html>';

		return $sPage;
	}

	private function getErrorPHPVersion() : string
	{
		$sReturn =
		'<h2>Old PHP version (error #'.PHP_MIN_VERSION.')</h2>'.
		'<p>Sorry, the current version of PHP that is '.
		'running on your server is '.phpversion().'. '.
		APP_NAME.' requires at least PHP '.PHP_MIN_VERSION.' '.
		'or above.</p>'.
		'<p>Unless you can use the required version of PHP, you\'ll '.
		'not be able to use '.APP_NAME.'.</p>';

		return $sReturn;
	}

	private function showErrorPage(int $error = 0, string $msg = '')
	{
		$sError = '';

		if ($error == self::ERROR_PHP_VERSION) {
			$sError = self::getErrorPHPVersion();
		}

		if (trim($msg) !== '') {
			$sError .= '<p>'.$msg.'</p>';
		}

		// Get the template and display the error
		$sError = '<h1 class="failure">An error has occured during the '.
		'installation of '.APP_NAME.'</h1>'.$sError;

		$sHTML = str_replace('%CONTENT%', $sError, self::getHTMLPage());

		die($sHTML);
	}

	private function zipExists() : bool
	{
		$bReturn = false;

		if (file_exists(static::$Zip) && (filesize(static::$Zip) > 0)) {
			// If the file contain '<html' (not necessary
			// at the begining - That can be due to the
			// http protocol HEADER or IF browser IE...
			$content = file_get_contents(static::$Zip, null, null, 0, 2048);

			if (stripos($content, '<html') !== false) {
				unlink(static::$Zip);
				$bReturn = false;
			} else {
				$bReturn = true;
			}
		}

		return $bReturn;
	}

	/**
	* Unzip the package ZIP file
	*/
	private function unzip() : bool
	{
		$aeZip = new \MarkNotes\Zip();
		$aeZip->setFileName(self::$Zip);
		$wReturn = $aeZip->unzip();

		if ($wReturn !== 0) {
			$sErrorMsg = $aeZip->getErrorMessage($wReturn);
			self::showErrorPage($wReturn, $sErrorMsg);
		}

		unset($aeZip);

		// return True if wReturn is equal to 0 i.e. no error found
		return ($wReturn == 0);
	}

	/**
	* Once downloaded and unzipped, finalize the installation
	*/
	private function finalize() : bool
	{
		$bReturn = true;

		// The application path is f.i.
		// /public_html/site/repo_master/
		$path = static::$sApplicationFolder;

		$aeFiles = new \MarkNotes\Files();

		// Verify that, after the unzip, we don't have
		// /public_html/site/repo_master/repo_master/(all files)
		// i.e. the "repo_master" foldername twice
		if (is_dir($badFolder = $path.basename($path).DS)) {
			// Yes : the repo_master folder is repeated :
			// so move all files and folders one folder up
			$aeFiles->rmove($badFolder, $path);
		}

		// If there is a /dist folder, check if that folder
		// contains the distribution files (this is the case
		// where there are more than one file in that folder)
		if (is_dir($dist = static::$sApplicationFolder.'dist')) {
			if ($aeFiles->countFiles($dist) <= 1) {
				// The folder contains one file (or even none);
				// remove that folder
				$aeFiles->rrmdir($dist);
			}
		}

		unset($aeFiles);

		// Kill this file and the ZIP file too
		if (!DEBUG) {
			try {
				unlink(__FILE__);
				unlink(self::$Zip);
			} catch (Exception $e) {
			}
		}

		return $bReturn;
	}

	private function showPostInstall()
	{
		// Try to find the index.php page
		// First in the root application folder, if not found,
		// in the /dist folder and if still not, in the /src folder
		$path = static::$sApplicationFolder;

		if (!file_exists($fname = $path.'index.php')) {
			if (!file_exists($fname = $path.'dist'.DS.'index.php')) {
				$fname = $path.'src'.DS.'index.php';
			}
		}

		// Derive the URL for the application

		// Get the current URL (i.e. the one of the
		// installation script)
		$sURL = \MarkNotes\Helpers::getCurrentURL();

		// Get the folder, on the disk, of the installation
		// script
		$sScriptFolder = dirname($_SERVER['SCRIPT_FILENAME']);
		$sScriptFolder = str_replace('/', DS, $sScriptFolder);

		// And remove that part to the application folder
		// so we just keep the name of the folder where the
		// application has been installed
		$site = str_replace($sScriptFolder.DS, '', $fname);

		$appURL = str_replace(DS, '/', $sURL.$site);

		// Get the template
		$sMsg = '<h1 class="success">Installation of '.APP_NAME.' '.
			'is successfull</h1>'.
			'<p>The application has been successfully installed '.
			'in the folder '.static::$sApplicationFolder.'.</p>'.
			'<p>You can use '.APP_NAME.' from now : '.
			'<a href="'.$appURL.'">'.$appURL.'</a></p>'.
			'<p><em>This '.basename(__FILE__).' installation script '.
			'has been removed.</em></p>';

		$sHTML = str_replace('%CONTENT%', $sMsg, self::getHTMLPage());

		die($sHTML);

		return;
	}

	// Run the installation process
	public function run()
	{
		$wReturn = 0;
		$sErrorMsg = '';

		// If the package ZIP file isn't in the same folder
		// of this installation script, try to download it
		if (!self::zipExists()) {
			try {
				// Try to download
				$aeDownload = new \MarkNotes\Download(APP_NAME);
				$aeDownload->debugMode(DEBUG);
				$aeDownload->setURL(URL);
				$aeDownload->setFileName(static::$Zip);
				$wReturn = $aeDownload->download();

				if ($wReturn !== 0) {
					$sErrorMsg = $aeDownload->getErrorMessage($wReturn);
				}
			} catch (Exception $e) {
				$wReturn = ERROR_UNKNOWN;
				$sErrorMsg = $e->getMessage();
			}

			unset($aeDownload);
		} // if (!self::zipExists())

		if (!self::zipExists()) {
			// The download has failed
			if (($wReturn == 0) && ($sErrorMsg == '')) {
				$sErrorMsg= 'The file '.static::$Zip.' is missing';
			}
			self::showErrorPage($wReturn, $sErrorMsg);
		} else {
			// The package ZIP file is there...
			if (self::unzip()) {
				if (self::finalize()) {
					self::showPostInstall();
				}
			}
		} // if (!self::zipExists())

		return true;
	}
}

/***********************
 ***** Entry point *****
 **********************/

// Run the installation process
$aeInstall = new \MarkNotes\Install();
$aeInstall->run();
unset($aeInstall);
