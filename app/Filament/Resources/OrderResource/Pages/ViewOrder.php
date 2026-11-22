<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\AdminService;
use App\Services\BillingService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * adminOrderDetail (spec §3.6): refund request handling.
 * - Admin may mark a requested refund "under review" — status only, no money.
 * - Approve / reject is SUPER ADMIN ONLY and goes through
 *   BillingService::refundOrder() (Builder B): an approved refund lands
 *   in the user's WALLET (type=refund), never cash.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markRefundUnderReview')
                ->label(__('admin.orders.mark_under_review'))
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->visible(fn (Order $record) => $record->refund_status === 'requested')
                ->action(function (Order $record) {
                    $record->refund_status = 'under_review';
                    $record->save();
                })
                ->successNotificationTitle(__('admin.orders.marked_under_review')),
            Action::make('approveRefund')
                ->label(__('admin.orders.approve_refund'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (Order $record) => in_array($record->refund_status, ['requested', 'under_review'], true)
                    && AdminService::isSuperAdmin(auth()->user()))
                ->requiresConfirmation()
                ->action(fn (Order $record) => app(BillingService::class)->refundOrder($record, auth()->user()))
                ->successNotificationTitle(__('admin.orders.refund_approved')),
            Action::make('rejectRefund')
                ->label(__('admin.orders.reject_refund'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Order $record) => in_array($record->refund_status, ['requested', 'under_review'], true)
                    && AdminService::isSuperAdmin(auth()->user()))
                ->requiresConfirmation()
                ->action(function (Order $record) {
                    $record->refund_status = 'rejected';
                    $record->save();
                })
                ->successNotificationTitle(__('admin.orders.refund_rejected')),
        ];
    }
}
