<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'tenancy_id',
        'bill_date',
        'period_label',
        'previous_units',
        'present_units',
        'units_consumed',
        'electricity_amount',
        'water_amount',
        'rent_amount',
        'other_amount',
        'total_amount',
        'upi_qr_path',
        'upi_intent',
        'paid_at',
        'payment_ref',
    ];

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }
}
