<?php

class CRM_Enrollment_Listener_Event_Case_ChecklistEmailActivityCreated extends CRM_Enrollment_Listener_Event_Case {

  public function raiseConditionsAreMet() {
    $result = FALSE;

    // verify that the activity is being created (not updated) and that it is associated with the right type of case
    if (parent::raiseConditionsAreMet()) {
      $check = $this->checkActivity('Email Follow Up Checklist', 'Scheduled');
      $this->activityToComplete = $check['activityID'];
      $result = ($check['passes'] === TRUE && $check['activityID'] == $this->dao->id);
    }
    return $result;
  }
}