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
 * Permission and eligibility checks for a questionnaire.
 *
 * Holds a reference to a questionnaire and answers "may this user do X?"
 * questions. Wraps Moodle has_capability() calls, plus the composite policy
 * rules (response-view restrictions, attempt-frequency timing).
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class capabilities {
    /** @var questionnaire The questionnaire whose permissions this object answers. */
    private questionnaire $questionnaire;

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire
     */
    public function __construct(questionnaire $questionnaire) {
        $this->questionnaire = $questionnaire;
    }

    /**
     * True if the specified user is eligible to view and submit this questionnaire.
     *
     * @param int|null $userid Defaults to current user.
     * @return bool
     */
    public function user_is_eligible(?int $userid = null): bool {
        $context = $this->questionnaire->context();
        return has_capability('mod/questionnaire:view', $context, $userid) &&
            has_capability('mod/questionnaire:submit', $context, $userid);
    }

    /**
     * True if the specified user can read their own responses.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_read_own_responses(?int $userid = null): bool {
        return has_capability('mod/questionnaire:readownresponses', $this->questionnaire->context(), $userid);
    }

    /**
     * True if the specified user can view a single response.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_view_single_response(?int $userid = null): bool {
        return has_capability('mod/questionnaire:viewsingleresponse', $this->questionnaire->context(), $userid);
    }

    /**
     * True if the specified user can delete responses.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_delete_responses(?int $userid = null): bool {
        return has_capability('mod/questionnaire:deleteresponses', $this->questionnaire->context(), $userid);
    }

    /**
     * True if the specified user can download responses.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_download_responses(?int $userid = null): bool {
        return has_capability('mod/questionnaire:downloadresponses', $this->questionnaire->context(), $userid);
    }

    /**
     * True if the specified user can manage this questionnaire.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_manage_questionnaire(?int $userid = null): bool {
        return has_capability('mod/questionnaire:manage', $this->questionnaire->context(), $userid);
    }

    /**
     * True if the specified user can edit questions in this questionnaire.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_edit_questions(?int $userid = null): bool {
        return has_capability('mod/questionnaire:editquestions', $this->questionnaire->context(), $userid);
    }

    /**
     * True if the current user can view this questionnaire.
     *
     * @return bool
     */
    public function can_view(): bool {
        return has_capability('mod/questionnaire:view', $this->questionnaire->context());
    }

    /**
     * True if the current user can preview this questionnaire.
     *
     * @return bool
     */
    public function can_preview(): bool {
        return has_capability('mod/questionnaire:preview', $this->questionnaire->context());
    }

    /**
     * True if the current user can print a blank copy of this questionnaire.
     *
     * Returns false in survey-only mode (no real module instance, so the print.php
     * link cannot be built).
     *
     * @return bool
     */
    public function can_print_blank(): bool {
        if ($this->questionnaire->coursemodule() === null) {
            return false;
        }
        return has_capability('mod/questionnaire:printblank', $this->questionnaire->context());
    }

    /**
     * True if the current user can create template surveys.
     *
     * @return bool
     */
    public function can_create_templates(): bool {
        return has_capability('mod/questionnaire:createtemplates', $this->questionnaire->context());
    }

    /**
     * True if the current user can create public surveys.
     *
     * @return bool
     */
    public function can_create_public(): bool {
        return has_capability('mod/questionnaire:createpublic', $this->questionnaire->context());
    }

    /**
     * Return true if the current user has site-level access to all groups.
     *
     * @return bool
     */
    public function can_view_all_groups(): bool {
        return has_capability('moodle/site:accessallgroups', $this->questionnaire->context());
    }

    /**
     * True if the specified user is allowed to take this questionnaire right now.
     *
     * @param int $userid
     * @return bool
     */
    public function user_can_take(int $userid): bool {
        if (!$this->questionnaire->is_active() || !$this->user_is_eligible($userid)) {
            return false;
        } else if ($this->questionnaire->qtype() == questionnaire::QTYPE_UNLIMITED) {
            return true;
        } else if ($userid > 0) {
            return $this->user_time_for_new_attempt($userid);
        } else {
            return false;
        }
    }

    /**
     * True if the timing rules allow the user to start a new attempt.
     *
     * @param int $userid
     * @return bool
     */
    public function user_time_for_new_attempt(int $userid): bool {
        $attempt = \mod_questionnaire\local\db\response_record::get_latest_complete_for_user(
            $this->questionnaire->id(),
            $userid
        );
        if ($attempt === null) {
            return true;
        }

        $lastsubmitted = (int)$attempt->get('submitted');
        $timenow = time();

        switch ($this->questionnaire->qtype()) {
            case questionnaire::QTYPE_UNLIMITED:
                return true;

            case questionnaire::QTYPE_ONCE:
                return false;

            case questionnaire::QTYPE_DAILY:
                return (date('Y', $lastsubmitted) < date('Y', $timenow)) ||
                    (date('Yz', $lastsubmitted) < date('Yz', $timenow));

            case questionnaire::QTYPE_WEEKLY:
                return (date('Y', $lastsubmitted) < date('Y', $timenow)) ||
                    (date('YW', $lastsubmitted) < date('YW', $timenow));

            case questionnaire::QTYPE_MONTHLY:
                return (date('Y', $lastsubmitted) < date('Y', $timenow)) ||
                    (date('Yn', $lastsubmitted) < date('Yn', $timenow));

            default:
                return false;
        }
    }

    /**
     * True if the current user can view all responses (checking group membership and response counts).
     *
     * @param int|null $usernumresp Number of responses the user has made; null to calculate.
     * @param bool $isviewreport Whether the context is a view-report page.
     * @return bool
     */
    public function can_view_all_responses(?int $usernumresp = null, bool $isviewreport = false): bool {
        global $USER;

        $numresp = $this->questionnaire->count_submissions();
        if ($usernumresp === null) {
            $usernumresp = $this->questionnaire->count_submissions($USER->id);
        }

        $context = $this->questionnaire->context();
        $cm = $this->questionnaire->coursemodule();
        $canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
        $groupmode = groups_get_activity_groupmode($cm, $this->questionnaire->courseid());
        $canviewgroups = ($groupmode == 1)
            ? groups_has_membership($cm, $USER->id)
            : true;

        $grouplogic = $canviewgroups || $canviewallgroups;
        $respslogic = ($numresp > 0) || $isviewreport;

        return $this->can_view_all_responses_anytime($grouplogic, $respslogic) ||
            $this->can_view_all_responses_with_restrictions($usernumresp, $grouplogic, $respslogic);
    }

    /**
     * True if the user can view all responses at any time (no submission requirement).
     *
     * @param bool $grouplogic
     * @param bool $respslogic
     * @return bool
     */
    public function can_view_all_responses_anytime(bool $grouplogic = true, bool $respslogic = true): bool {
        return $grouplogic && $respslogic && $this->questionnaire->is_survey_owner() &&
            has_capability('mod/questionnaire:readallresponseanytime', $this->questionnaire->context());
    }

    /**
     * True if the user can view all responses subject to the questionnaire's view restrictions.
     *
     * @param int|null $usernumresp
     * @param bool $grouplogic
     * @param bool $respslogic
     * @return bool
     */
    public function can_view_all_responses_with_restrictions(
        ?int $usernumresp,
        bool $grouplogic = true,
        bool $respslogic = true
    ): bool {
        $respview = $this->questionnaire->respview();
        return $grouplogic && $respslogic && $this->questionnaire->is_survey_owner() &&
            has_capability('mod/questionnaire:readallresponses', $this->questionnaire->context()) &&
            ($respview == questionnaire::RESPVIEW_ALWAYS ||
                ($respview == questionnaire::RESPVIEW_WHENCLOSED && $this->questionnaire->is_closed()) ||
                ($respview == questionnaire::RESPVIEW_WHENANSWERED && $usernumresp));
    }

    /**
     * True if the current user can view the specified response (or any response if $rid is 0).
     *
     * @param int $rid Response id to check, or 0 to check general viewing rights.
     * @return bool
     */
    public function can_view_response(int $rid = 0): bool {
        global $USER, $DB;

        $context = $this->questionnaire->context();
        $respview = $this->questionnaire->respview();

        if (!empty($rid)) {
            $response = $DB->get_record('questionnaire_response', ['id' => $rid]);

            // Response not found or belongs to a different questionnaire.
            if (empty($response) || $response->questionnaireid != $this->questionnaire->id()) {
                return false;
            }

            // Can always view if you have the unrestricted capability.
            if (has_capability('mod/questionnaire:readallresponseanytime', $context)) {
                return true;
            }

            // Can view other users' responses if capability is set and view conditions are met.
            if (
                has_capability('mod/questionnaire:readallresponses', $context) &&
                ($respview == questionnaire::RESPVIEW_ALWAYS ||
                 ($respview == questionnaire::RESPVIEW_WHENCLOSED && $this->questionnaire->is_closed()) ||
                 ($respview == questionnaire::RESPVIEW_WHENANSWERED && !$this->user_can_take($USER->id)))
            ) {
                return true;
            }

            // Can view own response.
            if (
                $response->userid == $USER->id &&
                has_capability('mod/questionnaire:readownresponses', $context) &&
                $this->questionnaire->count_submissions($USER->id) > 0
            ) {
                return true;
            }
        } else {
            // No specific response — check general viewing rights.
            if (has_capability('mod/questionnaire:readallresponseanytime', $context)) {
                return true;
            }

            if (
                has_capability('mod/questionnaire:readallresponses', $context) &&
                ($respview == questionnaire::RESPVIEW_ALWAYS ||
                 ($respview == questionnaire::RESPVIEW_WHENCLOSED && $this->questionnaire->is_closed()) ||
                 ($respview == questionnaire::RESPVIEW_WHENANSWERED && !$this->user_can_take($USER->id)))
            ) {
                return true;
            }

            if (
                has_capability('mod/questionnaire:readownresponses', $context) &&
                $this->questionnaire->count_submissions($USER->id) > 0
            ) {
                return true;
            }
        }

        return false;
    }
}
