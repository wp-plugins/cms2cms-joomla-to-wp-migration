<?php
/**
 * This script is necessary setup automated data exchange 
 * between Your/Merchant site and CMS2CMS.
 *
 * Please carefully follow steps below.
 *
 * Requirements
 * ===========================================================================
 * PHP 4.3.x - 5.x
 * PHP Extensions: CURL (libcurl), GZip (zlib)
 *
 * Installation Instructions
 * ===========================================================================
 * 1. Extract files from archive and upload "cms2cms" folder into your site 
 *    root catalog via FTP.
 *    Example how to upload: "http://www.yourstore.com/cms2cms"
 * 2. Make "cms2cms" folder writable (set the 777 permissions, "write for all")
 * 3. Press Continue in Migration Wizard at CMS2CMS to check compatibility
 *    You are done.
 *
 * If you have any questions or issues
 * ===========================================================================
 * 1. Check steps again, startign from step 1
 * 2. See Frequently Asked Questions at http://cms2cms.com/faq
 * 3. Send email (support@cms2cms.com) to CMS2CMS support requesting help.
 * 4. Add feedback on http://cms2cms.betaeasy.com/
 *
 * Most likely you uploaded this script into wrong folder 
 * or misstyped the site address.
 *
 * DISCLAIMER
 * ===========================================================================
 * THIS SOFTWARE IS PROVIDED BY CMS2CMS ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL CMS2CMS OR ITS
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
 * OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 * ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * CMS2CMS by MagneticOne
 * (c) 2010 MagneticOne.com <contact@cms2cms.com>
 */
?><?php

@set_time_limit(0);
@ini_set('max_execution_time', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_WARNING);
@ini_set('display_errors', '1');


if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = &$process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}

header('X-msa-localtime: ' . time());
$loader = & Bridge_Loader::getInstance();

$app = new Bridge_Dispatcher();
$app->dispatch();

$response = & Bridge_Response::getInstance();
$response->sendResponse();
?><?php

/**
 * This class load and locate some libs
 * Also can determine shopping cart folder structure
 *
 */
class Bridge_Loader
{

    var $installLevel = 4;

    /**
     * @var Bridge_Module_Cms_Abstract
     */
    protected $cmsInstance;

    function Bridge_Loader()
    {
        $path = realpath(dirname(__FILE__) . str_repeat('/..', $this->installLevel));
        $this->_base_dir = str_replace('\\', '/', $path);
    }

    public function getCmsInstance()
    {
        if ($this->cmsInstance === null) {
            $this->cmsInstance = $this->detectCms();
        }

        return $this->cmsInstance;
    }

    public function getLocalPath($host, $url)
    {
        $host = urldecode($host);
        $url = urldecode($url);

        $urlHost = parse_url($host, PHP_URL_HOST);
        $urlPath = parse_url($host, PHP_URL_PATH);

        $urlHost = str_replace('~', '\~', $urlHost);
        $urlPath = str_replace('~', '\~', $urlPath);

        if (strpos($urlHost, 'www.') === 0){
            $urlHost = substr($urlHost, 4);
        }

        $pattern = sprintf('~https?://(www\.)?%s%s~', $urlHost, $urlPath);
        $path = preg_replace($pattern, '', $url);

        return $path;
    }

    public function getLocalAbsPath($host, $url)
    {
        $path = $this->getLocalPath($host, $url);
        $dir = Bridge_Loader::getInstance()->_base_dir;
        $absPath = $dir . DIRECTORY_SEPARATOR . $path;

        return $absPath;
    }

    public function getLocalRelativePath($path)
    {
        $currentPath = $this->getCurrentPath();
        if (strpos($path, $currentPath) !== 0){
            return $path;
        }

        $path = substr($path, strlen($currentPath));

        return $path;
    }

    public function createPathIfNotExists($path)
    {
        $dir = dirname($path);
        if (!file_exists($dir)
            && !mkdir($dir, 0777, true)
        ){
            throw new Exception(sprintf('Can not create target dir %s', $dir));
        }
    }

    public function isSafeModeEnabled()
    {
        return !!ini_get('safe_mode');
    }

    /**
     * Make string point to upper directory
     * Ex: /etc/smb -> /etc
     *
     * @param string $dirname
     *
     * @return string
     */
    function chdirup($dirname)
    {
        return substr($dirname, 0, strrpos($dirname, '/'));
    }

    /**
     * Get current directory path in linux notation
     *
     * @return string
     */
    function getCurrentPath()
    {
        return $this->_base_dir;
    }

    /**
     * Wrapper for realpath() function (with Windows support)
     *
     * @param string $path
     *
     * @return string Normalized path
     */
    function realpath($path)
    {
        $path = @realpath($path);
        if ($path == false) {
            return false;
        }

        return str_replace('\\', '/', $path);
    }

    /**
     * Check if path in open base dir
     *
     * @param string $dir
     *
     * @return bool
     */
    function isInOpenBaseDir($dir)
    {
        $basedir = ini_get('open_basedir');
        if ($basedir == false) {
            return true;
        }
        // exception for apache constant VIRTUAL_DOCUMENT_ROOT.
        // See http://wiki.preshweb.co.uk/doku.php?id=apache:securemassvhosting
        if (strpos($basedir, 'VIRTUAL_DOCUMENT_ROOT') !== false) {
            $basedir = str_replace('VIRTUAL_DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'], $basedir);
        }

        if (strpos($basedir, PATH_SEPARATOR) !== false) {
            $arrBaseDir = explode(PATH_SEPARATOR, $basedir);
        }
        elseif (strpos($basedir, ':') !== false) {
            $arrBaseDir = explode(':', $basedir);
        }
        else {
            $arrBaseDir = array($basedir);
        }
        $path = $this->realpath($dir) . '/';
        if ($path == false) {
            return false;
        }

