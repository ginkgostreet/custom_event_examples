<?php

class CRM_Enrollment_Listener_Listener_SendCaseEmail extends CRM_Listener {

  /**
   * The label of the role for the account manager
   */
  const ACCT_MGR = 'Account Manager';

  /**
   * The label of the role for the primary contact for the client
   */
  const CLIENT_CONTACT = 'DFWPS Primary Contact';

  /**
   * A matrix of event class to message template ID
   *
   * @var array
   */
  private $lookup_eventClass_msgTemplate = array(
    'CRM_Enrollment_Listener_Event_Case_ChecklistEmailActivityCreated' => 146,
    'CRM_Enrollment_Listener_Event_Case_RelationshipCreated' => 145,
  );

  /**
   * A matrix of case role label to contact data (array keyed by contact_id,
   * sort_name, display_name, email)
   *
   * @var array
   */
  private $lookup_role_contact = array();

  /**
   * Roles that must be filled in order for the welcome email to go out
   *
   * @var array
   */
  private $rolesRequiringAssignments = array(
    self::ACCT_MGR,
    self::CLIENT_CONTACT,
  );

  /**
   * The event handler
   *
   * @param \CRM_Listener_Event $event
   */
  function handle(\CRM_Listener_Event $event) {
    $this->setRoleContacts($event->case['contacts']);

    if (empty($this->getEmptyRequiredRoles())) {
      $params = array(
        'contactId' => $this->lookup_role_contact[self::CLIENT_CONTACT]['contact_id'], // standard CiviCRM tokens apply to this contact (i.e., the recipient)
        'from' => $this->lookup_role_contact[self::ACCT_MGR]['display_name'] . ' <' . $this->lookup_role_contact[self::ACCT_MGR]['email'] . '>',
        'messageTemplateID' => $this->getMessageTemplateFromEvent($event),
        'toEmail' => $this->lookup_role_contact[self::CLIENT_CONTACT]['email'],
        'toName' => $this->lookup_role_contact[self::CLIENT_CONTACT]['display_name'],
        'tplParams' => array(
          'acctMgrDisplayName' => $this->lookup_role_contact[self::ACCT_MGR]['display_name'],
          'acctMgrPhone' => $this->getPhone(self::ACCT_MGR),
          'acctMgrEmail' => $this->lookup_role_contact[self::ACCT_MGR]['email'],
        ),
      );
      CRM_Core_BAO_MessageTemplate::sendTemplate($params);

      if (isset($event->activityToComplete)) {
        civicrm_api3('Activity', 'create', array(
          'id' => $event->activityToComplete,
          'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Completed')
        ));
      }
    }
  }

  /**
   * Sets $this->lookup_role_contact
   *
   * @param array $caseContacts An array of arrays. The inner inner array is
   *        expected to have keys 'role' and 'contact_id'
   */
  private function setRoleContacts(array $caseContacts) {
    foreach ($caseContacts as $contact) {
      $this->lookup_role_contact[$contact['role']] = array(
        'contact_id' => $contact['contact_id'],
        'display_name' => $contact['display_name'],
        'email' => $contact['email'],
        'sort_name' => $contact['sort_name'],
      );
    }
  }

  /**
   * Returns an array of unmet roles, empty if all required roles are filled
   *
   * @return array
   */
  private function getEmptyRequiredRoles() {
    $unmet = array();
    foreach ($this->rolesRequiringAssignments as $role) {
      if (!CRM_Utils_Array::value($role, $this->lookup_role_contact)) {
        $unmet[] = $role;
      }
    }
    return $unmet;
  }

  /**
   * Given a role, gets and formats the phone number for the contact in that role
   *
   * @param string $role The role of the case contact whose phone number is to be retrieve
   * @return mixed string on success, NULL on fail
   */
  private function getPhone($role) {
    $result = NULL;

    if (array_key_exists($role, $this->lookup_role_contact)) {
      $apiPhoneType = civicrm_api3('OptionGroup', 'getsingle', array(
        'name' => 'phone_type',
        'api.OptionValue.getvalue' => array(
          'name' => 'Phone',
          'return' => 'value',
        )
      ));

      $apiPhone = civicrm_api3('Phone', 'get', array(
        'contact_id' => $this->lookup_role_contact[$role]['contact_id'],
        'is_primary' => 1,
        'phone_type_id' => $apiPhoneType['api.OptionValue.getvalue'],
      ));

      // if only one result came back, its ID will be on the API return array
      $phoneID = CRM_Utils_Array::value('id', $apiPhone);

      // if more than one primary phone is found (this is rare) give preference to the
      // last encountered work phone
      if ($apiPhone['count'] > 1) {
        $apiPhoneLoc = civicrm_api3('OptionGroup', 'getsingle', array(
          'name' => 'location_type',
          'api.OptionValue.getvalue' => array(
            'name' => 'Work',
            'return' => 'value',
          )
        ));

        foreach ($apiPhone['values'] as $key => $data) {
          if ($data['location_type_id'] == $apiPhoneLoc['api.OptionValue.getvalue']) {
            $phoneID = $key;
          }
        }

        // if none of the primary phones are work phones, just use the last one
        if (is_null($phoneID)) {
          $phoneID = $key;
        }
      }

      $result = CRM_Utils_Array::value('phone', $apiPhone['values'][$phoneID]);

      // don't bother with the extension if there's no phone
      if (!is_null($result)) {
        $ext = CRM_Utils_Array::value('phone_ext', $apiPhone['values'][$phoneID]);
        if (!is_null($ext)) {
          $result .= " x {$ext}";
        }
      }
    }

    return $result;
  }

  /**
   * Looks up the message template ID for the given event
   *
   * @param \CRM_Enrollment_Listener_Event_Case $event
   * @return int Message template ID
   * @throws Exception for unknown event classes
   */
  private function getMessageTemplateFromEvent(\CRM_Enrollment_Listener_Event_Case $event) {
    $eventClass = get_class($event);
    $msgTemplateID = CRM_Utils_Array::value($eventClass, $this->lookup_eventClass_msgTemplate);
    if (is_null($msgTemplateID)) {
      throw new Exception("No message template found for event {$eventClass}");
    }
    return $msgTemplateID;
  }
}