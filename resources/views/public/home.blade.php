@extends('layouts.public-site')

@section('title', ($page->seo_title ?: $page->title))
@section('description', ($page->seo_description ?: $page->summary))

@section('content')
    <section class="public-hero">
        <div class="public-hero-copy">
            <p class="eyebrow">{{ $page->eyebrow }}</p>
            <h1>{{ $page->headline }}</h1>
            <p>{{ $page->summary }}</p>
            <div class="public-hero-actions">
                <a class="public-primary-action" href="{{ $settings['admissions_cta_url'] ?? route('public.admissions') }}">{{ $settings['admissions_cta_label'] ?? 'Explore Admissions' }}</a>
                <a class="public-secondary-action" href="{{ route('public.about') }}">Discover the School</a>
            </div>
        </div>

        <div class="public-hero-media" data-hero-rotator data-delay="{{ max(3, (int) ($settings['hero_autoplay_seconds'] ?? 7)) * 1000 }}">
            @forelse ($heroMedia as $media)
                <figure @class(['hero-frame', 'is-active' => $loop->first])>
                    @if ($media->media_type === 'video')
                        <video muted playsinline loop preload="metadata" @if ($loop->first) autoplay @endif>
                            <source src="{{ $media->publicUrl() }}">
                        </video>
                    @else
                        <img src="{{ $media->publicUrl() }}" alt="{{ $media->alt_text ?: $media->title ?: 'Beloved College campus' }}">
                    @endif
                    @if ($media->caption)<figcaption>{{ $media->caption }}</figcaption>@endif
                </figure>
            @empty
                <div class="hero-fallback" aria-label="Beloved College">
                    <span>BC</span>
                    <strong>{{ $settings['school_name'] ?? 'Beloved College' }}</strong>
                    <small>{{ $settings['school_tagline'] ?? 'Learning with purpose' }}</small>
                </div>
            @endforelse
        </div>
    </section>

    <section class="public-stat-strip" aria-label="School statistics">
        @foreach ($stats as $stat)
            <div><strong>{{ is_numeric($stat['value']) ? number_format($stat['value']) : $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></div>
        @endforeach
    </section>

    <section class="public-intro-section">
        <div>
            <p class="eyebrow">Our approach</p>
            <h2>{{ data_get($page->sections, 'welcome_title', 'A school community where every learner matters') }}</h2>
        </div>
        <div>
            <p>{{ data_get($page->sections, 'welcome_body', $page->body) }}</p>
            <a class="text-arrow-link" href="{{ route('public.about') }}">Read about Beloved College <span aria-hidden="true">→</span></a>
        </div>
    </section>

    <section class="public-programmes">
        <header class="public-section-heading">
            <p class="eyebrow">Learning pathways</p>
            <h2>Strong foundations and focused preparation.</h2>
        </header>
        <div class="programme-list">
            @foreach (data_get($page->sections, 'programs', []) as $programme)
                <article>
                    <span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $programme['title'] ?? 'Programme' }}</h3>
                    <p>{{ $programme['text'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="public-news-section">
        <header class="public-section-heading public-section-heading-actions">
            <div><p class="eyebrow">School updates</p><h2>News and announcements</h2></div>
            <a class="text-arrow-link" href="{{ route('public.news.index') }}">View all news <span aria-hidden="true">→</span></a>
        </header>
        <div class="public-news-list">
            @forelse ($announcements as $announcement)
                <article>
                    <div><span>{{ $announcement->category ?: 'Announcement' }}</span><time>{{ $announcement->published_at?->format('d M Y') ?: $announcement->created_at->format('d M Y') }}</time></div>
                    <h3><a href="{{ route('public.news.show', $announcement->slug) }}">{{ $announcement->title }}</a></h3>
                    <p>{{ $announcement->excerpt ?: str($announcement->body)->stripTags()->limit(180) }}</p>
                </article>
            @empty
                <div class="public-empty-state">No public announcements have been published yet.</div>
            @endforelse
        </div>
    </section>

    @if ($gallery->isNotEmpty())
        <section class="public-gallery-section">
            <header class="public-section-heading public-section-heading-actions">
                <div><p class="eyebrow">Campus life</p><h2>Learning, creativity and community</h2></div>
                <a class="text-arrow-link" href="{{ route('public.gallery') }}">Open gallery <span aria-hidden="true">→</span></a>
            </header>
            <div class="homepage-gallery-grid">
                @foreach ($gallery->take(6) as $media)
                    <figure>
                        @if ($media->media_type === 'video')
                            <video controls preload="metadata"><source src="{{ $media->publicUrl() }}"></video>
                        @else
                            <img src="{{ $media->publicUrl() }}" alt="{{ $media->alt_text ?: $media->title ?: 'Beloved College gallery image' }}" loading="lazy">
                        @endif
                        @if ($media->caption)<figcaption>{{ $media->caption }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    @if ($testimonials->isNotEmpty())
        <section class="public-testimonials-section">
            <header class="public-section-heading"><p class="eyebrow">Community voices</p><h2>What families and students say</h2></header>
            <div class="testimonial-list">
                @foreach ($testimonials as $testimonial)
                    <blockquote>
                        <p>“{{ $testimonial->quote }}”</p>
                        <footer>
                            @if ($testimonial->photo_path)<img src="{{ $testimonial->photoUrl() }}" alt="{{ $testimonial->name }}" loading="lazy">@endif
                            <span><strong>{{ $testimonial->name }}</strong><small>{{ $testimonial->role }}</small></span>
                        </footer>
                    </blockquote>
                @endforeach
            </div>
        </section>
    @endif

    <section class="public-admission-band">
        <div>
            <p class="eyebrow">Admissions</p>
            <h2>{{ data_get($page->sections, 'admission_title', 'Admissions are open') }}</h2>
            <p>{{ data_get($page->sections, 'admission_body', 'Speak with the school office to learn about current openings and the next steps.') }}</p>
        </div>
        <div>
            <a class="public-primary-action" href="{{ route('public.admissions') }}">Admission Information</a>
            <a class="public-secondary-action" href="{{ route('public.contact') }}">Contact the School</a>
        </div>
    </section>

    <script>
        (() => {
            const rotator = document.querySelector('[data-hero-rotator]');
            const frames = [...(rotator?.querySelectorAll('.hero-frame') ?? [])];
            if (frames.length < 2) return;
            let active = 0;
            const delay = Number(rotator.dataset.delay || 7000);
            window.setInterval(() => {
                frames[active].classList.remove('is-active');
                active = (active + 1) % frames.length;
                frames[active].classList.add('is-active');
                frames[active].querySelector('video')?.play().catch(() => {});
            }, delay);
        })();
    </script>
@endsection
