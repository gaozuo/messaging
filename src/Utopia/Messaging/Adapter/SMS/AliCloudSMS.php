<?php

namespace Utopia\Messaging\Adapter\SMS;

use Utopia\Messaging\Adapter\SMS as SMSAdapter;
use Utopia\Messaging\Messages\SMS as SMSMessage;
use Utopia\Messaging\Response;

class AliCloudSMS extends SMSAdapter
{
    protected const NAME = 'AliCloudSMS';

    /**
     * @param string $accessKeyId AliCloud AccessKey ID
     * @param string $accessKeySecret AliCloud AccessKey Secret
     * @param string $templateCode Default SMS template code
     * @param string $apiEndpoint API endpoint for the AliCloud SMS proxy service
     * @param string $fallbackParamKey Parameter key for non-JSON content
     */
    public function __construct(
        private string $accessKeyId,
        private string $accessKeySecret,
        private string $templateCode = 'SMS_325980128',
        private string $apiEndpoint = 'https://alisms.functions.cloud.vkwave.com/',
        private string $fallbackParamKey = 'code'
    ) {
    }

    public function getName(): string
    {
        return static::NAME;
    }

    public function getMaxMessagesPerRequest(): int
    {
        return 100;
    }

    /**
     * {@inheritdoc}
     */
    protected function process(SMSMessage $message): array
    {
        $response = new Response($this->getType());

        // Parse template parameters and code from message content
        $templateParam = [];
        $templateCode = $this->templateCode;

        $content = $message->getContent();
        $decodedContent = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
            // Content is valid JSON
            $templateParam = $decodedContent;

            // Extract templateCode if present
            if (isset($templateParam['templateCode'])) {
                $templateCode = $templateParam['templateCode'];
                unset($templateParam['templateCode']);
            }
        } else {
            // Content is not JSON, use fallback parameter key
            $templateParam[$this->fallbackParamKey] = $content;
        }

        // Use sign name from message
        $signName = $message->getFrom();

        $smsList = [];
        foreach ($message->getTo() as $phoneNumber) {
            $smsList[] = [
                'phoneNumber' => $phoneNumber,
                'signName' => $signName,
                'templateParam' => $templateParam
            ];
        }

        $requestBody = [
            'accessKeyId' => $this->accessKeyId,
            'accessKeySecret' => $this->accessKeySecret,
            'templateCode' => $templateCode,
            'smsList' => $smsList,
        ];

        $result = $this->request(
            method: 'POST',
            url: $this->apiEndpoint,
            headers: [
                'Content-Type: application/json',
            ],
            body: $requestBody,
        );

        if ($result['statusCode'] >= 200 && $result['statusCode'] < 300) {
            $responseBody = $result['response'];

            if (isset($responseBody['Code']) && $responseBody['Code'] === 'OK') {

                $response->setDeliveredTo(count($message->getTo()));
                foreach ($message->getTo() as $to) {
                    $response->addResult($to);
                }
            } else {

                $errorMessage = $responseBody['Message'] ?? 'Unknown error';
                foreach ($message->getTo() as $to) {
                    $response->addResult($to, $errorMessage);
                }
            }
        } else {

            $errorMessage = $result['response']['Message'] ?? 'HTTP error: ' . $result['statusCode'];
            foreach ($message->getTo() as $to) {
                $response->addResult($to, $errorMessage);
            }
        }

        return $response->toArray();
    }
}