        foreach ($arrBaseDir as $base) {
            if (strpos($path, $this->realpath($base)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get array of subdirectories
     *
     * @param string $dir Path to directory
     *
     * @return array
     */
    function getDirList($dir, $self = false)
    {
        if ($self) {
            $fileList = array('.');
        }
        else {
            $fileList = array();
        }

        if (Bridge_Loader::isInOpenBaseDir($dir)) {
            if (PHP_OS != 'Linux' || ((is_link($dir) || is_dir($dir)) && is_readable($dir))) {
                if (($dh = @opendir($dir)) !== false) {
                    while (($file = readdir($dh)) !== false) {
                        if ($file != '.' && $file != '..') {
                            if (!@is_link($dir . '/' . $file) && @is_dir($dir . '/' . $file)) {
                                $fileList[] = $file;
                            }
                        }
                    }
                    closedir($dh);
                }
            }
        }

        return $fileList;
    }

    function dir_base()
    {
        return realpath(dirname(__FILE__)) . '/';
    }

    function getSupportedCmsModules()
    {
        $modules = array(
            'WordPress' => array(
                'WordPress3'
            ),
            'Drupal' => array(
                'Drupal7',
                'Drupal6',
                'Drupal5'
            ),
            'Joomla' => array(
                'Joomla15',
                'Joomla17',
                'Joomla25'
            ),
            'Typo3' => array(
                'Typo34',
                'Typo36'
            ),
            'phpBB' => array(
                'phpBB3',
            )
        );


        return $modules;
    }

    protected function getCmsModuleClassName($cmsType, $module)
    {
        return 'Bridge_Module_Cms_' . ucfirst($cmsType) . '_' . ucfirst($module);
    }

    protected function getCmsModuleClass($cmsType, $module)
    {
        $className = $this->getCmsModuleClassName($cmsType, $module);
        if (!class_exists($className)) {
            return false;
        }

        return $className;
    }

    protected function detectCmsModule($cmsType, $modules)
    {
        $detectedCmsModule = null;
        foreach ($modules as $module) {
            $className = $this->getCmsModuleClass($cmsType, $module);
            if (!$className) {
                continue;
            }

            /**@var $cmsModuleInstance Bridge_Module_Cms_Abstract */
            $cmsModuleInstance = new $className();
            if ($cmsModuleInstance->detect()) {
                $detectedCmsModule = $cmsModuleInstance;
                break;
            }
        }

        return $detectedCmsModule;
    }

    /**
     * Suggest, a cms  type by files
     *
     * @return string cms cart type could be 'WordPress', 'Joomla'
     */
    function detectCms()
    {
        $detectedModule = null;
        $cmsModules = $this->getSupportedCmsModules();
        foreach ($cmsModules as $cmsType => $modules) {
            $detectedModule = $this->detectCmsModule($cmsType, $modules);
            if ($detectedModule !== null) {
                break;
            }
        }

        if ($detectedModule === null) {
            Bridge_Exception::ex('Can not detect cms', 'detect_error');
        }

        return $detectedModule;
    }

    /**
     * Create new Bridge_Loader instance and return created object reference
     * If object was created, this function will return that object reference
     *
     * @return Bridge_Loader
     */
    function &getInstance()
    {
        static $class;
        if ($class == null) {
            $class = new Bridge_Loader();
        }

        return $class;
    }

    function is_writable($path)
    {
        if ($path{strlen($path) - 1} == '/') {
            return $this->is_writable($path . uniqid(mt_rand()) . '.tmp');
        }

        if (file_exists($path)) {
            if (!($f = @fopen($path, 'r+'))) {
                return false;
            }
            fclose($f);

            return true;
        }

        if (!($f = @fopen($path, 'w'))) {
            return false;
        }
        fclose($f);
        unlink($path);

        return true;
    }

}

?><?php
class Bridge_Response_Null
{
    function openNode() {} 
    function closeNode() {}
    function closeResponseFile() {}
    function sendResponse() {}
    function sendData($data){}
}

class Bridge_Response_Memory
{
    var $hFile;
    var $response;

    var $openNodes = array();

    function Bridge_Response_Memory()
    {
        $this->response = '<response>';
        $this->openNodes = array();
    }

    function openNode($nodeName)
    {
        $this->response .= '<' . $nodeName . '>';
        array_push($this->openNodes, $nodeName);
    }

    function closeNode()
    {
        $nodeName = array_pop($this->openNodes);
        if ($nodeName == false){
            Bridge_Exceprion::ex('Trying to close response node but no des are opened');
        }
        $this->response .= '</' . $nodeName . '>';
    }

    function sendData($data)
    {
        $this->response .= '<![CDATA[' . $data . ']]>';
    }

    function sendNode($nodeName, $data)
    {
        $this->openNode($nodeName);
        $this->sendData($data);
        $this->closeNode($nodeName);
    }

    function closeResponseFile()
    {
        $this->response .= '</response>';
    }

    function sendResponse()
    {
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 0);
        }

        $this->closeResponseFile();
        $responseData = gzencode($this->response);
        header('Content-Encoding: gzip');
        header('Content-Type: application/x-gzip');
        header('Content-Length: ' . strlen($responseData));
        echo $responseData;
    }
}

class Bridge_Response
{
	/**
	 * Create a singleton instance of Bridge_Response
	 *
	 * @return Bridge_Response_Solid
	 */
	function &getInstance($classname = '')
	{
	    static $obj;
	    if ($obj === null || ($classname != '' && get_class($obj) != $classname)) 
	    {
	        if ($classname == '') {
	            $classname = 'Bridge_Response_Memory';
            }

	        $obj = new $classname();
	    }
	    return $obj;
	}

	function openNode($nodeName = '')
	{
       $obj = & Bridge_Response::getInstance();
       $obj->openNode($nodeName);
	}
	
	function closeNode()
	{
	   $obj = & Bridge_Response::getInstance();
       $obj->closeNode();
	}

    function sendData($data)
    {
        $obj = & Bridge_Response::getInstance();
        $obj->sendData($data);
    }
	
	function getFileHandler()
	{
	    $obj = & Bridge_Response::getInstance();
	    return $obj->hFile;
	}
		
	function disable()
	{
	    Bridge_Response::getInstance('Bridge_Response_Null');
	}
	
	function sendResponse() 
	{
	    $obj = & Bridge_Response::getInstance();
        $obj->sendResponse();
	}
}
?><?php

class Bridge_Includer {

    public static function backupEnvironment()
    {
        $environment = array(
            'globals' => $GLOBALS,
            'session' => $_SESSION,
            'server' => $_SERVER,
            'env' => $_ENV,
            'cookie' => $_COOKIE,
            'request' => $_REQUEST,
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'general' => array(
                'bufferCount' => ob_get_level()
            )
        );
        return $environment;
    }

    public static function safeInclude($fileName, $constants = array(), $variables = array(), $functions = array())
    {

        if (function_exists('php_check_syntax')){
            if (!php_check_syntax($fileName)){
                return false;
            }
        }
        //TODO think about it ?!
        /*
        else {
            //http://bytes.com/topic/php/answers/538287-check-syntax-before-include
            $code = file_get_contents($fileName);
            $code = preg_replace('/(^|\?>).*?(<\?php|$)/i', '', $code);
            $f = @create_function('', $code);
            $result = !empty($f);
        }
        */
        
        if (! file_exists($fileName)){
            return false;
        }

        ob_start();
        include($fileName);
        ob_clean();

        if (function_exists('error_get_last')) {
            $lastPHPError = error_get_last();
        }

        //TODO think about moving "general" environment variables to sub-array
        $environment = array(
            'globals' => $GLOBALS,
            'session' => $_SESSION,
            'server' => $_SERVER,
            'env' => $_ENV,
            'cookie' => $_COOKIE,
            'request' => $_REQUEST,
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'constants' => array(),
            'variables' => array(),
            'functions' => array()
        );

        $constantsValues = array();
        foreach($constants as $constantName){
            if (defined($constantName)){
                $constantsValues[$constantName] = constant($constantName);
            }
            else {
                $constantsValues[$constantName] = null;
            }
        }
        $environment['constants'] = $constantsValues;

        $variablesValues = array();
        foreach($variables as $variableName){
            $variablesValues[$variableName] = $$variableName;
        }
        $environment['variables'] = $variablesValues;

        $functionsCallbacks = array();
        foreach($functions as $functionName){
            if (function_exists($functionName)){
                $functionsCallbacks[$functionName] = $functionName;
            }
        }
        $environment['functions'] = $functionsCallbacks;
        $environment['general'] = array();
        $environment['general']['bufferCount'] = ob_get_level();
        return $environment;
    }

    public static function restoreEnvironment($environment)
    {
        $GLOBALS = $environment['globals'];
        $_SESSION = $environment['session'];
        $_SERVER = $environment['server'];
        $_ENV = $environment['env'];
        $_COOKIE = $environment['cookie'];
        $_REQUEST = $environment['request'];
        $_GET = $environment['get'];
        $_POST = $environment['post'];
        $_FILES = $environment['files'];

        $buffCount = (int)$environment['general']['bufferCount'];
        for($i = ob_get_level(); $i > $buffCount; $i--){
            ob_get_clean();
        }
    }

    public static function initCookies($data, $serialized = false)
    {
        if ($serialized){
            $data = unserialize($data);
        }

        if (!is_array($data)){
            return false;
        }

        $_COOKIE = $data;
        return true;
    }

    public static function initSession($data, $serialized = false)
    {
        session_start();

        if ($serialized){
            session_decode($data);
        }
        else {
            $_SESSION = $data;
        }

        return is_array($_SESSION);
    }

    public static function parseConfigFile($filename)
    {
        $fileData = file_get_contents($filename);
        $lines = explode("\n", $fileData);
        $lines = array_filter($lines);
        $existingKeys = array();
        $configData = array();
        foreach($lines as $line){
            preg_match('/define\s{0,}\(s{0,}(?P<key>.*)\s{0,},\s{0,}(?P<value>.*)\s{0,}\);/im', $line, $matches);
            if ($matches){
                $key = self::unquoteString(trim($matches['key'], ' '));
                $value = trim($matches['value'], ' ');
                foreach($existingKeys as $existingKey){
                    if (strpos($value, $existingKey) !== false){
                        $value = str_replace($existingKey, $configData[$existingKey], $value);
                    }
                }
                $configData[$key] = $value;
                $existingKeys[] = $key;
            }
        }

        foreach($configData as $confKey => $confValue){
            //$confValue = preg_replace('/(\'|")\s{0,}\.\s{0,}(\1)/', '', $confValue);
            $confValue = self::unquoteString($confValue);
            if ($confValue){
                $configData[$confKey] = $confValue;
            }
        }

        return $configData;
    }

