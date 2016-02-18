<?php

class CRM_Enrollment_Listener_Listener_AddActivitySet extends CRM_Listener {

  function handle(\CRM_Listener_Event $event) {
    /* @var $case CRM_Case_DAO_Case */
    /* @var $event CRM_Enrollment_Listener_Event_CaseCreated */
    $case = $event->getCase();

    $wpe_case_type_id = CRM_Core_OptionGroup::getValue(
      'case_type', 'WorkplacePolicyEnrollment', 'name');
    $case_type = trim($case->case_type_id, CRM_Core_DAO::VALUE_SEPARATOR);

    if ($case_type == $wpe_case_type_id) {
      CRM_Enrollment_CaseActivityUtils::addActivitySetToCase($case->id);
    }
  }
}
