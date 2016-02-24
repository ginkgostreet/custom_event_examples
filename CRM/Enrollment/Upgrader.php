<?php

/**
 * Collection of upgrade steps
 */
class CRM_Enrollment_Upgrader extends CRM_Enrollment_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  private $_customGroupProperties;
  private $_customGroupNames;

  /**
   * Get or declare psuedo-constants for Custom Group machine names
   *
   * @return string
   */
  public function getCustomGroupNames() {
    if (!is_null($this->_customGroupNames)) {
      return $this->_customGroupNames;
    }

    $this->_customGroupNames = array(
      'REASON_FOR_POLICY' => 'reason_for_policy',
      'WHO_COVERED' => 'who_covered',
      'WHEN_APPLY' => 'when_apply',
      'EMPLOYEE_TESTING' => 'employee_testing',
      'TEST_PROTOCOL' => 'test_protocol',
      'DISCIPLINARY_ACT' => 'disciplinary_action',
      'EMP_RESOURCES' => 'employee_resources',
      'PARTICIPATION' => 'participation',
      'WORK_ASSESSMENT' => 'workplace_assessment',
    );

    return $this->_customGroupNames;
  }

  /**
   * Get array of custom groups to create
   * 'name' => 'title'
   *
   * @return array
   */
  public function getCustomGroupProperties() {
    if (isset($this->_customGroupProperties)) {
      return $this->_customGroupProperties;
    }
    $names = $this->getCustomGroupNames();

    $this->_customGroupProperties = array(
      $names['REASON_FOR_POLICY'] => array(
        'title'     => 'Reason for Policy',
        'pre_help'  => '',
      ),
      $names['WHO_COVERED'] => array(
        'title' => 'Who will be covered?',
        'pre_help' => '',
      ),
      $names['WHEN_APPLY'] => array(
        'title' => 'When will the Policy apply?',
        'pre_help' => '',
      ),
      $names['EMPLOYEE_TESTING'] => array(
        'title' => 'Testing of Applicants or Employees',
        'pre_help' => '',
      ),
      $names['TEST_PROTOCOL'] => array(
        'title' => 'Testing Protocols',
        'pre_help' => '',
      ),
      $names['DISCIPLINARY_ACT'] => array(
        'title' => 'Disciplinary Actions',
        'pre_help' => 'Disciplinary action to be imposed for violations.',
      ),
      $names['EMP_RESOURCES'] => array(
        'title' => 'Additional Resources for Employees',
        'pre_help' => '',
      ),
      $names['PARTICIPATION'] => array(
        'title' => 'Reason for Participating',
        'pre_help' => 'Please indicate your company\'s reason for participating in the Program.',
      ),
      $names['WORK_ASSESSMENT'] => array(
        'title' => 'Workplace Characteristics',
        'pre_help' => 'Please note how strongly you agree or disagree with each statement about your workplace',
      ),
    );

    return $this->_customGroupProperties;
  }

  /**
   * Generate table names of the form, 'civicrm_value_<name>_<id>'
   * and assign to the table_name key of each group array.
   *
   * @param array $arrCustomGroups of group properties arrays indexed by name
   */
  public static function createCustomGroupTableNames(&$arrCustomGroups) {
    $customIDs = CRM_Utils_Ext_CustomData::getCustomDataNextIDs();
    $id = $customIDs['civicrm_custom_group'];

    foreach ($arrCustomGroups as $name => $group) {
       $group['table_name'] = "civicrm_value_{$name}_{$id}";
      $id = $id++;
    }
  }

  /**
   *
   */
  public function install() {

    $api_result = civicrm_api3('Extension', 'get', array());
    $extensions = array();
    foreach($api_result['values'] as $ext) {
      $extensions[$ext['key']] = $ext['status'];
    }
    $extUtils = 'com.ginkgostreet.utils.ext';
    if (!key_exists($extUtils, $extensions)
      || $extensions[$extUtils] !== 'installed') {
      throw new CRM_Extension_Exception_DependencyException(
        "This extension requires $extUtils is enabled.");
    }

    self::createActivityTypes(CRM_Enrollment_CaseActivityUtils::getActivityTypeDefinitions());

    $this->createChecklistCustomData();

    foreach(self::getCaseRoleTypes() as $params) {
      CRM_Utils_Ext_CustomData::safeCreateRelationshipType($params);
    }


  }

  /**
   * Example: Run an external SQL script when the module is uninstalled
   */
  public function uninstall() {
   //Disable Relationship Types
   foreach (self::getCaseRoleTypes() as $params) {
     $params['is_active'] = 0;
     CRM_Utils_Ext_CustomData::safeCreateRelationshipType($params);
   }
  }

  /**
   * Enable CiviCase on enable.
   */
  public function enable() {
    $api_result = civicrm_api3('Setting', 'getvalue', array(
      'name' => 'enable_components',
    ));

    if (!in_array('CiviCase', $api_result)) {
      $msg = 'This extension requires that CiviCase is enabled.';
      throw new CRM_Extension_Exception_DependencyException($msg);
    }

    $api_result = civicrm_api3('Extension', 'get', array());
    $extensions = array();
    foreach($api_result['values'] as $ext) {
      $extensions[$ext['key']] = $ext['status'];
    }

    $extListener = 'com.ginkgostreet.listener';
    if (!key_exists($extListener, $extensions)
      || $extensions[$extListener] != 'installed') {
      throw new CRM_Extension_Exception_DependencyException(
        "This extension requires $extListener is enabled.");
    }

    CRM_Listener_Registry::addListener(
      'CRM_Enrollment_Listener_Event_CaseCreated',
      'CRM_Enrollment_Listener_Listener_AddActivitySet' ,
      'org.ginkgostreet.example');

    CRM_Listener_Registry::addListener(
      'CRM_Enrollment_Listener_Event_Case_ActivityCompleted',
      'CRM_Enrollment_Listener_Listener_UpdateWorkflowDueDates',
      'org.ginkgostreet.example');

    CRM_Listener_Registry::addListener(
      'CRM_Enrollment_Listener_Event_Case_RelationshipCreated',
      'CRM_Enrollment_Listener_Listener_SendCaseEmail',
      'org.ginkgostreet.example');

    CRM_Listener_Registry::addListener(
      'CRM_Enrollment_Listener_Event_Case_ChecklistEmailActivityCreated',
      'CRM_Enrollment_Listener_Listener_SendCaseEmail',
      'org.ginkgostreet.example');

    $profileIds = $this->createChecklistProfiles();

    CRM_Core_BAO_Setting::setItem($profileIds, 'org.ginkgostreet.example', 'checklist_profiles');
    civicrm_api('System', 'flush', array('version' => 3));

  }

  private function createChecklistProfiles() {
    $profiles = array();
    $customGroups = array();

    foreach($this->getCustomGroupNames() as $group) {
      $apiResult = civicrm_api3('CustomGroup', 'getsingle', array(
        'name' => $group,
        'return' => array('weight', 'name', 'title', 'help_pre', 'help_post')
      ));

      if (key_exists($apiResult['weight'], $customGroups)) {
        $key = max(array_keys($customGroups));
        $customGroups[++$key] = $apiResult['name'];
      } else {
        $customGroups[$apiResult['weight']] = $apiResult;
      }
    }
    ksort($customGroups);
    foreach($customGroups as $group) {

      $params = array('version' => 3,
        'title' => $group['title'],
      );
      $apiUFGroup = civicrm_api('UFGroup', 'getsingle', $params);

      if (CRM_Utils_Array::value('is_error', $apiUFGroup)) {
        $params = array_merge($params, array(
          'help_pre' => $group['help_pre'],
          'help_post' => $group['help_post'],
        ));
        $apiUFGroup = civicrm_api('UFGroup', 'create', $params);
        $profile = $apiUFGroup['id'];
        CRM_Utils_Ext_CustomData::profileAddCustomGroupFields($profile, $group['name']);
      }

      $profiles[] = $apiUFGroup['id'];
    }

    return $profiles;
  }

  public function disable() {
    // TODO: refactor removeListeners to take an array
    CRM_Listener_Registry::removeListeners(
      'CRM_Enrollment_Listener_Event_Case_ActivityCompleted');
    CRM_Listener_Registry::removeListeners(
      'CRM_Enrollment_Listener_Event_CaseCreated');
    CRM_Listener_Registry::removeListeners(
      'CRM_Enrollment_Listener_Event_Case_RelationshipCreated');
    CRM_Listener_Registry::removeListeners(
      'CRM_Enrollment_Listener_Event_Case_ChecklistEmailActivityCreated');
  }

  private function createChecklistCustomData() {
    $smarty = CRM_Core_Smarty::singleton();
    $customIDs = CRM_Utils_Ext_CustomData::getCustomDataNextIDs();
    $smarty->assign('customIDs', $customIDs);
    $smarty->assign('customGroups', $this->getCustomGroupProperties());
    $smarty->assign('customGroupNames', $this->getCustomGroupNames());

    CRM_Utils_Ext_CustomData::executeCustomDataTemplateFile('checklist_custom_data.xml.tpl');
  }

  /**
   * Creates activity types from a list of name => labels
   *
   * @param assoc array $typesConf array( 'name' => 'label [...])
   * @return array
   * @throws CRM_Core_Exception
   */
  public static function createActivityTypes($typesConf) {
    $optionGroup = civicrm_api3('OptionGroup', 'GetValue', array(
      'name' => 'activity_type',
      'return' => 'id'
    ));

    $activityTypeIDs = array();
    foreach ($typesConf as $name => $label) {
      CRM_Utils_Ext_CustomData::
        safeCreateOptionValue($optionGroup, $name, $label);
    }

    return $activityTypeIDs;
  }

  /**
   * Create a new case type, providing name and label
   *
   * @param string $typeName
   * @param string $typeLabel
   * @return int Case Type ID from safeCreateOptionValue()
   */
  public static function createCaseType( $typeName, $typeLabel) {
    $optionGroup = civicrm_api('OptionGroup', 'Get', array(
      'version' => 3,
      'name' => 'case_type',
      'return' => 'id'
    ));

    return CRM_Utils_Ext_CustomData::safeCreateOptionValue($optionGroup, $typeName, $typeLabel);
  }

  public static function getCaseRoleTypes() {
    return array(
      array(
        'name_a_b' => 'Account Manager is',
        'label_a_b' => 'Account Manager is',
        'name_b_a' => 'Account Manager',
        'label_b_a' => 'Account Manager',
        'description' => 'Workplace Solutions Membership Account Manager',
        'contact_type_a' => 'Organization',
        'contact_type_b' => 'Individual',
        'is_reserved' => '1',
        'is_active' => '1',
      ),
      array(
        'name_a_b' => 'Primary Contact is',
        'label_a_b' => 'Primary Contact is',
        'name_b_a' => 'Primary Contact',
        'label_b_a' => 'Primary Contact',
        'description' => 'Workplace Solutions Membership Employer Point of Contact',
        'contact_type_a' => 'Organization',
        'contact_type_b' => 'Individual',
        'is_reserved'=> '1',
        'is_active' => '1',
      ),
      array(
        'name_a_b' => 'Testing Provider is',
        'label_a_b' => 'Testing Provider is',
        'name_b_a' => 'Testing Provider',
        'label_b_a' => 'Testing Provider',
        'description' => 'Workplace Solutions assigned Testing Provider',
        'contact_type_a' => 'Organization',
        'contact_type_b' => 'Organization',
        'is_reserved'=> '1',
        'is_active' => '1',
      ),
    );
  }

  /**
   * Example: Run a couple simple queries
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}
