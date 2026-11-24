<?php

namespace App\Filament\Resources\BookRequestResource\Pages;

use App\Filament\Resources\BookRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditBookRequest extends EditRecord
{
    protected static string $resource = BookRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['reviewed_by'] = auth()->id();

        return $data;
    }
}
