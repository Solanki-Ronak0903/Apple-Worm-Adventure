@extends('layouts.app')
@section('title', 'Home')
@section('content')
<div class="home-redesign">
    <section class="play-hero">
        <div class="play-hero-copy">
            <div class="home-kicker"><span class="kicker-sparkle">✦</span> YOUR NEXT LITTLE BIG ADVENTURE</div>
            <h1>Ready, set,<br><span>SLITHER!</span></h1>
            <p class="play-lead">Meet your new wiggly best friend. Collect shiny apples, solve clever puzzles and find your way to the portal!</p>
            <div class="play-hero-actions">
                <a class="play-button" href="{{ auth()->check() ? route('levels') : route('register') }}">{{ auth()->check() ? 'Continue adventure' : 'Play for free' }} <span>➜</span></a>
                <a class="how-link" href="#how-to-play">How to play <span>↓</span></a>
            </div>
            <div class="home-social-proof"><span class="proof-avatars"><i>🐸</i><i>🐻</i><i>🐼</i></span><span><b>Big fun. Tiny worm.</b><small>One clever move at a time.</small></span></div>
        </div>
        <div class="play-hero-scene" aria-label="A cheerful cartoon worm ready for an apple adventure">
            <span class="scene-cloud cloud-one">☁️</span><span class="scene-cloud cloud-two">☁️</span>
            <span class="scene-sun">☀️</span><span class="scene-star star-one">⭐</span><span class="scene-star star-two">✨</span>
            <div class="scene-hill hill-back"></div><div class="scene-hill hill-front"></div>
            <div class="scene-portal"><span>✦</span><small>GOAL</small></div>
            <div class="scene-worm">🐛</div>
            <div class="scene-apple">🍎</div>
            <div class="scene-level-tag"><span>🧩</span><span><b>LEVEL 01</b><small>The first bite</small></span><strong>★</strong></div>
            <div class="scene-coin">🪙</div>
        </div>
        <div class="hero-bottom-note"><span>🍏</span> THINK • WIGGLE • WIN <span>🍏</span></div>
    </section>

    <section class="home-quick-stats" aria-label="Game highlights">
        <div><span class="quick-icon mint">🗺️</span><b>25</b><small>puzzle levels</small></div>
        <div><span class="quick-icon yellow">🍎</span><b>One goal</b><small>collect every apple</small></div>
        <div><span class="quick-icon pink">🏆</span><b>Be a star</b><small>earn your best score</small></div>
    </section>

    <section class="home-how" id="how-to-play">
        <div class="home-section-heading"><span class="home-kicker">EASY TO LEARN, FUN TO MASTER</span><h2>Three steps to <span>wormsome!</span></h2><p>Ready for a tiny adventure? Here's how it works.</p></div>
        <div class="home-step-grid">
            <article class="home-step-card step-mint"><span class="step-number">01</span><div class="step-illustration">🧭</div><h3>Plan your path</h3><p>Look around, spot the obstacles and think a move ahead.</p></article>
            <article class="home-step-card step-peach"><span class="step-number">02</span><div class="step-illustration">🍎</div><h3>Munch the apples</h3><p>Guide your growing worm and collect the apples on the board.</p></article>
            <article class="home-step-card step-lilac"><span class="step-number">03</span><div class="step-illustration">🌀</div><h3>Reach the portal</h3><p>Find the exit, beat the puzzle and celebrate your win!</p></article>
        </div>
    </section>

    <section class="home-feature-banner" id="features">
        <div class="feature-banner-art" aria-hidden="true"><span>🎮</span><i>✨</i><b>🍓</b></div>
        <div class="feature-banner-copy"><span class="home-kicker">A LITTLE GAME WITH BIG IDEAS</span><h2>More than just<br><span>a snack run.</span></h2><p>Explore tricky paths, unlock new challenges and build your own high-score story. Every level brings a new way to think!</p><a href="{{ auth()->check() ? route('levels') : route('register') }}" class="banner-link">Let's play <span>➜</span></a></div>
    </section>
</div>
@endsection
