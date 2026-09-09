<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TicketCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'infrastructure_type',
        'network_type', // backward compatibility alias
        'status',
    ];

    public function getNetworkTypeAttribute()
    {
        return $this->attributes['infrastructure_type'] ?? null;
    }

    public function setNetworkTypeAttribute($value)
    {
        $this->attributes['infrastructure_type'] = $value;
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(TicketResolution::class, 'category_id');
    }

    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, TicketResolution::class, 'category_id', 'id', 'id', 'ticket_id');
    }
}
