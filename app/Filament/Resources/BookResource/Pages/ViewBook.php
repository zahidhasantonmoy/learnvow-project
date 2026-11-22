<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use App\Models\Book;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewBook extends ViewRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('admin.actions.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (Book $record) => in_array($record->status, ['draft', 'in_review', 'rejected'], true))
                ->requiresConfirmation()
                ->action(fn (Book $record) => app(AdminService::class)->approveBook($record, auth()->user()))
                ->successNotificationTitle(__('admin.actions.approved')),
            Action::make('reject')
                ->label(__('admin.actions.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Book $record) => in_array($record->status, ['draft', 'in_review', 'published'], true))
                ->form([
                    Textarea::make('reason')->label(__('admin.actions.reject'))->maxLength(500),
                ])
                ->action(fn (Book $record, array $data) => app(AdminService::class)->rejectBook($record, $data['reason'] ?? null, auth()->user()))
                ->successNotificationTitle(__('admin.actions.rejected')),
        ];
    }
}
