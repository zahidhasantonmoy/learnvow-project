<?php

namespace App\Filament\Resources\AuthorResource\Pages;

use App\Filament\Resources\AuthorResource;
use App\Models\Author;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewAuthor extends ViewRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('admin.actions.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (Author $record) => in_array($record->status, ['pending', 'rejected'], true))
                ->requiresConfirmation()
                ->action(fn (Author $record) => app(AdminService::class)->approveAuthor($record, auth()->user()))
                ->successNotificationTitle(__('admin.actions.approved')),
            Action::make('reject')
                ->label(__('admin.actions.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Author $record) => in_array($record->status, ['pending', 'approved'], true))
                ->form([
                    Textarea::make('reason')->label(__('admin.actions.reject'))->maxLength(500),
                ])
                ->action(fn (Author $record, array $data) => app(AdminService::class)->rejectAuthor($record, $data['reason'] ?? null, auth()->user()))
                ->successNotificationTitle(__('admin.actions.rejected')),
        ];
    }
}
