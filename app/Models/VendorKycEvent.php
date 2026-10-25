<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorKycEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
