<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillResource\Pages;
use App\Filament\Resources\BillResource\RelationManagers;
use App\Filament\Resources\BillResource\RelationManagers\ItemsRelationManager;
use App\Models\Bill;
use App\Models\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static ?string $navigationGroup = 'Rent Management';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = Auth::user();

        // Super Admin (admin guard) sees all
        if ($user && $user->hasRole('Super Admin', 'admin')) {
            return $query;
        }

        // Others (Manager / Landlord) – only bills of properties they own
        return $query->whereHas('tenancy.property', fn ($q) => $q->where('landlord_id', $user?->id));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('tenancy_id')
                    ->label('Tenancy')
                    ->relationship('tenancy', 'id')   // must point to a REAL column
                    ->preload()
                    ->searchable()
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn (Tenancy $t) =>
                        "{$t->tenant->name} ({$t->property->name})"
                    ),
                Forms\Components\TextInput::make('period_label')
                    ->required()
                    ->maxLength(50),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('previous_units')->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('present_units')->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('units_consumed')->numeric()->minValue(0)->required(),
                ]),
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('electricity_amount')->numeric()->required()->label('Electricity (₹)'),
                    Forms\Components\TextInput::make('water_amount')->numeric()->required()->label('Water (₹)'),
                    Forms\Components\TextInput::make('rent_amount')->numeric()->required()->label('Rent (₹)'),
                    Forms\Components\TextInput::make('total_amount')->numeric()->required()->label('Total (₹)'),
                ]),
                Forms\Components\TextInput::make('other_amount')
                    ->label('Other (₹)')
                    ->numeric()
                    ->default(0),
                Forms\Components\DatePicker::make('bill_date')
                    ->required(),
                Forms\Components\TextInput::make('payment_ref')->maxLength(100)->label('Payment Ref')->columnSpanFull(),

                Forms\Components\FileUpload::make('upi_qr_path')
                    ->disk('public')
                    ->directory('qrs')
                    ->image()
                    ->label('UPI QR (PNG)'),

                Forms\Components\TextInput::make('upi_intent')
                    ->label('UPI Intent URL')
                    ->maxLength(2048)
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('paid_at')->label('Paid At'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenancy_id')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bill_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('period_label')
                    ->label('Period')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tenancy.tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('previous_units')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('present_units')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('units_consumed')
                    ->label('Units')
                    ->sortable(),
                Tables\Columns\TextColumn::make('electricity_amount')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('water_amount')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rent_amount')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('other_amount')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Paid' : 'Unpaid')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_paid')->label('Paid')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('paid_at'),
                        false: fn (Builder $q) => $q->whereNull('paid_at'),
                        blank: fn (Builder $q) => $q, // no filtering
                    ),

                Tables\Filters\SelectFilter::make('property')
                    ->label('Property')
                    ->relationship('tenancy.property', 'name'),

                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Tenant')
                    ->relationship('tenancy.tenant', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark Paid')->icon('heroicon-o-banknotes')
                    ->visible(fn (\App\Models\Bill $record) => is_null($record->paid_at))
                    ->form([
                        Forms\Components\TextInput::make('payment_ref')
                            ->label('Payment Ref')->maxLength(100),
                    ])
                    ->action(fn (\App\Models\Bill $record, array $data)
                        => $record->update([
                            'paid_at' => now('Asia/Kolkata'),
                            'payment_ref' => $data['payment_ref'] ?? null,
                        ])
                    ),

                Tables\Actions\Action::make('resendEmail')
                    ->label('Resend Email')->icon('heroicon-o-paper-airplane')
                    ->action(function (\App\Models\Bill $record) {
                        $tenancy = $record->tenancy()->with('tenant')->first();
                        $qrUrl = $record->upi_qr_path
                            ? config('filesystems.disks.public.url').'/'.$record->upi_qr_path
                            : null;

                        Mail::to($tenancy->tenant->email)->queue(new \App\Mail\TenantBillMail(
                            tenantName: $tenancy->tenant->name,
                            totalAmount: (float) $record->total_amount,
                            qrCodeUrl: $qrUrl,
                        ));
                        \Filament\Notifications\Notification::make()->title('Email sent')->success()->send();
                    }),

                Tables\Actions\Action::make('viewQr')
                    ->label('View QR')->icon('heroicon-o-qr-code')
                    ->url(fn ($record) => $record->upi_qr_path ? config('filesystems.disks.public.url').'/'.$record->upi_qr_path : null, true)
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBills::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }
}
