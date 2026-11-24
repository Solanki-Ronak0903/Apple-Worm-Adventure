@extends('layouts.admin')
@section('title', 'Reward economy')
@section('section', 'REWARD ECONOMY')
@section('content')
<div class="admin-page-head"><div><span class="admin-kicker">REWARD OPERATIONS</span><h1>Reward <span>economy.</span></h1><p>Inspect rewarded actions and provider responses. Claims are displayed for auditing; this screen does not modify wallet balances.</p></div><div class="admin-date-chip">{{ number_format($claims->total()) }} claims recorded</div></div>
<section class="admin-panel"><div class="admin-panel-head"><div><h2>Reward claim ledger</h2><p>Latest reward claim records</p></div><span class="admin-pill warn">DEVELOPMENT PROVIDER</span></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Claim</th><th>Player</th><th>Session</th><th>Action</th><th>Provider</th><th>Status</th><th>Granted</th></tr></thead><tbody>
        @forelse($claims as $claim)
            <tr><td>#{{ $claim->id }}<span class="sub">{{ $claim->claim_token }}</span></td><td>@if($claim->user)<a class="admin-player-name" href="{{ route('admin.players.show', $claim->user) }}">{{ $claim->user->name }}</a><span class="sub">{{ $claim->user->email }}</span>@else Deleted account @endif</td><td>{{ $claim->game_session_id ?? '—' }}</td><td>{{ str_replace('_', ' ', $claim->action) }}</td><td>{{ $claim->provider }}</td><td><span class="admin-pill {{ $claim->status === 'granted' ? 'good' : ($claim->status === 'pending' ? 'warn' : 'info') }}">{{ $claim->status }}</span></td><td>{{ $claim->granted_at?->format('d M Y H:i') ?? '—' }}</td></tr>
        @empty<tr><td colspan="7" class="admin-empty">No reward claims recorded yet.</td></tr>@endforelse
    </tbody></table></div>
    <div class="admin-table-footer"><span>Showing {{ $claims->firstItem() ?? 0 }}–{{ $claims->lastItem() ?? 0 }} of {{ number_format($claims->total()) }} claims</span><div class="admin-pagination">{{ $claims->links() }}</div></div>
</section>
@endsection
