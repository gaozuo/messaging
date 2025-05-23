<?php

namespace Utopia\Messaging\Adapter\SMS;

use Utopia\Messaging\Adapter\SMS as SMSAdapter;
use Utopia\Messaging\Messages\SMS as SMSMessage;
use Utopia\Messaging\Response;

class AliCloud365 extends SMSAdapter
{
    protected const NAME = 'AliCloud365';

    /**
     * @param  string  $appCode AliCloud AppCode
     * @param  string  $from SMS signature, default is empty
     */
    public function __construct(
        private string $appCode,
        private ?string $from = null
    ) {
    }

    public function getName(): string
    {
        return static::NAME;
    }

    public function getMaxMessagesPerRequest(): int
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    protected function process(SMSMessage $message): array
    {
        $response = new Response($this->getType());
        
        $content = $message->getContent();
        
        // First check this->from, then fallback to message->getFrom()
        $from = $this->from;
        if (empty($from)) {
            $from = $message->getFrom();
        }
        
        // Add signature if provided and not already in the content
        if (!empty($from) && !str_contains($content, "【{$from}】")) {
            $content = "【{$from}】" . $content;
        }
        
        $recipient = $message->getTo()[0];
        $mobile = \ltrim($recipient, '+');
        
        $result = $this->request(
            method: 'POST',
            url: 'https://smsv2.market.alicloudapi.com/sms/sendv2',
            headers: [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: APPCODE ' . $this->appCode,
            ],
            body: [
                'mobile' => $mobile,
                'content' => $content,
            ],
        );

        if ($result['statusCode'] >= 200 && $result['statusCode'] < 300) {
            // Check for success code in response body
            if (isset($result['response']['error_code']) && $result['response']['error_code'] === 0) {
                $response->setDeliveredTo(1);
                $response->addResult($recipient);
            } else {
                $errorMessage = $result['response']['reason'] ?? 'API returned error code: ' . ($result['response']['error_code'] ?? 'unknown');
                $response->addResult($recipient, $errorMessage);
            }
        } else {
            $errorMessage = $result['response']['reason'] ?? 'HTTP status code: ' . $result['statusCode'];
            $response->addResult($recipient, $errorMessage);
        }

        return $response->toArray();
    }
} 