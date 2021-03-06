<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Translation
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2008-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * primary class to handle translations
 *
 * @package     Tinebase
 * @subpackage  Translation
 */
class Tinebase_Translation
{
    /**
     * Lazy loading for {@see getCountryList()}
     * 
     * @var array
     */
    protected static $_countryLists = array();
    
    /**
     * cached instances of Zend_Translate
     * 
     * @var array
     */
    protected static $_applicationTranslations = array();
    
    /**
     * returns list of all available translations
     * 
     * NOTE available are those, having a Tinebase translation
     * 
     * @return array list of all available translation
     *
     * @todo add test
     */
    public static function getAvailableTranslations($appName = 'Tinebase')
    {
        $availableTranslations = array();

        // look for po files in Tinebase 
        $officialTranslationsDir = dirname(__FILE__) . "/../$appName/translations";
        foreach(scandir($officialTranslationsDir) as $poFile) {
            list ($localestring, $suffix) = explode('.', $poFile);
            if ($suffix == 'po') {
                $availableTranslations[$localestring] = array(
                    'path' => "$officialTranslationsDir/$poFile" 
                );
            }
        }
        
        // lookup/merge custom translations
        if (Tinebase_Config::isReady() === TRUE) {
            $logger = Tinebase_Core::getLogger();
            $customTranslationsDir = Tinebase_Config::getInstance()->translations;
            if ($customTranslationsDir) {
                foreach((array) @scandir($customTranslationsDir) as $dir) {
                    $poFile = "$customTranslationsDir/$dir/$appName/translations/$dir.po";
                    if (is_readable($poFile)) {
                        $availableTranslations[$dir] = array(
                            'path' => $poFile
                        );
                    }
                }
            }
        } else {
            $logger = null;
        }
        
        $filesToWatch = array();
        
        // compute information
        foreach ($availableTranslations as $localestring => $info) {
            if (! Zend_Locale::isLocale($localestring, TRUE, FALSE)) {
                if ($logger) {
                    $logger->WARN(__METHOD__ . '::' . __LINE__ . " $localestring is not supported, removing translation form list");
                }
                unset($availableTranslations[$localestring]);
                continue;
            }
            
            $filesToWatch[] = $info['path'];
        }
        
        if (Tinebase_Config::isReady()) {
            $cache = new Zend_Cache_Frontend_File(array(
                'master_files' => $filesToWatch
            ));
            $cache->setBackend(Tinebase_Core::get(Tinebase_Core::CACHE)->getBackend());
        } else {
            $cache = null;
        }
        
        if ($cache) {
            $cacheId = Tinebase_Helper::convertCacheId(__FUNCTION__ . $appName . sha1(serialize($filesToWatch)));
            $cache = new Zend_Cache_Frontend_File(array(
                'master_files' => $filesToWatch
            ));
            $cache->setBackend(Tinebase_Core::get(Tinebase_Core::CACHE)->getBackend());
            
            if ($cachedTranslations = $cache->load($cacheId)) {
                $cachedTranslations = unserialize($cachedTranslations);
                
                if ($cachedTranslations !== null) {
                    return $cachedTranslations;
                }
            }
        }
        
        // compute information
        foreach ($availableTranslations as $localestring => $info) {
            // fetch header grep for X-Poedit-Language, X-Poedit-Country
            $fh = fopen($info['path'], 'r');
            $header = fread($fh, 1024);
            fclose($fh);
            
            preg_match('/X-Tine20-Language: (.+)(?:\\\\n?)(?:"?)/', $header, $language);
            preg_match('/X-Tine20-Country: (.+)(?:\\\\n?)(?:"?)/', $header, $region);
            
            $locale = new Zend_Locale($localestring);
            $availableTranslations[$localestring]['locale'] = $localestring;
            $availableTranslations[$localestring]['language'] = isset($language[1]) ? 
                $language[1] : Zend_Locale::getTranslation($locale->getLanguage(), 'language', $locale);
            $availableTranslations[$localestring]['region'] = isset($region[1]) ? 
                $region[1] : Zend_Locale::getTranslation($locale->getRegion(), 'country', $locale);
        }

        ksort($availableTranslations);
        
        if ($cache) {
            $cache->save(serialize($availableTranslations), $cacheId, array(), /* 1 day */ 86400);
        }
        
        return $availableTranslations;
    }
    
    /**
     * get list of translated country names
     *
     * @return array list of countrys
     */
    public static function getCountryList()
    {
        $locale = Tinebase_Core::get('locale');
        $language = $locale->getLanguage();
        
        //try lazy loading of translated country list
        if (empty(self::$_countryLists[$language])) {
            $countries = Zend_Locale::getTranslationList('territory', $locale, 2);
            asort($countries);
            foreach($countries as $shortName => $translatedName) {
                $results[] = array(
                    'shortName'         => $shortName, 
                    'translatedName'    => $translatedName
                );
            }
    
            self::$_countryLists[$language] = $results;
        }

        return array('results' => self::$_countryLists[$language]);
    }
    
