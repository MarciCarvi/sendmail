<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $table = 'sm_campaigns';

    protected $fillable = [
        'subject', 'from_name', 'from_email', 'reply_to',
        'html_content', 'design_json', 'text_content', 'status', 'scheduled_at', 'sent_at', 'total_recipients',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function lists(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MailList::class, 'sm_campaign_lists', 'campaign_id', 'list_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class, 'campaign_id');
    }

    public function getListNamesAttribute(): string
    {
        return $this->lists->pluck('name')->join(', ') ?: '—';
    }

    public function isDraft(): bool    { return $this->status === 'draft'; }
    public function isSent(): bool     { return $this->status === 'sent'; }
    public function isSending(): bool  { return $this->status === 'sending'; }
    public function isPaused(): bool   { return $this->status === 'paused'; }
}
