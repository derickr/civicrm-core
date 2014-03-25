<?php
/*
  +--------------------------------------------------------------------+
  | CiviCRM version 4.4                                                |
  +--------------------------------------------------------------------+
  | Copyright CiviCRM LLC (c) 2004-2013                                |
  +--------------------------------------------------------------------+
  | This file is a part of CiviCRM.                                    |
  |                                                                    |
  | CiviCRM is free software; you can copy, modify, and distribute it  |
  | under the terms of the GNU Affero General Public License           |
  | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
  |                                                                    |
  | CiviCRM is distributed in the hope that it will be useful, but     |
  | WITHOUT ANY WARRANTY; without even the implied warranty of         |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
  | See the GNU Affero General Public License for more details.        |
  |                                                                    |
  | You should have received a copy of the GNU Affero General Public   |
  | License and the CiviCRM Licensing Exception along                  |
  | with this program; if not, contact CiviCRM LLC                     |
  | at info[AT]civicrm[DOT]org. If you have questions about the        |
  | GNU Affero General Public License or the licensing of CiviCRM,     |
  | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
  +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

/**
 * Class that uses Yahoo! PlaceFinder API to retrieve the lat/long of an address
 * Documentation is at http://developer.yahoo.com/geo/placefinder/
 */
class CRM_Utils_Geocode_Nominatim {

  /**
   * server to retrieve the lat/long
   *
   * @var string
   * @static
   */
  static protected $_server = 'nominatim.openstreetmap.org';

  /**
   * uri of service
   *
   * @var string
   * @static
   */
  static protected $_uri = '/search';

  /**
   * function that takes an address array and gets the latitude / longitude
   * and postal code for this address. Note that at a later stage, we could
   * make this function also clean up the address into a more valid format
   *
   * @param array $values associative array of address data: country, street_address, city, state_province, postal code
   * @param boolean $stateName this parameter currently has no function
   *
   * @return boolean true if we modified the address, false otherwise
   * @static
   */
  static function format(&$values, $stateName = FALSE) {
    CRM_Utils_System::checkPHPVersion(5, TRUE);

    $whereComponents = array();

    if (!empty($values['street_address'])) {
      $whereComponents['street'] = $values['street_address'];
    }

    if ($city = CRM_Utils_Array::value('city', $values)) {
      $whereComponents['city'] = $city;
    }

    if (!empty($values['state_province'])) {
      if (!empty($values['state_province_id'])) {
        $stateProvince = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_StateProvince', $values['state_province_id']);
      }
      else {
        if (!$stateName) {
          $stateProvince = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_StateProvince',
            $values['state_province'],
            'name',
            'abbreviation'
          );
        }
        else {
          $stateProvince = $values['state_province'];
        }
      }

      // dont add state twice if replicated in city (happens in NZ and other countries, CRM-2632)
      if ($stateProvince != $city) {
        $whereComponents['state'] = $stateProvince;
      }
    }

    if (!empty($values['postal_code'])) {
      $whereComponents['postalcode'] = $values['postal_code'];
    }

    if (!empty($values['country'])) {
      $whereComponents['country'] = $values['country'];
    }

    foreach ($whereComponents as $componentName => $componentValue) {
      $whereComponents[$componentName] = urlencode("$componentName=\"$componentValue\"");
    }

    $add = join('&', $whereComponents);
	$add = '&format=json';

    $query = 'http://' . self::$_server . self::$_uri . '?' . $add;

    require_once 'HTTP/Request.php';
    $request = new HTTP_Request($query);
    $request->sendRequest();
    $string = $request->getResponseBody();

	$result = json_decode($string);
	$ret = $result[0];

    $values['geo_code_1'] = $ret['lat'];
    $values['geo_code_2'] = $ret['lon'];

    if ($ret['address']['postcode']) {
      $values['postval_code'] = CRM_Utils_Array::value('postal_code', $values);
    }
	return TRUE;
  }
}
