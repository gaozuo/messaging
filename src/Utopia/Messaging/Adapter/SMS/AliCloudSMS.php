<?php

namespace Utopia\Messaging\Adapter\SMS;

use Utopia\Messaging\Adapter\SMS as SMSAdapter;
use Utopia\Messaging\Messages\SMS as SMSMessage;
use Utopia\Messaging\Response;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Dara\Models\RuntimeOptions;
use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\Credentials\Credential\Config as CredentialConfig;

class AliCloudSMS extends SMSAdapter
{
    protected const NAME = 'AliCloudSMS';

    /**
     * @param string $accessKeyId AliCloud Access Key ID
     * @param string $accessKeySecret AliCloud Access Key Secret
     * @param string $signName SMS signature name
     * @param string $templateCode SMS template code
     * @param string $templateParam Template parameter JSON string with placeholders (e.g., '{"code":"{{token}}"}')
     * @param string $placeholderParam Placeholder to replace in templateParam (default: '{{token}}')
     */
    public function __construct(
        private string $accessKeyId,
        private string $accessKeySecret,
        private string $signName,
        private string $templateCode,
        private string $templateParam,
        private string $placeholderParam = '{{token}}'
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
     * Create AliCloud SMS client
     */
    private function createClient(): Dysmsapi
    {
        $credConfig = new CredentialConfig([
            'type' => 'access_key',
            'accessKeyId' => $this->accessKeyId,
            'accessKeySecret' => $this->accessKeySecret,
        ]);

        $credential = new Credential($credConfig);
        $config = new Config([
            'credential' => $credential
        ]);
        $config->endpoint = 'dysmsapi.aliyuncs.com';

        return new Dysmsapi($config);
    }

    /**
     * {@inheritdoc}
     */
    protected function process(SMSMessage $message): array
    {
        $response = new Response($this->getType());

        $recipient = $message->getTo()[0];
        $phoneNumber = $recipient; // Support phone numbers with country code and '+'
        $content = $message->getContent();

        // Replace placeholder in template parameter with actual content
        $templateParam = str_replace($this->placeholderParam, $content, $this->templateParam);

        try {
            $client = $this->createClient();

            $sendSmsRequest = new SendSmsRequest([
                'signName' => $this->signName,
                'templateCode' => $this->templateCode,
                'phoneNumbers' => $phoneNumber,
                'templateParam' => $templateParam
            ]);

            $runtime = new RuntimeOptions();
            $result = $client->sendSmsWithOptions($sendSmsRequest, $runtime);

            if ($result->statusCode >= 200 && $result->statusCode < 300) {
                $responseBody = $result->body;
                if ($responseBody->code === 'OK') {
                    $response->setDeliveredTo(1);
                    $response->addResult($recipient);
                } else {
                    $errorMessage = $responseBody->message ?: 'SMS send failed with code: ' . $responseBody->code;
                    $response->addResult($recipient, $errorMessage);
                }
            } else {
                $response->addResult($recipient, 'HTTP status code: ' . $result->statusCode);
            }
        } catch (TeaError $error) {
            $errorMessage = $error->message ?? 'Unknown error occurred';
            $response->addResult($recipient, $errorMessage);
        } catch (\Exception $error) {
            $response->addResult($recipient, $error->getMessage());
        }

        return $response->toArray();
    }
}
