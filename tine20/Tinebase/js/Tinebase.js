/*
 * Tine 2.0
 *
 * @package     Tine
 * @subpackage  Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2016 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * webpack entry
 */
var lodash = require('lodash');
var postal = require('postal');
require('postal.federation');
require('postal.xwindow');
require('postal.request-response');
var html2canvas = require('html2canvas');

module.exports = {
    postal: postal,
    html2canvas: html2canvas,
    lodash: lodash
};