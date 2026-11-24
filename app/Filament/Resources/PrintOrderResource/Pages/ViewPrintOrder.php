<?php

namespace App\Filament\Resources\PrintOrderResource\Pages;

use App\Filament\Resources\PrintOrderResource;
use App\Models\PrintOrder;
use App\Models\User;
use App\Services\AdminService;
use App\Services\PrintService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

/**
 * Print order detail (spec §2.2): Admin confirms a TrxID-verified
 * order, sends it to a partner (partner + cost form), marks delivery
 * (accrues the author share), or cancels. No price editing here.
 */
class ViewPrintOrder extends ViewRecord
{
    protected static string $resource = PrintOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmPayment')
                ->label(__('admin.print_orders.confirm_payment'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (PrintOrder $record) => $record->status === PrintOrder::STATUS_ORDERED
                    && AdminService::isAdmin(auth()->user()))
                ->requiresConfirmation()
                ->action(fn (PrintOrder $record) => app(PrintService::class)->onPaymentVerified($record, auth()->user())),
            Action::make('sendToPartner')
                ->label(__('admin.print_orders.send_to_partner'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (PrintOrder $record) => $record->status === PrintOrder::STATUS_CONFIRMED
                    && AdminService::isAdmin(auth()->user()))
                ->form([
                    Select::make('partner_id')
                        ->label(__('admin.print_orders.partner'))
                        ->options(fn () => User::role('print-partner')->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
                    TextInput::make('partner_cost_bdt')
                        ->label(__('admin.print_orders.partner_cost'))
                        ->numeric()->minValue(0),
                ])
                ->action(function (PrintOrder $record, array $data) {
                    $partner = User::findOrFail((int) $data['partner_id']);
                    app(PrintService::class)->sendToPartner(
                        $record, $partner,
                        $data['partner_cost_bdt'] !== null && $data['partner_cost_bdt'] !== ''
                            ? (int) $data['partner_cost_bdt'] : null,
                        auth()->user()
                    );
                }),
            Action::make('markDelivered')
                ->label(__('admin.print_orders.mark_delivered'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (PrintOrder $record) => $record->status === PrintOrder::STATUS_SHIPPED
                    && AdminService::isAdmin(auth()->user()))
                ->requiresConfirmation()
                ->action(fn (PrintOrder $record) => app(PrintService::class)->markDelivered($record, auth()->user())),
            Action::make('cancelOrder')
                ->label(__('admin.print_orders.cancel_order'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (PrintOrder $record) => ! $record->isTerminal()
                    && AdminService::isAdmin(auth()->user()))
                ->requiresConfirmation()
                ->action(fn (PrintOrder $record) => app(PrintService::class)->cancel($record, auth()->user())),
        ];
    }
}
