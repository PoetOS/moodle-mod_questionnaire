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

namespace mod_questionnaire\local\feedback;

use mod_questionnaire\local\db\feedback_record;
use invalid_parameter_exception;
use coding_exception;

/**
 * Class for describing a feedback section's feedback definition.
 *
 * @package    mod_questionnaire
 * @copyright  2018 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sectionfeedback {
    /** @var int */
    public $id = 0;
    /** @var int */
    public $sectionid = 0;
    /** @var string */
    public $feedbacklabel = ''; // I don't think this is actually used?
    /** @var string */
    public $feedbacktext = '';
    /** @var string */
    public $feedbacktextformat = FORMAT_HTML;
    /** @var float */
    public $minscore = 0.0;
    /** @var float */
    public $maxscore = 0.0;

    /** The table name. */
    const TABLE = 'questionnaire_feedback';

    /**
     * Class constructor.
     * @param int $id
     * @param null|object $record
     */
    public function __construct($id = 0, $record = null) {
        // Return a new section based on the data id.
        if ($id != 0) {
            $persistent = feedback_record::get_record(['id' => $id]);
            if (!$persistent) {
                throw new invalid_parameter_exception('No section feedback exists with that ID.');
            }
            $record = $persistent->to_record();
        }
        if (($id != 0) || is_object($record)) {
            $this->loadproperties($record);
        }
    }

    /**
     * Factory method to create a new sectionfeedback from the provided data and return an instance.
     * @param \stdClass $data
     * @return sectionfeedback
     */
    public static function new_sectionfeedback($data) {
        $newsf = new self();
        $newsf->sectionid = $data->sectionid;
        $newsf->feedbacklabel = $data->feedbacklabel;
        $newsf->feedbacktext = $data->feedbacktext;
        $newsf->feedbacktextformat = $data->feedbacktextformat;
        $newsf->minscore = $data->minscore;
        $newsf->maxscore = $data->maxscore;

        $record = new feedback_record(0, (object)[
            'sectionid' => $newsf->sectionid,
            'feedbacklabel' => $newsf->feedbacklabel,
            'feedbacktext' => $newsf->feedbacktext,
            'feedbacktextformat' => $newsf->feedbacktextformat,
            'minscore' => $newsf->minscore,
            'maxscore' => $newsf->maxscore,
        ]);
        $record->create();

        $newsf->id = $record->get('id');
        return $newsf;
    }

    /**
     * Updates the data record with what is currently in the object instance.
     *
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function update() {
        $record = new feedback_record($this->id, (object)[
            'sectionid' => $this->sectionid,
            'feedbacklabel' => $this->feedbacklabel,
            'feedbacktext' => $this->feedbacktext,
            'feedbacktextformat' => $this->feedbacktextformat,
            'minscore' => $this->minscore,
            'maxscore' => $this->maxscore,
        ]);
        $record->update();
    }

    /**
     * Load object properties from a provided record for any properties defined in that record.
     *
     * @param object $record
     */
    protected function loadproperties($record) {
        foreach ($this as $property => $value) {
            if (isset($record->$property)) {
                $this->$property = $record->$property;
            }
        }
    }
}
