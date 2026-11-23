<?php

namespace App\Filament\Resources\PayoutResource\Pages;

use App\Filament\Resources\PayoutResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayout extends ViewRecord
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PayoutResource::approveAction(),
            PayoutResource::releaseAction(),
            PayoutResource::failAction(),
            PayoutResource::retryAction(),
        ];
    }
}
