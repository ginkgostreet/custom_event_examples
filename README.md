# custom_event_examples
com.ginkgostreet.listener example consumption
https://github.com/ginkgostreet/com.ginkgostreet.listener

Rough Guide to implement:

Define an event by extending the Event base class. Minimal definition will include implementation raise conditions.

Define listeners by extending the Listener class.

Register Listeners: See the enable() function in Upgrader.php for examples of registering listeners

Instantiate Event objects and call raise() which will confirm all conditions are met to raise.
e.g. - instantiate the event on each instance of the Case hook, and allow the event to confirm when to raise:

function _enrollment_civicrm_post_Case($op, $objectName, $objectId, &$case) {
  $event = new CRM_Enrollment_Listener_Event_CaseCreated($case, $op);
  $event->raise();
}

