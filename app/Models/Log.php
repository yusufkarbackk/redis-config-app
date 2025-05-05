<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;
    protected $fillable = [
        'source',
        'destination',
        'data_sent',
        'data_received',
        'sent_at',
        'received_at '
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

}
