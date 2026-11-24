@extends('layouts.admin')
@section('title', 'Create achievement')
@section('section', 'CREATE ACHIEVEMENT')
@section('content')
<a class="admin-inline-back" href="{{ route('admin.achievements') }}">← Back to achievements</a>
<div class="admin-page-head"><div><span class="admin-kicker">PLAYER PROGRESSION</span><h1>Create <span>achievement.</span></h1><p>Add a new player goal using a progress metric already supported by the game.</p></div></div>
@include('admin.achievements._form', ['achievement' => null, 'creating' => true, 'formAction' => route('admin.achievements.store'), 'method' => 'POST', 'formTitle' => 'Achievement definition', 'submitLabel' => 'Create achievement', 'currentMetric' => 'apples', 'target' => 1, 'rewardCoins' => 0])
@endsection
