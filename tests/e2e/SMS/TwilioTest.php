<?php

namespace Tests\E2E\SMS;

use Tests\E2E\Base;
use Utopia\Messaging\Adapters\SMS\Twilio;
use Utopia\Messaging\Messages\SMS;

class TwilioTest extends Base
{
    /**
     * @throws \Exception
     */
    public function testSendSMS()
    {
        $sender = new Twilio(getenv('TWILIO_ACCOUNT_SID'), getenv('TWILIO_AUTH_TOKEN'));
        $to = [getenv('TWILIO_TO')];
        $from = getenv('TWILIO_FROM');

        $message = new SMS(
            to: $to,
            content: 'Test Content',
            from: $from
        );

        $result = \json_decode($sender->send($message), true);

        $this->assertResponse($result);

    }
}
