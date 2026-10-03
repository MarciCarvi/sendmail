<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Registro degli eventi SES ricevuti dal webhook. Ogni evento viene salvato prima di essere
 * applicato alla riga di invio: se l'evento arriva prima che l'invio abbia salvato il proprio
 * message_id resta in attesa (applied_at nullo) e viene applicato appena possibile.
 */
class SesEvent extends Model
{
    public const TYPES = ['Delivery', 'Bounce', 'Complaint', 'DeliveryDelay', 'Reject'];

    public $timestamps = false;

    protected $table = 'sm_ses_events';

    protected $fillable = [
        'sns_message_id', 'message_id', 'event_type', 'source', 'topic_arn', 'duplicates', 'dup_source',
        'bounce_type', 'bounce_subtype', 'recipient', 'diagnostic', 'occurred_at', 'applied_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'applied_at'  => 'datetime',
        'created_at'  => 'datetime',
    ];

    /** Salva l'evento (idempotente rispetto all'id del messaggio SNS). */
    public static function record(?string $snsMessageId, string $messageId, string $type, array $message, ?string $topicArn = null): ?self
    {
        if (!in_array($type, self::TYPES, true)) {
            return null;
        }

        if ($snsMessageId && ($existing = static::where('sns_message_id', $snsMessageId)->first())) {
            return $existing;
        }

        // eventType = Configuration Set (event publishing); notificationType = notifiche di identità
        $source = array_key_exists('eventType', $message) ? 'eventType' : 'notificationType';

        $data = [
            'sns_message_id' => $snsMessageId,
            'message_id'     => $messageId,
            'event_type'     => $type,
            'source'         => $source,
            'topic_arn'      => $topicArn ? mb_substr($topicArn, 0, 255) : null,
        ];

        switch ($type) {
            case 'Delivery':
                $d = $message['delivery'] ?? [];
                $data['recipient']   = $d['recipients'][0] ?? null;
                $data['occurred_at'] = self::parseTime($d['timestamp'] ?? null);
                break;
            case 'Bounce':
                $b = $message['bounce'] ?? [];
                $r = $b['bouncedRecipients'][0] ?? [];
                $data['bounce_type']    = $b['bounceType'] ?? null;
                $data['bounce_subtype'] = $b['bounceSubType'] ?? null;
                $data['recipient']      = $r['emailAddress'] ?? null;
                $data['diagnostic']     = isset($r['diagnosticCode']) ? mb_substr($r['diagnosticCode'], 0, 1000) : null;
                $data['occurred_at']    = self::parseTime($b['timestamp'] ?? null);
                break;
            case 'Complaint':
                $c = $message['complaint'] ?? [];
                $data['bounce_subtype'] = $c['complaintFeedbackType'] ?? null;
                $data['recipient']      = $c['complainedRecipients'][0]['emailAddress'] ?? null;
                $data['occurred_at']    = self::parseTime($c['timestamp'] ?? null);
                break;
            case 'DeliveryDelay':
                $d = $message['deliveryDelay'] ?? [];
                $r = $d['delayedRecipients'][0] ?? [];
                $data['bounce_subtype'] = $d['delayType'] ?? null;
                $data['recipient']      = $r['emailAddress'] ?? null;
                $data['diagnostic']     = isset($r['diagnosticCode']) ? mb_substr($r['diagnosticCode'], 0, 1000) : null;
                $data['occurred_at']    = self::parseTime($d['timestamp'] ?? null);
                break;
            case 'Reject':
                $data['diagnostic']  = $message['reject']['reason'] ?? null;
                $data['occurred_at'] = self::parseTime($message['mail']['timestamp'] ?? null);
                break;
        }

        // Stesso evento già registrato (es. pubblicato due volte da percorsi diversi: Configuration Set
        // e notifiche di identità, o su due topic): non duplicarlo, ma tieni traccia del doppione.
        if (!empty($data['occurred_at'])) {
            $same = static::where('message_id', $messageId)
                ->where('event_type', $type)
                ->where('occurred_at', $data['occurred_at'])
                ->when(
                    $data['recipient'] ?? null,
                    fn($q, $r) => $q->where('recipient', $r),
                    fn($q) => $q->whereNull('recipient')
                )
                ->first();

            if ($same) {
                $same->duplicates = min(65000, $same->duplicates + 1);
                $same->dup_source = mb_substr($source . '|' . basename(str_replace(':', '/', (string) $topicArn)), 0, 120);
                $same->save();

                return $same;
            }
        }

        try {
            return static::create($data);
        } catch (QueryException $e) {
            // doppio recapito simultaneo dello stesso messaggio SNS
            if ($snsMessageId && ($existing = static::where('sns_message_id', $snsMessageId)->first())) {
                return $existing;
            }
            throw $e;
        }
    }

    /** Applica l'evento alla riga di invio con lo stesso message_id. False se l'invio non c'è ancora. */
    public function applyToSend(): bool
    {
        $send = CampaignSend::where('message_id', $this->message_id)->first();
        if (!$send) {
            return false;
        }

        $when = $this->occurred_at ?? now();

        switch ($this->event_type) {
            case 'Delivery':
                if (!$send->delivered_at) {
                    $send->delivered_at = $when;
                }
                break;
            case 'Bounce':
                // un bounce permanente prevale su uno temporaneo registrato prima
                if (!$send->bounce_type || $this->bounce_type === 'Permanent') {
                    $send->bounced_at     = $when;
                    $send->bounce_type    = $this->bounce_type;
                    $send->bounce_subtype = $this->bounce_subtype;
                }
                break;
            case 'Complaint':
                $send->complained_at = $when;
                break;
        }

        if ($send->isDirty()) {
            $send->save();
        }

        $this->applied_at = now();
        $this->save();

        return true;
    }

    /** Applica gli eventi in attesa per un messaggio appena salvato. */
    public static function applyPendingFor(string $messageId): void
    {
        static::where('message_id', $messageId)
            ->whereNull('applied_at')
            ->orderBy('occurred_at')
            ->get()
            ->each(fn(self $e) => $e->applyToSend());
    }

    /** Applica tutti gli eventi in attesa per gli invii di una campagna. Restituisce quanti. */
    public static function reconcileCampaign(int $campaignId): int
    {
        $pending = static::whereNull('applied_at')
            ->whereIn('message_id', CampaignSend::where('campaign_id', $campaignId)
                ->whereNotNull('message_id')->select('message_id'))
            ->orderBy('occurred_at')
            ->limit(10000)
            ->get();

        return $pending->filter(fn(self $e) => $e->applyToSend())->count();
    }

    /** Elimina gli eventi più vecchi del periodo di conservazione. */
    public static function prune(?int $days = null): int
    {
        $days ??= (int) config('sendmail.ses_events_retention_days', 180);

        return static::where('created_at', '<', now()->subDays($days))->delete();
    }

    private static function parseTime(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
