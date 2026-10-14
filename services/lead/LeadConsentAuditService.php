<?php

namespace app\services\lead;

use app\models\Lead;
use app\services\guest\GuestSessionService;

final class LeadConsentAuditService
{
    public function apply(Lead $lead): void
    {
        if (!(bool)$lead->consent) {
            return;
        }

        $lead->consent_given_at = date('Y-m-d H:i:s');
        $lead->consent_given_by_name = trim((string)$lead->name);
        $sessionId = (new GuestSessionService())->resolveFromRequestOptional();
        $lead->consent_session_id = $sessionId !== null && $sessionId !== '' ? $sessionId : null;
    }
}
