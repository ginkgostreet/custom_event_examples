<?php

class CRM_Enrollment_Listener_Listener_UpdateWorkflowDueDates extends CRM_Listener {
  public function handle(\CRM_Listener_Event $event) {
    CRM_Enrollment_CaseActivityUtils::slipActivityDates($event->dao);
  }
}
