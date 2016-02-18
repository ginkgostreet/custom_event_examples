<?php

abstract class CRM_Enrollment_Listener_Event_Case extends CRM_Listener_Event {

  const ENROLLMENT_CASE_TYPE = 'WorkplacePolicyEnrollment';

  protected $_op;

  /**
   * It is common pattern that an activity should be completed once a mailing has
   * been sent; if this property is set the listener will update the referenced
   * activity accordingly.
   *
   * @var string ID of activity that should be completed after mailing
   */
  public $activityToComplete;

  /**
   * DAO just written to the database
   *
   * @var CRM_Core_DAO
   */
  public $dao;

  /**
   * @var array api.Case.getsingle result
   */
  public $case = array();

  /**
   * The case type ID for workplace policy enrollment cases
   *
   * @var int-like string
   */
  private static $caseTypeID;

  public function __construct(CRM_Core_DAO $dao, $op) {
    $this->_op = $op;
    $this->dao = $dao;

    if (!isset(self::$caseTypeID)) {
      $api = civicrm_api3('OptionGroup', 'getsingle', array(
        'name' => 'case_type',
        'api.OptionValue.getvalue' => array(
          'name' => self::ENROLLMENT_CASE_TYPE,
          'return' => 'value',
        ),
      ));
      self::$caseTypeID = $api['api.OptionValue.getvalue'];
    }

    if (property_exists($this->dao, 'case_id') && isset($this->dao->case_id)) {
      $this->case = civicrm_api3('Case', 'getsingle', array(
        'id' => $this->dao->case_id)
      );
    }
  }

  public function raiseConditionsAreMet() {
    return ($this->_op === 'create'
      && CRM_Utils_Array::value('case_type_id', $this->case) == self::$caseTypeID);
  }

  /**
   * A conditional raise if raiseConditionsAreMet()
   */
  public function raise() {
    if ($this->raiseConditionsAreMet()) {
      parent::raise();
    }
  }

  /**
   * @param string $activityTypeMachineName (e.g., 'Email Welcome')
   * @param string $activityStatus (e.g., 'Completed')
   * @return array with keys 'passes' and 'activityID'
   *         - 'passes' is set to TRUE if an activity of given type exists in the case and has
   *           the provided status; FALSE otherwise.
   *         - 'activityID' is set to the ID of the activity that was evaluated; NULL if the case
   *           doesn't have an activity of type $activityTypeMachineName.
   * @throws Exception if no activity of the given type exists in the case
   */
  protected function checkActivity($activityTypeMachineName, $activityStatus) {
    $activityTypeID = CRM_Core_Pseudoconstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', $activityTypeMachineName);
    $statusID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', $activityStatus);
    $result = array(
      'passes' => FALSE,
      'activityID' => NULL,
    );

    if (array_key_exists('activities', $this->case)) {
      foreach($this->case['activities'] as $activityID) {
        $api = civicrm_api3('Activity', 'getsingle', array(
          'id' => $activityID,
        ));

        if ($api['activity_type_id'] == $activityTypeID) {
          $result['passes'] = ($api['status_id'] == $statusID);
          $result['activityID'] = $activityID;
          // assumes there is only one activity of this type in the case, which is fine for now
          break;
        }
      }
    }

    return $result;
  }
}