    /**
     * Get translated country name for a given ISO {@param $_regionCode}
     * 
     * @param String $regionCode [e.g. DE, US etc.]
     * @return String | null [e.g. Germany, United States etc.]
     */
    public static function getCountryNameByRegionCode($_regionCode)
    {
        $countries = self::getCountryList();
        foreach($countries['results'] as $country) {
            if ($country['shortName'] === $_regionCode) {
                return $country['translatedName'];
            }
        } 

        return null;
    }
    
    /**
     * Get translated country name for a given ISO {@param $_regionCode}
     * 
     * @param String $regionCode [e.g. DE, US etc.]
     * @return String | null [e.g. Germany, United States etc.]
     */
    public static function getRegionCodeByCountryName($_countryName)
    {
        $countries = self::getCountryList();
        foreach($countries['results'] as $country) {
            if ($country['translatedName'] === $_countryName) {
                return $country['shortName'];
            }
        } 

        return null;
    }
    
    /**
     * gets a supported locale
     *
     * @param   string $_localeString
     * @return  Zend_Locale
     * @throws  Tinebase_Exception_NotFound
     */
    public static function getLocale($_localeString = 'auto')
    {
        Zend_Locale::$compatibilityMode = false;
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " given localeString '$_localeString'");
        try {
            $locale = new Zend_Locale($_localeString);
            
            // check if we suppot the locale
            $supportedLocales = array();
            $availableTranslations = self::getAvailableTranslations();
            foreach ($availableTranslations as $translation) {
                $supportedLocales[] = $translation['locale'];
            }
            
            if (! in_array($_localeString, $supportedLocales)) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " '$locale' is not supported, checking fallback");
                
