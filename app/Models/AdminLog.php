<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    protected $guarded = [];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
