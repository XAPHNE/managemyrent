<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenancyResource\Pages;
use App\Filament\Resources\TenancyResource\RelationManagers;
use App\Filament\Resources\TenancyResource\RelationManagers\BillsRelationManager;
use App\Models\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TenancyResource extends Resource
{
    protected static ?string $model = Tenancy::class;

    protected static ?string $navigationGroup = 'Rent Management';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user(); // or Filament\Facades\Filament::auth()->user()

        if ($user && ! $user->hasRole('Super Admin', 'admin')) {
            $query->whereHas('property', fn ($q) => $q->where('landlord_id', $user->id));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('property_id')
                    ->relationship('property', 'name')
                    ->preload()
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->preload()
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('initial_units')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->after('start_date')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('property.name')
                    ->label('Property')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('initial_units')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
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
                Tables\Filters\Filter::make('this_month')
                    ->query(fn($q) => $q->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)),
            ])
            ->actions([
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
            'index' => Pages\ManageTenancies::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            BillsRelationManager::class,
        ];
    }
}
