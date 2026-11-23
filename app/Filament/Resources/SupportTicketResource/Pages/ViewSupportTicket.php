<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SupportTicketResource::replyAction(),
            SupportTicketResource::escalateAction(),
            Action::make('resolve')
                ->label(__('support.tickets.resolve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (SupportTicket $record) => in_array($record->status, ['open', 'in_progress', 'waiting_reply', 'escalated'], true))
                ->action(function (SupportTicket $record) {
                    $record->status = 'resolved';
                    $record->save();
                })
                ->successNotificationTitle(__('support.tickets.resolved')),
            Action::make('close')
                ->label(__('support.tickets.close'))
                ->icon('heroicon-o-lock-closed')
                ->visible(fn (SupportTicket $record) => $record->status !== 'closed')
                ->action(function (SupportTicket $record) {
                    $record->status = 'closed';
                    $record->save();
                })
                ->successNotificationTitle(__('support.tickets.closed')),
        ];
    }
}
