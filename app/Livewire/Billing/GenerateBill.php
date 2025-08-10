<?php

namespace App\Livewire\Billing;

use App\Mail\TenantBillMail;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Tenancy;
use App\Services\Billing\BillCalculator;
use App\Services\Billing\UpiQrService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class GenerateBill extends Component
{
    use AuthorizesRequests;

    public int $tenancy_id;
    public float $present_units;

    public function submit(BillCalculator $calc, UpiQrService $qr)
    {
        $tenancy = Tenancy::with(['property', 'tenant', 'bills' => fn ($q) => $q->latest('bill_date')])
            ->findOrFail($this->tenancy_id);

        // Ensure the logged-in landlord owns this property
        $this->authorize('update', $tenancy->property);

        // Determine previous units (last bill present, else tenancy initial)
        $lastBill = $tenancy->bills->first();
        $previousUnits = (int) ($lastBill->present_units ?? $tenancy->initial_units);

        // Calculate totals with your service
        $total = $calc->calculate(
            waterCharge: (float) $tenancy->property->water_charge,
            electricityChargePerUnit: (float) $tenancy->property->electricity_rate,
            initialUnits: (int) $tenancy->initial_units,  // not used inside now
            previousUnits: (int) $previousUnits,
            presentUnits: (int) $this->present_units,
            monthlyRent: (float) $tenancy->property->monthly_rent,
            advancePayment: (float) $tenancy->property->advance_payment,
            securityDeposit: (float) $tenancy->property->security_deposit
        );

        // Derive breakdown to store on the bill
        $unitsConsumed = max(0, $this->present_units - $previousUnits);
        $electricityAmount = round($unitsConsumed * (float) $tenancy->property->electricity_rate, 2);
        $waterAmount = (float) $tenancy->property->water_charge;
        $rentAmount = (float) $tenancy->property->monthly_rent;

        $billDate = Carbon::now('Asia/Kolkata')->startOfMonth();
        $periodLabel = $billDate->isoFormat('MMM YYYY');

        // Create & save the Bill
        $bill = new Bill([
            'bill_date'          => $billDate->toDateString(),
            'period_label'       => $periodLabel,
            'previous_units'     => $previousUnits,
            'present_units'      => $this->present_units,
            'units_consumed'     => $unitsConsumed,
            'electricity_amount' => $electricityAmount,
            'water_amount'       => $waterAmount,
            'rent_amount'        => $rentAmount,
            'other_amount'       => 0,
            'total_amount'       => $total,
        ]);
        $tenancy->bills()->save($bill);

        // Line items (optional but nice)
        BillItem::create(['bill_id' => $bill->id, 'label' => 'Monthly Rent',   'amount' => $rentAmount]);
        BillItem::create(['bill_id' => $bill->id, 'label' => 'Water Charge',   'amount' => $waterAmount]);
        BillItem::create(['bill_id' => $bill->id, 'label' => 'Electricity',    'amount' => $electricityAmount]);

        // Generate UPI QR
        $note = "{$periodLabel} - {$tenancy->property->name}";
        $upi = $qr->generate(
            vpa: $tenancy->property->upi_vpa,
            name: $tenancy->property->landlord->name,
            amount: $total,
            note: $note
        );
        $bill->update([
            'upi_qr_path' => $upi['path'],
            'upi_intent'  => $upi['intent'],
        ]);

        // Email tenant (adjust your mailable to accept a Bill or pass required data)
        Mail::to($tenancy->tenant->email)->queue(new TenantBillMail(
            tenantName: $tenancy->tenant->name,
            totalAmount: $total,
            qrCodeUrl: config('filesystems.disks.public.url') . '/' . $upi['path']
        ));

        $this->reset('present_units');
        session()->flash('success', 'Bill generated & emailed.');
    }

    public function render()
    {
        return view('livewire.billing.generate-bill', [
            // If you want to list tenancies for the current landlord:
            'tenancies' => Tenancy::with('property','tenant')
                ->whereHas('property', fn($q) => $q->where('landlord_id', Auth::id()))
                ->orderByDesc('id')->get(),
        ]);
    }
}