                // check if we find suiteable fallback
                $language = $locale->getLanguage();
                switch ($language) {
                    case 'zh':
                        $locale = new Zend_Locale('zh_CN');
                        break;
                    default: 
                        if (in_array($language, $supportedLocales)) {
                            $locale = new Zend_Locale($language);
                        } else {
                            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " no suiteable lang fallback found within this locales: " . print_r($supportedLocales, true) );
                            throw new Tinebase_Exception_NotFound('No suiteable lang fallback found.');
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . 
                ' ' . $e->getMessage() . ', falling back to locale en.');
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . 
                ' ' . $e->getTraceAsString());
            $locale = new Zend_Locale('en');
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " selected locale: '$locale'");
        return $locale;
    }
    
    /**
     * get zend translate for an application
     * 
     * @param  string $_applicationName
     * @param  Zend_Locale $_locale [optional]
     * @return Zend_Translate_Adapter
     * 
     * @todo return 'void' if locale = en
    */
    public static function getTranslation($_applicationName, Zend_Locale $_locale = NULL)
    {
        $locale = ($_locale !== NULL) ? $_locale : Tinebase_Core::get('locale');
        
        $cacheId = (string) $locale . $_applicationName;
        
        // get translation from internal class member?
        if ((isset(self::$_applicationTranslations[$cacheId]) || array_key_exists($cacheId, self::$_applicationTranslations))) {
            return self::$_applicationTranslations[$cacheId];
        }
        
        // get translation from filesystem
        $availableTranslations = self::getAvailableTranslations(ucfirst($_applicationName));
        $info = (isset($availableTranslations[(string) $locale]) || array_key_exists((string) $locale, $availableTranslations)) 
            ? $availableTranslations[(string) $locale] 
            : $availableTranslations['en'];
        
        // create new translation
        $options = array(
            'disableNotices' => true
        );
        
        // TODO remove workaround for server tests, maybe we should fix/rework bootstrap of tests
        $buildtype = (! defined('TINE20_BUILDTYPE')) ? 'DEVELOPMENT' : TINE20_BUILDTYPE;
        
        // Switch between Po and Mo adapter depending on the mode
        switch ($buildtype) {
            case 'DEVELOPMENT':
                $translate = new Zend_Translate('gettextPo', $info['path'], $info['locale'], $options);
                break;
            case 'DEBUG':
            case 'RELEASE':
                $translate = new Zend_Translate('gettext', str_replace('.po', '.mo', $info['path']), $info['locale'], $options);
                break;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) 
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .' locale used: ' . $_applicationName . '/' . $info['locale']);
        
        self::$_applicationTranslations[$cacheId] = $translate;
        
        return $translate;
    }
    
    /**
     * Returns collection of all javascript translations data for requested language
     * 
     * This is a javascript special function!
     * The data will be preseted to be included as javascript on client side!
     *
     * NOTE: This function is called from release.php cli script. In this case no 
     *       tine 2.0 core initialisation took place beforehand
     *       
     * @param  Zend_Locale|string $_locale
     * @return string      javascript
     */
    public static function getJsTranslations($_locale, $_appName = 'all')
    {
        $locale = ($_locale instanceof Zend_Locale) ? $_locale : new Zend_Locale($_locale);
        $localeString = (string) $_locale;
        
        $availableTranslations = self::getAvailableTranslations();
        $info = (isset($availableTranslations[$localeString]) || array_key_exists($localeString, $availableTranslations)) ? $availableTranslations[$localeString] : array('locale' => $localeString);
        $baseDir = ((isset($info['path']) || array_key_exists('path', $info)) ? dirname($info['path']) . '/..' : dirname(__FILE__)) . '/..';
        
        $defaultDir = dirname(__FILE__) . "/..";
        
        $genericTranslationFile = "$baseDir/Tinebase/js/Locale/static/generic-$localeString.js";
        $genericTranslationFile = is_readable($genericTranslationFile) ? $genericTranslationFile : "$defaultDir/Tinebase/js/Locale/static/generic-$localeString.js";
        
        $extjsTranslationFile   = "$baseDir/library/ExtJS/src/locale/ext-lang-$localeString.js";
        $extjsTranslationFile   = is_readable($extjsTranslationFile) ? $extjsTranslationFile : "$defaultDir/library/ExtJS/src/locale/ext-lang-$localeString.js";
        if (! is_readable($extjsTranslationFile)) {
            // trying language as fallback if lang_region file can not be found, @see 0008242: Turkish does not work / throws an error
            $language = $locale->getLanguage();
            $extjsTranslationFile   = "$baseDir/library/ExtJS/src/locale/ext-lang-$language.js";
            $extjsTranslationFile   = is_readable($extjsTranslationFile) ? $extjsTranslationFile : "$defaultDir/library/ExtJS/src/locale/ext-lang-$language.js";
        }
        $tine20TranslationFiles = self::getPoTranslationFiles($info);
        
        $allTranslationFiles    = array_merge(array($genericTranslationFile, $extjsTranslationFile), $tine20TranslationFiles);
        
        $jsTranslations = NULL;
        
        if (Tinebase_Core::get(Tinebase_Core::CACHE) && $_appName == 'all') {
            // setup cache (saves about 20% @2010/01/28)
            $cache = new Zend_Cache_Frontend_File(array(
                'master_files' => $allTranslationFiles
            ));
            $cache->setBackend(Tinebase_Core::get(Tinebase_Core::CACHE)->getBackend());
            
            $cacheId = __CLASS__ . "_". __FUNCTION__ . "_{$localeString}";
            
            $jsTranslations = $cache->load($cacheId);
        }
        
        if (! $jsTranslations) {
            $jsTranslations  = "";
            
            if (in_array($_appName, array('Tinebase', 'all'))) {
                $jsTranslations .= "/************************** generic translations **************************/ \n";
                
                $jsTranslations .= file_get_contents($genericTranslationFile);
                
                $jsTranslations  .= "/*************************** extjs translations ***************************/ \n";
                if (file_exists($extjsTranslationFile)) {
                    $jsTranslations  .= file_get_contents($extjsTranslationFile);
                } else {
                    $jsTranslations  .= "console.error('Translation Error: extjs changed their lang file name again ;-(');";
                }
            }
            
            $poFiles = self::getPoTranslationFiles($info);
            
            foreach ($poFiles as $appName => $poPath) {
                if ($_appName !='all' && $_appName != $appName) continue;
                $poObject = self::po2jsObject($poPath);
                
                //if (! json_decode($poObject)) {
                //    $jsTranslations .= "console.err('tanslations for application $appName are broken');";
                //} else {
                    $jsTranslations  .= "/********************** tine translations of $appName**********************/ \n";
                    $jsTranslations .= "Locale.Gettext.prototype._msgs['./LC_MESSAGES/$appName'] = new Locale.Gettext.PO($poObject); \n";
                //}
            }
            
            if (isset($cache)) {
                $cache->save($jsTranslations, $cacheId);
            }
        }
        
        return $jsTranslations;
    }
    
    /**
     * gets array of lang dirs from all applications having translations
     * 
     * Note: This functions must not query the database! 
     *       It's only used in the development and release building process
     * 
     * @return array appName => translationDir
     */
    public static function getTranslationDirs($_customPath = NULL)
    {
        $tine20path = dirname(__File__) . "/..";
        
        $langDirs = array();
        $d = dir($tine20path);
        while (false !== ($appName = $d->read())) {
            $appPath = "$tine20path/$appName";
            if ($appName{0} != '.' && is_dir($appPath)) {
                $translationPath = "$appPath/translations";
                if (is_dir($translationPath)) {
                    $langDirs[$appName] = $translationPath;
                }
            }
        }
        
        // evaluate customPath
        if ($_customPath) {
            $d = dir($_customPath);
            while (false !== ($appName = $d->read())) {
                $appPath = "$_customPath/$appName";
                if ($appName{0} != '.' && is_dir($appPath)) {
                    $translationPath = "$appPath/translations";
                    if (is_dir($translationPath)) {
                        $langDirs[$appName] = $translationPath;
                    }
                }
            }
        }
        
        return $langDirs;
    }
    
    /**
     * gets all available po files for a given locale
     *
     * @param  array $_info translation info
     * @return array appName => pofile path
     */
    public static function getPoTranslationFiles($_info)
    {
        $localeString = $_info['locale'];
        $poFiles = array();
        
        $translationDirs = self::getTranslationDirs(isset($_info['path']) ? dirname($_info['path']) . '/../..': NULL);
        foreach ($translationDirs as $appName => $translationDir) {
            $poPath = "$translationDir/$localeString.po";
            if (file_exists($poPath)) {
                $poFiles[$appName] = $poPath;
            }
        }
        
        return $poFiles;
    }
    
    /**
     * convertes po file to js object
     *
     * @param  string $filePath
     * @return string
     */
    public static function po2jsObject($filePath)
    {
        $po = file_get_contents($filePath);
        
        global $first, $plural;
        $first = true;
        $plural = false;
        
        $po = preg_replace('/\r?\n/', "\n", $po);
        $po = preg_replace('/^#.*\n/m', '', $po);
        // 2008-08-25 \s -> \n as there are situations when whitespace like space breaks the thing!
        $po = preg_replace('/"(\n+)"/', '', $po);
        // Create a singular version of plural defined words
        preg_match_all('/msgid "(.*?)"\nmsgid_plural ".*"\nmsgstr\[0\] "(.*?)"\n/', $po, $plurals);
        for ($i = 0; $i < count($plurals[0]); $i++) {
            $po = $po . "\n".'msgid "' . $plurals[1][$i] . '"' . "\n" . 'msgstr "' . $plurals[2][$i] . '"' . "\n";
        }
        $po = preg_replace('/msgid "(.*?)"\nmsgid_plural "(.*?)"/', 'msgid "$1, $2"', $po);
        $po = preg_replace_callback('/msg(\S+) /', create_function('$matches','
            global $first, $plural;
            switch ($matches[1]) {
                case "id":
                    if ($first) {
                        $first = false;
                        return "";
                    }
                    if ($plural) {
                        $plural = false;
                        return "]\n, ";
                    }
                    return ", ";
                case "str":
                    return ": ";
                case "str[0]":
                    $plural = true;
                    return ": [\n  ";
                default:
                    return " ,";
            }
        '), $po);
        $po = "({\n" . (string)$po . ($plural ? "]\n})" : "\n})");
        return $po;
    }
    
    /**
     * convert date to string
     * 
     * @param Tinebase_DateTime $date [optional]
     * @param string            $timezone [optional]
     * @param Zend_Locale       $locale [optional]
     * @param string            $part one of date, time or datetime [optional]
     * @param boolean           $addWeekday should the weekday be added (only works with $part = 'date[time]') [optional] 
     * @return string
     */
    public static function dateToStringInTzAndLocaleFormat(DateTime $date = null, $timezone = null, Zend_Locale $locale = null, $part = 'datetime', $addWeekday = false)
    {
        $date = ($date !== null) ? clone($date) : Tinebase_DateTime::now();
        $timezone = ($timezone !== null) ? $timezone : Tinebase_Core::getUserTimezone();
        $locale = ($locale !== null) ? $locale : Tinebase_Core::get(Tinebase_Core::LOCALE);
        
        $date = new Zend_Date($date->getTimestamp());
        $date->setTimezone($timezone);

        if (in_array($part, array('date', 'time', 'datetime'))) {
            $dateString = $date->toString(Zend_Locale_Format::getDateFormat($locale), $locale);
            if ($addWeekday) {
                $dateString = $date->toString('EEEE', $locale) . ', ' . $dateString;
            }
            $timeString = $date->toString(Zend_Locale_Format::getTimeFormat($locale), $locale);

            switch ($part) {
                case 'date':
                    return $dateString;
                case 'time':
                    return $timeString;
                default:
                    return $dateString . ' ' . $timeString;
            }
        } else {
            return $date->toString($part, $locale);
        }

    }
}
