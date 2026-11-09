@extends('layouts.app') @section('title', 'Leaderboard') @section('content')<section class="page-heading">
    <span class="eyebrow">COMMUNITY SCOREBOARD</span>
    <h1>Top <span>adventurers.</span></h1>
    <p class="muted">Ranked by total best score across completed levels.</p>
</section>
<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Player</th>
                    <th>Score</th>
                    <th>Stars</th>
                    <th>Levels complete</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leaders as $i=>$leader)
                    <tr>
                        <td>{{ ['🥇', '🥈', '🥉'][$i] ?? '#' . ($i + 1) }}</td>
                        <td>{{ $leader->user?->name ?? 'Player' }} @if ($leader->user_id === auth()->id())
                                <span class="pill">You</span>
                            @endif
                        </td>
                        <td>{{ number_format($leader->score) }}</td>
                        <td>⭐ {{ $leader->stars }}</td>
                        <td>{{ $leader->levels_completed }}</td>
                </tr>@empty<tr>
                        <td colspan="5" class="muted">No scores yet. Be the first to complete a level!</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>@endsection
