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

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }
}
