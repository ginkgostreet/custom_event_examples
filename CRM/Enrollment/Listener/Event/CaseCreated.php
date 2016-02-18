<?php

class CRM_Enrollment_Listener_Event_CaseCreated extends CRM_Listener_Event{

  private $_case;
  private $_op;
  
  function __construct(\CRM_Case_DAO_Case $case, $op) {
    $this->_case = $case;
    $this->_op = $op;
  }

  function raiseConditionsAreMet() {
    return ($this->_op == 'create');
  }

  /**
   * Conditional Raise
   */
  function raise() {
    if ($this->raiseConditionsAreMet()) {
      parent::raise();
    }
  }

  function getCase() {
    return clone $this->_case;
  }
}
