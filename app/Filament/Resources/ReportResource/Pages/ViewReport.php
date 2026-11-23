<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReportResource::decideAction('dismiss', 'moderation.reports.dismiss', 'gray'),
            ReportResource::decideAction('action', 'moderation.reports.take_action', 'danger'),
        ];
    }
}
