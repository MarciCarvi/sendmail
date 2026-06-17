<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignClick extends Model
{
    protected $table = 'sm_campaign_clicks';
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'subscriber_id', 'original_url', 'clicked_at', 'ip'];

    protected $casts = ['clicked_at' => 'datetime'];

    public function subscriber() { return $this->belongsTo(Subscriber::class); }
}