    protected static function unquoteString($str)
    {
        $unquotedStr = $str;
        if (is_string($str) && preg_match("/^('|\")(?P<quoted>.*)(\\1)$/", $str, $matches)){
            $unquotedStr = $matches['quoted'];
        }
        return $unquotedStr;
    }

    public static function stripIncludes($filePath)
    {
        $content = file_get_contents($filePath);
        $content = preg_replace('/<\?(=|%|php)?/mi', '', $content);
        $content = preg_replace('/([^\$](require_once|require|include|include_once).*)$/mi', '', $content);
        return $content;
    }


}
?><?php
class Bridge_Exception {
	
	var $_warnings;
	var $throwExceptions;
    
	function & getInstance() {
		static $instance = null;
		if ($instance === null) {
			$instance = new Bridge_Exception();
		}
		return $instance;
	}
	
	/*
	function canThrowExceptions($throwExceptions = false)
	{
		if (is_bool($throwExceptions)) {
			Bridge_Exception::$throwExceptions = $throwExceptions;
		}
		return $this;
	}
	*/
	
	function ex($message, $code)
	{
		$obj = & Bridge_Exception::getInstance();
		$xmlError = '
			<response>
				<error>
    				<type>Exception</type>
    				<backtrace><![CDATA[' . print_r($obj->_backtrace(), true) . ']]></backtrace>
    				<runtime><![CDATA[' . $obj->_runtimeInfo() . ']]></runtime>
    				<message><![CDATA[' . $message . ']]></message>
    				<code>' . $code . '</code>
    				<mysql_error><![CDATA[' . mysql_error() . ']]></mysql_error>
    				<hostname>' . $_SERVER['SERVER_NAME'] . '(' . $_SERVER['SERVER_ADDR'] . ')</hostname>
    				<ipaddr>' . $_SERVER['SERVER_ADDR'] . '</ipaddr>
    				<query>' . $_SERVER['REQUEST_URI'] . '</query>
				</error>
			</response>
		';

        /*
        if ($this->canThrowExceptions()) {
			throw new Exception($xmlError);
		} 
		else {
		}
        */

        header('X-msa-iserror: 1');
        die($xmlError);

	}
	
	function warn($message)
	{
		$this->_warnings[] = $message;
	}
	
	function _runtimeInfo() {
		$info = 'PHP Version: ' . phpversion() . PHP_EOL 
		      //. 'MySQL Server Version: ' . mysql_get_server_info() . PHP_EOL 
		      . 'Webserver Version: ' . $_SERVER['SERVER_SOFTWARE'] . PHP_EOL;
		
		return $info;
		// 1. debug backtrace
		// 2. php version
		// 3. mysql version
		// 4. webserver version
		// 5. shopping cart type
		// 6. last mysql error
		// 7. last mysql query
	}
	
	function _backtrace()
	{
	    $trace = debug_backtrace();
	    $m1_trace = array();
	    $trace = array_reverse($trace, true);
	    foreach ($trace as $i => $call) {
	    	if ($i == 0 || $i == 1)
	    	    continue;
	    	    
	    	$newIndex = $i - 2;
	        
	    	if ( $newIndex == 0)
	    	{
	    	    $b = '<b>'; $be = '</b>';
	    	}
	    	else 
	    	{
	    	    $b = ''; $be = '';
	    	}
	    	
	    	$call['file'] = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath($call['file']));
	    	$call['file'] = str_replace('/' . basename($call['file']), '<b>' . '/' . basename($call['file']) . '</b>', $call['file']);
	    	    
	    	$m1_trace .= "\n" .
	    	    $newIndex . ': ' . $call['file'] . ':<b>' . $call['line'] . '</b> => ' 
	    	    . $call['class'] . $call['type'] . '<b>' . $call['function'] . '</b>'
	    	    . (!empty($call['args']) ?'("' . implode('","', $call['args']) . '")' : '()');
	    }
	    
	    return $m1_trace;
	}
}
?><?php
class Bridge_Dispatcher
{
    function dispatch()
    {
        if (!isset($_REQUEST['module'])) {
            echo 'Bridge successfully installed';
            die;
        }

        if ($this->_read_access_key() != $_REQUEST['accesskey']) {
            Bridge_Exception::ex('Hash is invalid', 'invalid_hash');
        }

        if (basename(__FILE__) == 'dispatcher.php') {
            define('ENVIRONMENT', 'development');
        }
        else {
            define('ENVIRONMENT', 'production');
        }

        $module = $_REQUEST['module'];
        $params = $_REQUEST['params'];

        if (isset($params['encoding']) && ($params['encoding'] === 'base64-serialize')){
            $encodedParams = $params['value'];
            $params = unserialize(base64_decode($encodedParams));
        }

        $module_class_name = 'Bridge_Module_' . ucfirst($module);
        $oModule = new $module_class_name;
        $oModule->run($params);
    }

    function _read_access_key()
    {
        $loader = Bridge_Loader::getInstance();
        $dir = $loader->dir_base();
        $keyFile = $dir . DIRECTORY_SEPARATOR . 'key.php';

        if (!file_exists($keyFile)){
            $currentCms = $loader->getCmsInstance();
            $key = $currentCms->getAccessKey();
            define('CMS2CMS_ACCESS_KEY', $key);
        }
        else {
            include $keyFile;
        }

        $accessKey = false;
        if (defined('CMS2CMS_ACCESS_KEY')) {
            $accessKey = constant('CMS2CMS_ACCESS_KEY');
        }

        if ($accessKey == false || strlen($accessKey) != 64) {
            Bridge_Exception::ex('Access Key is corrupted', 'invalid_hash');
        }

        return $accessKey;
    }

}

?><?php
/**
 * Database abstraction layer
 *
 */
class Bridge_Db
{
    /**
     * Default Mysql Connection link
     *
     * @var resource
     */
    var $_link;

    /**
     * Fetch into file chunk size
     *
     * @var int
     */
    var $_chunk_size = 300;

    /**
     * Profile state flag.
     *
     * @var bool
     */
    var $_enable_profiler = false;

    /**
     * Profiling result set
     *
     * @var array
     */
    var $_profiler_log = array();

