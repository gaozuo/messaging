<?php

namespace Utopia\Messaging\Adapter\Push;

use Utopia\Messaging\Adapter\Push as PushAdapter;
use Utopia\Messaging\Helpers\JWT;
use Utopia\Messaging\Messages\Push as PushMessage;
use Utopia\Messaging\Priority;
use Utopia\Messaging\Response;

class FCM extends PushAdapter
{
    protected const NAME = 'FCM';
    protected const DEFAULT_EXPIRY_SECONDS = 3600;    // 1 hour
    protected const DEFAULT_SKEW_SECONDS = 60;        // 1 minute
    protected const GOOGLE_TOKEN_URL = 'https://www.googleapis.com/oauth2/v4/token';

    /**
     * @param string $serviceAccountJSON Service account JSON file contents
     */
    public function __construct(
        private string $serviceAccountJSON,
    ) {
    }

    /**
     * Get adapter name.
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * Get max messages per request.
     */
    public function getMaxMessagesPerRequest(): int
    {
        return 5000;
    }

    /**
     * {@inheritdoc}
     */
    protected function process(PushMessage $message): array
    {
        $credentials = \json_decode($this->serviceAccountJSON, true);

        $now = \time();

        $signingKey = $credentials['private_key'];
        $signingAlgorithm = 'RS256';

        $payload = [
            'iss' => $credentials['client_email'],
            'exp' => $now + self::DEFAULT_EXPIRY_SECONDS,
            'iat' => $now - self::DEFAULT_SKEW_SECONDS,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => self::GOOGLE_TOKEN_URL,
        ];

        $jwt = JWT::encode(
            $payload,
            $signingKey,
            $signingAlgorithm,
        );

        $signingKey = null;
        $payload = null;

        $token = $this->request(
            method: 'POST',
            url: self::GOOGLE_TOKEN_URL,
            headers: [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            body: [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]
        );

        $accessToken = $token['response']['access_token'];

        $shared = [];

        if (!\is_null($message->getTitle())) {
            $shared['message']['notification']['title'] = $message->getTitle();
        }
        if (!\is_null($message->getBody())) {
            $shared['message']['notification']['body'] = $message->getBody();
        }
        if (!\is_null($message->getData())) {
            $shared['message']['data'] = $message->getData();
        }
        if (!\is_null($message->getAction())) {
            $shared['message']['android']['notification']['click_action'] = $message->getAction();
            $shared['message']['apns']['payload']['aps']['category'] = $message->getAction();
        }
        if (!\is_null($message->getImage())) {
            $shared['message']['android']['notification']['image'] = $message->getImage();
            $shared['message']['apns']['payload']['aps']['mutable-content'] = 1;
            $shared['message']['apns']['fcm_options']['image'] = $message->getImage();
        }
        if (!\is_null($message->getCritical())) {
            $shared['message']['apns']['payload']['aps']['sound']['critical'] = 1;
        }
        if (!\is_null($message->getSound())) {
            $shared['message']['android']['notification']['sound'] = $message->getSound();

            if (!\is_null($message->getCritical())) {
                $shared['message']['apns']['payload']['aps']['sound']['name'] = $message->getSound();
            } else {
                $shared['message']['apns']['payload']['aps']['sound'] = $message->getSound();
            }
        }
        if (!\is_null($message->getIcon())) {
            $shared['message']['android']['notification']['icon'] = $message->getIcon();
        }
        if (!\is_null($message->getColor())) {
            $shared['message']['android']['notification']['color'] = $message->getColor();
        }
        if (!\is_null($message->getTag())) {
            $shared['message']['android']['notification']['tag'] = $message->getTag();
        }
        if (!\is_null($message->getBadge())) {
            $shared['message']['apns']['payload']['aps']['badge'] = $message->getBadge();
        }
        if (!\is_null($message->getContentAvailable())) {
            $shared['message']['apns']['payload']['aps']['content-available'] = (int)$message->getContentAvailable();
        }
        if (!\is_null($message->getPriority())) {
            $shared['message']['android']['priority'] = match ($message->getPriority()) {
                Priority::HIGH => 'high',
                Priority::NORMAL => 'normal'
            };
            $shared['message']['apns']['headers']['apns-priority'] = match ($message->getPriority()) {
                Priority::HIGH => '10',
                Priority::NORMAL => '5',
            };
        }

        $bodies = [];

        foreach ($message->getTo() as $to) {
            $body = $shared;
            $body['message']['token'] = $to;
            $bodies[] = $body;
        }

        $results = $this->requestMulti(
            method: 'POST',
            urls: ["https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send"],
            headers: [
                'Content-Type: application/json',
                "Authorization: Bearer {$accessToken}",
            ],
            bodies: $bodies
        );

        $response = new Response($this->getType());

        foreach ($results as $result) {
            if ($result['statusCode'] === 200) {
                $response->incrementDeliveredTo();
                $response->addResult($message->getTo()[$result['index']]);
            } else {
                $error =
                    ($result['response']['error']['status'] ?? null) === 'UNREGISTERED'
                    || ($result['response']['error']['status'] ?? null) === 'NOT_FOUND'
                        ? $this->getExpiredErrorMessage()
                        : $result['response']['error']['message'] ?? 'Unknown error';

                $response->addResult($message->getTo()[$result['index']], $error);
            }
        }

        return $response->toArray();
    }
}
