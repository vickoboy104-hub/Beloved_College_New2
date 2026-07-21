@extends('layouts.portal')

@php $routePrefix = request()->routeIs('app.*') ? 'app' : 'web'; @endphp

@section('title', 'Report Card')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Published report card</p>
            <h1>{{ $report->student->user->fullName() }}</h1>
            <p>{{ $report->term->academicSession?->name }} · {{ $report->term->name }} · {{ $report->student->schoolClass?->display_name }}</p>
        </div>
        <div class="inline-actions">
            <a class="secondary-link" href="{{ route($routePrefix.'.portal.index', ['section' => 'reports', 'student_id' => request('student_id')]) }}">Back to reports</a>
            <button class="primary-button" type="button" onclick="window.print()">Print report</button>
        </div>
    </header>

    <section class="identity-strip">
        <div><span>Average</span><strong>{{ number_format((float) $report->average_score, 2) }}%</strong></div>
        <div><span>Overall grade</span><strong>{{ $report->overall_grade ?: '—' }}</strong></div>
        <div><span>Class position</span><strong>{{ $report->class_position ?: '—' }}</strong></div>
        <div><span>Subjects</span><strong>{{ $report->subject_count }}</strong></div>
    </section>

    @include('reports._card', ['report' => $report, 'subjectRows' => $subjectRows])
@endsection