    /**
     * Create instance of db connection layer
     *
     * @return Bridge_Db
     */
    function & getAdapter()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Bridge_Db();
        }

        return $instance;
    }

    /**
     * Enable or Disable profile
     *
     * @param bool $enable Pass true to enable profiler or false to disable it
     */
    function enableProfiler($enable = true)
    {
        $db = & Bridge_Db::getAdapter();
        $db->_enable_profiler = ($enable ? true : false); // cast to boolean
    }

    /**
     * mysql_connect wrapper with error handling
     * Throws an Bridge_Exception if can't connect to database server
     *
     * @param string $host Host name or IP Address
     * @param string $username Database username
     * @param string $password Databse password in plain text format
     * @param string $dbname Database Name cannot be empty
     */
    function connect($host = 'localhost', $username = 'root', $password = '', $dbname = '')
    {
        if (!is_resource($this->_link)) {
            $this->_link = @mysql_connect($host, $username, $password);
            if (!$this->_link) {
                Bridge_Exception::ex('Cannot connect to MySql Server', 'db_error');
            }
            $this->_setNamesUtf8();
            $this->_setdb($dbname);
        }
    }

    function _setNamesUtf8()
    {
        if (!mysql_query('SET NAMES binary')) {
            Bridge_Exception::ex("Can't change database connection charset to binary", 'db_error');
        }
    }

    /**
     * mysql_select_db wrapper with error handling
     * Throws an Bridge_Exception if can't use database
     *
     * @param string $dbname
     */
    function _setdb($dbname)
    {
        if (!mysql_select_db($dbname, $this->_link)) {
            Bridge_Exception::ex("Can't find the database '" . $dbname . "'", 'db_error');
        }

    }

    /**
     * Returns default mysql link resource
     *
     * @return resource MySQL link resource
     */
    function getConnection()
    {
        return $this->_link;
    }

    /**
     * Fetch the all data for sql query and return an array
     *
     * @param string $sql SQL Plain SQL query
     * @param string $keyField primary key field
     * @return array Fetched rows array
     */
    function fetchAll($sql, $keyField = '')
    {
        $this->_profile_start();
        $rQuery = $this->execute($sql);
        $resultArr = array();
        while (($row = mysql_fetch_assoc($rQuery)) !== false) {
            if (isset($row[$keyField])) {
                $key = $row[$keyField];
                $resultArr[$key] = $row;
            }
            else {
                $resultArr[] = $row;
            }
        }
        mysql_free_result($rQuery);
        $this->_profile_end($sql);

        return $resultArr;
    }

    function fetchOne($sql)
    {
        $rQuery = $this->execute($sql);
        if (($row = mysql_fetch_assoc($rQuery)) !== false) {
            return current($row);
        }
        else {
            return false;
        }
    }

    /**
     * Fetch the all data for sql query and write it into gzipped file
     *
     * @param string $sql Plain SQL query
     * @param string $fileName Destination filename
     * @param string $keyField Primary Key field
     * @return string Result file full path
     */
    function fetchAllIntoFile($sql, $keyField = '')
    {

        $this->_profile_start();

        $rQuery = $this->execute($sql);
        $cFile = Bridge_Response::getFileHandler();
        $numRows = mysql_num_rows($rQuery);
        gzwrite($cFile, '<rows count="' . $numRows . '">');
        $buffer = '';
        $i = 0;
        while (($row = mysql_fetch_assoc($rQuery)) !== false) {

            $i++;
            $buffer .= '<row id="' . $row[$keyField] . '"><![CDATA[' . base64_encode(serialize($row)) . "]]></row>\n";
            if ($i % $this->_chunk_size == 0) {
                gzwrite($cFile, $buffer);
                $buffer = '';
                if ($i != $numRows) {
                    gzwrite($cFile, '</rows>');
                    // start new chunk
                    Bridge_Response::closeNode();
                    Bridge_Response::openNode();
                    $cFile = Bridge_Response::getFileHandler();
                    gzwrite($cFile, '<rows>');
                }
            }
        }
        if ($buffer != '') {
            gzwrite($cFile, $buffer);
        }
        $buffer = ''; // free buffer allocated memory
        gzwrite($cFile, '</rows>');

        $this->_profile_end($sql);

        return $rQuery;
    }

    /**
     * Store profiling start time
     *
     */
    function _profile_start()
    {
        if ($this->_enable_profiler) {
            $this->_startTime = time() + floatval(microtime(true));
        }
    }

    /**
     * Store profiling result time, sql and trace for debugging
     *
     * @param string $sql
     */
    function _profile_end($sql)
    {
        if ($this->_enable_profiler) {
            $this->_profiler_log[] = array(
                'sql' => $sql,
                'time' => (time() + floatval(microtime(true)) - $this->_startTime),
                'backtrace' => debug_backtrace()
            );
        }
    }

    /**
     * Execute passed SQL query using default mysql connetion
     *
     * @param string $sql Plain SQL query
     * @return resource MySQL result set
     */
    function execute($sql)
    {
        $res = mysql_query($sql, $this->_link);
        // handle mysql error
        if ($res === false) {
            throw new Exception(mysql_error($this->_link));
        }

        return $res;
    }

    function affectedRows()
    {
        return mysql_affected_rows($this->_link);
    }

    function rowsCount($res)
    {
        return mysql_num_rows($res);
    }

    function lastInsertId()
    {
        return mysql_insert_id($this->_link);
    }

    function escape($string)
    {
        return mysql_real_escape_string($string, $this->_link);
    }

    public function getVariable($name)
    {
        $data = $this->fetchAll(sprintf("SHOW VARIABLES LIKE '%s'", $name));
        if (count($data) === 0) {
            Bridge_Exception::ex(sprintf('Variable %s is not found', $name), 'db_error');
        }
        $row = array_shift($data);

        return $row['Value'];
    }

    public function getMaxAllowedPacket()
    {
        $value = $this->getVariable('max_allowed_packet');

        return intval($value);
    }

    public static function getDbAdapter()
    {
        $db = & Bridge_Db::getAdapter();
        if (!is_resource($db->_link)) {
            $config = Bridge_Loader::getInstance()->getCmsInstance()->getConfig();
            $db->connect(
                $config['db']['host'],
                $config['db']['user'],
                $config['db']['password'],
                $config['db']['dbname']
            );
        }

        return $db;
    }

    public function fetchDataChunkWithLimits($sql, $responseLimit)
    {
        $this->_profile_start();

        $rQuery = $this->execute($sql);
        $data = array();
        $responseSize = 0;
        while (($row = mysql_fetch_assoc($rQuery)) !== false) {
            $encodedRow = base64_encode(serialize($row));
            $rowSize = strlen($encodedRow);
            if ($responseSize > 0 && $responseSize + $rowSize > $responseLimit) {
                break;
            }
            $responseSize += $rowSize;
            $data[] = $encodedRow;
        }

        $this->_profile_end($sql);

        return $data;
    }

}
?><?php
class Bridge_Base
{
    function getShoppingCartType()
    {
        return Bridge_Loader::getCmsTypeSuggested();
    }

    function _matchFirst($pattern, $subject, $matchNum = 0)
    {
        $matches = array();
        preg_match($pattern, $subject, $matches);
        return $matches[$matchNum];
    }

}
?><?php
class Bridge_Module_Info
{

    function run()
    {
        $bridgeVersion = '';
        $loader = Bridge_Loader::getInstance();
        $versionFile = $loader->dir_base() . 'version.txt';
        if (file_exists($versionFile)) {
            $bridgeVersion = file_get_contents($versionFile);
        }

        $currentCms = $loader->getCmsInstance();

        $config = $currentCms->getConfig();
        $imgDirectory = $currentCms->getImageDir();
        $modules = $currentCms->detectExtensions();

        $imgDirectory = $loader->getLocalRelativePath($imgDirectory);
        $imgAbsPath = $loader->getCurrentPath() . $imgDirectory;

        $params = array(
            'phpVersion' => phpversion(),
            'db_prefix' => $config['db']['dbprefix'],
            'db_driver' => $config['db']['driver'],
            'max_allowed_packet' => Bridge_Db::getDbAdapter()->getMaxAllowedPacket(),
            'max_request_size' => ini_get('post_max_size'),
            'charset' => array(
                'web_server' => $this->getWebServerCharset(),
                'php' => $this->getPhpCharset(),
                'mysql' => $this->getMySqlCharset(),
                'db' => '' //$this->getDbCharset($config['db']['dbname'])
            ),
            'imageDirectoryOption' => array(
                'path' => $imgDirectory,
                'isWritable' => is_writable($imgAbsPath)
            ),
            'seoParams' => isset($config['seo']) ? $config['seo'] : array()
        );

        $infoNew = array(
            'bridge_version' => $bridgeVersion,
            'cart_type' =>  $config['CMSType'],
            'cart_version' => $config['version'],
            'params' => $params,
            'modules' => $modules
        );
        $encodedInfo = base64_encode(serialize($infoNew));

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('info');
        $response->sendNode('bridge_version', $bridgeVersion);
        $response->sendNode('cart_type', $config['CMSType']);
        $response->sendNode('cart_version', $config['version']);
        //$response->sendNode('params', serialize($params));
        //$response->sendNode('modules', serialize($modules));
        $response->sendNode('encodedResult', $encodedInfo);
        $response->closeNode();
    }

    protected function getWebServerCharset()
    {
        return $_SERVER['HTTP_ACCEPT_CHARSET'];
    }

    protected function getPhpCharset()
    {
        return ini_get('default_charset');
    }

