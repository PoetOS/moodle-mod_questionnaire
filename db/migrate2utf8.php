<?php // $Id$
function migrate2utf8_questionnaire_name($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$questionnaire = get_record('questionnaire','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($questionnaire->course);  //Non existing!
        $userlang   = get_main_teacher_lang($questionnaire->course); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($questionnaire->name, $fromenc);

        $newquestionnaire = new object;
        $newquestionnaire->id = $recordid;
        $newquestionnaire->name = $result;
        migrate2utf8_update_record('questionnaire',$newquestionnaire);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_summary($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$questionnaire = get_record('questionnaire','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($questionnaire->course);  //Non existing!
        $userlang   = get_main_teacher_lang($questionnaire->course); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($questionnaire->summary, $fromenc);

        $newquestionnaire = new object;
        $newquestionnaire->id = $recordid;
        $newquestionnaire->summary = $result;
        migrate2utf8_update_record('questionnaire',$newquestionnaire);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_survey_name($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$qsurvey = get_record('questionnaire_survey','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($qsurvey->owner);  //Non existing!
        $userlang   = get_main_teacher_lang($qsurvey->owner); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($qsurvey->name, $fromenc);

        $newqsurvey = new object;
        $newqsurvey->id = $recordid;
        $newqsurvey->name = $result;
        migrate2utf8_update_record('questionnaire_survey',$newqsurvey);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_survey_title($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$qsurvey = get_record('questionnaire_survey','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($qsurvey->owner);  //Non existing!
        $userlang   = get_main_teacher_lang($qsurvey->owner); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($qsurvey->title, $fromenc);

        $newqsurvey = new object;
        $newqsurvey->id = $recordid;
        $newqsurvey->title = $result;
        migrate2utf8_update_record('questionnaire_survey',$newqsurvey);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_survey_email($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$qsurvey = get_record('questionnaire_survey','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($qsurvey->owner);  //Non existing!
        $userlang   = get_main_teacher_lang($qsurvey->owner); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($qsurvey->email, $fromenc);

        $newqsurvey = new object;
        $newqsurvey->id = $recordid;
        $newqsurvey->email = $result;
        migrate2utf8_update_record('questionnaire_survey',$newqsurvey);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_survey_subtitle($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$qsurvey = get_record('questionnaire_survey','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($qsurvey->owner);  //Non existing!
        $userlang   = get_main_teacher_lang($qsurvey->owner); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($qsurvey->subtitle, $fromenc);

        $newqsurvey = new object;
        $newqsurvey->id = $recordid;
        $newqsurvey->subtitle = $result;
        migrate2utf8_update_record('questionnaire_survey',$newqsurvey);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_survey_info($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$qsurvey = get_record('questionnaire_survey','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($qsurvey->owner);  //Non existing!
        $userlang   = get_main_teacher_lang($qsurvey->owner); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($qsurvey->info, $fromenc);

        $newqsurvey = new object;
        $newqsurvey->id = $recordid;
        $newqsurvey->info = $result;
        migrate2utf8_update_record('questionnaire_survey',$newqsurvey);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_survey_thanks_page($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$qsurvey = get_record('questionnaire_survey','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($qsurvey->owner);  //Non existing!
        $userlang   = get_main_teacher_lang($qsurvey->owner); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($qsurvey->thanks_page, $fromenc);

        $newqsurvey = new object;
        $newqsurvey->id = $recordid;
        $newqsurvey->thanks_page = $result;
        migrate2utf8_update_record('questionnaire_survey',$newqsurvey);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_survey_thanks_head($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$qsurvey = get_record('questionnaire_survey','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($qsurvey->owner);  //Non existing!
        $userlang   = get_main_teacher_lang($qsurvey->owner); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($qsurvey->thanks_head, $fromenc);

        $newqsurvey = new object;
        $newqsurvey->id = $recordid;
        $newqsurvey->thanks_head = $result;
        migrate2utf8_update_record('questionnaire_survey',$newqsurvey);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_survey_thanks_body($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$qsurvey = get_record('questionnaire_survey','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($qsurvey->owner);  //Non existing!
        $userlang   = get_main_teacher_lang($qsurvey->owner); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($qsurvey->thanks_body, $fromenc);

        $newqsurvey = new object;
        $newqsurvey->id = $recordid;
        $newqsurvey->thanks_body = $result;
        migrate2utf8_update_record('questionnaire_survey',$newqsurvey);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_question_name($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$question = get_record('questionnaire_question','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if (!$courseid = get_field('questionnaire_survey','owner','id',$question->survey_id)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($courseid);  //Non existing!
        $userlang   = get_main_teacher_lang($courseid); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($question->name, $fromenc);

        $newquestion = new object;
        $newquestion->id = $recordid;
        $newquestion->name = $result;
        migrate2utf8_update_record('questionnaire_question',$newquestion);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_question_content($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$question = get_record('questionnaire_question','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if (!$courseid = get_field('questionnaire_survey','owner','id',$question->survey_id)) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($courseid);  //Non existing!
        $userlang   = get_main_teacher_lang($courseid); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($question->content, $fromenc);

        $newquestion = new object;
        $newquestion->id = $recordid;
        $newquestion->content = $result;
        migrate2utf8_update_record('questionnaire_question',$newquestion);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_question_choice_content($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$choice = get_record('questionnaire_question_choice','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if (!$courseid = get_field('questionnaire_survey','owner','id',
                               get_field('questionnaire_question','survey_id','id',$choice->question_id))) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($courseid);  //Non existing!
        $userlang   = get_main_teacher_lang($courseid); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($choice->content, $fromenc);

        $newchoice = new object;
        $newchoice->id = $recordid;
        $newchoice->content = $result;
        migrate2utf8_update_record('questionnaire_question_choice',$newchoice);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_question_choice_value($recordid){
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$choice = get_record('questionnaire_question_choice','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if (!$courseid = get_field('questionnaire_survey','owner','id',
                               get_field('questionnaire_question','survey_id','id',$choice->question_id))) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($courseid);  //Non existing!
        $userlang   = get_main_teacher_lang($courseid); //N.E.!!

        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($choice->value, $fromenc);

        $newchoice = new object;
        $newchoice->id = $recordid;
        $newchoice->value = $result;
        migrate2utf8_update_record('questionnaire_question_choice',$newchoice);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_response_text_response($recordid) {
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$rtext = get_record('questionnaire_response_text','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if (!$courseid = get_field('questionnaire_survey','owner','id',
                               get_field('questionnaire_question','survey_id','id',$rtext->question_id))) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($courseid);  //Non existing!
        $userid = get_field('questionnaire_response', 'username', 'id', $rtext->response_id);
        if (is_numeric($userid)) {
            $userlang = get_user_lang($userid);
        } else {
            $userlang = false;
        }
        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($rtext->response, $fromenc);

        $newrtext = new object;
        $newrtext->id = $recordid;
        $newrtext->response = $result;
        migrate2utf8_update_record('questionnaire_response_text',$newrtext);
    }
/// And finally, just return the converted field
    return $result;
}

function migrate2utf8_questionnaire_response_other_response($recordid) {
    global $CFG, $globallang;

/// Some trivial checks
    if (empty($recordid)) {
        log_the_problem_somewhere();
        return false;
    }

    if (!$rother = get_record('questionnaire_response_other','id',$recordid)) {
        log_the_problem_somewhere();
        return false;
    }
    if (!$courseid = get_field('questionnaire_survey','owner','id',
                               get_field('questionnaire_question','survey_id','id',$rother->question_id))) {
        log_the_problem_somewhere();
        return false;
    }
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $sitelang   = $CFG->lang;
        $courselang = get_course_lang($courseid);  //Non existing!
        $userid = get_field('questionnaire_response', 'username', 'id', $rother->response_id);
        if (is_numeric($userid)) {
            $userlang = get_user_lang($userid);
        } else {
            $userlang = false;
        }
        $fromenc = get_original_encoding($sitelang, $courselang, $userlang);
    }

/// We are going to use textlib facilities
    
/// Convert the text
    if (($fromenc != 'utf-8') && ($fromenc != 'UTF-8')) {
        $result = utfconvert($rother->response, $fromenc);

        $newrother = new object;
        $newrother->id = $recordid;
        $newrother->response = $result;
        migrate2utf8_update_record('questionnaire_response_other',$newrother);
    }
/// And finally, just return the converted field
    return $result;
}
?>