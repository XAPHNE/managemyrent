<?php

namespace App\Filament\Resources\BillItemResource\Pages;

use App\Filament\Resources\BillItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBillItems extends ManageRecords
{
    protected static string $resource = BillItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