    protected function fetchOneStrFromDb($sql)
    {
        $str = "";
        $db = Bridge_Db::getDbAdapter();
        $res = $db->fetchOne($sql);
        if ($res !== false && is_string($res) && strlen($res) > 0) {
            $str = $res;
        }

        return $str;
    }

    protected function getDbCharset($dbName)
    {
        $sql = sprintf(
            "
                SELECT `s`.`default_character_set_name`
                FROM `information_schema`.`SCHEMATA` `s`
                WHERE schema_name = '%s'
            ",
            $dbName
        );

        return $this->fetchOneStrFromDb($sql);
    }

    protected function getMySqlCharset()
    {
        $sql = "SELECT @@character_set_server";

        return $this->fetchOneStrFromDb($sql);
    }

}

?><?php
class Bridge_Module_Dbsql2
{

    protected $db;

    /**@var Bridge_Response_Memory */
    protected $response;

    public function __construct()
    {
        $this->db = Bridge_Db::getDbAdapter();
        /**@var $response Bridge_Response_Memory */
        $this->response = Bridge_Response::getInstance('Bridge_Response_Memory');
    }

    protected function getDefaultRowsLimit()
    {
        return 100;
    }

    protected function getDefaultResponseLimit()
    {
        return 4 * 1024 * 1024;
    }

    protected function getSqlType($sqlQuery)
    {
        $sqlQuery = strtolower(trim($sqlQuery));
        if (strpos($sqlQuery, 'select') === 0
            || strpos($sqlQuery, 'show') === 0
            || strpos($sqlQuery, 'describe') === 0
        ) {
            return 'fetch';
        }

        return 'exec';
    }

    protected function fetchData($sql, $responseLimit)
    {
        $rowsData = $this->db->fetchDataChunkWithLimits($sql, $responseLimit);

        $this->response->openNode('rows');
        foreach ($rowsData as $itemData) {
            $this->response->sendNode('row', $itemData);
        }
        $this->response->closeNode();

        header('X-msa-db-rowscount: ' . count($rowsData));
    }

    protected function exec($sql)
    {
        $this->db->execute($sql);
        header('X-msa-emptyqueryresult: 1');
        header('X-msa-db-affectedrows: ' . $this->db->affectedRows());
        header('X-msa-db-lastinsertid: ' . $this->db->lastInsertId());
    }

    function run($params)
    {
        $sql = $params['sql'];
        $sqlType = $this->getSqlType($sql);
        switch ($sqlType) {
            case 'fetch' :
                $responseLimit = $params['responseLimit'];
                $this->fetchData($sql, $responseLimit);
                break;
            case 'exec' :
                $this->exec($sql);
                break;
            default:
                Bridge_Exception::ex(sprintf('Unknown sql type %s', $sqlType), null);
        }
    }
}
?><?php
class Bridge_Module_Transfer
{

    protected function getCleanHost($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', $host);

        return $host;
    }

    protected function getTransferMode($sourceUrl, $targetUrl)
    {
        $cleanSource = $this->getCleanHost($sourceUrl);
        $cleanTarget = $this->getCleanHost($targetUrl);

        $mode = 'remote';
        if ($cleanSource === $cleanTarget){
            return 'local';
        }

        return $mode;
    }

    // http://ua2.php.net/manual/en/function.parse-url.php
    function combineUrl($parsedUrl) {
        $scheme   = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host     = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $port     = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $user     = isset($parsedUrl['user']) ? $parsedUrl['user'] : '';
        $pass     = isset($parsedUrl['pass']) ? ':' . $parsedUrl['pass']  : '';
        $pass     = ($user || $pass) ? $pass . "@" : '';
        $path     = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        $query    = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }

    protected function encodeUrl($url)
    {
        if (rawurldecode($url) !== $url){
            return $url;
        }

        $urlParts = parse_url($url);
        if (isset($urlParts['path'])){
            $encodedPathParts = array();
            $pathParts = explode('/', $urlParts['path']);
            foreach($pathParts as $pathPart){
                $encodedPathParts[] = rawurlencode($pathPart);
            }

            $urlParts['path'] = implode('/', $encodedPathParts);
        }

        $url = $this->combineUrl($urlParts);

        return $url;
    }

    protected function parseRedirectUrlFromCurlResponse($data)
    {
        list($header) = explode("\r\n\r\n", $data, 2);

        $matches = array();
        preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
        $url = trim(str_replace($matches[1], "", $matches[0]));

        $url_parsed = parse_url($url);
        if (!isset($url_parsed)) {
            throw new Exception(sprintf('Bad redirect url %s', $url));
        }

        return $url;
    }

    protected function curlRedirectSafeMode($ch, $redirects)
    {
        if ($redirects < 0){
            throw new Exception('Too many redirects');
        }

        $data = curl_exec($ch);

        $errNo = curl_errno($ch);
        if ($errNo) {
            throw new Exception(sprintf('cURL error %s', $errNo));
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $code = intval($code);
        if ($code === 200){
            return $data;
        }

        if ($code == 301 || $code == 302) {
            $url = $this->parseRedirectUrlFromCurlResponse($data);
            curl_setopt($ch, CURLOPT_URL, $url);

            return $this->curlRedirectSafeMode($ch, $redirects - 1);
        }

        throw new Exception(sprintf('Response code is %s', $code));
    }

    //http://www.php.net/manual/en/function.curl-setopt.php#95027
    protected function curlExec($ch, $redirects)
    {
        if ((ini_get('open_basedir') == '') && (ini_get('safe_mode') == 'Off')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $redirects);
            $data = curl_exec($ch);
        }
        else {
            $data = $this->curlRedirectSafeMode($ch, $redirects);
        }

        list(, $body) = explode("\r\n\r\n", $data, 2);

        return $body;
    }

    protected function transferRemoteFile($sourceUrl, $targetPath)
    {
        $host = parse_url($sourceUrl, PHP_URL_HOST);
        $encodedUrl = $this->encodeUrl($sourceUrl);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $encodedUrl);
        curl_setopt($ch, CURLOPT_REFERER, $host);
        curl_setopt($ch, CURLOPT_USERAGENT, 'CMS2CMS Bridge');

        $body = $this->curlExec($ch, 5);

        $fp = fopen($targetPath, 'w');
        if (!$fp){
            throw new Exception(sprintf('Can not write into %s', $targetPath));
        }

        fwrite($fp, $body);
        fclose($fp);

