<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignOpen extends Model
{
    protected $table = 'sm_campaign_opens';
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'subscriber_id', 'opened_at', 'ip', 'user_agent'];

    protected $casts = ['opened_at' => 'datetime'];

    public function subscriber() { return $this->belongsTo(Subscriber::class); }
}
