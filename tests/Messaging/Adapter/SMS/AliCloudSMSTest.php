<?php

namespace Utopia\Tests\Adapter\SMS;

use Utopia\Messaging\Adapter\SMS\AliCloudSMS;
use Utopia\Messaging\Messages\SMS;
use Utopia\Tests\Adapter\Base;

class AliCloudSMSTest extends Base
{
    /**
     * Test AliCloud SMS with default placeholder
     */
    public function testSendSMSWithDefaultPlaceholder(): void
    {
        $sender = new AliCloudSMS(
            accessKeyId: getenv('ALICLOUD_ACCESS_KEY_ID'),
            accessKeySecret: getenv('ALICLOUD_ACCESS_KEY_SECRET'),
            signName: getenv('ALICLOUD_SMS_SIGN_NAME'),
            templateCode: getenv('ALICLOUD_SMS_TEMPLATE_CODE'),
            templateParam: '{"code":"{{token}}"}'
        );

        $message = new SMS(
            to: [getenv('ALICLOUD_SMS_TO')],
            content: '778890', // Verification code
        );

        $response = $sender->send($message);

        $this->assertResponse($response);
    }

    /**
     * Test AliCloud SMS with phone number containing country code
     */
    public function testSendSMSWithCountryCode(): void
    {
        $sender = new AliCloudSMS(
            accessKeyId: getenv('ALICLOUD_ACCESS_KEY_ID'),
            accessKeySecret: getenv('ALICLOUD_ACCESS_KEY_SECRET'),
            signName: getenv('ALICLOUD_SMS_SIGN_NAME'),
            templateCode: getenv('ALICLOUD_SMS_TEMPLATE_CODE'),
            templateParam: '{"code":"{{token}}"}'
        );

        // Test with +86 prefix (China country code)
        $message = new SMS(
            to: ['+86' . getenv('ALICLOUD_SMS_TO')],
            content: '556688',
        );

        $response = $sender->send($message);

        $this->assertResponse($response);
    }

    /**
     * Test adapter properties
     */
    public function testAdapterProperties(): void
    {
        $sender = new AliCloudSMS(
            accessKeyId: 'test_key_id',
            accessKeySecret: 'test_key_secret',
            signName: 'TestSign',
            templateCode: 'SMS_123456789',
            templateParam: '{"code":"{{token}}"}'
        );

        $this->assertEquals('AliCloudSMS', $sender->getName());
        $this->assertEquals('sms', $sender->getType());
        $this->assertEquals(1, $sender->getMaxMessagesPerRequest());
    }
}