        return $targetPath;
    }

    protected function transferLocalFile($sourcePath, $targetPath)
    {
        if (!file_exists($sourcePath)){
            throw new Exception(sprintf('%s does not exists', $sourcePath));
        }

        if (!is_readable($sourcePath)){
            throw new Exception(sprintf('%s is not readable', $sourcePath));
        }

        if ($sourcePath === $targetPath){
            return $targetPath;
        }

        if (!copy($sourcePath, $targetPath)){
            throw new Exception(sprintf('Copy %s to %s failed', $sourcePath, $targetPath));
        }

        return $targetPath;
    }

    protected function transferFile($sourceHost, $targetHost, $sourceUrl, $targetUrl)
    {
        $success = array();
        $error = array();

        $transferResult = null;
        $targetCopies = array();
        if (is_array($targetUrl)){
            $targetCopies = array_splice($targetUrl, 1);
            $targetUrl = array_pop($targetUrl);
        }

        $loader = Bridge_Loader::getInstance();

        $method = $this->getTransferMode($sourceHost, $targetHost);

        $sourcePath = $loader->getLocalAbsPath($sourceHost, $sourceUrl);

        $targetPath = $loader->getLocalAbsPath($targetHost, $targetUrl);
        $loader->createPathIfNotExists($targetPath);

        if (is_dir($targetPath)){
            throw new Exception(sprintf('%s is a directory', $targetPath));
        }

        $targetDir = dirname($targetPath);
        if (!is_writable($targetDir)){
            throw new Exception(sprintf('Directory %s is not writable', $targetDir));
        }


        if ($method === 'local'){
            try {
                $success[] = $this->transferLocalFile($sourcePath, $targetPath);
            }
            catch(Exception $e){
                $method = 'remote';
            }
        }

        if ($method === 'remote'){
            $success[] = $this->transferRemoteFile($sourceUrl, $targetPath);
        }

        foreach($targetCopies as $targetUrl){
            $targetCopy = $loader->getLocalAbsPath($targetHost, $targetUrl);
            try {
                $success[] = $this->transferLocalFile($targetPath, $targetCopy);
            }
            catch(Exception $e){
                $error[] = $e->getMessage();
            }
        }

        $transferResult = array(
            'success' => $success,
            'error' => $error
        );

        return $transferResult;
    }

    protected function transferFileList($sourceHost, $targetHost, array $transferList)
    {
        $transferResults = array();

        foreach($transferList as $source => $target){
            try {
                $transferResults[$source] = $this->transferFile($sourceHost, $targetHost, $source, $target);
            }
            catch(Exception $e){
                $transferResults[$source] = array('error' => array($e->getMessage()));
            }
        }

        return $transferResults;
    }

    public function transferFiles(array $params)
    {
        if (!isset($params['sourceHost'])){
            throw new Exception('Source is required');
        }

        if (!isset($params['targetHost'])){
            throw new Exception('Target is required');
        }

        if (!isset($params['list'])){
            throw new Exception('Transfer list is required');
        }

        $sourceHost = $params['sourceHost'];
        $targetHost = $params['targetHost'];
        $transferList = $params['list'];

        if (!is_array($transferList)){
            throw new Exception('Bad transfer list format');
        }

        if (count($transferList) === 0){
            throw new Exception('Transfer list is empty');
        }

        return $this->transferFileList($sourceHost, $targetHost, $transferList);
    }

    public function run($params = array())
    {
        try {
            $results = $this->transferFiles($params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), null);
            return;
        }

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('transfer');
        $response->sendNode('results', serialize($results));
        $response->closeNode();
    }

}
?><?php
class Bridge_Module_Resize
{

    /**
     * Calculates image crop by width and height
     *
     * @param $imageWidth
     * @param $imageHeight
     * @param $resizeWidth
     * @param $resizeHeight
     * @return array $imageSize array with height and width keys
     */
    protected function getCropSize($imageWidth, $imageHeight, $resizeWidth, $resizeHeight)
    {
        $marginLeft = 0;
        $marginTop = 0;

        $paddingLeft = 0;
        $paddingTop = 0;

        if ($resizeWidth > $imageWidth){
            $diffWidth = $resizeWidth - $imageWidth;
            $marginLeft = intval(floor($diffWidth / 2));

            $innerWidth = $imageWidth;
            $outerWidth = $imageWidth;
        }
        else {
            $kWidth = ($imageWidth * $resizeHeight) / ($imageHeight * $resizeWidth);

            $newWidth = intval(floor($imageWidth / $kWidth));
            $newWidth = min($newWidth, $imageWidth);

            $widthDiff = $imageWidth - $newWidth;
            $paddingLeft = intval(floor(($widthDiff) / 2));

            $innerWidth = $imageWidth - ($paddingLeft * 2);
            $outerWidth = $resizeWidth;
        }

        if ($resizeHeight > $imageHeight){
            $diffHeight = $resizeHeight - $imageHeight;
            $marginTop = intval(floor($diffHeight / 2));

            $innerHeight = $imageHeight;
            $outerHeight = $imageHeight;
        }
        else {
            $kHeight = ($imageHeight * $resizeWidth) / ($imageWidth * $resizeHeight);

            $newHeight = intval(floor($imageHeight / $kHeight));
            $newHeight = min($newHeight, $imageHeight);

            $diffHeight = $imageHeight - $newHeight;
            $paddingTop = intval(floor(($diffHeight) / 2));

            $innerHeight = $imageHeight - ($paddingTop * 2);
            $outerHeight = $resizeHeight;
        }

        $cropSize = array(
            'marginLeft' => $marginLeft,
            'marginTop' => $marginTop,

            'paddingLeft' => $paddingLeft,
            'paddingTop' => $paddingTop,

            'innerWidth' => $innerWidth,
            'innerHeight' => $innerHeight,

            'outerWidth' => $outerWidth,
            'outerHeight' => $outerHeight
        );

        return $cropSize;
    }

    public function resize($sourcePath, $targetPath, $resizeWidth, $resizeHeight)
    {
        if (!file_exists($sourcePath)){
            throw new Exception(sprintf('File %s does not exists', $sourcePath));
        }

        $type = strtolower(substr(strrchr($sourcePath, '.'), 1));
        if($type == 'jpeg') {
            $type = 'jpg';
        }

        switch($type){
            case 'bmp':
                $image = imagecreatefromwbmp($sourcePath);
                break;
            case 'gif':
                $image = imagecreatefromgif($sourcePath);
                break;
            case 'jpg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $image = imagecreatefrompng($sourcePath);
                break;
            default :
                throw new Exception("Unsupported picture type");
        }

        if ($image === false){
            throw new Exception(sprintf('Can not read image %s', $sourcePath));
        }

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        if ($resizeWidth === -1 && $resizeHeight === -1){
            throw new Exception(sprintf('Can not resize %s %s', $resizeWidth, $resizeHeight));
        }

        if ($resizeWidth === -1 && $resizeHeight > 0){
            $resizeWidth = ($imageWidth * $resizeHeight) / $imageHeight;
            $resizeWidth = intval(floor($resizeWidth));
        }

        if ($resizeHeight === -1 && $resizeWidth > 0){
            $resizeHeight = ($imageHeight * $resizeWidth) / $imageWidth;
            $resizeHeight = intval(floor($resizeHeight));
        }

        $cropSize = $this->getCropSize($imageWidth, $imageHeight, $resizeWidth, $resizeHeight);
        $resultImage = imagecreatetruecolor($resizeWidth, $resizeHeight);

        $color = imagecolorallocate($resultImage, 255, 255, 255);
        imagefilledrectangle($resultImage, 0, 0, $resizeWidth, $resizeHeight, $color);

        $copySuccess = imagecopyresampled(
            $resultImage, $image,

            $cropSize['marginLeft'],
            $cropSize['marginTop'],

            $cropSize['paddingLeft'],
            $cropSize['paddingTop'],

            $cropSize['outerWidth'],
            $cropSize['outerHeight'],

            $cropSize['innerWidth'],
            $cropSize['innerHeight']
        );

        if (!$copySuccess){
            throw new Exception('Resize failed');
        }

        switch($type){
            case 'bmp':
                $saveSuccess = imagewbmp($resultImage, $targetPath);
                break;
            case 'gif':
                $saveSuccess = imagegif($resultImage, $targetPath);
                break;
            case 'jpg':
                $saveSuccess = imagejpeg($resultImage, $targetPath);
                break;
            case 'png':
                $saveSuccess = imagepng($resultImage, $targetPath);
                break;
            default:
                throw new Exception('Unsupported picture type');
        }

        imagedestroy($image);
        imagedestroy($resultImage);

        if (!$saveSuccess){
            throw new Exception('Save failed');
        }

        return array(
            'targetPath' => $targetPath,
            'original' => array(
                'width' => $imageWidth,
                'height' => $imageHeight
            )
        );
    }

    protected function resizeImage($resizeHost, $resizeParams)
    {
        if (!isset($resizeParams['sourcePath'])){
            throw new Exception('SourcePath is required');
        }

        if (!isset($resizeParams['targetPath'])){
            throw new Exception('TargetPath is required');
        }

        if (!isset($resizeParams['width'])){
            throw new Exception('Width is required');
        }

        if (!isset($resizeParams['height'])){
            throw new Exception('Height is required');
        }

        $source = $resizeParams['sourcePath'];
        $target = $resizeParams['targetPath'];

        $width = $resizeParams['width'];
        $width = intval($width);

        $height = $resizeParams['height'];
        $height = intval($height);

        $loader = Bridge_Loader::getInstance();
        $sourcePath = $loader->getLocalAbsPath($resizeHost, $source);

        $targetPath = $loader->getLocalAbsPath($resizeHost, $target);
        $loader->createPathIfNotExists($targetPath);

        return $this->resize($sourcePath, $targetPath, $width, $height);
    }

