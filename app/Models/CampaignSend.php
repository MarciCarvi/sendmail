<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignSend extends Model
{
    protected $table = 'sm_campaign_sends';

    protected $fillable = ['campaign_id', 'subscriber_id', 'status', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }
}
