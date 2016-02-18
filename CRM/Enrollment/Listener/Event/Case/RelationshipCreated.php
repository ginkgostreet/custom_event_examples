<?php

class CRM_Enrollment_Listener_Event_Case_RelationshipCreated extends CRM_Enrollment_Listener_Event_Case {

  public function raiseConditionsAreMet() {
    $check = $this->checkActivity('Email Welcome', 'Completed');
    $this->activityToComplete = $check['activityID'];
    return (parent::raiseConditionsAreMet() && $check['passes'] === FALSE);
  }
}