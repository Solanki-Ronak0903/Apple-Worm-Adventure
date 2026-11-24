@php($criteria = (array) ($achievement?->criteria ?? []))
<form method="POST" action="{{ $formAction }}" class="admin-panel admin-achievement-form">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <div class="admin-panel-head"><div><h2>{{ $formTitle }}</h2><p>Use one of the progress metrics currently recorded by the game engine.</p></div></div>
    <div class="admin-form-grid admin-achievement-form-grid">
        @if($creating)
            <label class="admin-form-field"><span>Internal code</span><input class="admin-field" name="code" required maxlength="60" pattern="[a-z][a-z0-9_]*" value="{{ old('code') }}" placeholder="example_collector"><small>Lowercase letters, numbers and underscores. This code is permanent.</small></label>
        @else
            <label class="admin-form-field"><span>Internal code</span><input class="admin-field" value="{{ $achievement->code }}" disabled><small>Codes cannot be changed after creation because they are used in reward history.</small></label>
        @endif
        <label class="admin-form-field"><span>Display name</span><input class="admin-field" name="name" required maxlength="80" value="{{ old('name', $achievement?->name) }}" placeholder="Puzzle Pathfinder"></label>
        <label class="admin-form-field"><span>Badge icon</span><input class="admin-field admin-icon-input" name="icon" required maxlength="16" value="{{ old('icon', $achievement?->icon ?? '🏆') }}" placeholder="🏆"><small>Use a single emoji or short symbol.</small></label>
        <label class="admin-form-field"><span>Progress metric</span><select class="admin-field" name="metric" required>@foreach($metrics as $code => $label)<option value="{{ $code }}" @selected(old('metric', $currentMetric ?? 'apples') === $code)>{{ $label }}</option>@endforeach</select><small>Changing this is blocked once player progress exists.</small></label>
        <label class="admin-form-field"><span>Unlock target</span><input class="admin-field" type="number" name="target" min="1" max="100000" required value="{{ old('target', $target ?? 1) }}"></label>
        <label class="admin-form-field"><span>Coin reward</span><input class="admin-field" type="number" name="reward_coins" min="0" max="100000" required value="{{ old('reward_coins', $rewardCoins ?? 0) }}"></label>
        <label class="admin-form-field admin-form-full"><span>Description shown to players</span><textarea class="admin-field admin-textarea" name="description" maxlength="240" required placeholder="Describe what the player needs to achieve…">{{ old('description', $achievement?->description) }}</textarea></label>
    </div>
    <div class="admin-form-footer"><span>Existing unlock records and wallet transactions are never deleted by editing an achievement.</span><button class="admin-button" type="submit">{{ $submitLabel }}</button></div>
</form>
