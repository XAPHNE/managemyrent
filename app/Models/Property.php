<?php

namespace App\Models;

use Althinect\FilamentSpatieRolesPermissions\Concerns\HasSuperAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'landlord_id',
        'name',
        'description',
        'water_charge',
        'electricity_rate',
        'monthly_rent',
        'advance_payment',
        'security_deposit',
        'upi_vpa',
    ];

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class);
    }
}
