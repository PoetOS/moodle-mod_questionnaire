<?php

$functions = array(
        'mod_questionnaire_get_questionnaire' => array(
                'classname'    => 'mod_questionnaire_external',
                'methodname'   => 'get_questionnaire',
                'classpath'    => 'mod/questionnaire/externallib.php',
                'description'  => 'Get a questionnaire',
                'type'         => 'read',
                'capabilities' => 'mod/questionnaire:view'
        ),

        'mod_questionnaire_get_responses' => array(
                'classname'    => 'mod_questionnaire_external',
                'methodname'   => 'get_responses',
                'classpath'    => 'mod/questionnaire/externallib.php',
                'description'  => 'Get responses from a question of the questionnaire',
                'type'         => 'read',
                'capabilities' => 'mod/questionnaire:viewsingleresponse'
        ),
);
