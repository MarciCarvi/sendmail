<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    protected $table = 'sm_subscribers';

    protected $fillable = [
        'list_id', 'email', 'first_name', 'last_name', 'company',
        'status', 'token', 'subscribed_at', 'unsubscribed_at',
    ];

    protected $casts = [
        'subscribed_at'   => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscriber $subscriber) {
            if (empty($subscriber->token)) {
                $subscriber->token = Str::random(64);
            }
            if (empty($subscriber->subscribed_at)) {
                $subscriber->subscribed_at = now();
            }
        });
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'list_id');
    }

    public function sends()
    {
        return $this->hasMany(CampaignSend::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
