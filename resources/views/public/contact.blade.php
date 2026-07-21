@extends('layouts.public-site')

@section('title', ($page->seo_title ?: $page->title))
@section('description', ($page->seo_description ?: $page->summary))

@section('content')
    <section class="public-page-hero contact-page-hero">
        <p class="eyebrow">{{ $page->eyebrow }}</p>
        <h1>{{ $page->headline ?: $page->title }}</h1>
        <p>{{ $page->summary }}</p>
    </section>

    <section class="public-contact-layout">
        <div class="public-contact-details">
            <p class="eyebrow">School office</p>
            <h2>Contact information</h2>
            <p>{!! nl2br(e($page->body)) !!}</p>
            <dl>
                <div><dt>Address</dt><dd>{{ $settings['school_address'] ?? 'Contact the school office for the current address.' }}</dd></div>
                <div><dt>Telephone</dt><dd>@if ($settings['school_phone'] ?? null)<a href="tel:{{ preg_replace('/\s+/', '', $settings['school_phone']) }}">{{ $settings['school_phone'] }}</a>@else Not published @endif</dd></div>
                <div><dt>Email</dt><dd>@if ($settings['school_email'] ?? null)<a href="mailto:{{ $settings['school_email'] }}">{{ $settings['school_email'] }}</a>@else Not published @endif</dd></div>
                <div><dt>WhatsApp</dt><dd>@if ($settings['school_whatsapp'] ?? null)<a href="https://wa.me/{{ preg_replace('/\D+/', '', $settings['school_whatsapp']) }}" rel="noopener">Start a WhatsApp conversation</a>@else Not published @endif</dd></div>
            </dl>
        </div>

        <form method="POST" action="{{ route('public.contact.store') }}" class="public-contact-form">
            @csrf
            <div><p class="eyebrow">Send an enquiry</p><h2>How can the school help?</h2></div>
            <label><span>Your name</span><input name="name" value="{{ old('name') }}" required></label>
            <div class="public-form-grid">
                <label><span>Email address</span><input name="email" type="email" value="{{ old('email') }}"></label>
                <label><span>Telephone</span><input name="phone" value="{{ old('phone') }}"></label>
            </div>
            <label><span>Subject</span><input name="subject" value="{{ old('subject') }}"></label>
            <label><span>Message</span><textarea name="message" rows="8" required>{{ old('message') }}</textarea></label>
            <p class="form-help">Provide either an email address or telephone number so the school can respond.</p>
            <button type="submit">Send message</button>
        </form>
    </section>
@endsection
