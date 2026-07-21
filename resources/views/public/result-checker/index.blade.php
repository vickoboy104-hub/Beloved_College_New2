@extends('layouts.public-site')

@section('title', 'Result Checker | '.($settings['school_name'] ?? 'Beloved College'))
@section('description', 'Securely check a published Beloved College academic report using the student admission number, academic term and result PIN.')

@section('content')
    <section class="public-checker-section">
        <header class="public-checker-header">
            <p class="eyebrow">Secure academic verification</p>
            <h1>Check a published result</h1>
            <p>Enter the student's admission number, select the academic term and provide the result PIN issued by the school.</p>
        </header>

        <form method="POST" action="{{ route('public.result-checker.lookup') }}" class="public-checker-form">
            @csrf
            <label class="field-group"><span>Admission number</span><input name="admission_no" value="{{ old('admission_no') }}" autocomplete="off" required></label>
            <label class="field-group"><span>Academic term</span><select name="term_id" required><option value="">Select term</option>@foreach ($terms as $term)<option value="{{ $term->id }}" @selected((string) old('term_id') === (string) $term->id)>{{ $term->academicSession?->name }} · {{ $term->name }}</option>@endforeach</select></label>
            <label class="field-group"><span>Result PIN</span><input name="pin" type="password" inputmode="numeric" autocomplete="off" required></label>
            <button class="primary-button" type="submit">Check result</button>
        </form>

        <p class="public-checker-help">For privacy, incorrect admission numbers and incorrect PINs receive the same error message. Contact the school office when access details are unavailable.</p>
    </section>
@endsection
