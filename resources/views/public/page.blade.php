@extends('layouts.public-site')

@section('title', ($page->seo_title ?: $page->title))
@section('description', ($page->seo_description ?: $page->summary))

@section('content')
    <section class="public-page-hero">
        <p class="eyebrow">{{ $page->eyebrow }}</p>
        <h1>{{ $page->headline ?: $page->title }}</h1>
        <p>{{ $page->summary }}</p>
    </section>

    <section class="public-page-body">
        <div class="public-prose">{!! nl2br(e($page->body)) !!}</div>

        @foreach (($page->sections ?? []) as $sectionKey => $sectionValue)
            @continue(! is_array($sectionValue) || $sectionValue === [])
            <section class="public-content-list">
                <header><p class="eyebrow">{{ str($sectionKey)->headline() }}</p><h2>{{ str($sectionKey)->headline() }}</h2></header>
                <div>
                    @foreach ($sectionValue as $item)
                        @if (is_array($item))
                            <article><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $item['title'] ?? 'Information' }}</h3><p>{{ $item['text'] ?? '' }}</p></article>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
    </section>

    <section class="public-page-cta">
        <div><p class="eyebrow">Next step</p><h2>{{ request()->routeIs('public.admissions') ? 'Speak with the admissions team.' : 'Learn more about Beloved College.' }}</h2></div>
        <div><a class="public-primary-action" href="{{ route('public.contact') }}">Contact the School</a>@unless(request()->routeIs('public.admissions'))<a class="public-secondary-action" href="{{ route('public.admissions') }}">Admissions</a>@endunless</div>
    </section>
@endsection
