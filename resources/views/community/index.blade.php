@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('community.feed_heading') }}</h1>
  <p>
    <a href="{{ route('clubs.index', ['locale' => app()->getLocale()]) }}">{{ __('community.clubs_heading') }}</a>
    |
    <a href="{{ route('community.find-friends', ['locale' => app()->getLocale()]) }}">{{ __('community.find_friends_heading') }}</a>
  </p>

  @if(empty($items))
    <p>{{ __('community.feed_empty') }}</p>
  @else
    <ul>
      @foreach($items as $item)
        <li>
          @if($item['kind'] === 'finished')
            {{ __('community.finished_book', ['name' => $item['user']->name ?? '', 'title' => $item['book']->title ?? '']) }}
          @elseif($item['kind'] === 'review')
            {{ __('community.reviewed_book', ['name' => $item['user']->name ?? '', 'title' => $item['book']->title ?? '']) }}
          @elseif($item['kind'] === 'club_post')
            {{ __('community.club_post', ['name' => $item['user']->name ?? '', 'club' => $item['club']->name ?? '']) }} — {{ \Illuminate\Support\Str::limit($item['post']->body, 200) }}
          @elseif($item['kind'] === 'announcement')
            <strong>{{ __('community.announcement') }}:</strong> {{ $item['announcement']->title ?? '' }}
          @endif
        </li>
      @endforeach
    </ul>
  @endif
@endsection
