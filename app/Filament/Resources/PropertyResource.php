<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\Pages;
use App\Filament\Resources\PropertyResource\RelationManagers;
use App\Filament\Resources\PropertyResource\RelationManagers\TenanciesRelationManager;
use App\Models\Property;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationGroup = 'Rent Management';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('landlord_id')
                    ->label('Landlord')
                    ->relationship(
                        name: 'landlord',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->role('Landlord', 'web') // Spatie Roles
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('name')
                        ->label('Property Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('water_charge')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('electricity_rate')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('monthly_rent')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('advance_payment')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('security_deposit')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('upi_vpa')
                    ->label('UPI VPA')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('landlord.name')
                    ->label('Landlord')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('water_charge')
                    ->money('INR'),
                Tables\Columns\TextColumn::make('electricity_rate')
                    ->money('INR'),
                Tables\Columns\TextColumn::make('monthly_rent')
                    ->money('INR'),
                Tables\Columns\TextColumn::make('advance_payment')
                    ->money('INR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('security_deposit')
                    ->money('INR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('upi_vpa')
                    ->searchable(),
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
                //
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
            'index' => Pages\ManageProperties::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            TenanciesRelationManager::class,
        ];
    }
}
