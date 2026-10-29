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

namespace mod_questionnaire;

/**
 * Controller for the qsettings.php save flow.
 *
 * Converts the survey settings form data into an explicit allowlist of survey
 * fields, processes the info / thank-you editor draft areas, and persists the
 * result via {@see survey::update_settings()}.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class survey_settings_controller {
    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The active questionnaire.
     */
    public function __construct(
        /** @var questionnaire The active questionnaire. */
        private readonly questionnaire $questionnaire,
    ) {
    }

    /**
     * Persist survey settings from parsed qsettings form data.
     *
     * @param \stdClass $formdata Validated form data returned by settings_form::get_data().
     * @return int Survey id on success.
     */
    public function save(\stdClass $formdata): int {
        // The survey's name and owning course are never changed here.
        $fields = [
            'realm'      => $this->allowed_realm((string)($formdata->realm ?? '')),
            'title'      => $formdata->title ?? '',
            'subtitle'   => $formdata->subtitle ?? '',
            'info'       => $this->save_editor($formdata, 'info'),
            'theme'      => '',
            'thankspage' => $formdata->thankspage ?? '',
            'thankhead'  => $formdata->thankhead ?? '',
            'thankbody'  => $this->save_editor($formdata, 'thankbody'),
        ];
        // The email field is disabled in the form when email reporting is off; never accept a posted value then.
        if (get_config('questionnaire', 'allowemailreporting')) {
            $fields['email'] = $formdata->email ?? '';
        }

        $result = $this->questionnaire->survey()->update_settings($fields);
        if ($result === false) {
            throw new \moodle_exception('couldnotcreatenewsurvey', 'mod_questionnaire');
        }
        return $result;
    }

    /**
     * Return the posted realm if the current user may set it, otherwise the survey's current realm.
     *
     * The current realm and 'private' are always allowed; 'public' and 'template' need their create capabilities.
     *
     * @param string $realm The posted realm.
     * @return string
     */
    private function allowed_realm(string $realm): string {
        $current = $this->questionnaire->survey()->realm();
        $capabilities = $this->questionnaire->capabilities();
        $allowed = ($realm === $current) || ($realm === 'private') ||
            ($realm === 'public' && $capabilities->can_create_public()) ||
            ($realm === 'template' && $capabilities->can_create_templates());
        return $allowed ? $realm : $current;
    }

    /**
     * Save a draft-area editor field and return its processed body text.
     *
     * @param \stdClass $formdata
     * @param string $area File area name (matches the editor element name).
     * @return string
     */
    private function save_editor(\stdClass $formdata, string $area): string {
        $value = $formdata->$area ?? null;
        if (!is_array($value)) {
            return '';
        }
        return file_save_draft_area_files(
            (int) $value['itemid'],
            $this->questionnaire->context()->id,
            'mod_questionnaire',
            $area,
            $this->questionnaire->surveyid(),
            ['subdirs' => true],
            $value['text']
        );
    }
}
