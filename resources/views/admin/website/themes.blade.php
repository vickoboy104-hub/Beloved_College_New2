@extends('layouts.portal')

@section('title', 'Theme Manager')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Semantic design system</p>
            <h1>Classic and Dark Themes</h1>
            <p>Control the two approved themes with accessible semantic colour tokens, preview, draft history, publishing and rollback.</p>
        </div>
        <div class="inline-actions"><a class="secondary-link" href="{{ route('web.admin.website.index') }}">Back to Website CMS</a><a class="primary-link" href="{{ route('public.home') }}" target="_blank">Open Public Website</a></div>
    </header>

    <section class="content-section theme-preference-panel">
        <div class="section-heading"><div><p class="eyebrow">Global behaviour</p><h2>Theme availability</h2></div><p>Choose the forced default and decide whether authenticated users may switch between Classic and Dark.</p></div>
        <form method="POST" action="{{ route('web.admin.website.themes.preferences') }}" class="form-grid form-grid-3">@csrf @method('PUT')<label class="field-group"><span>Default theme</span><select name="theme_default_mode">@foreach($modes as $mode)<option value="{{ $mode->value }}" @selected($defaultMode === $mode)>{{ $mode->label() }}</option>@endforeach</select></label><label class="check-row"><input name="theme_allow_user_selection" type="checkbox" value="1" @checked($allowUserSelection)><span>Allow users to choose Classic or Dark</span></label><div class="form-actions"><button class="primary-button" type="submit">Save preferences</button></div></form>
    </section>

    <div class="theme-editor-grid">
        @foreach ([\App\Enums\ThemeMode::Classic->value => $classicTokens, \App\Enums\ThemeMode::Dark->value => $darkTokens] as $modeValue => $tokens)
            @php $mode = \App\Enums\ThemeMode::from($modeValue); @endphp
            <section class="theme-editor-panel" data-theme="{{ $mode->value }}" style="{{ app(\App\Services\Website\ThemeService::class)->cssVariables($tokens) }}">
                <header><div><p class="eyebrow">{{ $mode->label() }} theme</p><h2>{{ $mode->label() }} semantic tokens</h2></div><a class="secondary-link" href="{{ route('web.admin.website.themes.preview', $mode->value) }}" target="_blank">Preview</a></header>
                <form method="POST" class="theme-token-form">
                    @csrf
                    <div class="theme-token-list">
                        @foreach ($tokens as $key => $value)
                            <label><span>{{ str($key)->headline() }}</span><span class="theme-colour-input"><input name="tokens[{{ $key }}]" type="color" value="{{ $value }}"><input name="tokens[{{ $key }}]" value="{{ $value }}" pattern="#[0-9A-Fa-f]{6}" required></span></label>
                        @endforeach
                    </div>
                    <label class="field-group"><span>Revision notes</span><textarea name="notes" rows="3" placeholder="What changed and why?"></textarea></label>
                    <div class="form-actions theme-form-actions"><button class="secondary-button" type="submit" formaction="{{ route('web.admin.website.themes.drafts.store', $mode->value) }}">Save draft</button><button class="primary-button" type="submit" formaction="{{ route('web.admin.website.themes.publish', $mode->value) }}">Publish {{ $mode->label() }}</button></div>
                </form>
            </section>
        @endforeach
    </div>

    <section class="content-section">
        <div class="section-heading"><div><p class="eyebrow">Revision history</p><h2>Theme versions and rollback</h2></div><p>Every draft and published revision is retained. Rollback creates a new published revision instead of deleting history.</p></div>
        <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Revision</th><th>Theme</th><th>Status</th><th>Created</th><th>Created by</th><th>Notes</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>@forelse($revisions as $revision)<tr><td>#{{ $revision->id }}</td><td>{{ $revision->mode->label() }}</td><td><span class="status-badge status-{{ $revision->is_published ? 'active' : 'inactive' }}">{{ $revision->is_published ? 'Published' : 'Draft' }}</span></td><td>{{ $revision->created_at->format('d M Y H:i') }}</td><td>{{ $revision->creator?->fullName() ?? 'System' }}</td><td>{{ $revision->notes ?: '—' }}</td><td class="table-actions"><form method="POST" action="{{ route('web.admin.website.themes.rollback', $revision) }}">@csrf<button class="text-button" type="submit">Rollback to this</button></form></td></tr>@empty<tr><td colspan="7">No theme revisions have been created.</td></tr>@endforelse</tbody></table></div>
    </section>
@endsection
