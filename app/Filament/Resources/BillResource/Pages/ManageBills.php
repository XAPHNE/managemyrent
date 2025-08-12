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
use Illuminate\Support\Facades\DB;
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
                    // Load tenancy with last bill + property
                    $tenancy = Tenancy::with([
                        'property',
                        'bills' => fn ($q) => $q->latest('bill_date'),
                    ])->findOrFail($data['tenancy_id']);

                    $lastBill      = $tenancy->bills->first();
                    $previousUnits = (int) ($lastBill->present_units ?? $tenancy->initial_units);

                    // Validate present >= previous
                    if ((int) $data['present_units'] < $previousUnits) {
                        throw ValidationException::withMessages([
                            'present_units' => 'Present units cannot be less than previous units.',
                        ]);
                    }

                    // Determine period/bill date
                    $base = $lastBill?->bill_date
                        ? Carbon::parse($lastBill->bill_date)->addMonthNoOverflow()
                        : Carbon::parse($tenancy->start_date ?? now());
                    $data['period_label'] = $data['period_label'] ?: $base->isoFormat('MMM YYYY');
                    $data['bill_date']    = $data['bill_date'] ?? $base->copy()->startOfMonth()->toDateString();

                    // Prevent duplicates (one bill per tenancy per period)
                    if (Bill::where('tenancy_id', $tenancy->id)
                        ->where('period_label', $data['period_label'])
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'period_label' => "A bill for {$data['period_label']} already exists for this tenancy.",
                        ]);
                    }

                    // Charges from Property (you said you use `electricity_rate`)
                    $rate  = (float) ($tenancy->property->electricity_rate ?? 0);
                    $water = (float) ($tenancy->property->water_charge ?? 0);
                    $rent  = (float) ($tenancy->property->monthly_rent ?? 0);
                    $other = (float) ($data['other_amount'] ?? 0);

                    // Derived values
                    $units = max(0, (int) $data['present_units'] - $previousUnits);
                    $elec  = round($units * $rate, 2);
                    $total = round($rent + $water + $elec + $other, 2);

                    // Override incoming data with authoritative values
                    $data['previous_units']     = $previousUnits;
                    $data['units_consumed']     = $units;
                    $data['electricity_amount'] = $elec;
                    $data['water_amount']       = $water;
                    $data['rent_amount']        = $rent;
                    $data['total_amount']       = $total;

                    return $data;
                })
                ->after(function (Bill $record) {
                    // Wrap side-effects in a transaction (update + mail enqueue)
                    DB::transaction(function () use ($record) {
                        $tenancy = $record->tenancy()->with(['property.landlord', 'tenant'])->first();

                        // Generate UPI QR if not set
                        if (empty($record->upi_qr_path)) {
                            $qr = app(UpiQrService::class)->generate(
                                vpa:   $tenancy->property->upi_vpa,
                                name:  $tenancy->property->landlord->name,
                                amount:(float) $record->total_amount,
                                note:  "{$record->period_label} - {$tenancy->property->name}",
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
                    });

                    Notification::make()
                        ->title('Bill generated, QR created & email sent')
                        ->success()
                        ->send();
                }),
        ];
    }
}
