(() => {
  'use strict';

  const app = document.getElementById('gameApp');
  if (!app) return;

  const cfg = JSON.parse(app.dataset.level || '{}');
  const sessionId = app.dataset.sessionId;
  const nextLevelUrl = app.dataset.nextLevelUrl || '/levels';
  const replayLevelUrl = app.dataset.replayLevelUrl || location.href;
  const hasNextLevel = app.dataset.hasNextLevel === '1';
  let revivesUsed = Number(app.dataset.revivesUsed || 0);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const board = document.getElementById('gameBoard');
  const scoreEl = document.getElementById('score');
  const movesEl = document.getElementById('moves');
  const applesEl = document.getElementById('appleCount');
  const goldenApplesEl = document.getElementById('goldenAppleCount');
  const missionGoldenApples = document.getElementById('missionGoldenApples');
  const missionKeys = document.getElementById('missionKeys');
  const missionSwitches = document.getElementById('missionSwitches');
  const limitEl = document.getElementById('moveLimit');
  const missionApples = document.getElementById('missionApples');
  const missionMoves = document.getElementById('missionMoves');
  const missionProgressValue = document.getElementById('missionProgressValue');
  const missionProgressFill = document.getElementById('missionProgressFill');
  const missionProgressMeter = document.getElementById('missionProgressMeter');
  const missionObjectiveCopy = document.getElementById('missionObjectiveCopy');
  const portalStateText = document.getElementById('portalStateText');
  const appleProgressText = document.getElementById('appleProgressText');
  const appleProgressTrack = document.getElementById('appleProgressTrack');
  const appleProgressFill = document.getElementById('appleProgressFill');
  const gameStatus = document.getElementById('gameStatus');
  const gameNotice = document.getElementById('gameNotice');
  const modal = document.getElementById('gameModal');
  const soundButton = document.getElementById('soundBtn');

  const rows = Number(cfg.grid?.rows || 8);
  const cols = Number(cfg.grid?.cols || 8);
  const originalLimit = Number(cfg.moveLimit || 40);
  const totalApples = (cfg.apples || []).length;
  const totalGoldenApples = (cfg.goldenApples || []).length;
  const totalMegaApples = (cfg.megaApples || []).length;
  const totalMysteryApples = (cfg.mysteryApples || []).length;
  const totalFruit = totalApples + totalGoldenApples + totalMegaApples + totalMysteryApples;
  const requiredLength = Number(cfg.mission?.requiredLength || 0);
  const totalKeys = (cfg.keys || []).length;
  const totalSwitches = (cfg.switches || []).length;
  let state = null;
  let alive = true;
  let gameOver = false;
  let busy = false;
  let sound = false;
  let audioCtx = null;
  let touchStart = null;
  let paused = false;

  const same = (a, b) => Number(a.x) === Number(b.x) && Number(a.y) === Number(b.y);
  const cellAt = (x, y) => board.children[(y * cols) + x] || null;

  function setNotice(message = '', type = '') {
    gameNotice.textContent = message;
    gameNotice.dataset.type = type;
  }

  function setStatus(message, type = 'playing') {
    gameStatus.textContent = message;
    gameStatus.dataset.state = type;
  }

  function setControlsDisabled(isBusy) {
    document.querySelectorAll('[data-dir]').forEach(button => {
      button.disabled = Boolean(isBusy) || gameOver || paused;
    });
    const restartButton = document.getElementById('restartBtn');
    const hintButton = document.getElementById('hintBtn');
    const pauseButton = document.getElementById('pauseBtn');
    if (pauseButton) pauseButton.disabled = Boolean(isBusy) || gameOver;
    if (restartButton) restartButton.disabled = Boolean(isBusy) || Boolean(state?.won);
    if (hintButton) hintButton.disabled = Boolean(isBusy) || gameOver || paused;
    document.querySelectorAll('[data-reward]').forEach(button => {
      const isRevive = button.dataset.reward === 'revive';
      const failedRevive = Boolean(state?.lost) && isRevive && revivesUsed < 1;
      button.disabled = Boolean(isBusy) || paused || (gameOver && !failedRevive) || (isRevive && revivesUsed >= 1);
      if (button.disabled) button.setAttribute('aria-disabled', 'true');
      else button.removeAttribute('aria-disabled');
    });
  }

  function render() {
    if (!state) return;

    const previousWormRects = new Map([...board.querySelectorAll('.worm')].map(element => [Number(element.dataset.segment || 0), element.getBoundingClientRect()]));
    board.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
    board.style.setProperty('--board-cols', cols);
    board.style.setProperty('--board-rows', rows);
    board.style.aspectRatio = `${cols} / ${rows}`;
    board.dataset.theme = app.dataset.worldTheme || cfg.world?.theme || 'meadow';
    board.dataset.pattern = cfg.layout?.pattern || 'route';
    board.innerHTML = '';

    const worm = state.worm || [];
    const apples = state.apples || [];
    const goldenApples = state.goldenApples || [];
    const megaApples = state.megaApples || [];
    const mysteryApples = state.mysteryApples || [];
    const obstacles = cfg.obstacles || [];
    const thorns = cfg.thorns || [];
    const mudPits = cfg.mudPits || [];
    const keys = state.keys || [];
    const switches = state.switches || [];
    const gates = cfg.gates || [];
    const portal = cfg.portal || null;
    const wormHead = worm[0];
    const wormNeck = worm[1];
    let facing = board.dataset.facing || 'right';
    if (wormHead && wormNeck) {
      const dx = Number(wormHead.x) - Number(wormNeck.x);
      const dy = Number(wormHead.y) - Number(wormNeck.y);
      facing = Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up');
    }
    board.dataset.facing = facing;

    const portalReady = apples.length === 0
      && goldenApples.length === 0
      && megaApples.length === 0
      && mysteryApples.length === 0
      && keys.length === 0
      && switches.length === 0
      && (!requiredLength || (worm.length + Number(state.growthPending || 0)) >= requiredLength);

    const hasAt = (items, point) => items.some(item => same(item, point));
    const appendIcon = (cell, className, value) => {
      const icon = document.createElement('span');
      icon.className = `tile-icon ${className || ''}`.trim();
      icon.textContent = value;
      icon.setAttribute('aria-hidden', 'true');
      cell.appendChild(icon);
    };

    for (let y = 0; y < rows; y += 1) {
      for (let x = 0; x < cols; x += 1) {
        const point = { x, y };
        const cell = document.createElement('div');
        cell.className = 'cell game-cell topdown-cell';
        cell.setAttribute('aria-hidden', 'true');
        cell.dataset.x = String(x);
        cell.dataset.y = String(y);
        cell.classList.add((x + y) % 2 === 0 ? 'cell-soft' : 'cell-deep');

        if (hasAt(obstacles, point)) {
          cell.classList.add('obstacle', 'stone-wall');
          appendIcon(cell, 'stone-icon', '🪨');
        }
        if (hasAt(thorns, point)) {
          cell.classList.add('thorn');
          appendIcon(cell, 'thorn-icon', '🔺');
        }
        if (hasAt(mudPits, point)) {
          cell.classList.add('mud-pit');
          appendIcon(cell, 'mud-icon', '🟤');
        }
        if (hasAt(gates, point)) {
          cell.classList.add('gate');
          if (keys.length === 0 && switches.length === 0) cell.classList.add('gate-open');
          appendIcon(cell, 'gate-icon', keys.length === 0 && switches.length === 0 ? '✨' : '🔒');
        }
        if (hasAt(keys, point)) {
          cell.classList.add('key');
          appendIcon(cell, 'key-icon', '🗝️');
        }
        if (hasAt(switches, point)) {
          cell.classList.add('switch');
          appendIcon(cell, 'switch-icon', '🔘');
        }
        if (hasAt(apples, point)) {
          cell.classList.add('apple');
          appendIcon(cell, 'apple-icon', '🍎');
        }
        if (hasAt(goldenApples, point)) {
          cell.classList.add('golden');
          appendIcon(cell, 'golden-icon', '🌟');
        }
        if (hasAt(megaApples, point)) {
          cell.classList.add('mega-apple');
          appendIcon(cell, 'mega-icon', '🍎');
          appendIcon(cell, 'mega-badge', '+3');
        }
        const mystery = mysteryApples.find(item => same(item, point));
        if (mystery) {
          cell.classList.add('mystery-apple');
          appendIcon(cell, 'mystery-icon', '❔');
        }
        if (portal && same(portal, point)) {
          cell.classList.add('portal');
          if (portalReady) cell.classList.add('portal-ready');
          appendIcon(cell, 'portal-icon', '🌀');
        }

        const wormIndex = worm.findIndex(part => same(part, point));
        if (wormIndex !== -1) {
          cell.classList.add('worm', wormIndex === 0 ? 'head' : 'worm-segment');
          cell.dataset.segment = String(wormIndex);
          const sprite = document.createElement('span');
          sprite.className = wormIndex === 0 ? 'worm-avatar' : 'worm-body-orb';
          sprite.dataset.segment = String(wormIndex);
          sprite.setAttribute('aria-hidden', 'true');
          if (wormIndex === 0) {
            sprite.innerHTML = `<svg viewBox="0 0 100 76" role="presentation" focusable="false">
              <defs>
                <linearGradient id="topWormSkin" x1="0" y1="0" x2=".85" y2="1"><stop offset="0" stop-color="#eaffbf"/><stop offset=".3" stop-color="#9bea78"/><stop offset=".68" stop-color="#4ec56a"/><stop offset="1" stop-color="#21884e"/></linearGradient>
                <radialGradient id="topWormShine" cx=".25" cy=".12" r=".8"><stop offset="0" stop-color="#fff" stop-opacity=".95"/><stop offset=".48" stop-color="#fff" stop-opacity=".16"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
              </defs>
              <ellipse cx="47" cy="63" rx="34" ry="7" fill="#23583a" opacity=".18"/>
              <path d="M13 48 C8 32 17 13 37 10 C55 2 79 11 86 28 C96 44 85 61 66 64 L31 64 C20 63 15 57 13 48Z" fill="url(#topWormSkin)" stroke="#257d49" stroke-width="3.2"/>
              <path d="M16 39 C20 19 38 8 56 10 C70 10 81 18 86 30 C70 20 48 16 33 27 C24 34 20 43 20 55Z" fill="url(#topWormShine)"/>
              <path d="M28 14 Q22 2 32 2 M45 10 Q45 -1 54 1" fill="none" stroke="#2e8b4f" stroke-width="2.6" stroke-linecap="round"/>
              <circle cx="32" cy="3" r="3" fill="#ff9eab"/><circle cx="54" cy="2" r="3" fill="#ff9eab"/>
              <ellipse cx="66" cy="29" rx="10" ry="13" fill="#fff" stroke="#256c42" stroke-width="2"/><ellipse cx="84" cy="33" rx="8.5" ry="11" fill="#fff" stroke="#256c42" stroke-width="2"/>
              <ellipse cx="69" cy="31" rx="4" ry="6" fill="#183b2e"/><ellipse cx="86" cy="35" rx="3.7" ry="5.2" fill="#183b2e"/>
              <circle cx="70" cy="28" r="1.8" fill="#fff"/><circle cx="87" cy="32" r="1.6" fill="#fff"/>
              <path d="M71 50 Q80 56 88 48" stroke="#5b4239" stroke-width="2.6" fill="none" stroke-linecap="round"/>
              <ellipse cx="53" cy="48" rx="5" ry="3" fill="#f18b93" opacity=".75"/>
            </svg>`;
          }
          cell.appendChild(sprite);
        }

        board.appendChild(cell);
      }
    }

    board.classList.toggle('board-won', Boolean(state.won));
    board.classList.toggle('board-lost', Boolean(state.lost));
    board.classList.toggle('board-has-specials', totalMegaApples > 0 || totalMysteryApples > 0);

    // Animate each worm segment from the previous segment's cell.
    const nextWormCells = [...board.querySelectorAll('.worm')];
    nextWormCells.forEach((element, index) => {
      const segmentIndex = Number(element.dataset.segment || 0);
      const from = previousWormRects.get(Math.max(0, segmentIndex - 1));
      if (!from) return;
      const to = element.getBoundingClientRect();
      const dx = from.left - to.left;
      const dy = from.top - to.top;
      if (!dx && !dy) return;
      element.style.transition = 'none';
      element.style.transform = `translate(${dx}px, ${dy}px)`;
      requestAnimationFrame(() => {
        element.style.transition = 'transform 170ms cubic-bezier(.2,.75,.25,1)';
        element.style.transform = 'translate(0, 0)';
      });
    });
    updateStats();
  }

  function updateStats() {
    if (!state) return;

    const normalCollected = totalApples - (state.apples || []).length;
    const collected = totalFruit - ((state.apples || []).length + (state.goldenApples || []).length + (state.megaApples || []).length + (state.mysteryApples || []).length);
    const remainingMoves = Math.max(0, originalLimit + Number(state.bonus_moves || 0) - Number(state.moves || 0));
    const moveLimit = originalLimit + Number(state.bonus_moves || 0);
    const progress = totalFruit > 0 ? Math.round((collected / totalFruit) * 100) : 100;
    const goldenCollected = Number(state.goldenApplesCollected || 0);
    const keysCollected = Number(state.keysCollected || 0);
    const remainingKeys = (state.keys || []).length;
    const remainingSwitches = (state.switches || []).length;
    const switchesActivated = Number(state.switchesActivated || 0);
    const remainingMega = (state.megaApples || []).length;
    const remainingMystery = (state.mysteryApples || []).length;
    const currentLength = (state.worm || []).length + Number(state.growthPending || 0);
    const remainingFruit = (state.apples || []).length + (state.goldenApples || []).length + (state.megaApples || []).length + (state.mysteryApples || []).length;
    const allObjectivesCollected = remainingFruit === 0 && remainingKeys === 0 && remainingSwitches === 0;
    const lengthGoalMet = !requiredLength || currentLength >= requiredLength;
    const portalReady = allObjectivesCollected && lengthGoalMet;
    const objectiveCurrent = requiredLength ? Math.min(currentLength, requiredLength) : collected;
    const objectiveTotal = Math.max(1, requiredLength || totalFruit);
    const objectivePercent = Math.min(100, Math.round((objectiveCurrent / objectiveTotal) * 100));

    scoreEl.textContent = Number(state.score || 0).toLocaleString();
    movesEl.textContent = Number(state.moves || 0);
    applesEl.textContent = `${normalCollected}/${totalApples}`;
    if (goldenApplesEl) goldenApplesEl.textContent = `${goldenCollected}/${totalGoldenApples}`;
    const reviveCount = document.getElementById('reviveCount');
    if (reviveCount) reviveCount.textContent = String(Math.max(0, 1 - revivesUsed));
    if (missionGoldenApples) missionGoldenApples.textContent = `${goldenCollected} found`;
    if (missionKeys) missionKeys.textContent = remainingKeys > 0 ? `${remainingKeys} left` : `${keysCollected} collected`;
    if (missionSwitches) missionSwitches.textContent = remainingSwitches > 0 ? `${remainingSwitches} left` : `${switchesActivated} activated · gate open`;
    const wormLengthEl = document.getElementById('wormLength');
    if (wormLengthEl) wormLengthEl.textContent = requiredLength ? `${currentLength}/${requiredLength}` : String(currentLength);
    const missionLength = document.getElementById('missionLength');
    if (missionLength) missionLength.textContent = `${currentLength}/${requiredLength}`;
    const missionMega = document.getElementById('missionMegaApples');
    if (missionMega) missionMega.textContent = remainingMega ? `${remainingMega} left` : `${Number(state.megaApplesCollected || 0)} collected`;
    const missionMystery = document.getElementById('missionMysteryApples');
    if (missionMystery) missionMystery.textContent = remainingMystery ? `${remainingMystery} left` : `${Number(state.mysteryApplesCollected || 0)} collected`;
    limitEl.textContent = moveLimit;
    missionApples.textContent = state.apples?.length ? `${state.apples.length} left` : 'All collected!';
    missionMoves.textContent = `${remainingMoves} left`;
    appleProgressText.textContent = `${collected} of ${totalFruit} collected`;
    appleProgressFill.style.width = `${progress}%`;
    appleProgressTrack.setAttribute('aria-valuenow', String(collected));
    if (missionProgressValue) missionProgressValue.textContent = `${objectiveCurrent}/${objectiveTotal}`;
    if (missionProgressFill) missionProgressFill.style.width = `${objectivePercent}%`;
    if (missionProgressMeter) {
      missionProgressMeter.setAttribute('aria-valuemax', String(objectiveTotal));
      missionProgressMeter.setAttribute('aria-valuenow', String(objectiveCurrent));
    }
    if (missionObjectiveCopy) missionObjectiveCopy.textContent = requiredLength
      ? 'Worm length goal'
      : `${collected} of ${totalFruit} fruit collected`;
    if (portalStateText) {
      portalStateText.textContent = portalReady ? 'Portal awake' : 'Portal sleeping';
      portalStateText.classList.toggle('is-ready', portalReady);
    }
    setControlsDisabled(busy);

    if (state.won) setStatus('Portal reached! Amazing work!', 'won');
    else if (state.lost) setStatus('Oh no! Your worm needs another try.', 'lost');
    else if (remainingKeys > 0) setStatus('Find the key to unlock the gate!', 'playing');
    else if (remainingSwitches > 0) setStatus('Activate the switch to open the gate!', 'playing');
    else if (state.apples?.length === 0 && state.goldenApples?.length === 0 && state.megaApples?.length === 0 && state.mysteryApples?.length === 0 && currentLength < requiredLength) setStatus(`Grow to ${requiredLength} segments before the portal!`, 'playing');
    else if (state.apples?.length === 0 && state.goldenApples?.length === 0 && state.megaApples?.length === 0 && state.mysteryApples?.length === 0 && remainingKeys === 0 && remainingSwitches === 0) setStatus('All fruit collected — find the portal!', 'ready');
    else setStatus('Collect every apple and keep wiggling!', 'playing');
  }

  function playTone(freq = 520, duration = 0.09) {
    if (!sound) return;
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      if (audioCtx.state === 'suspended') audioCtx.resume();
      const oscillator = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      oscillator.type = 'sine';
      oscillator.frequency.value = freq;
      oscillator.connect(gain);
      gain.connect(audioCtx.destination);
      gain.gain.setValueAtTime(0.045, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
      oscillator.start();
      oscillator.stop(audioCtx.currentTime + duration);
    } catch (_) {
      // Audio is optional; the game remains playable without it.
    }
  }

  async function post(url, data = {}) {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(data),
    });
    const json = await response.json().catch(() => ({ message: 'The server returned an unreadable response.' }));
    if (!response.ok) throw new Error(json.message || 'The request could not be completed.');
    return json;
  }

  function showModal(title, message, emoji = '🎉', stars = '★★★', primary = 'Continue', callback = null, showRestart = true) {
    modal.classList.remove('is-victory', 'is-failure');
    document.getElementById('gameResultStats').hidden = true;
    document.getElementById('modalSecondary').hidden = true;
    document.getElementById('modalConfetti').hidden = true;
    document.getElementById('resultCoinsCard').hidden = false;
    document.getElementById('modalEyebrow').textContent = 'APPLE WORM ADVENTURE';
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalMessage').textContent = message;
    document.getElementById('modalEmoji').textContent = emoji;
    document.getElementById('modalStars').textContent = stars;
    document.getElementById('modalPrimary').textContent = primary;
    document.getElementById('modalPrimary').onclick = callback || (() => { location.href = '/levels'; });
    document.getElementById('modalRestart').hidden = !showRestart;
    document.getElementById('modalRestart').textContent = 'Play again';
    document.getElementById('modalRestart').onclick = restart;
    modal.classList.remove('hidden');
    document.getElementById('modalPrimary').focus();
  }

  function collectedFruitCount() {
    return totalFruit - ((state?.apples || []).length + (state?.goldenApples || []).length + (state?.megaApples || []).length + (state?.mysteryApples || []).length);
  }

  function fillResultStats({ score = 0, moves = 0, collected = 0, total = totalFruit, goldenCollected = 0, goldenTotal = totalGoldenApples, coins = null }) {
    document.getElementById('resultScore').textContent = Number(score).toLocaleString();
    document.getElementById('resultMoves').textContent = String(moves);
    document.getElementById('resultApples').textContent = `${collected}/${total}`;
    document.getElementById('resultGoldenApples').textContent = `${goldenCollected}/${goldenTotal}`;
    document.getElementById('resultCoinsCard').hidden = coins === null;
    if (coins !== null) document.getElementById('resultCoins').textContent = `+${Number(coins).toLocaleString()} 🪙`;
    document.getElementById('gameResultStats').hidden = false;
  }

  function showVictoryModal(result) {
    showModal(
      'Amazing work, little worm!',
      result.message || 'You collected every apple and found the portal!',
      '🏆',
      '★'.repeat(result.stars) + '☆'.repeat(Math.max(0, 3 - result.stars)),
      hasNextLevel ? 'Next adventure' : 'Back to level map',
      () => { location.href = nextLevelUrl; },
      true
    );
    modal.classList.add('is-victory');
    document.getElementById('modalEyebrow').textContent = 'LEVEL COMPLETE';
    document.getElementById('modalConfetti').hidden = false;
    document.getElementById('modalRestart').textContent = 'Replay this level';
    document.getElementById('modalRestart').onclick = () => { location.href = replayLevelUrl; };
    const collected = collectedFruitCount();
    fillResultStats({
      score: result.score,
      moves: state?.moves || 0,
      collected,
      total: totalFruit,
      goldenCollected: result.golden_apples_collected || 0,
      goldenTotal: totalGoldenApples,
      coins: result.coins_earned,
    });
    if (result.achievements_unlocked?.length) {
      const names = result.achievements_unlocked.map(item => `${item.icon} ${item.name}`).join(' · ');
      document.getElementById('modalMessage').textContent += ` Achievement unlocked: ${names}.`;
    }
    playTone(880, 0.22);
  }

  function showFailureModal(reason) {
    const collected = collectedFruitCount();
    showModal(
      'Oops! A little bump in the trail.',
      reason || 'Your worm had a little mishap. Give it another try!',
      '🥺',
      '☆☆☆',
      'Try again',
      restart,
      false
    );
    modal.classList.add('is-failure');
    document.getElementById('modalEyebrow').textContent = 'ADVENTURE PAUSED';
    fillResultStats({
      score: state?.score || 0,
      moves: state?.moves || 0,
      collected,
      total: totalFruit,
      goldenCollected: state?.goldenApplesCollected || 0,
      goldenTotal: totalGoldenApples,
      coins: null,
    });
    const reviveButton = document.getElementById('modalSecondary');
    if (revivesUsed < 1) {
      reviveButton.hidden = false;
      reviveButton.textContent = '🐛 Revive worm · Ad demo';
      reviveButton.onclick = () => claimReward('revive');
    }
  }

  function applyState(nextState) {
    state = nextState;
    alive = !state.lost;
    gameOver = Boolean(state.won || state.lost);
    render();
    updateStats();
  }

  async function completeLevel() {
    gameOver = true;
    setControlsDisabled(true);
    setStatus('Saving your adventure…', 'saving');
    try {
      const result = await post(`/game/${sessionId}/complete`);
      showVictoryModal(result);
    } catch (error) {
      showModal('Completion could not be saved', error.message, '⚠️', '☆☆☆', 'Back to levels', () => { location.href = '/levels'; }, false);
    }
  }

  async function move(direction) {
    if (!alive || gameOver || busy || paused) return;
    busy = true;
    setControlsDisabled(true);
    setNotice('');
    try {
      const previousAppleCount = state?.apples?.length ?? totalApples;
      const previousGoldenCount = state?.goldenApples?.length ?? totalGoldenApples;
      const previousMegaCount = state?.megaApples?.length ?? totalMegaApples;
      const previousMysteryCount = state?.mysteryApples?.length ?? totalMysteryApples;
      const previousKeyCount = state?.keys?.length ?? totalKeys;
      const previousSwitchCount = state?.switches?.length ?? totalSwitches;
      const result = await post(`/game/${sessionId}/move`, { direction });
      applyState(result.state);
      if (result.blocked) {
        setNotice(result.reason || 'That gate is locked. Find its key!', 'warning');
        playTone(260, 0.1);
      } else if (result.failed) {
        playTone(170, 0.16);
        showFailureModal(result.reason || 'Your worm could not move there.');
      } else if (result.won) {
        await completeLevel();
      } else {
        const collectedApple = (result.state.apples?.length ?? totalApples) < previousAppleCount;
        const collectedGolden = (result.state.goldenApples?.length ?? totalGoldenApples) < previousGoldenCount;
        const collectedMega = (result.state.megaApples?.length ?? totalMegaApples) < previousMegaCount;
        const collectedMystery = (result.state.mysteryApples?.length ?? totalMysteryApples) < previousMysteryCount;
        const collectedKey = (result.state.keys?.length ?? totalKeys) < previousKeyCount;
        const activatedSwitch = (result.state.switches?.length ?? totalSwitches) < previousSwitchCount;
        if ((collectedKey || activatedSwitch) && !collectedApple && !collectedGolden) {
          playTone(920, 0.14);
          setNotice(collectedKey ? '🗝️ Key collected! The gate is now open!' : '🔘 Switch activated! The gate is now open!', 'success');
        } else if (collectedApple || collectedGolden || collectedMega || collectedMystery) {
          playTone(760, 0.11);
          board.classList.remove('apple-collected');
          void board.offsetWidth;
          board.classList.add('apple-collected');
          let fruitNotice = 'Yum! Apple collected — your worm grew!';
          if (collectedGolden) fruitNotice = '✨ Golden apple! +50 bonus points and your worm grew!';
          if (collectedMega) fruitNotice = '🍎 Mega apple! Your worm gains three segments and +10 points!';
          if (collectedMystery) {
            const effect = result.state.lastMysteryEffect;
            fruitNotice = effect === 'extra_growth' ? '❔ Mystery apple! +2 bonus segments!' : effect === 'bonus_moves' ? '❔ Mystery apple! +5 extra moves!' : '❔ Mystery apple! Bonus points!';
          }
          setNotice(fruitNotice, 'success');
          if (collectedKey) setNotice('🗝️ Key collected! The gate is now open!', 'success');
          if (activatedSwitch) setNotice('🔘 Switch activated! The gate is now open!', 'success');
          if (result.achievements_unlocked?.length) {
            const labels = result.achievements_unlocked.map(item => `${item.icon} ${item.name}${item.coins_awarded ? ` (+${item.coins_awarded} coins)` : ''}`).join(' · ');
            setNotice(`🏆 Achievement unlocked: ${labels}`, 'success');
          }
          window.setTimeout(() => board.classList.remove('apple-collected'), 700);
        } else {
          playTone(480, 0.07);
        }
      }
    } catch (error) {
      setNotice(error.message, 'error');
    } finally {
      busy = false;
      setControlsDisabled(false);
    }
  }

  async function restart() {
    if (busy) return;
    paused = false;
    app.classList.remove('is-paused');
    document.getElementById('pauseOverlay')?.setAttribute('hidden', '');
    const pauseButton = document.getElementById('pauseBtn');
    if (pauseButton) { pauseButton.innerHTML = '<span>Ⅱ</span> Pause'; pauseButton.setAttribute('aria-pressed', 'false'); }
    busy = true;
    setControlsDisabled(true);
    setNotice('Restarting level…');
    try {
      const result = await post(`/game/${sessionId}/restart`);
      applyState(result.state);
      modal.classList.add('hidden');
      setNotice('Fresh start! You can do this.', 'success');
      playTone(540);
    } catch (error) {
      setNotice(error.message, 'error');
    } finally {
      busy = false;
      setControlsDisabled(false);
    }
  }

  const keyDirection = {
    ArrowUp: 'up', w: 'up', W: 'up',
    ArrowDown: 'down', s: 'down', S: 'down',
    ArrowLeft: 'left', a: 'left', A: 'left',
    ArrowRight: 'right', d: 'right', D: 'right',
  };

  document.addEventListener('keydown', event => {
    const target = event.target;
    if (target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))) return;
    if (modal && !modal.classList.contains('hidden')) {
      if (event.key === 'Escape') modal.classList.add('hidden');
      return;
    }
    if (event.key === 'Escape' && !gameOver) { togglePause(); return; }
    const direction = keyDirection[event.key];
    if (direction && !paused) {
      event.preventDefault();
      move(direction);
    }
  });

  document.querySelectorAll('[data-dir]').forEach(button => {
    button.addEventListener('click', () => move(button.dataset.dir));
  });

  board.addEventListener('pointerdown', event => {
    if (event.pointerType === 'mouse') return;
    touchStart = { x: event.clientX, y: event.clientY };
  }, { passive: true });

  board.addEventListener('pointerup', event => {
    if (!touchStart) return;
    const dx = event.clientX - touchStart.x;
    const dy = event.clientY - touchStart.y;
    touchStart = null;
    if (Math.max(Math.abs(dx), Math.abs(dy)) < 24) return;
    move(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up'));
  }, { passive: true });

  board.addEventListener('pointercancel', () => { touchStart = null; }, { passive: true });

  document.getElementById('restartBtn').addEventListener('click', restart);

  function togglePause(force = null) {
    if (gameOver || busy) return;
    paused = force === null ? !paused : Boolean(force);
    app.classList.toggle('is-paused', paused);
    const overlay = document.getElementById('pauseOverlay');
    if (overlay) overlay.hidden = !paused;
    const button = document.getElementById('pauseBtn');
    if (button) {
      button.innerHTML = paused ? '<span>▶</span> Resume' : '<span>Ⅱ</span> Pause';
      button.setAttribute('aria-pressed', String(paused));
    }
    setStatus(paused ? 'Adventure paused' : 'Back to the adventure!', paused ? 'paused' : 'playing');
    setControlsDisabled(false);
  }

  document.getElementById('pauseBtn')?.addEventListener('click', () => togglePause());
  document.getElementById('resumeGameBtn')?.addEventListener('click', () => togglePause(false));
  document.getElementById('modalClose').addEventListener('click', () => modal.classList.add('hidden'));

  soundButton.addEventListener('click', event => {
    sound = !sound;
    event.currentTarget.setAttribute('aria-pressed', String(sound));
    event.currentTarget.innerHTML = `<span>♫</span> Sound ${sound ? 'on' : 'off'}`;
    playTone(620);
  });

  document.getElementById('hintBtn').addEventListener('click', async () => {
    if (busy || gameOver || paused) return;
    busy = true;
    setControlsDisabled(true);
    try {
      const result = await post(`/game/${sessionId}/hint`);
      showModal('A little hint!', result.hint, '💡', '☆☆☆', 'Back to puzzle', () => modal.classList.add('hidden'), false);
    } catch (error) {
      setNotice(error.message, 'error');
    } finally {
      busy = false;
      setControlsDisabled(false);
    }
  });

  async function claimReward(action) {
    if (busy || paused) return;
    busy = true;
    setControlsDisabled(true);
    setNotice('Preparing development reward…');
    try {
      const result = await post(`/game/${sessionId}/reward`, {
        action,
        claim_token: crypto.randomUUID(),
      });
      if (result.state) applyState(result.state);
      if (action === 'revive') {
        revivesUsed += 1;
        updateStats();
        modal.classList.add('hidden');
        setNotice('Your worm is back! Keep going.', 'success');
        alive = true;
        gameOver = false;
        playTone(720);
      } else {
        const achievementText = result.achievements_unlocked?.length
          ? ` Achievement unlocked: ${result.achievements_unlocked.map(item => `${item.icon} ${item.name}`).join(' · ')}.`
          : '';
        showModal('Demo reward', result.message + achievementText, '🎁', '☆☆☆', 'Back to game', () => modal.classList.add('hidden'), false);
      }
    } catch (error) {
      setNotice(error.message, 'error');
    } finally {
      busy = false;
      setControlsDisabled(false);
    }
  }

  document.querySelectorAll('[data-reward]').forEach(button => {
    button.addEventListener('click', () => claimReward(button.dataset.reward));
  });

  setControlsDisabled(true);
  setStatus('Loading your puzzle…', 'loading');
  fetch(`/game/${sessionId}/state`, {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  })
    .then(response => {
      if (!response.ok) throw new Error('Unable to load this game session. Refresh the page or return to the level map.');
      return response.json();
    })
    .then(result => {
      if (!result.state) throw new Error('The game session has no saved state.');
      applyState(result.state);
      setControlsDisabled(false);
    })
    .catch(error => {
      setStatus('Puzzle could not be loaded', 'lost');
      setNotice(error.message, 'error');
    });
})();
