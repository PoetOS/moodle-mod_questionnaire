<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// Este bloco é parte do Moodle Provas - http://tutoriais.moodle.ufsc.br/provas/
// Este projeto é financiado pela
// UAB - Universidade Aberta do Brasil (http://www.uab.capes.gov.br/)
// e é distribuído sob os termos da "GNU General Public License",
// como publicada pela "Free Software Foundation".

define('CLI_SCRIPT', true);

include(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

$domainname = $CFG->wwwroot;
$token = 'a23b513e0c3a4fcfebebd612491ce185';

function call_ws($functionname, $params) {
    global $domainname, $token;

    $serverurl = $domainname . '/webservice/rest/server.php' .
                                '?wstoken=' . $token .
                                '&wsfunction=' . $functionname .
                                '&moodlewsrestformat=json';
    $curl = new curl;
    $curl->setopt(array('CURLOPT_SSL_VERIFYHOST'=>0, 'CURLOPT_SSL_VERIFYPEER'=>0));
    $respj = $curl->post($serverurl, $params);
    return json_decode($respj);
}
