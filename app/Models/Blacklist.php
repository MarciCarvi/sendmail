<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $table = 'sm_blacklist';
    public $timestamps = false;

    protected $fillable = ['email', 'list_ids', 'reason', 'created_at'];

    protected $casts = [
        'list_ids'   => 'array',
        'created_at' => 'datetime',
    ];

    public static function isBlacklisted(string $email): bool
    {
        return static::where('email', strtolower($email))->exists();
    }

    public static function isDomainBlocked(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        $blocked = Setting::get('blocked_domains', '');
        $domains = array_filter(array_map('trim', explode("\n", $blocked)));
        return in_array($domain, array_map('strtolower', $domains));
    }
}
