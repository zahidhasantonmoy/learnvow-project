<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        CouponResource::assertWithinPromoLimits($data);

        // max_uses_per_user is NOT NULL in the DB (default 1); an empty
        // form value must not insert NULL.
        $data['max_uses_per_user'] = $data['max_uses_per_user'] ?? 1;

        return $data;
    }
}
