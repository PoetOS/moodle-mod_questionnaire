<?php // $Id$

/////////////////////////////////////////////////////////////////////////////////
///  Code fragment to define the version of NEWMODULE
///  This fragment is called by moodle_needs_upgrading() and /admin/index.php
/////////////////////////////////////////////////////////////////////////////////

$module->version  = 2008060405;  // The current module version (Date: YYYYMMDDXX)
$module->requires = 2007101509;  // Requires this Moodle version
$module->cron     = 60*60*12;    // Period for cron to check this module (secs)

$module->release  = '1.9.1 (Build - 20120106)';
$module->maturity = 'STABLE';
?>