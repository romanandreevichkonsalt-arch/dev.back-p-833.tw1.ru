<?php

namespace tests\unit\services\lead;

use app\models\Lead;
use app\services\lead\LeadConsentAuditService;
use Codeception\Test\Unit;
use Yii;

class LeadConsentAuditServiceTest extends Unit
{
    private const SESSION_ID = 'unit-lead-consent-sess1';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->request->headers->remove('X-Session-ID');
        Yii::$app->request->setBodyParams([]);
    }

    public function testApplyStoresNameTimeAndSession(): void
    {
        Yii::$app->request->headers->set('X-Session-ID', self::SESSION_ID);

        $lead = new Lead([
            'type' => Lead::TYPE_CONTACTS,
            'name' => 'Иван Петров',
            'phone' => '+79894232000',
            'consent' => true,
        ]);

        (new LeadConsentAuditService())->apply($lead);

        verify($lead->consent_given_by_name)->equals('Иван Петров');
        verify($lead->consent_given_at)->notEmpty();
        verify($lead->consent_session_id)->equals(self::SESSION_ID);
    }

    public function testApplyIgnoresInvalidSessionId(): void
    {
        Yii::$app->request->headers->set('X-Session-ID', 'bad id!!!');

        $lead = new Lead([
            'type' => Lead::TYPE_CONTACTS,
            'name' => 'Test',
            'phone' => '+79894232000',
            'consent' => true,
        ]);

        (new LeadConsentAuditService())->apply($lead);

        verify($lead->consent_session_id)->null();
        verify($lead->consent_given_by_name)->equals('Test');
    }

    public function testApplySkipsWhenConsentFalse(): void
    {
        $lead = new Lead([
            'type' => Lead::TYPE_CONTACTS,
            'name' => 'Test',
            'phone' => '+79894232000',
            'consent' => false,
        ]);

        (new LeadConsentAuditService())->apply($lead);

        verify($lead->consent_given_at)->null();
        verify($lead->consent_given_by_name)->null();
        verify($lead->consent_session_id)->null();
    }
}
