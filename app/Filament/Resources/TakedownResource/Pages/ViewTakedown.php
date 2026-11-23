<?php

namespace App\Filament\Resources\TakedownResource\Pages;

use App\Filament\Resources\TakedownResource;
use App\Models\TakedownRequest;
use Filament\Resources\Pages\ViewRecord;

class ViewTakedown extends ViewRecord
{
    protected static string $resource = TakedownResource::class;

    protected function getHeaderActions(): array
    {
        $visible = fn (TakedownRequest $record) => in_array($record->status, ['open', 'awaiting_proof'], true);

        return [
            TakedownResource::decisionAction('take_down', 'moderation.takedowns.take_down', 'danger')
                ->visible($visible),
            TakedownResource::decisionAction('extend_proof', 'moderation.takedowns.extend_proof', 'warning')
                ->visible($visible),
            TakedownResource::decisionAction('reject', 'moderation.takedowns.reject_claim', 'gray')
                ->visible($visible),
            TakedownResource::decisionAction('close', 'moderation.takedowns.close_claim', 'success')
                ->visible($visible),
        ];
    }
}
