<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SenderProfile extends Model
{
    protected $table = 'sm_sender_profiles';

    protected $fillable = ['name', 'from_name', 'from_email', 'reply_to', 'configuration_set'];

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'sender_profile_id');
    }
}
