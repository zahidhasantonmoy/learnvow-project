{{-- Rating stars. Contract: $rating (float), $count (int). Real reviews only — no fake aggregates. --}}
@props(['rating' => 0, 'count' => 0])
<span class="rating">★ {{ number_format((float) $rating, 1) }}@if($count) <span class="rating-count">({{ number_format($count) }})</span>@endif</span>
