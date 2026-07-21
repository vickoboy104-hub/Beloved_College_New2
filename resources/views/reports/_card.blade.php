<article class="report-card">
    <header class="report-card-header">
        <div>
            <p class="eyebrow">Beloved College</p>
            <h2>Student Academic Report</h2>
            <p>{{ $report->term->academicSession?->name }} · {{ $report->term->name }}</p>
        </div>
        <dl class="report-student-facts">
            <div><dt>Student</dt><dd>{{ $report->student->user->fullName() }}</dd></div>
            <div><dt>Admission number</dt><dd>{{ $report->student->admission_no }}</dd></div>
            <div><dt>Class</dt><dd>{{ $report->student->schoolClass?->display_name ?? 'Unassigned' }}</dd></div>
            <div><dt>Position</dt><dd>{{ $report->class_position ?: '—' }}</dd></div>
        </dl>
    </header>

    <div class="data-table-wrap report-table-wrap">
        <table class="data-table report-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Quiz</th>
                    <th>Test</th>
                    <th>Project</th>
                    <th>Exam</th>
                    <th>Obtained</th>
                    <th>Possible</th>
                    <th>%</th>
                    <th>Grade</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjectRows as $row)
                    <tr>
                        <td><strong>{{ $row['subject_name'] }}</strong><small>{{ $row['subject_code'] ?? '' }}</small></td>
                        <td>{{ number_format((float) $row['quiz_score'], 2) }}</td>
                        <td>{{ number_format((float) $row['test_score'], 2) }}</td>
                        <td>{{ number_format((float) $row['project_score'], 2) }}</td>
                        <td>{{ number_format((float) $row['exam_score'], 2) }}</td>
                        <td>{{ number_format((float) $row['score_obtained'], 2) }}</td>
                        <td>{{ number_format((float) $row['score_possible'], 2) }}</td>
                        <td>{{ number_format((float) $row['percentage'], 2) }}</td>
                        <td>{{ $row['grade'] }}</td>
                        <td>{{ $row['remark'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10">No subject scores have been compiled.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5">Overall</th>
                    <th>{{ number_format((float) $report->total_score, 2) }}</th>
                    <th>—</th>
                    <th>{{ number_format((float) $report->average_score, 2) }}</th>
                    <th>{{ $report->overall_grade ?: '—' }}</th>
                    <th>{{ $report->subject_count }} subjects</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <section class="report-summary-grid">
        <dl>
            <div><dt>Days school opened</dt><dd>{{ $report->days_school_open ?? '—' }}</dd></div>
            <div><dt>Days present</dt><dd>{{ $report->days_present ?? '—' }}</dd></div>
            <div><dt>Days absent</dt><dd>{{ $report->days_absent ?? '—' }}</dd></div>
            <div><dt>Next term begins</dt><dd>{{ $report->next_term_begins_on?->format('d M Y') ?? '—' }}</dd></div>
        </dl>
        <div>
            <h3>Character traits</h3>
            <div class="trait-list">
                @forelse (($report->character_traits ?? []) as $trait => $value)
                    <span><strong>{{ str($trait)->headline() }}:</strong> {{ $value }}</span>
                @empty
                    <span>No character traits recorded.</span>
                @endforelse
            </div>
        </div>
        <div>
            <h3>Practical skills</h3>
            <div class="trait-list">
                @forelse (($report->practical_skills ?? []) as $skill => $value)
                    <span><strong>{{ str($skill)->headline() }}:</strong> {{ $value }}</span>
                @empty
                    <span>No practical skills recorded.</span>
                @endforelse
            </div>
        </div>
    </section>

    <section class="report-remarks">
        <article><h3>Class Teacher</h3><p>{{ $report->class_teacher_remark ?: 'No remark recorded.' }}</p></article>
        <article><h3>Guidance</h3><p>{{ $report->guidance_remark ?: 'No remark recorded.' }}</p></article>
        <article><h3>Principal</h3><p>{{ $report->principal_remark ?: 'No remark recorded.' }}</p></article>
        <article><h3>House Master</h3><p>{{ $report->house_master_remark ?: 'No remark recorded.' }}</p></article>
    </section>

    <footer class="report-card-footer">
        <span>Approved by {{ $report->approver?->fullName() ?? '—' }}</span>
        <span>Published {{ $report->published_at?->format('d M Y H:i') ?? 'Not published' }}</span>
    </footer>
</article>
