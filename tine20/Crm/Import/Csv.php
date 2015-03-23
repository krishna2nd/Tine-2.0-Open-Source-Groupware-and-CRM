<?php
/**
 * Tine 2.0
 * 
 * @package     Crm
 * @subpackage  Import
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2015 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Import class for the Crm
 * 
 * @package     Crm
 * @subpackage  Import
 */
class Crm_Import_Csv extends Tinebase_Import_Csv_Abstract
{
    /**
     * additional config options
     *
     * @var array
     */
    protected $_additionalOptions = array(
        'container_id' => '',
    );

    /**
     * creates a new importer from an import definition
     *
     * @param  Tinebase_Model_ImportExportDefinition $_definition
     * @param  array                                 $_options
     * @return Tinebase_Import_Csv_Abstract
     *
     * @todo move this to abstract when we no longer need to be php 5.2 compatible
     */
    public static function createFromDefinition(Tinebase_Model_ImportExportDefinition $_definition, array $_options = array())
    {
        return new self(self::getOptionsArrayFromDefinition($_definition, $_options));
    }

    /**
     * constructs a new importer from given config
     *
     * @param array $_options
     */
    public function __construct(array $_options = array())
    {
        parent::__construct($_options);

        // disable lead notifications on import
        Crm_Controller_Lead::getInstance()->sendNotifications(false);

        // get container id from default container if not set
        if (empty($this->_options['container_id'])) {
            $defaultContainer = Tinebase_Container::getInstance()->getDefaultContainer('Crm_Model_Lead');
            $this->_options['container_id'] = $defaultContainer->getId();
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Setting default container id: ' . $this->_options['container_id']);
        }
    }

    /**
     * add some more values (container id)
     *
     * @return array
     */
    protected function _addData()
    {
        $result['container_id'] = $this->_options['container_id'];
        return $result;
    }
}