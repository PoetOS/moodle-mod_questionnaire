<?php //$Id$

function xmldb_questionnaire_upgrade($oldversion=0) {
    global $CFG;

    $result = true;

    if ($oldversion < 2007120101) {
        $result &= questionnaire_upgrade_2007120101();
    }

    if ($oldversion < 2007120102) {
    /// Change enum values to lower case for all tables using them.
        $enumvals = array('y', 'n');

        $table = new XMLDBTable('questionnaire_question');

        $field = new XMLDBField('required');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, false, null, 'n');
        $result &= change_field_enum($table, $field);
        set_field('questionnaire_question', 'required', 'y', 'required', 'Y');
        set_field('questionnaire_question', 'required', 'n', 'required', 'N');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, XMLDB_ENUM, array('y', 'n'), 'n');
        $result &= change_field_enum($table, $field);
        $result &= change_field_default($table, $field);
        unset($field);

        $field = new XMLDBField('deleted');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, false, null, 'n');
        $result &= change_field_enum($table, $field);
        set_field('questionnaire_question', 'deleted', 'y', 'deleted', 'Y');
        set_field('questionnaire_question', 'deleted', 'n', 'deleted', 'N');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, XMLDB_ENUM, array('y', 'n'), 'n');
        $result &= change_field_enum($table, $field);
        $result &= change_field_default($table, $field);
        unset($field);

        $field = new XMLDBField('public');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, false, null, 'n');
        $result &= change_field_enum($table, $field);
        set_field('questionnaire_question', 'public', 'y', 'public', 'Y');
        set_field('questionnaire_question', 'public', 'n', 'public', 'N');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, XMLDB_ENUM, array('y', 'n'), 'y');
        $result &= change_field_enum($table, $field);
        $result &= change_field_default($table, $field);
        unset($field);

        unset($table);


        $table = new XMLDBTable('questionnaire_question_type');

        $field = new XMLDBField('has_choices');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, false, null, 'n');
        $result &= change_field_enum($table, $field);
        set_field('questionnaire_question_type', 'has_choices', 'y', 'has_choices', 'Y');
        set_field('questionnaire_question_type', 'has_choices', 'n', 'has_choices', 'N');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, XMLDB_ENUM, array('y', 'n'), 'y');
        $result &= change_field_enum($table, $field);
        $result &= change_field_default($table, $field);
        unset($field);

        unset($table);


        $table = new XMLDBTable('questionnaire_response');

        $field = new XMLDBField('complete');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, false, null, 'n');
        $result &= change_field_enum($table, $field);
        set_field('questionnaire_response', 'complete', 'y', 'complete', 'Y');
        set_field('questionnaire_response', 'complete', 'n', 'complete', 'N');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, XMLDB_ENUM, array('y', 'n'), 'n');
        $result &= change_field_enum($table, $field);
        $result &= change_field_default($table, $field);
        unset($field);

        unset($table);


        $table = new XMLDBTable('questionnaire_response_bool');

        $field = new XMLDBField('choice_id');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, false, null, 'n');
        $result &= change_field_enum($table, $field);
        set_field('questionnaire_response_bool', 'choice_id', 'y', 'choice_id', 'Y');
        set_field('questionnaire_response_bool', 'choice_id', 'n', 'choice_id', 'N');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, XMLDB_ENUM, array('y', 'n'), 'y');
        $result &= change_field_enum($table, $field);
        $result &= change_field_default($table, $field);
        unset($field);

        unset($table);


        $table = new XMLDBTable('questionnaire_survey');

        $field = new XMLDBField('public');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, false, null, 'n');
        $result &= change_field_enum($table, $field);
        set_field('questionnaire_survey', 'public', 'y', 'public', 'Y');
        set_field('questionnaire_survey', 'public', 'n', 'public', 'N');
        $field->setAttributes(XMLDB_TYPE_CHAR, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, XMLDB_ENUM, array('y', 'n'), 'y');
        $result &= change_field_enum($table, $field);
        $result &= change_field_default($table, $field);
        unset($field);

    /// Upgrade question_type table with corrected 'response_table' fields.
        set_field('questionnaire_question_type', 'response_table', 'resp_single', 'response_table', 'response_single');
        set_field('questionnaire_question_type', 'response_table', 'resp_multiple', 'response_table', 'response_multiple');
    }

    if ($oldversion < 2008031902) {
        $table = new XMLDBTable('questionnaire');
        $field = new XMLDBField('grade');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', false, true, false, false, null, 0, 'navigate');
        $result = $result && add_field($table, $field);

        unset($field);
        unset($table);
        $table = new XMLDBTable('questionnaire_response');
        $field = new XMLDBField('grade');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', false, true, false, false, null, 0, 'complete');
        $result = $result && add_field($table, $field);
    }

    if ($oldversion < 2008031904) {
        $sql = "SELECT q.id, q.resp_eligible, q.resp_view, cm.id as cmid
                FROM {$CFG->prefix}questionnaire q, {$CFG->prefix}course_modules cm, {$CFG->prefix}modules m
                WHERE m.name='questionnaire' AND m.id=cm.module AND cm.instance=q.id";
        if ($rs = get_recordset_sql($sql)) {
            $studentroleid = get_field('role', 'id', 'shortname', 'student');
            $editteacherroleid = get_field('role', 'id', 'shortname', 'editingteacher');
            $teacherroleid = get_field('role', 'id', 'shortname', 'teacher');
            $capview = 'mod/questionnaire:view';
            $capsubmit = 'mod/questionnaire:submit';

            while ($questionnaire = rs_fetch_next_record($rs)) {
                $context = get_context_instance(CONTEXT_MODULE, $questionnaire->cmid);

            /// Convert questionnaires with resp_eligible = 'all' so that students & teachers have view and submit
                if ($questionnaire->resp_eligible == 'all') {
                    assign_capability($capsubmit, CAP_ALLOW, $editteacherroleid, $context->id, true);
                    assign_capability($capsubmit, CAP_ALLOW, $teacherroleid, $context->id, true);
            /// Convert questionnaires with resp_eligible = 'students' so that just students have view and submit
                } else if ($questionnaire->resp_eligible == 'students') {
                    /// This is the default; no changes necessary.

            /// Convert questionnaires with resp_eligible = 'teachers' so just teachers have view and submit
                } else if ($questionnaire->resp_eligible == 'teachers') {
                    assign_capability($capsubmit, CAP_ALLOW, $editteacherroleid, $context->id, true);
                    assign_capability($capsubmit, CAP_ALLOW, $teacherroleid, $context->id, true);
                    assign_capability($capview, CAP_PREVENT, $studentroleid, $context->id, true);
                    assign_capability($capsubmit, CAP_PREVENT, $studentroleid, $context->id, true);
                }
            }
            rs_close($rs);
        }
    }

    if ($oldversion < 2008031905) {
        $table = new XMLDBTable('questionnaire_survey');
        $field = new XMLDBField('changed');
        $result = $result && drop_field($table, $field);
    }

    if ($oldversion < 2008031906) {
        $table = new XMLDBTable('questionnaire_response_rank');
        $field = new XMLDBField('rank');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, null, '0', 'choice_id');
        $field->setUnsigned(false);
        $result &= change_field_unsigned($table, $field);
    }

    if ($oldversion < 2008060401) {
        $table = new XMLDBTable('questionnaire_question');
        $field = new XMLDBField('name');
        $field->setAttributes(XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null, null, null, 'survey_id');
        $field->setNotnull(false);
        $result &= change_field_notnull($table, $field);
    }

    if ($oldversion < 2008060402) {
        $table = new XMLDBTable('questionnaire_question_type');
        $field = new XMLDBField('response_table');
        $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null, 'has_choices');
        $field->setNotnull(false);
        $result &= change_field_notnull($table, $field);
    }

    if ($oldversion < 2008060403) {
        $table = new XMLDBTable('questionnaire_resp_multiple');
        $index = new XMLDBIndex('response_question');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('response_id', 'question_id', 'choice_id'));

        $result = $result && add_index($table, $index);
    }

    if ($oldversion < 2008060404) {
        $table = new XMLDBTable('questionnaire_survey');
        $field = new XMLDBField('email');
        $field->setAttributes(XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, null, null, 'title');
        $field->setLength('255');
        $result &= change_field_precision($table, $field);
    }

    if ($oldversion < 2008060405) {
        /// CONTRIB-1153
        $table = new XMLDBTable('questionnaire_survey');
        $field = new XMLDBField('public');
        if (field_exists($table, $field)) {
            $result &= drop_field($table, $field);
        }

        $table = new XMLDBTable('questionnaire_question');
        $field = new XMLDBField('public');
        if (field_exists($table, $field)) {
            $result &= drop_field($table, $field);
        }
    }

    return $result;
}

    /// Supporting functions used once.
    function questionnaire_upgrade_2007120101() {

        $status = true;

        /// Shorten table names to bring them in accordance with the XML DB schema.
        $q_table = new XMLDBTable('questionnaire_question_choice');
        $status &= rename_table($q_table, 'questionnaire_quest_choice', false);
        unset($q_table);

        $q_table = new XMLDBTable('questionnaire_response_multiple');
        $status &= rename_table($q_table, 'questionnaire_resp_multiple', false);
        unset($q_table);

        $q_table = new XMLDBTable('questionnaire_response_single');
        $status &= rename_table($q_table, 'questionnaire_resp_single', false);
        unset($q_table);

        /// Upgrade the questionnaire_question_type table to use typeid.
        unset($table);
        unset($field);
        $table = new XMLDBTable('questionnaire_question_type');
        $field = new XMLDBField('typeid');
        $field->setAttributes(XMLDB_TYPE_CHAR, '20', true, true, false, false, null, '0', 'id');
        $status &= add_field($table, $field);
        if (($numrecs = count_records('questionnaire_question_type')) > 0) {
            $recstart = 0;
            $recstoget = 100;
            while ($recstart < $numrecs) {
                if ($records = get_records('questionnaire_question_type', '', '', '', '*', $recstart, $recstoget)) {
                    foreach ($records as $record) {
                        $status &= set_field('questionnaire_question_type', 'typeid', $record->id, 'id', $record->id);
                    }
                }
                $recstart += $recstoget;
            }
        }

        return $status;
    }
?>