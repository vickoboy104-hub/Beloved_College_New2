@extends('layouts.public-site')

@section('title', $announcement->title.' | '.($settings['school_name'] ?? 'Beloved College'))
@section('description', $announcement->excerpt ?: str($announcement->body)->stripTags()->limit(160))

@section('content')
    <article class="public-article-page">
        <header>
            <div><span>{{ $announcement->category ?: 'Announcement' }}</span><time>{{ $announcement->published_at?->format('d M Y') ?: $announcement->created_at->format('d M Y') }}</time></div>
            <h1>{{ $announcement->title }}</h1>
            @if ($announcement->excerpt)<p>{{ $announcement->excerpt }}</p>@endif
        </header>
        <div class="public-article-body">{!! nl2br(e($announcement->body)) !!}</div>
        <footer><a class="text-arrow-link" href="{{ route('public.news.index') }}"><span aria-hidden="true">←</span> Back to all news</a></footer>
    </article>
@endsection
