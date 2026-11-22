<?php

namespace App\Http\Controllers\Concerns;

/** Builds the $seo array the coordinator's layouts/partials/seo expects. */
trait BuildsSeo
{
    protected function seo(string $title, ?string $description = null, array $extra = []): array
    {
        $locale = app()->getLocale();
        $other = $locale === 'bn' ? 'en' : 'bn';
        $path = request()->path();
        $pathOther = preg_replace('#^(bn|en)(/|$)#', $other.'$2', $path, 1);

        return array_merge([
            'title' => $title,
            'description' => $description,
            'canonical' => url()->current(),
            'ogType' => 'website',
            'alternate' => [
                'bn' => url($locale === 'bn' ? $path : $pathOther),
                'en' => url($locale === 'en' ? $path : $pathOther),
            ],
        ], $extra);
    }
}
