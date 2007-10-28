<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
* Text_Spell_Audio Example 1
*
* Output a wav file to a web browser saying 'abC123#'
*
* PHP versions 4 and 5
*
* LICENSE: This source file is subject to version 3.01 of the PHP license
* that is available through the world-wide-web at the following URI:
* http://www.php.net/license/3_01.txt.  If you did not receive a copy of
* the PHP License and are unable to obtain it through the web, please
* send a note to license@php.net so we can mail you a copy immediately.
*
* @package   Text_Spell_Audio
* @category  Text
* @author    Tom Harwood <tom@r0x0rs.com>
* @copyright 2006-2007 Tom Harwood
* @license   http://www.php.net/license/3_01.txt  PHP License 3.01
* @version   CVS: $Id$
*/

/**
 * Requires Text_Spell_Audio package
 */
require_once 'Text/Spell/Audio.php';

/**
 * Output wav file 'abC123#'
 */
$options = array('distort' => 1);
$a = new Text_Spell_Audio($options);
$a->output('abC123#');

?>