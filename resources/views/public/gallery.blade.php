@extends('layouts.public-site')

@section('title', 'Gallery | '.($settings['school_name'] ?? 'Beloved College'))
@section('description', 'Images and videos from learning, events and campus life at Beloved College.')

@section('content')
    <section class="public-page-hero compact-public-hero">
        <p class="eyebrow">Campus life</p>
        <h1>School gallery</h1>
        <p>Published images and videos from learning activities, celebrations, creativity and community life.</p>
    </section>

    <section class="public-gallery-directory">
        @forelse ($gallery as $media)
            <figure>
                @if ($media->media_type === 'video')
                    <video controls preload="metadata"><source src="{{ $media->publicUrl() }}"></video>
                @else
                    <img src="{{ $media->publicUrl() }}" alt="{{ $media->alt_text ?: $media->title ?: 'Beloved College gallery image' }}" loading="lazy">
                @endif
                @if ($media->title || $media->caption)<figcaption><strong>{{ $media->title }}</strong><span>{{ $media->caption }}</span></figcaption>@endif
            </figure>
        @empty
            <div class="public-empty-state">No public gallery media has been published yet.</div>
        @endforelse
    </section>
@endsection
