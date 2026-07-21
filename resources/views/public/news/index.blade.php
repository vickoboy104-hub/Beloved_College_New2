@extends('layouts.public-site')

@section('title', 'News and Announcements | '.($settings['school_name'] ?? 'Beloved College'))
@section('description', 'Published news, events and public announcements from Beloved College.')

@section('content')
    <section class="public-page-hero compact-public-hero">
        <p class="eyebrow">School updates</p>
        <h1>News and announcements</h1>
        <p>Important information, events, achievements and public notices from the school community.</p>
    </section>

    <section class="public-news-directory">
        @forelse ($announcements as $announcement)
            <article>
                <div><span>{{ $announcement->category ?: 'Announcement' }}</span><time>{{ $announcement->published_at?->format('d M Y') ?: $announcement->created_at->format('d M Y') }}</time></div>
                <h2><a href="{{ route('public.news.show', $announcement->slug) }}">{{ $announcement->title }}</a></h2>
                <p>{{ $announcement->excerpt ?: str($announcement->body)->stripTags()->limit(220) }}</p>
                <a class="text-arrow-link" href="{{ route('public.news.show', $announcement->slug) }}">Read update <span aria-hidden="true">→</span></a>
            </article>
        @empty
            <div class="public-empty-state">No public news has been published yet.</div>
        @endforelse
    </section>

    <div class="public-pagination">{{ $announcements->links() }}</div>
@endsection
