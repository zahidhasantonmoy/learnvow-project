<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\BillingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label(__('admin.payments.verify'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (Payment $record) => $record->status === 'awaiting_verification')
                ->requiresConfirmation()
                ->action(fn (Payment $record) => app(BillingService::class)->verifyPayment($record, auth()->user()))
                ->successNotificationTitle(__('admin.payments.verified')),
            Action::make('reject')
                ->label(__('admin.payments.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Payment $record) => $record->status === 'awaiting_verification')
                ->form([
                    Textarea::make('admin_note')
                        ->label(__('admin.payments.admin_note'))
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(fn (Payment $record, array $data) => app(BillingService::class)->rejectPayment($record, auth()->user(), $data['admin_note']))
                ->successNotificationTitle(__('admin.payments.rejected')),
        ];
    }
}
