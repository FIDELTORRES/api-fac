<?php
/**
 * xmlseclibs.php
 *
 * Copyright (c) 2007-2016, Robert Richards <rrichards@cdatazone.org>.
 * All rights reserved.
 * @author    Robert Richards <rrichards@cdatazone.org>
 * @copyright 2007-2016 Robert Richards <rrichards@cdatazone.org>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   2.0.1
 */

$xmlseclibs_srcdir = dirname(__FILE__);
require $xmlseclibs_srcdir . '/XMLSecurityKey.php';
require $xmlseclibs_srcdir . '/XMLSecurityDSig.php';
require $xmlseclibs_srcdir . '/XMLSecEnc.php';
