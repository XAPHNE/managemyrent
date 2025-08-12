<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use App\Mail\TenantBillMail;
use App\Models\Bill;
use App\Models\Tenancy;
use App\Services\Billing\UpiQrService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ManageBills extends ManageRecords
{
    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $tenancy = Tenancy::with([
                        'property',
                        'bills' => fn ($q) => $q->latest('bill_date'),
                    ])->findOrFail($data['tenancy_id']);

                    $lastBill      = $tenancy->bills->first();
                    $previousUnits = (int) ($lastBill->present_units ?? $tenancy->initial_units);

                    if ((int) $data['present_units'] < $previousUnits) {
                        throw ValidationException::withMessages([
                            'present_units' => 'Present units cannot be less than previous units.',
                        ]);
                    }

                    // Period label & bill date (default)
                    $baseDate = $lastBill?->bill_date
                        ? Carbon::parse($lastBill->bill_date)->addMonthNoOverflow()
                        : Carbon::parse($tenancy->start_date ?? now());
                    $periodLabel = $data['period_label'] ?: $baseDate->isoFormat('MMM YYYY');
                    $billDate    = $data['bill_date'] ?? $baseDate->copy()->startOfMonth()->toDateString();

                    // Prevent duplicate bill for the same period
                    if (Bill::where('tenancy_id', $tenancy->id)
                        ->where('period_label', $periodLabel)
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'period_label' => "A bill for {$periodLabel} already exists for this tenancy.",
                        ]);
                    }

                    // Charges from property
                    $rate  = (float) ($tenancy->property->electricity_rate ?? $tenancy->property->electricity_charge ?? 0);
                    $water = (float) ($tenancy->property->water_charge ?? 0);
                    $rent  = (float) ($tenancy->property->monthly_rent ?? 0);
                    $other = (float) ($data['other_amount'] ?? 0);

                    // Derived amounts
                    $units = max(0, (int) $data['present_units'] - $previousUnits);
                    $elec  = round($units * $rate, 2);
                    $total = round($rent + $water + $elec + $other, 2);

                    // Override/augment incoming data
                    $data['previous_units']     = $previousUnits;
                    $data['units_consumed']     = $units;
                    $data['electricity_amount'] = $elec;
                    $data['water_amount']       = $water;
                    $data['rent_amount']        = $rent;
                    $data['total_amount']       = $total;
                    $data['period_label']       = $periodLabel;
                    $data['bill_date']          = $billDate;

                    return $data;
                })

                ->after(function (Bill $record) {
                    $tenancy = $record->tenancy()->with(['property.landlord', 'tenant'])->first();

                    // Generate UPI QR (skip if already set)
                    if (empty($record->upi_qr_path)) {
                        $qr = app(UpiQrService::class)->generate(
                            vpa: $tenancy->property->upi_vpa,
                            name: $tenancy->property->landlord->name,
                            amount: (float) $record->total_amount,
                            note: "{$record->period_label} - {$tenancy->property->name}",
                        );

                        $record->update([
                            'upi_qr_path' => $qr['path'] ?? null,
                            'upi_intent'  => $qr['intent'] ?? null,
                        ]);
                    }

                    // Email tenant
                    $qrUrl = $record->upi_qr_path
                        ? (config('filesystems.disks.public.url') . '/' . $record->upi_qr_path)
                        : null;

                    Mail::to($tenancy->tenant->email)->queue(new TenantBillMail(
                        tenantName:  $tenancy->tenant->name,
                        totalAmount: (float) $record->total_amount,
                        qrCodeUrl:   $qrUrl,
                    ));

                    Notification::make()
                        ->title('Bill created, QR generated & email sent')
                        ->success()
                        ->send();
                }),
        ];
    }
}
