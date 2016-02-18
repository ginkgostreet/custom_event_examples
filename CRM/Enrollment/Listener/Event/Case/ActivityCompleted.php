<?php

class CRM_Enrollment_Listener_Event_Case_ActivityCompleted extends CRM_Enrollment_Listener_Event_Case {

  public function raiseConditionsAreMet() {
    return (property_exists($this->dao, 'case_id')
      && $this->dao->status_id == CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Completed'));
  }
}