    protected function resizeImages($params)
    {
        if (!isset($params['resizeHost'])){
            throw new Exception('Resize host is required');
        }

        if (!isset($params['list'])){
            throw new Exception('Resize list is required');
        }

        $resizeHost = $params['resizeHost'];

        $resizeList = $params['list'];
        if (!is_array($resizeList)){
            throw new Exception('Resize list must be array');
        }

        $success = array();
        $error = array();

        foreach($resizeList as $index => $resizeParams){
            try {
                $success[$index] = $this->resizeImage($resizeHost, $resizeParams);
            }
            catch(Exception $e){
                $error[$index] = $e->getMessage();
            }
        }

        $results = array(
            'success' => $success,
            'error' => $error
        );

        return $results;
    }

    public function run($params = array())
    {
        try {
            $results = $this->resizeImages($params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), 'image_resize');
            return;
        }

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('resize');
        $response->sendNode('results', serialize($results));
        $response->closeNode();
    }

}
?><?php
class Bridge_Module_ImageSize
{

    public function getSize($host, $sourceUrl)
    {
        $loader = Bridge_Loader::getInstance();
        $sourcePath = $loader->getLocalAbsPath($host, $sourceUrl);

        if (!file_exists($sourcePath)){
            throw new Exception(sprintf('File %s does not exists', $sourcePath));
        }

        $type = strtolower(substr(strrchr($sourcePath, '.'), 1));
        if($type == 'jpeg') {
            $type = 'jpg';
        }

        switch($type){
            case 'bmp':
                $image = imagecreatefromwbmp($sourcePath);
                break;
            case 'gif':
                $image = imagecreatefromgif($sourcePath);
                break;
            case 'jpg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $image = imagecreatefrompng($sourcePath);
                break;
            default :
                throw new Exception("Unsupported picture type");
        }

        if ($image === false){
            throw new Exception(sprintf('Can not read image %s', $sourcePath));
        }

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        imagedestroy($image);

        return array(
            'width' => $imageWidth,
            'height' => $imageHeight
        );
    }

    protected function getSizes($params)
    {
        if (!isset($params['sizeHost'])){
            throw new Exception('Size host is required');
        }

        if (!isset($params['list'])){
            throw new Exception('Resize list is required');
        }

        $host = $params['sizeHost'];

        $list = $params['list'];
        if (!is_array($list)){
            throw new Exception('Resize list must be array');
        }

        $success = array();
        $error = array();

        foreach($list as $index => $sourceUrl){
            try {
                $success[$index] = $this->getSize($host, $sourceUrl);
            }
            catch(Exception $e){
                $error[$index] = $e->getMessage();
            }
        }

        $results = array(
            'success' => $success,
            'error' => $error
        );

        return $results;
    }

    public function run($params = array())
    {
        try {
            $results = $this->getSizes($params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), 'image_size');
            return;
        }

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('size');
        $response->sendNode('results', serialize($results));
        $response->closeNode();
    }

}
?><?php
class Bridge_Module_Fs
{

    protected $db;

    /**@var $response Bridge_Response_Memory */
    protected $response;

    public function __construct()
    {
        $this->db = Bridge_Db::getDbAdapter();
        /**@var $response Bridge_Response_Memory */
        $this->response = Bridge_Response::getInstance('Bridge_Response_Memory');
    }

    public function runFileExists(array $params)
    {
        if (!isset($params['list'])){
            throw new Exception('List params is missing');
        }

        $list = $params['list'];
        if (!is_array($list)){
            throw new Exception('Bad list type');
        }

        $loader = Bridge_Loader::getInstance();
        $rootDir = $loader->getCurrentPath();

        $existingFiles = array();
        foreach($list as $relativePath){
            $absPath = $rootDir . $relativePath;
            if (file_exists($absPath)){
                $existingFiles[] = $relativePath;
            }
        }

        $this->response->sendNode('fileExists', serialize($existingFiles));
    }

    public function doOperation($operation, $params)
    {
        switch($operation){
            case 'file-exists':
                $this->runFileExists($params);
                break;
            default:
                throw new Exception(sprintf('Unknown fs operation %s', $operation), null);
        }
    }

    public function run($params)
    {
        if (!isset($params['operation'])){
            Bridge_Exception::ex('Type param is missing', 'dump_error');
        }

        $operation = $params['operation'];

        $this->response->openNode('fs');
        try {
            $this->doOperation($operation, $params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), 'fs_error');
        }
        $this->response->closeNode();
    }

}
?><?php
class Bridge_Module_Dump
{

    protected $db;

    /**@var $response Bridge_Response_Memory */
    protected $response;

    public function __construct()
    {
        $this->db = Bridge_Db::getDbAdapter();
        /**@var $response Bridge_Response_Memory */
        $this->response = Bridge_Response::getInstance('Bridge_Response_Memory');
    }

    protected function runListTables()
    {
        $sql = 'SHOW TABLES';
        $tables = $this->db->fetchAll($sql);

        $this->response->openNode('tables');
        foreach ($tables as $table) {
            $tableName = array_pop($table);
            $this->response->sendNode('table', $tableName);
        }
        $this->response->closeNode();
    }

    protected function runShowCreate(array $params)
    {
        if (!isset($params['table'])){
            throw new Exception('Table param is missing');
        }

        $table = $params['table'];

        $sql = sprintf(
            'SHOW CREATE TABLE `%s`',
            $table
        );

        $rows = $this->db->fetchAll($sql);
        if (count($rows) === 0){
            throw new Exception();
        }

        $firstRow = array_pop($rows);
        $statement = array_pop($firstRow);

        $this->response->sendNode('statement', base64_encode($statement));
    }

