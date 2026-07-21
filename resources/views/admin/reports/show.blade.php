@extends('layouts.portal')

@section('title', 'Review Report')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Report review</p>
            <h1>{{ $report->student->user->fullName() }}</h1>
            <p>{{ $report->student->admission_no }} · {{ $report->student->schoolClass?->display_name }} · {{ $report->term->academicSession?->name }} {{ $report->term->name }}</p>
        </div>
        <div class="inline-actions"><a class="secondary-link" href="{{ route('web.admin.reports.index', ['term_id' => $report->term_id, 'class_id' => $report->school_class_id]) }}">Back to reports</a><a class="primary-link" href="{{ route('web.admin.reports.print', $report) }}" target="_blank">Print preview</a></div>
    </header>

    <section class="identity-strip">
        <div><span>Average</span><strong>{{ number_format((float) $report->average_score, 2) }}%</strong></div>
        <div><span>Grade</span><strong>{{ $report->overall_grade ?: '—' }}</strong></div>
        <div><span>Position</span><strong>{{ $report->class_position ?: '—' }}</strong></div>
        <div><span>Publication</span><strong>{{ $report->published_at ? 'Published' : 'Draft' }}</strong></div>
    </section>

    @include('reports._card', ['report' => $report, 'subjectRows' => $subjectRows])

    <section class="content-section">
        <div class="section-heading"><div><p class="eyebrow">Teacher and school input</p><h2>Attendance, traits and remarks</h2></div><p>These details remain part of the permanent term report.</p></div>
        <form method="POST" action="{{ route('web.admin.reports.update', $report) }}" class="long-form">
            @csrf
            @method('PATCH')
            <fieldset class="form-section"><legend>Attendance and next term</legend><div class="form-grid form-grid-4"><label class="field-group"><span>Days school opened</span><input name="days_school_open" type="number" min="0" max="366" value="{{ old('days_school_open', $report->days_school_open) }}"></label><label class="field-group"><span>Days present</span><input name="days_present" type="number" min="0" max="366" value="{{ old('days_present', $report->days_present) }}"></label><label class="field-group"><span>Days absent</span><input name="days_absent" type="number" min="0" max="366" value="{{ old('days_absent', $report->days_absent) }}"></label><label class="field-group"><span>Next term begins</span><input name="next_term_begins_on" type="date" value="{{ old('next_term_begins_on', $report->next_term_begins_on?->format('Y-m-d')) }}"></label></div></fieldset>
            <fieldset class="form-section"><legend>Character traits</legend><div class="form-grid form-grid-4">@foreach (['punctuality','neatness','conduct','attentiveness','honesty','leadership','cooperation','self_control'] as $trait)<label class="field-group"><span>{{ str($trait)->headline() }}</span><select name="character_traits[{{ $trait }}]"><option value="">Not rated</option>@foreach (['Excellent','Very Good','Good','Fair','Needs Improvement'] as $rating)<option value="{{ $rating }}" @selected(old('character_traits.'.$trait, data_get($report->character_traits, $trait)) === $rating)>{{ $rating }}</option>@endforeach</select></label>@endforeach</div></fieldset>
            <fieldset class="form-section"><legend>Practical skills</legend><div class="form-grid form-grid-4">@foreach (['handwriting','sports','creativity','music','technical_skill','communication','teamwork','problem_solving'] as $skill)<label class="field-group"><span>{{ str($skill)->headline() }}</span><select name="practical_skills[{{ $skill }}]"><option value="">Not rated</option>@foreach (['Excellent','Very Good','Good','Fair','Needs Improvement'] as $rating)<option value="{{ $rating }}" @selected(old('practical_skills.'.$skill, data_get($report->practical_skills, $skill)) === $rating)>{{ $rating }}</option>@endforeach</select></label>@endforeach</div></fieldset>
            <fieldset class="form-section"><legend>Remarks</legend><div class="form-grid form-grid-2"><label class="field-group"><span>Class Teacher remark</span><textarea name="class_teacher_remark" rows="4">{{ old('class_teacher_remark', $report->class_teacher_remark) }}</textarea></label><label class="field-group"><span>Guidance remark</span><textarea name="guidance_remark" rows="4">{{ old('guidance_remark', $report->guidance_remark) }}</textarea></label><label class="field-group"><span>Principal remark</span><textarea name="principal_remark" rows="4">{{ old('principal_remark', $report->principal_remark) }}</textarea></label><label class="field-group"><span>House Master remark</span><textarea name="house_master_remark" rows="4">{{ old('house_master_remark', $report->house_master_remark) }}</textarea></label></div></fieldset>
            <div class="form-actions"><button class="primary-button" type="submit">Save report details</button></div>
        </form>
    </section>

    <section class="content-section publication-panel">
        <div class="section-heading"><div><p class="eyebrow">Publication controls</p><h2>Release report</h2></div><p>Private portal access and public PIN access are controlled independently.</p></div>
        <form method="POST" action="{{ route('web.admin.reports.publish', $report) }}" class="form-grid form-grid-3">
            @csrf
            @method('PATCH')
            <label class="check-row"><input name="portal_enabled" type="checkbox" value="1" @checked($report->portal_enabled)><span>Publish to Student/Parent portal</span></label>
            <label class="check-row"><input name="checker_enabled" type="checkbox" value="1" @checked($report->checker_enabled)><span>Enable public result checker</span></label>
            <label class="field-group"><span>Optional result PIN</span><input name="checker_pin" autocomplete="off" placeholder="Generated when blank"><small>Existing PIN is preserved when public checking stays enabled and this field is blank.</small></label>
            <div class="form-actions form-span-full"><button class="primary-button" type="submit">Update publication</button></div>
        </form>
    </section>
@endsection
