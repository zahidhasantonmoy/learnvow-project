<?php

namespace App\Filament\Pages;

use App\Models\AuthorPayout;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Commands\BackupCommand;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builder C — superBackup (spec §4.6). spatie/laravel-backup to LOCAL
 * disk; CSV exports of orders / payouts / users WITHOUT full bKash
 * numbers or TrxID history. Super Admin only.
 *
 * Account-deletion requests: Phase 1 has no request queue (self-deletion
 * is direct), so the section shows the empty state until one exists.
 */
class Backup extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $slug = 'backup';

    protected static ?int $navigationSort = 26;

    public static function getNavigationLabel(): string
    {
        return __('admin.backup.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public function getTitle(): string
    {
        return __('admin.backup.heading');
    }

    public static function canAccess(): bool
    {
        return AdminService::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function backupAvailable(): bool
    {
        return class_exists(BackupCommand::class);
    }

    /** Newest backup .zip on the local disk, or null. */
    public static function lastBackup(): ?array
    {
        $zips = collect(Storage::disk('local')->allFiles())
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->map(fn (string $path) => [
                'path' => $path,
                'size' => Storage::disk('local')->size($path),
                'modified' => Storage::disk('local')->lastModified($path),
            ])
            ->sortByDesc('modified')
            ->values();

        return $zips->first();
    }

    public static function maskPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '—';
        }

        return '··'.substr($phone, -3);
    }

    public function content(Schema $schema): Schema
    {
        $last = static::lastBackup();

        return $schema->components([
            Placeholder::make('note')
                ->content(__('admin.backup.note')),
            Placeholder::make('last_backup')
                ->label(__('admin.backup.last_backup'))
                ->content($last
                    ? $last['path'].' ('.number_format((int) ($last['size'] / 1024)).' KB, '
                        .date('Y-m-d H:i', (int) $last['modified']).')'
                    : __('admin.backup.never')),
            Actions::make([
                Action::make('run_backup')
                    ->label(__('admin.actions.run_backup'))
                    ->icon('heroicon-o-archive-box')
                    ->disabled(! static::backupAvailable())
                    ->action(function () {
                        if (! static::backupAvailable()) {
                            Notification::make()
                                ->title(__('admin.actions.backup_unavailable'))
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            Artisan::call('backup:run');
                            AdminService::writeAudit(auth()->user(), 'backup.run', 'backup', null, [
                                'message' => 'Backup run by Super Admin',
                            ]);
                            Notification::make()
                                ->title(__('admin.actions.backup_started'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('export_orders')
                    ->label(__('admin.backup.export_orders'))
                    ->action(fn () => static::downloadCsv('orders', static::ordersCsv())),
                Action::make('export_payouts')
                    ->label(__('admin.backup.export_payouts'))
                    ->action(fn () => static::downloadCsv('payouts', static::payoutsCsv())),
                Action::make('export_users')
                    ->label(__('admin.backup.export_users'))
                    ->action(fn () => static::downloadCsv('users', static::usersCsv())),
            ]),
            Placeholder::make('delete_requests')
                ->label(__('admin.backup.delete_requests'))
                ->content(__('admin.backup.no_delete_requests')),
        ]);
    }

    protected static function downloadCsv(string $name, string $csv): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            'learnvow-'.$name.'-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    /** Orders CSV — no TrxID history (spec §4.6). */
    public static function ordersCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['order_no', 'buyer', 'type', 'status', 'total_bdt', 'refund_status', 'created_at']);

        if (SchemaFacade::hasTable('orders')) {
            foreach (Order::query()->with('user')->orderBy('created_at')->cursor() as $order) {
                fputcsv($out, [
                    $order->order_no,
                    $order->user?->name,
                    $order->type,
                    $order->status,
                    $order->total_bdt,
                    $order->refund_status,
                    $order->created_at?->format('Y-m-d H:i'),
                ]);
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Payouts CSV — masked destination only, never a full bKash number. */
    public static function payoutsCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['author', 'period', 'sales_bdt', 'pool_bdt', 'total_bdt', 'method', 'destination_masked', 'status', 'paid_at']);

        if (SchemaFacade::hasTable('author_payouts')) {
            foreach (AuthorPayout::query()->with('author')->orderBy('created_at')->cursor() as $payout) {
                fputcsv($out, [
                    $payout->author?->name,
                    $payout->period,
                    $payout->sales_bdt,
                    $payout->pool_bdt,
                    $payout->total_bdt,
                    $payout->method,
                    $payout->maskedDestination(),
                    $payout->status,
                    $payout->paid_at?->format('Y-m-d H:i'),
                ]);
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Users CSV — masked phone, no sensitive data beyond the panel's own view. */
    public static function usersCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['name', 'email', 'phone_masked', 'status', 'roles', 'created_at']);

        if (SchemaFacade::hasTable('users')) {
            foreach (User::query()->orderBy('created_at')->cursor() as $user) {
                fputcsv($out, [
                    $user->name,
                    $user->email,
                    static::maskPhone($user->phone),
                    $user->status,
                    implode(',', AdminService::userRoles($user)),
                    $user->created_at?->format('Y-m-d H:i'),
                ]);
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
