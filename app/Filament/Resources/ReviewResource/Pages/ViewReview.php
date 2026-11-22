<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewReview extends ViewRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('admin.actions.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (Review $record) => in_array($record->status, ['pending', 'hidden', 'rejected'], true))
                ->action(fn (Review $record) => app(AdminService::class)->moderateReview($record, 'approved', auth()->user()))
                ->successNotificationTitle(__('admin.actions.approved')),
            Action::make('reject')
                ->label(__('admin.actions.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Review $record) => in_array($record->status, ['pending', 'approved', 'hidden'], true))
                ->action(fn (Review $record) => app(AdminService::class)->moderateReview($record, 'rejected', auth()->user()))
                ->successNotificationTitle(__('admin.actions.rejected')),
            Action::make('hide')
                ->label(__('admin.actions.hide'))
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn (Review $record) => in_array($record->status, ['pending', 'approved'], true))
                ->action(fn (Review $record) => app(AdminService::class)->moderateReview($record, 'hidden', auth()->user()))
                ->successNotificationTitle(__('admin.actions.hidden')),
        ];
    }
}
