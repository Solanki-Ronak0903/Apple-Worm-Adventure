@extends('layouts.admin')
@section('title', 'Audit logs')
@section('section', 'AUDIT LOGS')
@section('content')
<div class="admin-page-head">
    <div><span class="admin-kicker">ADMINISTRATOR ACCOUNTABILITY</span><h1>Audit history<span>.</span></h1><p>Review recorded changes to application settings, levels and achievement definitions.</p></div>
    <a class="admin-button admin-button-link" href="{{ route('admin.settings') }}">⚙ Application settings</a>
</div>

<div class="admin-stat-grid admin-audit-stats">
    <article class="admin-stat-card" style="--accent:var(--ad-teal)"><div class="admin-stat-top"><span>Recorded events</span><span class="admin-stat-icon">≡</span></div><strong>{{ number_format($totalLogs) }}</strong><div class="admin-stat-foot">All recorded administrator mutations</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-lime)"><div class="admin-stat-top"><span>Events today</span><span class="admin-stat-icon">◷</span></div><strong>{{ number_format($todayLogs) }}</strong><div class="admin-stat-foot">Changes recorded since midnight</div></article>
</div>

<section class="admin-panel">
    <form class="admin-toolbar" method="GET" action="{{ route('admin.audit') }}">
        <div class="admin-search-form admin-audit-filters">
            <input class="admin-field admin-search" type="search" name="q" value="{{ $search }}" maxlength="120" placeholder="Search actions or summaries…" aria-label="Search audit logs">
            <select class="admin-field admin-select" name="action" aria-label="Filter action"><option value="">All actions</option>@foreach($actions as $option)<option value="{{ $option }}" @selected($selectedAction === $option)>{{ str_replace('.', ' / ', $option) }}</option>@endforeach</select>
            <select class="admin-field admin-select" name="admin" aria-label="Filter administrator"><option value="">All administrators</option>@foreach($admins as $administrator)<option value="{{ $administrator->id }}" @selected((string) $selectedAdmin === (string) $administrator->id)>{{ $administrator->name }}</option>@endforeach</select>
            <input class="admin-field" type="date" name="from" value="{{ $from }}" aria-label="From date">
            <input class="admin-field" type="date" name="to" value="{{ $to }}" aria-label="To date">
            <button class="admin-button" type="submit">Apply filters</button><a class="admin-reset-link" href="{{ route('admin.audit') }}">Reset</a>
        </div>
    </form>
    <div class="admin-table-wrap"><table class="admin-table admin-audit-table">
        <thead><tr><th>Event</th><th>Administrator</th><th>Subject</th><th>IP address</th><th>When</th><th>Details</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td><span class="admin-pill info">{{ str_replace('.', ' / ', $log->action) }}</span><div class="sub admin-audit-summary">{{ $log->summary }}</div></td>
                <td><strong>{{ $log->actor?->name ?? 'Deleted administrator' }}</strong><span class="sub">{{ $log->actor?->email ?? 'Account unavailable' }}</span></td>
                <td>{{ $log->subject_type ?? 'Application' }}{{ $log->subject_id ? ' #' . $log->subject_id : '' }}</td>
                <td>{{ $log->ip_address ?? '—' }}</td>
                <td><strong>{{ $log->created_at?->format('d M Y') }}</strong><span class="sub">{{ $log->created_at?->format('H:i:s') }}</span></td>
                <td>
                    @if($log->before || $log->after)
                        <details class="admin-audit-details"><summary>View changes</summary>
                            @if($log->before)<strong>Before</strong><pre>{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                            @if($log->after)<strong>After</strong><pre>{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                        </details>
                    @else
                        <span class="sub">Event recorded</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="admin-empty">No audit events match these filters.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="admin-table-footer"><span>Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ number_format($logs->total()) }} events</span><div class="admin-pagination">{{ $logs->links() }}</div></div>
</section>
<div class="admin-level-note"><span>ⓘ</span><div><strong>Audit log handling</strong><p>Audit entries are append-only through this application interface. Passwords, session cookies, claim tokens and provider secrets are never recorded in change snapshots.</p></div></div>
@endsection
