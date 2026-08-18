<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'network_type',
        'sla_hours',
        'status',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }
}
