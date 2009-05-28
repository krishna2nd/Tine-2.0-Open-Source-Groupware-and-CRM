<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Server
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @version     $Id$
 *
 */

/**
 * php helpers
 */
require_once 'Helper.php';

/**
 * dispatcher and initialisation class (functions are static)
 * - dispatchRequest() function
 * - initXYZ() functions 
 * - has registry and config
 * 
 * @package     Tinebase
 */
class Tinebase_Core
{
    /**************** registry indexes *************************/
    
    /**
     * constant for config registry index
     *
     */
    const CONFIG = 'configFile';    

    /**
     * constant for locale registry index
     *
     */
    const LOCALE = 'locale';    
    
    /**
     * constant for logger registry index
     *
     */
    const LOGGER = 'logger';    
    
    /**
     * constant for cache registry index
     *
     */
    const CACHE = 'cache';    

    /**
     * constant for session namespace (tinebase) registry index
     *
     */
    const SESSION = 'session';    
    
    /**
     * constant for current account/user
     *
     */
    const USER = 'currentAccount';
    
    /**
     * const for current users credentialcache
     *
     */
    const USERCREDENTIALCACHE = 'usercredentialcache';

    /**
     * constant for database adapter
     *
     */
    const DB = 'dbAdapter';    
    
    /**
     * constant for database adapter
     *
     */
    const USERTIMEZONE = 'userTimeZone';    
    
    /**
     * constant for preferences registry
     *
     */
    const PREFERENCES = 'preferences';
    
    /**************** other consts *************************/
    
    /**
     * const PDO_MYSQL
     *
     */
    const PDO_MYSQL = 'Pdo_Mysql';
    
    /**
     * const PDO_OCI
     *
     */
    const PDO_OCI = 'Pdo_Oci';
    
    /******************************* DISPATCH *********************************/
    
    /**
     * dispatch request
     *
     */
    public static function dispatchRequest()
    {
        // disable magic_quotes_runtime
        ini_set('magic_quotes_runtime', 0);
        
        // display errors we can't handle ourselves
        error_reporting(E_COMPILE_ERROR | E_CORE_ERROR | E_ERROR | E_PARSE);
        ini_set('display_errors', 1);
        
        ini_set('log_errors', 1);
        set_error_handler('Tinebase_Core::errorHandler', E_ALL);
        
        // set default internal encoding
        ini_set('iconv.internal_encoding', 'utf-8');
        
        $server = NULL;
        
        /**************************** JSON API *****************************/

        if (( (isset($_SERVER['HTTP_X_TINE20_REQUEST_TYPE']) && $_SERVER['HTTP_X_TINE20_REQUEST_TYPE'] == 'JSON')  || 
              (isset($_POST['requestType']) && $_POST['requestType'] == 'JSON')
            ) && isset($_REQUEST['method'])) {
            $server = new Tinebase_Server_Json();

        /**************************** SNOM API *****************************/
            
        } elseif(
            isset($_SERVER['HTTP_USER_AGENT']) && 
            preg_match('/^Mozilla\/4\.0 \(compatible; (snom...)\-SIP (\d+\.\d+\.\d+)/i', $_SERVER['HTTP_USER_AGENT'])
        ) {
            $server = new Voipmanager_Server_Snom();
            
        /**************************** ActiveSync API *****************************/
            
        } elseif($_SERVER['PHP_SELF'] == '/Microsoft-Server-ActiveSync' || 
                 $_SERVER['SCRIPT_NAME'] == '/Microsoft-Server-ActiveSync'|| 
                (isset($_SERVER['SCRIPT_URL']) && $_SERVER['SCRIPT_URL'] == '/Microsoft-Server-ActiveSync') ) {
            $server = new ActiveSync_Server_Http();
            
        /**************************** CLI API *****************************/
        
        } elseif (php_sapi_name() == 'cli') {
            $server = new Tinebase_Server_Cli();
            
        /**************************** HTTP API ****************************/
        
        } else {
            $server = new Tinebase_Server_Http();
        }        
        
        $server->handle();
    }
    
