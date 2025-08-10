<?php

namespace App\Filament\Resources\TenancyResource\RelationManagers;

use App\Mail\TenantBillMail;
use App\Models\Bill;
use App\Models\BillItem;
use App\Services\Billing\BillCalculator;
use App\Services\Billing\UpiQrService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Mail;

class BillsRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';
    protected static ?string $recordTitleAttribute = 'period_label';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('period_label')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('period_label')
            ->columns([
                Tables\Columns\TextColumn::make('period_label')->label('Period')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('bill_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('units_consumed')->label('Units')->sortable(),
                Tables\Columns\TextColumn::make('electricity_amount')->money('INR')->label('Electricity'),
                Tables\Columns\TextColumn::make('water_amount')->money('INR')->label('Water'),
                Tables\Columns\TextColumn::make('rent_amount')->money('INR')->label('Rent'),
                Tables\Columns\TextColumn::make('total_amount')->money('INR')->label('Total')->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Paid' : 'Unpaid')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label('Paid')
                    ->trueQuery(fn (Builder $q) => $q->whereNotNull('paid_at'))
                    ->falseQuery(fn (Builder $q) => $q->whereNull('paid_at')),
            ])
            ->headerActions([
                // Custom: Generate Bill
                Tables\Actions\Action::make('generateBill')
                    ->label('Generate Bill')
                    ->icon('heroicon-o-receipt-percent')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('present_units')
                            ->numeric()->minValue(0)->required()
                            ->label('Present Meter Units'),
                    ])
                    ->action(function (array $data) {
                        /** @var \App\Models\Tenancy $tenancy */
                        $tenancy = $this->ownerRecord->load(['property.landlord', 'tenant', 'bills' => fn ($q) => $q->latest('bill_date')]);

                        // Determine previous units (last bill present, else tenancy initial)
                        $lastBill = $tenancy->bills->first();
                        $previousUnits = (int) ($lastBill->present_units ?? $tenancy->initial_units);

                        // Compute totals using your existing service (Option B)
                        $calc = app(BillCalculator::class);
                        $total = $calc->calculate(
                            waterCharge: (float) $tenancy->property->water_charge,
                            electricityChargePerUnit: (float) ($tenancy->property->electricity_rate ?? $tenancy->property->electricity_charge),
                            initialUnits: (int) $tenancy->initial_units,   // kept for signature
                            previousUnits: (int) $previousUnits,
                            presentUnits: (int) $data['present_units'],
                            monthlyRent: (float) $tenancy->property->monthly_rent,
                            advancePayment: (float) ($tenancy->property->advance_payment ?? 0),
                            securityDeposit: (float) ($tenancy->property->security_deposit ?? 0)
                        );

                        $units = max(0, (int) $data['present_units'] - $previousUnits);
                        $elecAmount = round($units * (float) ($tenancy->property->electricity_rate ?? $tenancy->property->electricity_charge), 2);
                        $water = (float) $tenancy->property->water_charge;
                        $rent  = (float) $tenancy->property->monthly_rent;

                        $period = now('Asia/Kolkata')->startOfMonth()->isoFormat('MMM YYYY');

                        // Create Bill
                        /** @var \App\Models\Bill $bill */
                        $bill = $tenancy->bills()->create([
                            'bill_date'          => now('Asia/Kolkata')->startOfMonth()->toDateString(),
                            'period_label'       => $period,
                            'previous_units'     => $previousUnits,
                            'present_units'      => (int) $data['present_units'],
                            'units_consumed'     => $units,
                            'electricity_amount' => $elecAmount,
                            'water_amount'       => $water,
                            'rent_amount'        => $rent,
                            'other_amount'       => 0,
                            'total_amount'       => $total,
                        ]);

                        // Line items
                        BillItem::create(['bill_id' => $bill->id, 'label' => 'Monthly Rent', 'amount' => $rent]);
                        BillItem::create(['bill_id' => $bill->id, 'label' => 'Water Charge', 'amount' => $water]);
                        BillItem::create(['bill_id' => $bill->id, 'label' => 'Electricity',  'amount' => $elecAmount]);

                        // UPI QR
                        $qr = app(UpiQrService::class)->generate(
                            vpa: $tenancy->property->upi_vpa,
                            name: $tenancy->property->landlord->name,
                            amount: $total,
                            note: "{$period} - {$tenancy->property->name}"
                        );
                        $bill->update(['upi_qr_path' => $qr['path'], 'upi_intent' => $qr['intent']]);

                        // Email tenant
                        Mail::to($tenancy->tenant->email)->queue(new TenantBillMail(
                            tenantName: $tenancy->tenant->name,
                            totalAmount: $total,
                            qrCodeUrl: config('filesystems.disks.public.url') . '/' . $qr['path']
                        ));

                        Notification::make()->title('Bill generated & emailed')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Bill $record) => is_null($record->paid_at))
                    ->form([
                        Forms\Components\TextInput::make('payment_ref')->label('Payment Reference')->maxLength(100),
                    ])
                    ->action(function (Bill $record, array $data) {
                        $record->update([
                            'paid_at' => now('Asia/Kolkata'),
                            'payment_ref' => $data['payment_ref'] ?? null,
                        ]);
                        Notification::make()->title('Bill marked as paid')->success()->send();
                    }),

                Tables\Actions\Action::make('resendEmail')
                    ->label('Resend Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (Bill $record) {
                        $tenancy = $record->tenancy()->with(['tenant'])->first();
                        $qrUrl = $record->upi_qr_path
                            ? config('filesystems.disks.public.url') . '/' . $record->upi_qr_path
                            : null;

                        Mail::to($tenancy->tenant->email)->queue(new TenantBillMail(
                            tenantName: $tenancy->tenant->name,
                            totalAmount: (float) $record->total_amount,
                            qrCodeUrl: $qrUrl
                        ));

                        Notification::make()->title('Email sent')->success()->send();
                    }),

                Tables\Actions\Action::make('viewQr')
                    ->label('View QR')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (Bill $record) => $record->upi_qr_path ? (config('filesystems.disks.public.url') . '/' . $record->upi_qr_path) : null, true)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('regenerateQr')
                    ->label('Regenerate QR')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Bill $record) {
                        $tenancy = $record->tenancy()->with(['property.landlord'])->first();

                        $qr = app(UpiQrService::class)->generate(
                            vpa: $tenancy->property->upi_vpa,
                            name: $tenancy->property->landlord->name,
                            amount: (float) $record->total_amount,
                            note: "{$record->period_label} - {$tenancy->property->name}"
                        );

                        $record->update(['upi_qr_path' => $qr['path'], 'upi_intent' => $qr['intent']]);

                        Notification::make()->title('QR regenerated')->success()->send();
                    }),

                Tables\Actions\EditAction::make()->hidden(), // keep editing minimal; use actions above
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
