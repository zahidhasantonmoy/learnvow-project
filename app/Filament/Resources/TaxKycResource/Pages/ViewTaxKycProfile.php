<?php

namespace App\Filament\Resources\TaxKycResource\Pages;

use App\Filament\Resources\TaxKycResource;
use App\Models\TaxKycProfile;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

/** KYC verify / reject (Super Admin only — spec §3.3). */
class ViewTaxKycProfile extends ViewRecord
{
    protected static string $resource = TaxKycResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label(__('admin.tax_kyc.verify'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (TaxKycProfile $record) => $record->status === TaxKycProfile::STATUS_PENDING
                    && AdminService::isSuperAdmin(auth()->user()))
                ->requiresConfirmation()
                ->action(function (TaxKycProfile $record) {
                    $record->update([
                        'status' => TaxKycProfile::STATUS_VERIFIED,
                        'reviewed_by' => auth()->id(),
                        'review_note' => null,
                    ]);
                    AdminService::writeAudit(auth()->user(), 'tax_kyc.verified', 'tax_kyc_profiles', $record->id, []);
                }),
            Action::make('reject')
                ->label(__('admin.tax_kyc.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (TaxKycProfile $record) => $record->status === TaxKycProfile::STATUS_PENDING
                    && AdminService::isSuperAdmin(auth()->user()))
                ->form([
                    Textarea::make('review_note')
                        ->label(__('admin.tax_kyc.review_note'))
                        ->rows(3)->required(),
                ])
                ->action(function (TaxKycProfile $record, array $data) {
                    $record->update([
                        'status' => TaxKycProfile::STATUS_REJECTED,
                        'reviewed_by' => auth()->id(),
                        'review_note' => $data['review_note'],
                    ]);
                    AdminService::writeAudit(auth()->user(), 'tax_kyc.rejected', 'tax_kyc_profiles', $record->id, [
                        'review_note' => $data['review_note'],
                    ]);
                }),
        ];
    }
}