    protected function runExecCreate($params)
    {
        if (!isset($params['createStatement'])){
            throw new Exception('Statement param is missing');
        }

        if (!isset($params['dropStatement'])){
            throw new Exception('Table param is missing');
        }

        $dropSql = base64_decode($params['dropStatement']);
        $createSql = base64_decode($params['createStatement']);

        try {
            $this->db->execute($createSql);
            $this->db->execute($dropSql);
            $this->db->execute($createSql);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), 'db_error');
        }
    }

    protected function getDumpQuery($table, $limit, $offset, $filter = '')
    {
        $sql = sprintf(
            'SELECT * FROM `%s`',
            $table
        );

        if ($filter !== ''){
            $sql .= ' ' . $filter;
        }

        $sql .= ' ' . sprintf('LIMIT %s', $limit);
        $sql .= ' ' . sprintf('OFFSET %s', $offset);

        return $sql;
    }

    protected function runSelect(array $params)
    {
        if (!isset($params['table'])){
            throw new Exception('Table param is missing');
        }

        if (!isset($params['limit'])){
            throw new Exception('Limit param is missing');
        }

        if (!isset($params['offset'])){
            throw new Exception('Offset param is missing');
        }

        $table = $params['table'];
        $limit = $params['limit'];
        $offset = $params['offset'];

        $filter = '';
        if (isset($params['filter'])){
            $filter = $params['filter'];
        }

        $responseLimit = $params['responseLimit'];

        $sql = $this->getDumpQuery($table, $limit, $offset, $filter);
        $rowsData = $this->db->fetchDataChunkWithLimits($sql, $responseLimit);

        $this->response->openNode('rows');
        foreach ($rowsData as $itemData) {
            $this->response->sendNode('row', $itemData);
        }
        $this->response->closeNode();

        header('X-msa-db-rowscount: ' . count($rowsData));
    }

    protected function getInsertQuery($table, $data)
    {
        $fieldNamesEscaped = array();
        $fieldValuesEscaped = array();
        foreach($data as $fieldName => $fieldValue){
            $fieldNamesEscaped[] = '`' . $fieldName . '`';
            if ($fieldValue !== null){
                $fieldValuesEscaped[] = '"' . ($this->db->escape($fieldValue)) . '"';
            }
            else {
                $fieldValuesEscaped[] = 'null';
            }
        }

        $fieldsStr = implode(',', $fieldNamesEscaped);
        $valuesStr = implode(',', $fieldValuesEscaped);

        $sql = sprintf(
            'INSERT INTO `%s`(%s) VALUES (%s)',
            $table,
            $fieldsStr,
            $valuesStr
        );

        return $sql;
    }

    protected function runInsert(array $params)
    {
        if (!isset($params['table'])){
            throw new Exception('Table param is missing');
        }

        if (!isset($params['rows'])){
            throw new Exception('Rows param is missing');
        }

        $table = $params['table'];
        $rows = $params['rows'];

        $errors = array();
        foreach($rows as $index => $row){
            $data = unserialize(base64_decode($row));
            $sql = $this->getInsertQuery($table, $data);

            try {
                $this->db->execute($sql);
            }
            catch(Exception $e){
                $errors[$index] = $e->getMessage();
            }
        }

        $this->response->openNode('errors');
        foreach ($errors as $rowIndex => $message) {
            $this->response->openNode('error');
            $this->response->sendNode('row', $rowIndex);
            $this->response->sendNode('message', $message);
            $this->response->closeNode();
        }
        $this->response->closeNode();
    }

    protected function runExecute(array $params)
    {
        if (!isset($params['queryData'])){
            throw new Exception('QueryData param is missing');
        }

        $sql = $params['queryData'];
        $result = $this->db->execute($sql);

        $this->response->sendNode('result', is_bool($result));
    }

    protected function runCount(array $params)
    {
        if (!isset($params['table'])){
            throw new Exception('Table param is missing');
        }

        $table = $params['table'];

        $sql = sprintf(
            "SELECT COUNT(*) AS `count` FROM `%s`",
            $table
        );

        $count = $this->db->fetchOne($sql);

        $this->response->sendNode('count', $count);
    }

    protected function runDelete(array $params)
    {
        if (!isset($params['table'])){
            throw new Exception('Table param is missing');
        }

        if (!isset($params['count'])){
            throw new Exception('Table param is missing');
        }

        $table = $params['table'];
        $count = $params['count'];

        $count = intval($count);

        $sql = sprintf(
            "DELETE FROM `%s` WHERE 1=1 LIMIT %s",
            $table,
            $count
        );

        $result = $this->db->execute($sql);
        $count = $this->db->affectedRows();

        $this->response->sendNode('deleteCount', $count);
    }

    protected function doOperation($operation, $params)
    {
        switch($operation){
            case 'list-tables':
                $this->runListTables();
                break;
            case 'exec-create':
                $this->runExecCreate($params);
                break;
            case 'show-create':
                $this->runShowCreate($params);
                break;
            case 'select':
                $this->runSelect($params);
                break;
            case 'insert':
                $this->runInsert($params);
                break;
            case 'execute':
                $this->runExecute($params);
                break;
            case 'count':
                $this->runCount($params);
                break;
            case 'delete':
                $this->runDelete($params);
                break;
            default:
                Bridge_Exception::ex(sprintf('Unknown dump type %s', $operation), null);
        }
    }

    function run($params)
    {
        if (!isset($params['operation'])){
            Bridge_Exception::ex('Type param is missing', 'dump_error');
        }

        $operation = $params['operation'];

        $this->response->openNode('dump');
        try {
            $this->doOperation($operation, $params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), 'dump_error');
        }
        $this->response->closeNode();
    }

}
?><?php
class Bridge_Module_FileList
{
    /**
     * @param array  $directory
     */
    function run(array $directory)
    {
        $currentCms = Bridge_Loader::getInstance()->getCmsInstance();
        $fileList = $currentCms->getFileList($directory);

        $encodedFileList = base64_encode(serialize($fileList));

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('fileList');
        $response->sendNode('ImageEncoded',  $encodedFileList);
        $response->closeNode();
    }
}

?><?php
abstract class Bridge_Module_Cms_Abstract
{

    protected $config;

    protected function getTablePrefix($tableName)
    {
        $config = $this->getConfig();
        $prefix = '';
        if (isset($config['db']['dbprefix'])) {
            $prefix = $config['db']['dbprefix'];
        }

        if (is_array($prefix)){
            if (isset($prefix[$tableName])){
                $prefix = $prefix[$tableName];
            }
            else {
                $prefix = '';
            }
        }

        return $prefix;
    }

    protected function prefixTable($tableName)
    {
        $prefix = $this->getTablePrefix($tableName);

        return $prefix . $tableName;
    }

    public function getConfig()
    {
        if ($this->config == null) {
            $this->config = $this->getConfigFromConfigFiles();
        }

        return $this->config;
    }

    public function getFileList(array $params)
    {
        $directory = DIRECTORY_SEPARATOR;
        if (isset($params['directory']) && is_array($params['directory'])) {
            $directory .= implode(DIRECTORY_SEPARATOR, $params['directory']);
        }
        if (is_dir(Bridge_Loader::getInstance()->getCurrentPath() . $directory)) {
            $fileList = array(
                $directory => scandir(Bridge_Loader::getInstance()->getCurrentPath() . $directory)
            );
        }
        else {
            $fileList = array(
                DIRECTORY_SEPARATOR => scandir(Bridge_Loader::getInstance()->getCurrentPath())
            );
        }

        return $fileList;

    }

    abstract protected function getConfigFromConfigFiles();

    abstract public function detect();

    abstract public function detectExtensions();

    abstract public function getImageDir();

    abstract public function getSiteUrl();

    public function resolveRedirect()
    {
        return array(
            'entity' => '',
            'id' => 0
        );
    }

    public function handleRedirect($entity, $id)
    {
        return '';
    }

    public function getAccessKey()
    {
        return '';
    }

}
?><?php
class Bridge_Module_Cms_WordPress_WordPress3 extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'wp-config.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'wp-includes' . DIRECTORY_SEPARATOR . 'version.php';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {

        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);
        $versionConfigContent = Bridge_Includer::stripIncludes($versionConfig);

        ob_start();
        eval ($dbConfigContent);
        eval ($versionConfigContent);
        ob_clean();

        $config = array();
        $config['version'] = isset($wp_version) ? $wp_version : 'unknown';
        $config['CMSType'] = 'WordPress';
        $config['db']['host'] = defined('DB_HOST') ? constant('DB_HOST') : 'localhost';
        $config['db']['user'] = defined('DB_USER') ? constant('DB_USER') : 'root';
        $config['db']['password'] = defined('DB_PASSWORD') ? constant('DB_PASSWORD') : '';
        $config['db']['dbname'] = defined('DB_NAME') ? constant('DB_NAME') : 'wordpress';
        $config['db']['dbprefix'] = isset($table_prefix) ? $table_prefix : '';
        $config['db']['driver'] = 'mysqli'; // hardcoded database scheme

        return $config;
    }

    protected function getOptionValue($optionName)
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            "
                SELECT `option_value`
                FROM `%s`
                WHERE `option_name` = '%s'
            ",
            $this->prefixTable('options'),
            $optionName
        );

        $option = $db->fetchOne($sql);

        return $option;
    }

    public function getImageDir()
    {
        $optImgDirectory = $this->getOptionValue('upload_path');

        $path = '/wp-content/uploads';
        if (!empty($optImgDirectory)) {
            $path = Bridge_Loader::getInstance()->getLocalRelativePath($optImgDirectory);
        }

        return $path;
    }

    public function getSiteUrl()
    {
        $siteUrl = $this->getOptionValue('siteurl');

        return empty($siteUrl) ? '' : $siteUrl;
    }

    public function detectExtensions()
    {
        $plugins = array();
        $pluginsStr = $this->getOptionValue('active_plugins');

        $activePlugins = unserialize($pluginsStr);
        if (!$activePlugins) {
            return $plugins;
        }

        return $activePlugins;
    }

    public function getAccessKey()
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            "
                SELECT `option_value`
                FROM `%s`
                WHERE `option_name` = 'cms2cms-key'
            ",
            $this->prefixTable('cms2cms_options')
        );

        $key = $db->fetchOne($sql);
        if (!$key) {
            return null;
        }

        return $key;
    }

}

?>