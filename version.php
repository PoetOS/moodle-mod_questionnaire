<?php // $Id: version.php,v 1.39 2010/11/25 20:51:16 mchurch Exp $

/////////////////////////////////////////////////////////////////////////////////
///  Code fragment to define the version of NEWMODULE
///  This fragment is called by moodle_needs_upgrading() and /admin/index.php
/////////////////////////////////////////////////////////////////////////////////

$module->version  = 2010110101;  // The current module version (Date: YYYYMMDDXX)
$module->requires = 2010000000;  // Requires this Moodle version
$module->cron     = 60*60*12;    // Period for cron to check this module (secs)

?>
