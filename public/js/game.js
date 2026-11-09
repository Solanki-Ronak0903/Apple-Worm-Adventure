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
  const limitEl = document.getElementById('moveLimit');
  const missionApples = document.getElementById('missionApples');
  const missionMoves = document.getElementById('missionMoves');
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
  let state = null;
  let alive = true;
  let gameOver = false;
  let busy = false;
  let sound = false;
  let audioCtx = null;
  let touchStart = null;

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
      button.disabled = Boolean(isBusy) || gameOver;
    });
    const restartButton = document.getElementById('restartBtn');
    const hintButton = document.getElementById('hintBtn');
    if (restartButton) restartButton.disabled = Boolean(isBusy) || Boolean(state?.won);
    if (hintButton) hintButton.disabled = Boolean(isBusy) || gameOver;
    document.querySelectorAll('[data-reward]').forEach(button => {
      const isRevive = button.dataset.reward === 'revive';
      const failedRevive = Boolean(state?.lost) && isRevive && revivesUsed < 1;
      button.disabled = Boolean(isBusy) || (gameOver && !failedRevive) || (isRevive && revivesUsed >= 1);
      if (button.disabled) button.setAttribute('aria-disabled', 'true');
      else button.removeAttribute('aria-disabled');
    });
  }

  function render() {
    if (!state) return;

    board.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
    board.style.setProperty('--board-cols', cols);
    board.style.setProperty('--board-rows', rows);
    board.innerHTML = '';

    const worm = state.worm || [];
    const apples = state.apples || [];
    const goldenApples = state.goldenApples || [];
    const obstacles = cfg.obstacles || [];
    const portal = cfg.portal || null;
    const portalReady = apples.length === 0;

    for (let y = 0; y < rows; y += 1) {
      for (let x = 0; x < cols; x += 1) {
        const point = { x, y };
        const cell = document.createElement('div');
        cell.className = 'cell game-cell';
        cell.setAttribute('aria-hidden', 'true');
        cell.dataset.x = String(x);
        cell.dataset.y = String(y);

        if ((x + y) % 2 === 0) cell.classList.add('cell-soft');
        if (obstacles.some(item => same(item, point))) cell.classList.add('obstacle');
        if (apples.some(item => same(item, point))) cell.classList.add('apple');
        if (goldenApples.some(item => same(item, point))) cell.classList.add('golden');
        if (portal && same(portal, point)) {
          cell.classList.add('portal');
          if (portalReady) cell.classList.add('portal-ready');
        }

        const wormIndex = worm.findIndex(part => same(part, point));
        if (wormIndex !== -1) {
          cell.classList.add('worm', wormIndex === 0 ? 'head' : 'worm-segment');
          cell.dataset.segment = String(wormIndex);
          if (wormIndex > 0) cell.style.setProperty('--segment-tone', String(wormIndex % 3));
        }

        board.appendChild(cell);
      }
    }

    board.classList.toggle('board-won', Boolean(state.won));
    board.classList.toggle('board-lost', Boolean(state.lost));
    updateStats();
  }

  function updateStats() {
    if (!state) return;

    const collected = Math.max(0, totalApples - (state.apples || []).length);
    const remainingMoves = Math.max(0, originalLimit + Number(state.bonus_moves || 0) - Number(state.moves || 0));
    const moveLimit = originalLimit + Number(state.bonus_moves || 0);
    const progress = totalApples > 0 ? Math.round((collected / totalApples) * 100) : 100;
    const goldenCollected = Number(state.goldenApplesCollected || 0);

    scoreEl.textContent = Number(state.score || 0).toLocaleString();
    movesEl.textContent = Number(state.moves || 0);
    applesEl.textContent = `${collected}/${totalApples}`;
    if (goldenApplesEl) goldenApplesEl.textContent = `${goldenCollected}/${totalGoldenApples}`;
    if (missionGoldenApples) missionGoldenApples.textContent = `${goldenCollected} found`;
    limitEl.textContent = moveLimit;
    missionApples.textContent = state.apples?.length ? `${state.apples.length} left` : 'All collected!';
    missionMoves.textContent = `${remainingMoves} left`;
    appleProgressText.textContent = `${collected} of ${totalApples} collected`;
    appleProgressFill.style.width = `${progress}%`;
    appleProgressTrack.setAttribute('aria-valuenow', String(collected));
    setControlsDisabled(busy);

    if (state.won) setStatus('Portal reached! Amazing work!', 'won');
    else if (state.lost) setStatus('Oh no! Your worm needs another try.', 'lost');
    else if (state.apples?.length === 0) setStatus('All apples collected — find the portal!', 'ready');
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

  function fillResultStats({ score = 0, moves = 0, collected = 0, total = totalApples, goldenCollected = 0, goldenTotal = totalGoldenApples, coins = null }) {
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
    const collected = Math.max(0, totalApples - (state?.apples?.length || 0));
    fillResultStats({
      score: result.score,
      moves: state?.moves || 0,
      collected,
      total: totalApples,
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
    const collected = Math.max(0, totalApples - (state?.apples?.length || 0));
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
      total: totalApples,
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
    if (!alive || gameOver || busy) return;
    busy = true;
    setControlsDisabled(true);
    setNotice('');
    try {
      const previousAppleCount = state?.apples?.length ?? totalApples;
      const previousGoldenCount = state?.goldenApples?.length ?? totalGoldenApples;
      const result = await post(`/game/${sessionId}/move`, { direction });
      applyState(result.state);
      if (result.failed) {
        playTone(170, 0.16);
        showFailureModal(result.reason || 'Your worm could not move there.');
      } else if (result.won) {
        await completeLevel();
      } else {
        const collectedApple = (result.state.apples?.length ?? totalApples) < previousAppleCount;
        const collectedGolden = (result.state.goldenApples?.length ?? totalGoldenApples) < previousGoldenCount;
        if (collectedApple || collectedGolden) {
          playTone(760, 0.11);
          board.classList.remove('apple-collected');
          void board.offsetWidth;
          board.classList.add('apple-collected');
          setNotice(collectedGolden ? '✨ Golden apple! +50 bonus points and your worm grew!' : 'Yum! Apple collected — your worm grew!', 'success');
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
    const direction = keyDirection[event.key];
    if (direction) {
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
  document.getElementById('modalClose').addEventListener('click', () => modal.classList.add('hidden'));

  soundButton.addEventListener('click', event => {
    sound = !sound;
    event.currentTarget.setAttribute('aria-pressed', String(sound));
    event.currentTarget.innerHTML = `<span>♫</span> Sound ${sound ? 'on' : 'off'}`;
    playTone(620);
  });

  document.getElementById('hintBtn').addEventListener('click', async () => {
    if (busy || gameOver) return;
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
    if (busy) return;
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
