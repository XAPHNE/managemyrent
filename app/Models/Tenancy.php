<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenancy extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'initial_units',
        'start_date',
        'end_date',
        'status',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}
