<?php

namespace App\Filament\Pages;

use App\Models\AuditLog as AuditLogModel;
use App\Services\AdminService;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Builder C — superAudit (spec §4.5). Read-only audit log, Super Admin
 * only. No create / edit / delete actions exist anywhere on this page —
 * the log is append-only (AuditLog model guards + AuditLogPolicy).
 */
class AuditLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $slug = 'audit-log';

    protected static ?int $navigationSort = 23;

    public static function getNavigationLabel(): string
    {
        return __('moderation.audit.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public function getTitle(): string
    {
        return __('moderation.audit.heading');
    }

    public static function canAccess(): bool
    {
        return AdminService::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function content(Schema $schema): Schema
    {
        // Filament v5: a Page's table() alone renders nothing — the table
        // must be embedded in the page's content schema (as ListRecords
        // does with EmbeddedTable). Without this the page shows only the
        // heading and no <table> at all.
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLogModel::query()->with('actor'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('actor.name')
                    ->label(__('moderation.audit.actor'))
                    ->formatStateUsing(fn ($state, AuditLogModel $record) => $record->actor?->name
                        ?? __('moderation.audit.actor_system'))
                    ->searchable(),
                TextColumn::make('actor_role')
                    ->label(__('moderation.audit.role'))
                    ->placeholder('—'),
                TextColumn::make('action')
                    ->label(__('moderation.audit.action'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('subject')
                    ->label(__('moderation.audit.subject'))
                    ->state(fn (AuditLogModel $record) => ($record->subject_type ?? '—')
                        .($record->subject_id ? ' #'.$record->subject_id : '')),
                TextColumn::make('details_message')
                    ->label(__('moderation.audit.details'))
                    ->state(fn (AuditLogModel $record) => $record->details['message'] ?? '—')
                    ->limit(80),
                TextColumn::make('ip_address')
                    ->label(__('moderation.audit.ip'))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('moderation.audit.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('moderation.audit.action'))
                    ->options(fn () => AuditLogModel::query()
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
            ]);
    }
}