    /******************************* APPLICATION ************************************/
    
    /**
     * returns an instance of the controller of an application
     *
     * @param   string $_applicationName
     * @param   string $_modelName
     * @return  object the controller of the application
     * @throws  Tinebase_Exception_NotFound
     */
    public static function getApplicationInstance($_applicationName, $_modelName = '')
    {
        $controllerName = ucfirst((string) $_applicationName) . '_Controller';

        // check for model controller
        if (!empty($_modelName)) {
            $controllerNameModel = $controllerName . '_' . $_modelName;
            if (!class_exists($controllerNameModel)) {
    
                // check for generic app controller
                if (!class_exists($controllerName)) {            
                    throw new Tinebase_Exception_NotFound('No Controller found (checked classes '. $controllerName . ' and ' . $controllerNameModel . ')!');
                } 
            } else {
                $controllerName = $controllerNameModel;
            }
        } else {
            if (!@class_exists($controllerName)) {            
                throw new Tinebase_Exception_NotFound('No Application Controller found (checked class ' . $controllerName . ')!');
            }             
        }
        
        $controller = call_user_func(array($controllerName, 'getInstance'));
        
        return $controller;
    }
    
    /******************************* SETUP ************************************/
    
    /**
     * tines error expeption handler for catchable fatal errors
     *
     * NOTE: PHP < 5.3 don't throws exceptions for Catchable fatal errors per default, 
     * so we convert them into exceptions manually
     * 
     * @param integer $severity
     * @param string $errstr
     * @param string $errfile
     * @param integer $errline
     * @throws ErrorException
     */
    public static function errorHandler($severity, $errstr, $errfile, $errline)
    {
        if (error_reporting() == 0) { 
            return; 
        }
        
        switch ($severity) {
            case E_COMPILE_ERROR:
            case E_CORE_ERROR:
            case E_ERROR:
            case E_PARSE:
            case E_RECOVERABLE_ERROR:
            case E_USER_ERROR:
                throw new ErrorException($errstr, 0, $severity, $errfile, $errline);
                break;
                
            case E_COMPILE_WARNING:
            case E_CORE_WARNING:
            case E_USER_WARNING:
            case E_WARNING:
                if (Tinebase_Core::isRegistered(Tinebase_Core::LOGGER)) {
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " $errstr in {$errfile}::{$errline} ($severity)");
                } else {
                    error_log(" $errstr in {$errfile}::{$errline} ($severity)");
                }
                break;
                
            case E_NOTICE:
            case E_STRICT:
            case E_USER_NOTICE:
            default:
                if (Tinebase_Core::isRegistered(Tinebase_Core::LOGGER)) {
                    Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " $errstr in {$errfile}::{$errline} ($severity)");
                } else {
                    error_log(" $errstr in {$errfile}::{$errline} ($severity)");
                }
                break;
            
                
        }
        
    }
    
    /**
     * initializes the config
     *
     */
    public static function setupConfig()
    {
        $configData = include('config.inc.php');
        if($configData === false) {
            die ('central configuration file config.inc.php not found in includepath: ' . get_include_path());
        }
        
        $config = new Zend_Config($configData);
        
        self::set(self::CONFIG, $config);  
    }
    
    /**
     * initializes the logger
     *
     * @param $_defaultWriter Zend_Log_Writer_Abstract default log writer
     */
    public static function setupLogger(Zend_Log_Writer_Abstract $_defaultWriter = NULL)
    {
        $config = self::getConfig();
        $logger = new Zend_Log();
        
        if (isset($config->logger) && $config->logger->active) {
            try {
                $loggerConfig = $config->logger;
                
                $filename = $loggerConfig->filename;
                $priority = (int)$loggerConfig->priority;
    
                $writer = new Zend_Log_Writer_Stream($filename);
                $logger->addWriter($writer);
    
                $filter = new Zend_Log_Filter_Priority($priority);
                $logger->addFilter($filter);
            } catch (Exception $e) {
                error_log("Tine 2.0 can't setup the configured logger! The Server responded: $e");
                $writer = ($_defaultWriter === NULL) ? new Zend_Log_Writer_Null() : $_defaultWriter;
                $logger->addWriter($writer);
            }
        } else {
            $writer = new Zend_Log_Writer_Null;
            $logger->addWriter($writer);
        }

        self::set(self::LOGGER, $logger);

        $logger->debug(__METHOD__ . '::' . __LINE__ .' logger initialized');
    }
    
    /**
     * setup the cache and add it to zend registry
     *
     */
    public static function setupCache()
    {
        $config = self::getConfig();        
        
        // create zend cache
        if ($config->caching && $config->caching->active) {
            $frontendOptions = array(
                'cache_id_prefix' => SQL_TABLE_PREFIX,
                'lifetime' => ($config->caching->lifetime) ? $config->caching->lifetime : 7200,
                'automatic_serialization' => true // turn that off for more speed
            );
                        
            $backendType = ($config->caching->backend) ? ucfirst($config->caching->backend) : 'File';
            
            switch ($backendType) {
                case 'File':
                    $backendOptions = array(
                        'cache_dir' => ($config->caching->path) ? $config->caching->path : session_save_path()  // Directory where to put the cache files
                    );
                break;
                case 'Memcached':                        
                    $backendOptions = array(
                        'servers' => array(
                            'host' => ($config->caching->host) ? $config->caching->host : 'localhost',
                            'port' => ($config->caching->port) ? $config->caching->port : 11211,
                            'persistent' => TRUE
                    ));
                break;
            }
        } else {
            $backendType = 'Test';
            $frontendOptions = array(
                'caching' => false
            );
            $backendOptions = array(
            );
        }    

        // getting a Zend_Cache_Core object
        $cache = Zend_Cache::factory('Core', $backendType, $frontendOptions, $backendOptions);
        
        // some important caches
        Zend_Date::setOptions(array('cache' => $cache));
        Zend_Locale::setCache($cache);
        
        self::set(self::CACHE, $cache);
    }
    
    /**
     * places user credential cache id from cockie (if present) into registry
     *
     */
    public static function setupUserCredentialCache()
    {
        if (isset ($_COOKIE['usercredentialcache'])) {
            $cacheId = Zend_Json::decode(base64_decode($_COOKIE['usercredentialcache']));
            if (is_array($cacheId)) {
                self::set(self::USERCREDENTIALCACHE, new Tinebase_Model_CredentialCache($cacheId));
                $cache = self::get(self::USERCREDENTIALCACHE);
            }
        }
    }
    
    /**
     * initializes the session
     *
     */
    public static function setupSession()
    {
        $config = self::getConfig();
        
        Zend_Session::setOptions(array(
            'name'              => 'TINE20SESSID',
            'cookie_httponly'   => true,
            'hash_function'     => 1,
        
        ));
        if(isset($_SERVER['HTTPS'])) {
            Zend_Session::setOptions(array(
                'cookie_secure'     => true,
            ));
        }
        
        // set max session lifetime
        // defaults to one day (86400 seconds)
        $maxLifeTime     = $config->get('gc_maxlifetime', 86400);
        ini_set('session.gc_maxlifetime', $maxLifeTime);
        
        // set the session save path
        $sessionSavepath = $config->get('session.save_path', ini_get('session.save_path') . '/tine20_sessions');
        if(ini_set('session.save_path', $sessionSavepath) !== false) { 
            if (!is_dir($sessionSavepath)) { 
                mkdir($sessionSavepath, 0700); 
            }
        }
        
        Zend_Session::start();
        
        define('TINE20_BUILDTYPE',     strtoupper($config->get('buildtype', 'DEVELOPMENT')));
        define('TINE20_CODENAME',      getDevelopmentRevision());
        define('TINE20_PACKAGESTRING', 'none');
        define('TINE20_RELEASETIME',   'none');

        $session = new Zend_Session_Namespace('tinebase');
        
        if (!isset($session->jsonKey)) {
            $session->jsonKey = Tinebase_Record_Abstract::generateUID();
        }
        self::set('jsonKey', $session->jsonKey);

        if (isset($session->currentAccount)) {
            self::set(self::USER, $session->currentAccount);
        }
        
        self::set(self::SESSION, $session);
    }
    
    /**
     * initializes the database connection
     *
     * @throws  Tinebase_Exception_UnexpectedValue
     */
    public static function setupDatabaseConnection()
    {
        $config = self::getConfig();
        
        if (isset($config->database)) {
            $dbConfig = $config->database;
            
            define('SQL_TABLE_PREFIX', $dbConfig->get('tableprefix') ? $dbConfig->get('tableprefix') : 'tine20_');
        
            $dbBackend = constant('self::' . strtoupper($dbConfig->get('adapter', self::PDO_MYSQL)));
            
            switch($dbBackend) {
                case self::PDO_MYSQL:
                    $db = Zend_Db::factory('Pdo_Mysql', $dbConfig->toArray());
                    $db->query("SET SQL_MODE = 'STRICT_ALL_TABLES'");
                    $db->query("SET NAMES UTF8");
                    break;
                case self::PDO_OCI:
                    $db = Zend_Db::factory('Pdo_Oci', $dbConfig->toArray());
                    $db->query("SET NAMES UTF8");
                    break;
                default:
                    throw new Tinebase_Exception_UnexpectedValue('Invalid database adapter defined. Please set adapter to ' . self::PDO_MYSQL . ' or ' . self::PDO_OCI . ' in config.inc.php.');
                    break;
            }
            
            Zend_Db_Table_Abstract::setDefaultAdapter($db);
            
            // place table prefix into the concrete adapter
            $db->table_prefix = SQL_TABLE_PREFIX;

            self::set(self::DB, $db);
        } else {
            die ('database section not found in central configuration file');
        }
    }
    
    /**
     * sets the user locale
     * 
     * @param  string $_localeString
     * @param  bool   $_saveaspreference
     */
    public static function setupUserLocale($_localeString = 'auto', $_saveaspreference = FALSE)
    {
        $session = self::get(self::SESSION);
        
        self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " given localeString '$_localeString'");
        $localeString = NULL;
        if ($_localeString == 'auto') {
            
            // check if cookie with language is available
            if (isset($_COOKIE['TINE20LOCALE'])) {
                $localeString = $_COOKIE['TINE20LOCALE'];
            } else {
                
                // if the session already has a locale, use this, otherwise use the preference 
                // NOTE: we always have the preference setting as fallback because it is created in the setup
                if (isset($session->userLocale)) {
                    $localeString = $session->userLocale;
                    self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " session value '$localeString'");
                    
                } elseif (isset($session->currentAccount)) {
                    $localeString = self::getPreference()->{Tinebase_Preference::LOCALE};
                    self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " preference '$localeString'");
                }
            }
        } 
        $locale = Tinebase_Translation::getLocale($localeString ? $localeString : $_localeString);
        
        // save in session and registry
        if ($session !== NULL) {
            $session->userLocale = (string)$locale;
        }
        self::set('locale', $locale);
        
        // save locale as preference
        if ($_saveaspreference && Tinebase_Core::isRegistered(self::USER)) {
            self::getPreference()->{Tinebase_Preference::LOCALE} = (string)$locale;
        }
    }
    
    /**
     * intializes the timezone handling
     *
     */
    public static function setupServerTimezone()
    {
        // All server operations are done in UTC
        date_default_timezone_set('UTC');
    }

    /**
     * intializes the timezone handling
     * 
     * @param  string $_timezone
     * @param  bool   $_saveaspreference
     * @return string
     */
    public static function setupUserTimezone($_timezone = NULL, $_saveaspreference = FALSE)
    {
        $session = self::get(self::SESSION);
        
        if ($_timezone === NULL) {
            // get timezone from preferences
            $timezone = self::getPreference()->getValue(Tinebase_Preference::TIMEZONE);
            
        } else {
            $timezone = $_timezone;

            if ($_saveaspreference) {
                // save as user preference
                self::getPreference()->setValue(Tinebase_Preference::TIMEZONE, $timezone);
            }
        }
        
        self::set(self::USERTIMEZONE, $timezone);
        
        return $timezone;
    }
    
    /**
     * function to initialize the smtp connection
     *
     */
    public static function setupMailer()
    {
        $config = self::getConfig();
        
        if (isset($config->mail)) {
            $mailConfig = $config->mail;
        } else {
            $mailConfig = new Zend_Config(array(
                'smtpserver' => 'localhost', 
                'port' => 25
           ));
        }
        
        $transport = new Zend_Mail_Transport_Smtp($mailConfig->smtpserver,  $mailConfig->toArray());
        Zend_Mail::setDefaultTransport($transport);
    }
    
    /**
     * set php execution life (max) time
     *
     * @param int $_seconds
     */
    public static function setExecutionLifeTime($_seconds)
    {
        if(ini_get('max_execution_time') < $_seconds) { 
            if((bool)ini_get('safe_mode') === true) {
                Setup_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . ' max_execution_time(' . ini_get('max_execution_time') . ') is to low. Can\'t set limit to ' . $_seconds . ' because of safe mode restrictions.');
            } else { 
                set_time_limit($_seconds);
            }
        }
    }
    
    /******************************* REGISTRY ************************************/
    
    /**
     * get a value from the registry
     *
     */
    public static function get($index)
    {
        return (Zend_Registry::isRegistered($index)) ? Zend_Registry::get($index) : NULL;
    }

    /**
     * set a registry value 
     *
     * @return mixed value
     */
    public static function set($index, $value)
    {
        Zend_Registry::set($index, $value);
    }

    /**
     * checks a registry value 
     *
     * @return boolean
     */
    public static function isRegistered($index)
    {
        return Zend_Registry::isRegistered($index);
    }

    /**
     * get config from the registry
     *
     * @return Zend_Config|Zend_Config_Ini
     */
    public static function getConfig()
    {
        return self::get(self::CONFIG);
    }

    /**
     * get config from the registry
     *
     * @return Zend_Log the logger
     */
    public static function getLogger()
    {
        return self::get(self::LOGGER);
    }

    /**
     * get session namespace from the registry
     *
     * @return Zend_Session_Namespace tinebase session namespace
     */
    public static function getSession()
    {
        return self::get(self::SESSION);
    }

    /**
     * get current user account
     *
     * @return Tinebase_Model_FullUser the user account record
     */
    public static function getUser()
    {
        return self::get(self::USER);
    }

    /**
     * get preferences instance by application name (create+save it to registry if it doesn't exist)
     *
     * @param string $_application
     * @return Tinebase_Preference_Abstract
     */
    public static function getPreference($_application = 'Tinebase')
    {
        $result = NULL;
        
        if (self::isRegistered(self::PREFERENCES)) {
            $prefs = self::get(self::PREFERENCES);
            if (isset($prefs[$_application])) {
                $result = $prefs[$_application];
            }
        } else {
            $prefs = array();
        }
        
        if ($result === NULL) {
            $prefClassName = $_application . '_Preference';
            if (class_exists($prefClassName)) {
                $result = new $prefClassName();
                $prefs[$_application] = $result;
                self::set(self::PREFERENCES, $prefs);
            }
        }
        
        return $result;
    }

    /**
     * get db adapter
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public static function getDb()
    {
        return self::get(self::DB);
    }
}
