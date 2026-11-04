<?php

namespace App\Services;

use App\Models\Setting;
use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
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
        string $subscriberToken,
        ?string $configurationSet = null
    ): string|false {
        try {
            $params = [
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
            ];

            $configSet = $configurationSet ?: Setting::get('ses_configuration_set');
            if ($configSet) {
                $params['ConfigurationSetName'] = $configSet;
            }

            $result = $this->client->sendEmail($params);
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

    /**
     * Una pagina della suppression list di account di SES (API SESv2, stessa regione e credenziali dell'invio).
     * Serve il permesso IAM ses:ListSuppressedDestinations. $since limita alle voci aggiornate da quella data.
     * Lancia AwsException (permesso mancante, rete): lo gestisce SuppressionService.
     *
     * @return array{entries: array<int, array{email: string, reason: string, updated: ?\Illuminate\Support\Carbon}>, next: ?string}
     */
    public function listSuppressedDestinations(?string $nextToken = null, ?\DateTimeInterface $since = null, int $pageSize = 250): array
    {
        $client = new SesV2Client([
            'version'     => '2019-09-27',
            'region'      => Setting::get('ses_region', 'eu-west-1'),
            'credentials' => [
                'key'    => Setting::getEncrypted('ses_key', ''),
                'secret' => Setting::getEncrypted('ses_secret', ''),
            ],
            'http' => ['timeout' => 20, 'connect_timeout' => 8],
        ]);

        $params = ['PageSize' => max(1, min(1000, $pageSize))];
        if ($nextToken) {
            $params['NextToken'] = $nextToken;
        }
        if ($since) {
            $params['StartDate'] = $since;
        }

        $result = $client->listSuppressedDestinations($params);

        $entries = [];
        foreach ($result['SuppressedDestinationSummaries'] ?? [] as $row) {
            $updated = $row['LastUpdateTime'] ?? null;
            $entries[] = [
                'email'   => (string) ($row['EmailAddress'] ?? ''),
                'reason'  => strtoupper((string) ($row['Reason'] ?? '')),
                'updated' => $updated instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($updated) : null,
            ];
        }

        return ['entries' => $entries, 'next' => $result['NextToken'] ?? null];
    }
}
