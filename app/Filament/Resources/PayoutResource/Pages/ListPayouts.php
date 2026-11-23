<?php

namespace App\Filament\Resources\PayoutResource\Pages;

use App\Filament\Resources\PayoutResource;
use App\Services\AdminService;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        // Spec §2.4 step 1 (C5): the only UI entry point for
        // PayoutService::closePeriod(). Super Admin only; defaults to the
        // last completed month (the month that just ended).
        $lastMonth = now()->subMonth()->format('Y-m');

        return [
            Action::make('closePeriod')
                ->label(__('admin.payouts.close_period').' ('.$lastMonth.')')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->visible(fn () => AdminService::isSuperAdmin(auth()->user()))
                ->requiresConfirmation()
                ->modalHeading(__('admin.payouts.close_period').' ('.$lastMonth.')')
                ->modalDescription(__('admin.payouts.close_period_hint'))
                ->form([
                    TextInput::make('period')
                        ->label(__('admin.payouts.period'))
                        ->default($lastMonth)
                        ->required()
                        ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                        ->maxLength(7),
                ])
                ->action(function (array $data) {
                    try {
                        $result = app(PayoutService::class)->closePeriod($data['period']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->title(__('admin.payouts.close_period_failed', ['message' => $e->getMessage()]))
                            ->danger()
                            ->send();

                        return;
                    }

                    // The `payout.period_closed` audit row is written
                    // inside PayoutService::closePeriod() (round 2 minor)
                    // so the CLI command produces it too — no second
                    // write here.
                    Notification::make()
                        ->title(__('admin.payouts.close_period_done', [
                            'pool' => number_format($result['pool_bdt']),
                            'distributed' => number_format($result['distributed_bdt']),
                            'count' => $result['payouts_created'],
                        ]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
