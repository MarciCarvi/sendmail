<?php

namespace App\Services;

use App\Models\Setting;
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

class SesService
{
    private SesClient $client;

    public function __construct()
    {
        $this->client = new SesClient([
            'version'     => '2010-12-01',
            'region'      => Setting::get('ses_region', 'eu-west-1'),
            'credentials' => [
                'key'    => Setting::getEncrypted('ses_key', ''),
                'secret' => Setting::getEncrypted('ses_secret', ''),
            ],
        ]);
    }

    public function send(
        string $to,
        string $toName,
        string $subject,
        string $html,
        string $text,
        string $fromEmail,
        string $fromName,
        string $replyTo,
        string $campaignId,
        string $subscriberToken
    ): string|false {
        try {
            $result = $this->client->sendEmail([
                'Source'      => "{$fromName} <{$fromEmail}>",
                'Destination' => ['ToAddresses' => ["{$toName} <{$to}>"]],
                'Message'     => [
                    'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                    'Body'    => [
                        'Html' => ['Data' => $html, 'Charset' => 'UTF-8'],
                        'Text' => ['Data' => $text, 'Charset' => 'UTF-8'],
                    ],
                ],
                'ReplyToAddresses' => [$replyTo],
            ]);
            return $result['MessageId'];
        } catch (AwsException) {
            return false;
        }
    }

    public function verifyCredentials(): bool
    {
        try {
            $this->client->getSendQuota();
            return true;
        } catch (AwsException) {
            return false;
        }
    }

    public function getSendingQuota(): array
    {
        $result = $this->client->getSendQuota();
        return [
            'Max24HourSend'   => $result['Max24HourSend'],
            'SentLast24Hours' => $result['SentLast24Hours'],
            'MaxSendRate'     => $result['MaxSendRate'],
        ];
    }
}
