<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MailList extends Model
{
    protected $table = 'sm_lists';

    protected $fillable = ['name', 'from_name', 'from_email', 'reply_to', 'double_optin', 'api_token'];

    protected static function booted(): void
    {
        static::creating(function (MailList $list) {
            if (empty($list->api_token)) {
                $list->api_token = Str::random(32);
            }
        });
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class, 'list_id');
    }

    public function subscribersCount(): int
    {
        return $this->subscribers()->where('status', 'subscribed')->count();
    }
}
