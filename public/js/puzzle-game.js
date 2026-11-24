(() => {
  'use strict';

  const app = document.getElementById('puzzleGame');
  if (!app) return;

  const layout = JSON.parse(atob(app.dataset.layout || 'e30='));
  const sessionId = app.dataset.sessionId;
  const canvas = document.getElementById('puzzleCanvas');
  const ctx = canvas.getContext('2d');
  const tile = 80;
  const moveAnimationMs = 280;
  const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
  const width = Number(layout.width || 14);
  const height = Number(layout.height || 8);
  const hintTotal = Math.max(1, Number(app.dataset.hintTotal || 4));
  const terrain = new Set((layout.terrain || []).map(keyOf));
  const spikes = new Set((layout.spikes || []).map(keyOf));
  const applesTotal = (layout.apples || []).length + (layout.goldenApples || []).length;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const ui = {
    moves: document.getElementById('puzzleMoves'),
    movesLeft: document.getElementById('puzzleMovesLeft'),
    movesLeftCard: document.getElementById('puzzleMovesLeftCard'),
    apples: document.getElementById('puzzleApples'),
    length: document.getElementById('puzzleLength'),
    pushes: document.getElementById('puzzlePushes'),
    switches: document.getElementById('puzzleSwitches'),
    status: document.getElementById('puzzleStatus'),
    progress: document.getElementById('puzzleProgressFill'),
    toast: document.getElementById('puzzleToast'),
    pause: document.getElementById('puzzlePauseOverlay'),
    pauseButton: document.getElementById('puzzlePause'),
    result: document.getElementById('puzzleResult'),
    error: document.getElementById('puzzleError'),
    hintPanel: document.getElementById('puzzleHintPanel'),
    hintStage: document.getElementById('puzzleHintStage'),
    hintText: document.getElementById('puzzleHintText'),
    hintCount: document.getElementById('puzzleHintCount'),
    hintButton: document.getElementById('puzzleHint'),
    rewardStrip: document.getElementById('puzzleRewardStrip'),
    extraMovesReward: document.getElementById('puzzleExtraMovesReward'),
    doubleCoinsReward: document.getElementById('puzzleDoubleCoinsReward'),
    reviveReward: document.getElementById('puzzleReviveReward'),
  };

  const rewardPreviewEnabled = app.dataset.rewardPreviewEnabled === '1';
  const levelNumber = Number(app.dataset.levelNumber || 1);

  let state = null;
  let previousWorm = null;
  let previousBlocks = null;
  let animationStarted = 0;
  let moveAnimationDuration = moveAnimationMs;
  let turnPulseUntil = 0;
  let busy = false;
  let paused = false;
  let toastTimer = null;
  let facing = layout.start?.facing || 'right';
  let particles = [];
  let completed = false;
  let completionTimer = null;

  const directions = {
    ArrowLeft: 'left', KeyA: 'left',
    ArrowRight: 'right', KeyD: 'right',
    ArrowUp: 'up', KeyW: 'up',
    ArrowDown: 'down', KeyS: 'down',
  };

  function keyOf(point) { return `${Number(point.x)}:${Number(point.y)}`; }
  function pointAt(set, x, y) { return set.has(`${x}:${y}`); }

  // The first point is always the head and the second point is the neck.
  // Deriving the direction from the saved body prevents a resumed level from
  // reusing its original spawn direction after the worm has turned.
  function inferFacingFromWorm(worm, fallback = 'right') {
    if (!Array.isArray(worm) || worm.length < 2) return fallback;
    const dx = Number(worm[0].x) - Number(worm[1].x);
    const dy = Number(worm[0].y) - Number(worm[1].y);
    if (Math.abs(dx) > Math.abs(dy)) return dx < 0 ? 'left' : 'right';
    if (Math.abs(dy) > 0) return dy < 0 ? 'up' : 'down';
    return fallback;
  }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function ease(t) {
    if (prefersReducedMotion) return 1;
    const progress = Math.max(0, Math.min(1, t));
    return progress < .5 ? 4 * progress * progress * progress : 1 - Math.pow(-2 * progress + 2, 3) / 2;
  }

  async function request(url, payload = {}) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'The request could not be completed.');
    return data;
  }

  function toast(message) {
    ui.toast.textContent = message;
    ui.toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => ui.toast.classList.remove('show'), 1500);
  }

  function makeClaimToken() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    const bytes = new Uint8Array(16);
    if (window.crypto?.getRandomValues) window.crypto.getRandomValues(bytes);
    else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
  }

  function updateRewardPrompts() {
    if (!rewardPreviewEnabled || !state) {
      ui.rewardStrip?.classList.add('hidden');
      if (ui.reviveReward) ui.reviveReward.classList.add('hidden');
      return;
    }

    const baseMoveLimit = Number(state.move_limit ?? layout.moveLimit ?? app.dataset.moveLimit ?? (Number(layout.parMoves || 10) + 5));
    const movesLeft = Math.max(0, baseMoveLimit + Number(state.bonus_moves || 0) - Number(state.moves || 0));
    const hasObjectives = (state.apples || []).length + (state.goldenApples || []).length + (state.switches || []).length;
    const activeRun = !state.won && !state.lost;

    const showExtraMoves = levelNumber >= 6 && activeRun && movesLeft <= 3 && !state.extra_moves_claimed;
    const showDoubleCoins = levelNumber >= 6
      && app.dataset.doubleCoinsEligible === '1'
      && activeRun
      && hasObjectives === 0
      && !state.double_coins_claimed;

    if (ui.extraMovesReward) ui.extraMovesReward.classList.toggle('hidden', !showExtraMoves);
    if (ui.doubleCoinsReward) ui.doubleCoinsReward.classList.toggle('hidden', !showDoubleCoins);
    ui.rewardStrip?.classList.toggle('hidden', !(showExtraMoves || showDoubleCoins));

    if (ui.reviveReward) {
      const canRevive = levelNumber >= 11
        && app.dataset.reviveRewardEligible === '1'
        && state.lost
        && !state.revive_claimed;
      ui.reviveReward.classList.toggle('hidden', !canRevive);
    }
  }

  async function claimReward(action, button) {
    if (!rewardPreviewEnabled || !app.dataset.rewardUrl || !button || button.disabled) return;

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Processing preview…';
    try {
      const result = await request(app.dataset.rewardUrl, {
        action,
        claim_token: makeClaimToken(),
      });

      if (result.state) state = result.state;
      if (action === 'revive') {
        completed = false;
        previousWorm = null;
        previousBlocks = null;
        animationStarted = 0;
        ui.error?.classList.add('hidden');
      }

      updateHud();
      const previewNote = result.simulated
        ? ' Development preview only — no real video ad was played.'
        : '';
      const messages = {
        extra_moves: 'Five extra moves added.',
        double_coins: 'Double completion coins activated.',
        revive: 'The worm is back at its last safe position.',
      };
      toast((messages[action] || 'Optional reward applied.') + previewNote);
    } catch (error) {
      toast(error.message || 'The optional reward could not be applied.');
    } finally {
      button.textContent = originalText;
      button.disabled = false;
      updateRewardPrompts();
    }
  }

  function updateHud() {
    const remainingApples = (state.apples || []).length + (state.goldenApples || []).length;
    const collected = applesTotal - remainingApples;
    const baseMoveLimit = Number(state.move_limit ?? layout.moveLimit ?? app.dataset.moveLimit ?? (Number(layout.parMoves || 10) + 5));
    const bonusMoves = Math.max(0, Number(state.bonus_moves || 0));
    const movesLeft = Math.max(0, baseMoveLimit + bonusMoves - Number(state.moves || 0));
    ui.moves.textContent = String(state.moves || 0);
    if (ui.movesLeft) ui.movesLeft.textContent = String(movesLeft);
    if (ui.movesLeftCard) ui.movesLeftCard.classList.toggle('is-low', movesLeft <= 2);
    ui.apples.textContent = `${collected}/${applesTotal}`;
    ui.length.textContent = String((state.worm || []).length);
    ui.pushes.textContent = String(state.pushes || 0);
    ui.progress.style.width = `${applesTotal ? (collected / applesTotal) * 100 : 100}%`;
    const applesLeft = (state.apples || []).length + (state.goldenApples || []).length;
    const switchesLeft = (state.switches || []).length;
    ui.status.textContent = state.won
      ? 'The portal is open!'
      : movesLeft === 0
        ? 'Move limit reached · restart and plan a more efficient route.'
        : movesLeft <= 2
          ? `${movesLeft} move${movesLeft === 1 ? '' : 's'} left · choose the next turn carefully.`
          : applesLeft
            ? `${applesLeft} apple${applesLeft === 1 ? '' : 's'} left · keep a body segment supported.`
            : switchesLeft
              ? 'Find the rune switch to open the portal route.'
              : 'All objectives complete · guide the worm to the portal.';
    updateRewardPrompts();
  }

  function drawBackground(time) {
    const theme = layout.world?.theme || 'meadow';
    if (theme === 'sky') {
      drawSkyTempleBackground(time);
      return;
    }
    const palettes = {
      meadow: ['#a9e0ea', '#e0f3d4', '#9dcd9b', '#75b984'],
      forest: ['#a9d8d0', '#e4f0d7', '#91c09a', '#5d9a78'],
      ruins: ['#c5d9d2', '#f0e5c9', '#a3b79b', '#7d9a84'],
      crystal: ['#a9d7ed', '#e8e8f6', '#a2c5dd', '#7c9bc2'],
      sky: ['#9fd7ef', '#f6edc9', '#a8c9a5', '#82b78e'],
    };
    const colors = palettes[theme] || palettes.meadow;
    const sky = ctx.createLinearGradient(0, 0, 0, canvas.height);
    sky.addColorStop(0, colors[0]);
    sky.addColorStop(.78, colors[1]);
    sky.addColorStop(1, '#d2e9c8');
    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Soft sun and slow drifting clouds.
    ctx.save();
    ctx.globalAlpha = .9;
    ctx.fillStyle = '#fff2b2';
    ctx.beginPath();
    ctx.arc(940, 100, 48, 0, Math.PI * 2);
    ctx.fill();
    for (let i = 0; i < 4; i++) {
      const x = ((i * 315 + 120 + time * (.007 + i * .001)) % 1320) - 100;
      const y = 92 + (i % 2) * 82;
      drawCloud(x, y, .78 + (i % 2) * .18);
    }
    ctx.restore();

    ctx.fillStyle = colors[2];
    drawHill(-70, 430, 320, 175, .85);
    drawHill(190, 442, 340, 184, .9);
    drawHill(490, 425, 360, 190, 1);
    drawHill(830, 450, 390, 185, .9);
    ctx.fillStyle = colors[3];
    ctx.globalAlpha = .28;
    drawHill(70, 510, 390, 130, 1.2);
    drawHill(540, 510, 450, 150, 1.1);
    drawHill(1000, 505, 380, 135, 1.15);
    ctx.globalAlpha = 1;

    // Decorative foliage stays behind the puzzle tiles.
    for (let i = 0; i < 9; i++) {
      const x = 35 + i * 138;
      const y = 468 + (i % 3) * 18;
      ctx.fillStyle = '#7d9a68';
      ctx.fillRect(x - 5, y, 10, 48);
      ctx.fillStyle = i % 2 ? '#72b17b' : '#83bd82';
      ctx.beginPath(); ctx.arc(x, y - 12, 25, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.arc(x - 18, y + 2, 17, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.arc(x + 17, y + 3, 17, 0, Math.PI * 2); ctx.fill();
    }
  }

  function drawSkyTempleBackground(time) {
    const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, '#79bfe7');
    gradient.addColorStop(.48, '#c7e8f4');
    gradient.addColorStop(1, '#f0eaff');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // A warm celestial beacon gives Sky Temple a different mood from the
    // green meadow worlds. The halo is decorative; it does not affect play.
    const halo = ctx.createRadialGradient(940, 105, 12, 940, 105, 100);
    halo.addColorStop(0, 'rgba(255,250,211,.95)');
    halo.addColorStop(.55, 'rgba(255,239,177,.38)');
    halo.addColorStop(1, 'rgba(255,239,177,0)');
    ctx.fillStyle = halo;
    ctx.beginPath(); ctx.arc(940, 105, 100, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#fff0b8';
    ctx.beginPath(); ctx.arc(940, 105, 43, 0, Math.PI * 2); ctx.fill();

    // Distant cloud streams drift at different speeds for depth.
    ctx.save();
    ctx.globalAlpha = .85;
    for (let i = 0; i < 5; i++) {
      const x = ((i * 295 + 80 + time * (.004 + i * .0012)) % 1420) - 140;
      const y = 92 + (i % 3) * 76;
      drawCloud(x, y, .7 + (i % 2) * .2);
    }
    ctx.restore();

    // Floating temple towers replace the meadow's trees and rolling hills.
    drawTemplePillar(108, 350, 164, 48, 'rgba(93,119,169,.22)');
    drawTemplePillar(835, 310, 205, 58, 'rgba(98,116,165,.19)');
    drawTemplePillar(1015, 385, 132, 42, 'rgba(110,127,177,.2)');
    drawTemplePillar(420, 405, 115, 38, 'rgba(117,133,180,.16)');

    // Cloud islands sit behind the puzzle board; none are collision geometry.
    drawFloatingCloudIsland(-55, 500, 330, .82);
    drawFloatingCloudIsland(275, 535, 360, .72);
    drawFloatingCloudIsland(710, 505, 385, .88);
    drawFloatingCloudIsland(1030, 550, 300, .7);

    // Tiny temple lights make the world feel magical without visual clutter.
    for (let i = 0; i < 18; i++) {
      const x = (i * 137 + 41) % 1110;
      const y = 245 + ((i * 61) % 220);
      const pulse = .35 + ((Math.sin(time * .0015 + i) + 1) * .22);
      ctx.fillStyle = `rgba(255,255,255,${pulse})`;
      ctx.beginPath(); ctx.arc(x, y, 1.5 + (i % 3) * .45, 0, Math.PI * 2); ctx.fill();
    }
  }

  function drawTemplePillar(x, y, height, width, color) {
    ctx.save();
    ctx.fillStyle = color;
    roundRect(x, y + 14, width, height - 14, 7); ctx.fill();
    roundRect(x - 9, y + 4, width + 18, 18, 5); ctx.fill();
    roundRect(x - 5, y + height - 13, width + 10, 18, 5); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.16)';
    ctx.fillRect(x + 8, y + 23, 4, height - 50);
    ctx.restore();
  }

  function drawFloatingCloudIsland(x, y, width, alpha = .8) {
    ctx.save();
    ctx.globalAlpha = alpha;
    ctx.fillStyle = 'rgba(255,255,255,.72)';
    ctx.beginPath();
    ctx.ellipse(x + width * .5, y + 10, width * .5, 30, 0, 0, Math.PI * 2);
    ctx.ellipse(x + width * .25, y + 2, width * .24, 24, 0, 0, Math.PI * 2);
    ctx.ellipse(x + width * .73, y + 1, width * .26, 25, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = 'rgba(128,148,190,.14)';
    roundRect(x + width * .13, y + 17, width * .74, 20, 12); ctx.fill();
    ctx.restore();
  }

  function drawCloud(x, y, scale) {
    ctx.save(); ctx.translate(x, y); ctx.scale(scale, scale);
    ctx.fillStyle = 'rgba(255,255,255,.77)';
    ctx.beginPath();
    ctx.arc(0, 7, 18, Math.PI, 0);
    ctx.arc(20, 0, 24, Math.PI, 0);
    ctx.arc(43, 8, 17, Math.PI, 0);
    ctx.lineTo(60, 15); ctx.lineTo(-18, 15); ctx.closePath(); ctx.fill();
    ctx.restore();
  }

  function drawHill(x, y, w, h, scale = 1) {
    ctx.save(); ctx.beginPath();
    ctx.moveTo(x - w * .55, y + 140);
    ctx.quadraticCurveTo(x - w * .25, y - h * .75 * scale, x, y);
    ctx.quadraticCurveTo(x + w * .3, y - h * scale, x + w * .55, y + 140);
    ctx.closePath(); ctx.fill(); ctx.restore();
  }

  function drawTerrain() {
    const tileSize = tile;
    const theme = layout.world?.theme || 'meadow';
    const tilePalettes = {
      meadow: { top: '#80be58', highlight: '#a1d36a', dirtA: '#b77a4d', dirtB: '#794a32', speck: '#a96b45' },
      forest: { top: '#5d9f68', highlight: '#8cc77b', dirtA: '#8d6848', dirtB: '#574234', speck: '#a17c58' },
      ruins: { top: '#a7aa8f', highlight: '#d0c59e', dirtA: '#9c8c70', dirtB: '#5b5147', speck: '#c0aa87' },
      crystal: { top: '#81bad8', highlight: '#c3e8f2', dirtA: '#7b86b3', dirtB: '#3b426c', speck: '#abb6e4' },
      sky: { top: '#9edbe9', highlight: '#f4f4ff', dirtA: '#858ab6', dirtB: '#454a75', speck: '#c0c8ea' },
    };
    const palette = tilePalettes[theme] || tilePalettes.meadow;
    for (const value of terrain) {
      const [x, y] = value.split(':').map(Number);
      const px = x * tileSize;
      const py = y * tileSize;
      const above = pointAt(terrain, x, y - 1);
      const gradient = ctx.createLinearGradient(px, py, px, py + tileSize);
      gradient.addColorStop(0, palette.dirtA);
      gradient.addColorStop(1, palette.dirtB);
      ctx.fillStyle = gradient;
      roundRect(px + 1, py + 1, tileSize - 2, tileSize - 2, 8);
      ctx.fill();
      ctx.fillStyle = palette.speck;
      for (const [dx, dy] of [[17, 31], [55, 48], [36, 65]]) {
        ctx.beginPath(); ctx.ellipse(px + dx, py + dy, 4, 2.5, -.2, 0, Math.PI * 2); ctx.fill();
      }
      if (!above) {
        ctx.fillStyle = palette.top;
        roundRect(px, py, tileSize, 12, 5); ctx.fill();
        ctx.fillStyle = palette.highlight; ctx.fillRect(px + 3, py + 1, tileSize - 6, 4);
        if (theme === 'sky') {
          ctx.strokeStyle = 'rgba(238,246,255,.85)'; ctx.lineWidth = 2;
          ctx.beginPath(); ctx.moveTo(px + 18, py + 7); ctx.lineTo(px + 23, py + 2); ctx.lineTo(px + 28, py + 7); ctx.moveTo(px + 44, py + 4); ctx.lineTo(px + 48, py + 1); ctx.stroke();
        } else {
          ctx.strokeStyle = 'rgba(55,117,57,.45)'; ctx.lineWidth = 2;
          ctx.beginPath(); ctx.moveTo(px + 14, py + 2); ctx.lineTo(px + 9, py - 7); ctx.moveTo(px + 47, py + 2); ctx.lineTo(px + 43, py - 5); ctx.stroke();
        }
      } else {
        ctx.fillStyle = 'rgba(32,35,40,.18)'; ctx.fillRect(px + 1, py + 1, tileSize - 2, 4);
      }
      if (theme === 'crystal') {
        ctx.strokeStyle = 'rgba(231,247,255,.28)'; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(px + 18, py + 26); ctx.lineTo(px + 31, py + 12); ctx.lineTo(px + 42, py + 31); ctx.stroke();
      } else if (theme === 'ruins') {
        ctx.strokeStyle = 'rgba(54,48,42,.22)'; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(px + 9, py + 39); ctx.lineTo(px + 24, py + 44); ctx.lineTo(px + 30, py + 55); ctx.stroke();
      }
    }
  }

  function roundRect(x, y, w, h, r) {
    const radius = Math.min(r, w / 2, h / 2);
    ctx.beginPath(); ctx.moveTo(x + radius, y);
    ctx.arcTo(x + w, y, x + w, y + h, radius);
    ctx.arcTo(x + w, y + h, x, y + h, radius);
    ctx.arcTo(x, y + h, x, y, radius);
    ctx.arcTo(x, y, x + w, y, radius); ctx.closePath();
  }

  function drawBlock(point, time) {
    const x = point.x * tile + 9, y = point.y * tile + 8;
    ctx.save(); ctx.shadowColor = 'rgba(49,37,22,.24)'; ctx.shadowBlur = 8; ctx.shadowOffsetY = 5;
    const g = ctx.createLinearGradient(x, y, x, y + 63);
    g.addColorStop(0, '#c58a50'); g.addColorStop(1, '#81512f');
    ctx.fillStyle = g; roundRect(x, y, 62, 62, 9); ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.strokeStyle = 'rgba(82,48,27,.55)'; ctx.lineWidth = 4;
    ctx.strokeRect(x + 9, y + 9, 44, 44);
    ctx.beginPath(); ctx.moveTo(x + 11, y + 11); ctx.lineTo(x + 51, y + 51); ctx.moveTo(x + 51, y + 11); ctx.lineTo(x + 11, y + 51); ctx.stroke();
    ctx.fillStyle = '#e6bc7d'; ctx.beginPath(); ctx.arc(x + 13, y + 13, 3, 0, Math.PI * 2); ctx.arc(x + 49, y + 13, 3, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function drawGate(point, time = 0, open = false) {
    const x = point.x * tile;
    const y = point.y * tile;
    ctx.save();
    if (open) {
      ctx.globalAlpha = .26;
      ctx.strokeStyle = '#d9bbff';
      ctx.lineWidth = 5;
      ctx.setLineDash([7, 7]);
      ctx.strokeRect(x + 10, y + 8, 60, 64);
      ctx.restore();
      return;
    }
    ctx.shadowColor = 'rgba(38,31,54,.3)';
    ctx.shadowBlur = 9;
    ctx.fillStyle = '#51465d';
    roundRect(x + 9, y + 5, 62, 70, 8); ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = '#b8a4c9';
    for (const barX of [20, 37, 54]) {
      roundRect(x + barX - 4, y + 10, 8, 59, 4); ctx.fill();
    }
    ctx.fillStyle = '#e2c7ff';
    ctx.beginPath(); ctx.arc(x + 40, y + 36 + Math.sin(time / 190) * 2, 5, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function drawSwitch(point, time = 0) {
    const x = point.x * tile + tile / 2;
    const y = point.y * tile + tile / 2;
    ctx.save();
    ctx.shadowColor = 'rgba(120,83,207,.45)';
    ctx.shadowBlur = 12 + Math.sin(time / 160) * 3;
    ctx.fillStyle = '#65518e';
    roundRect(x - 25, y + 12, 50, 13, 6); ctx.fill();
    const glow = ctx.createRadialGradient(x, y - 8, 2, x, y - 8, 23);
    glow.addColorStop(0, 'rgba(235,220,255,.95)');
    glow.addColorStop(.35, 'rgba(169,126,238,.85)');
    glow.addColorStop(1, 'rgba(108,76,178,0)');
    ctx.fillStyle = glow;
    ctx.beginPath(); ctx.arc(x, y - 8, 23 + Math.sin(time / 170) * 2, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#d9c3ff';
    ctx.beginPath(); ctx.arc(x, y - 8, 8, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function drawSpike(point, time = 0) {
    const x = point.x * tile, y = point.y * tile;
    ctx.save();
    ctx.globalAlpha = .91 + Math.sin(time / 210 + x) * .07; ctx.shadowColor = 'rgba(83,44,40,.25)'; ctx.shadowBlur = 7; ctx.shadowOffsetY = 3;
    ctx.fillStyle = '#a84c45';
    ctx.beginPath(); ctx.moveTo(x + 7, y + 70); ctx.lineTo(x + 25, y + 26); ctx.lineTo(x + 42, y + 70); ctx.closePath(); ctx.fill();
    ctx.beginPath(); ctx.moveTo(x + 36, y + 70); ctx.lineTo(x + 56, y + 18); ctx.lineTo(x + 74, y + 70); ctx.closePath(); ctx.fill();
    ctx.shadowColor = 'transparent'; ctx.fillStyle = '#f4c9a9';
    ctx.beginPath(); ctx.moveTo(x + 25, y + 28); ctx.lineTo(x + 21, y + 43); ctx.lineTo(x + 29, y + 42); ctx.closePath(); ctx.fill();
    ctx.beginPath(); ctx.moveTo(x + 56, y + 20); ctx.lineTo(x + 51, y + 36); ctx.lineTo(x + 61, y + 35); ctx.closePath(); ctx.fill();
    ctx.restore();
  }

  function drawApple(point, time, index) {
    const x = point.x * tile + tile / 2;
    const bob = prefersReducedMotion ? 0 : Math.sin(time / 280 + index) * 4;
    const y = point.y * tile + tile / 2 + bob;
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(prefersReducedMotion ? 0 : Math.sin(time / 610 + index) * .035);

    const halo = ctx.createRadialGradient(0, 3, 5, 0, 3, 44);
    halo.addColorStop(0, 'rgba(255,116,84,.22)');
    halo.addColorStop(1, 'rgba(255,116,84,0)');
    ctx.fillStyle = halo;
    ctx.beginPath(); ctx.arc(0, 3, 44, 0, Math.PI * 2); ctx.fill();

    ctx.shadowColor = 'rgba(117,36,30,.3)'; ctx.shadowBlur = 12; ctx.shadowOffsetY = 6;
    const red = ctx.createLinearGradient(-18, -22, 20, 24);
    red.addColorStop(0, '#ff8b77'); red.addColorStop(.46, '#e73539'); red.addColorStop(1, '#a9152e');
    ctx.fillStyle = red;
    ctx.beginPath(); ctx.moveTo(0, -3); ctx.bezierCurveTo(-24, -30, -42, -6, -25, 18); ctx.bezierCurveTo(-14, 34, -3, 27, 0, 24); ctx.bezierCurveTo(12, 32, 27, 24, 31, 8); ctx.bezierCurveTo(39, -15, 18, -30, 0, -3); ctx.fill();
    ctx.shadowColor = 'transparent'; ctx.fillStyle = 'rgba(255,255,255,.58)';
    ctx.beginPath(); ctx.ellipse(-14, -7, 5, 11, -.4, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#70502f'; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(0, -21); ctx.quadraticCurveTo(-2, -34, 4, -39); ctx.stroke();
    ctx.fillStyle = '#6eaa4c'; ctx.beginPath(); ctx.ellipse(12, -34, 12, 6, -.48 + Math.sin(time / 420 + index) * .08, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function drawGoldenApple(point, time, index) {
    const x = point.x * tile + tile / 2;
    const bob = prefersReducedMotion ? 0 : Math.sin(time / 245 + index) * 4;
    const y = point.y * tile + tile / 2 + bob;
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(prefersReducedMotion ? 0 : Math.sin(time / 700 + index) * .07);

    const halo = ctx.createRadialGradient(0, 3, 4, 0, 3, 48);
    halo.addColorStop(0, 'rgba(255,211,91,.28)');
    halo.addColorStop(1, 'rgba(255,211,91,0)');
    ctx.fillStyle = halo;
    ctx.beginPath(); ctx.arc(0, 3, 48, 0, Math.PI * 2); ctx.fill();

    ctx.shadowColor = 'rgba(219,151,25,.52)'; ctx.shadowBlur = 18; ctx.shadowOffsetY = 5;
    const gold = ctx.createLinearGradient(-18, -24, 20, 25);
    gold.addColorStop(0, '#fff5b8'); gold.addColorStop(.42, '#ffc83e'); gold.addColorStop(1, '#d98a12');
    ctx.fillStyle = gold;
    ctx.beginPath(); ctx.moveTo(0, -3); ctx.bezierCurveTo(-24, -30, -42, -6, -25, 18); ctx.bezierCurveTo(-14, 34, -3, 27, 0, 24); ctx.bezierCurveTo(12, 32, 27, 24, 31, 8); ctx.bezierCurveTo(39, -15, 18, -30, 0, -3); ctx.fill();
    ctx.shadowColor = 'transparent'; ctx.fillStyle = 'rgba(255,255,255,.76)';
    ctx.beginPath(); ctx.ellipse(-14, -7, 5, 11, -.4, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#76502a'; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(0, -21); ctx.quadraticCurveTo(-2, -34, 4, -39); ctx.stroke();
    ctx.fillStyle = '#7ab34b'; ctx.beginPath(); ctx.ellipse(12, -34, 12, 6, -.48 + (prefersReducedMotion ? 0 : Math.sin(time / 390 + index) * .1), 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function drawPortal(point, time, isReady) {
    const x = point.x * tile + tile / 2;
    const y = point.y * tile + tile / 2;
    const pulse = prefersReducedMotion ? 0 : Math.sin(time / 180) * 2;
    ctx.save();
    ctx.translate(x, y);

    const aura = ctx.createRadialGradient(0, 0, 7, 0, 0, 54 + pulse);
    aura.addColorStop(0, isReady ? 'rgba(177,127,255,.2)' : 'rgba(75,67,98,.08)');
    aura.addColorStop(1, 'rgba(82,61,113,0)');
    ctx.fillStyle = aura;
    ctx.beginPath(); ctx.arc(0, 0, 54 + pulse, 0, Math.PI * 2); ctx.fill();

    const core = ctx.createRadialGradient(-5, -8, 2, 0, 0, 29);
    core.addColorStop(0, isReady ? '#3e285d' : '#20232e');
    core.addColorStop(.72, '#171923');
    core.addColorStop(1, '#090c13');
    ctx.fillStyle = core;
    ctx.beginPath(); ctx.ellipse(0, 0, 25, 34, 0, 0, Math.PI * 2); ctx.fill();

    ctx.shadowColor = isReady ? 'rgba(145,83,224,.72)' : 'rgba(35,40,46,.35)';
    ctx.shadowBlur = isReady ? 25 : 10;
    ctx.strokeStyle = isReady ? '#c7a9ff' : '#777185'; ctx.lineWidth = 9;
    ctx.beginPath(); ctx.ellipse(0, 0, 25, 34, 0, 0, Math.PI * 2); ctx.stroke();
    ctx.shadowColor = 'transparent';
    ctx.save();
    ctx.rotate(prefersReducedMotion ? 0 : time / (isReady ? 650 : 1250));
    ctx.strokeStyle = isReady ? 'rgba(229,208,255,.95)' : 'rgba(185,180,196,.58)';
    ctx.lineWidth = 4;
    ctx.beginPath(); ctx.ellipse(0, 0, 17, 25, 0, .15, Math.PI * 1.75); ctx.stroke();
    ctx.strokeStyle = isReady ? 'rgba(171,126,239,.9)' : 'rgba(92,90,108,.7)';
    ctx.lineWidth = 3;
    ctx.beginPath(); ctx.ellipse(0, 0, 9, 14, 0, .25, Math.PI * 1.72); ctx.stroke();
    ctx.restore();

    if (isReady) {
      ctx.fillStyle = '#f0dcff';
      for (let i = 0; i < 4; i++) {
        const angle = time / 700 + i * Math.PI / 2;
        const orbit = 40 + Math.sin(time / 210 + i) * 2;
        ctx.globalAlpha = .55 + (Math.sin(time / 160 + i) + 1) * .2;
        ctx.beginPath(); ctx.arc(Math.cos(angle) * orbit, Math.sin(angle) * orbit, 2.5, 0, Math.PI * 2); ctx.fill();
      }
    }
    ctx.restore();
  }

  function drawWorm(worm, time) {
    if (!worm || !worm.length) return;

    const blinkPhase = time % 3900;
    const blinking = blinkPhase > 3260 && blinkPhase < 3390;
    const movementProgress = animationStarted
      ? Math.max(0, Math.min(1, (time - animationStarted) / moveAnimationDuration))
      : 1;
    const movementPulse = prefersReducedMotion ? 0 : Math.sin(movementProgress * Math.PI);
    const bodyPoints = worm.map((part, index) => ({
      x: part.x * tile + tile / 2,
      y: part.y * tile + tile / 2 + (prefersReducedMotion ? 0 : Math.sin(time / 155 + index * .72) * 1.3),
    }));

    // A rounded connector stroke makes the individual tiles read as one
    // continuous, soft segmented creature, including around right-angle turns.
    if (bodyPoints.length > 1) {
      ctx.save();
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.beginPath();
      ctx.moveTo(bodyPoints[0].x, bodyPoints[0].y);
      if (bodyPoints.length === 2) {
        ctx.lineTo(bodyPoints[1].x, bodyPoints[1].y);
      } else {
        // Curve through the middle of each tile transition so right-angle
        // turns read as a flexible creature instead of a rigid polyline.
        for (let i = 1; i < bodyPoints.length - 1; i++) {
          const midpointX = (bodyPoints[i].x + bodyPoints[i + 1].x) / 2;
          const midpointY = (bodyPoints[i].y + bodyPoints[i + 1].y) / 2;
          ctx.quadraticCurveTo(bodyPoints[i].x, bodyPoints[i].y, midpointX, midpointY);
        }
        const tail = bodyPoints[bodyPoints.length - 1];
        ctx.lineTo(tail.x, tail.y);
      }
      ctx.strokeStyle = '#347e3b';
      ctx.lineWidth = 49;
      ctx.stroke();
      const bodyGradient = ctx.createLinearGradient(0, bodyPoints[0].y - 25, 0, bodyPoints[0].y + 25);
      bodyGradient.addColorStop(0, '#c3ef79');
      bodyGradient.addColorStop(.48, '#73c34d');
      bodyGradient.addColorStop(1, '#3e963e');
      ctx.strokeStyle = bodyGradient;
      ctx.lineWidth = 43;
      ctx.stroke();
      ctx.strokeStyle = 'rgba(238,255,194,.28)';
      ctx.lineWidth = 3;
      ctx.stroke();
      ctx.restore();
    }

    // Body segments are drawn tail-to-head over the connector for a soft,
    // layered look. Small alternating pulses give the worm an idle motion.
    for (let i = worm.length - 1; i >= 1; i--) {
      const part = bodyPoints[i];
      const next = bodyPoints[i - 1];
      let angle = Math.atan2(next.y - part.y, next.x - part.x);
      // A segment is an undirected oval: angles that differ by 180 degrees
      // describe the same body axis. Normalize them so left-facing worms do
      // not turn the segment's light/shadow upside down.
      if (angle > Math.PI / 2) angle -= Math.PI;
      else if (angle < -Math.PI / 2) angle += Math.PI;
      ctx.save();
      ctx.translate(part.x, part.y);
      ctx.rotate(angle + (prefersReducedMotion ? 0 : Math.sin(time / 250 + i) * .025));
      const turnPulse = !prefersReducedMotion && time < turnPulseUntil
        ? Math.sin(((220 - (turnPulseUntil - time)) / 220) * Math.PI) * .025
        : 0;
      const pulse = prefersReducedMotion ? 0 : Math.sin(time / 240 + i * .62) * .025 + turnPulse;
      ctx.scale(1 + pulse, 1 - pulse);
      ctx.shadowColor = 'rgba(26,82,43,.22)'; ctx.shadowBlur = 7; ctx.shadowOffsetY = 4;
      const segmentGradient = ctx.createLinearGradient(0, -24, 0, 24);
      segmentGradient.addColorStop(0, '#c3ef79'); segmentGradient.addColorStop(.48, '#73c34d'); segmentGradient.addColorStop(1, '#3e963e');
      ctx.fillStyle = segmentGradient;
      ctx.beginPath(); ctx.ellipse(0, 0, 27, 22, 0, 0, Math.PI * 2); ctx.fill();
      ctx.shadowColor = 'transparent'; ctx.strokeStyle = 'rgba(43,127,52,.62)'; ctx.lineWidth = 2; ctx.stroke();
      ctx.fillStyle = 'rgba(255,255,255,.45)';
      ctx.beginPath(); ctx.ellipse(-8, -9, 7, 4, -.35, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = 'rgba(33,112,49,.17)';
      ctx.beginPath(); ctx.ellipse(5, 12, 12, 5, 0, 0, Math.PI * 2); ctx.fill();
      ctx.restore();
    }

    const head = bodyPoints[0];
    ctx.save();
    ctx.translate(head.x, head.y + (prefersReducedMotion ? 0 : Math.sin(time / 170) * 1.4));
    // Keep the worm's back/highlights on top when facing left. Rotating by PI
    // flips the eyes and shading underneath; a horizontal mirror preserves
    // the upright silhouette while moving the face to the left.
    if (facing === 'left') ctx.scale(-1, 1);
    else if (facing === 'up') ctx.rotate(-Math.PI / 2);
    else if (facing === 'down') ctx.rotate(Math.PI / 2);
    const breathe = prefersReducedMotion ? 0 : Math.sin(time / 230) * .035;
    const stretch = movementPulse * .11;
    const turnSquash = !prefersReducedMotion && time < turnPulseUntil
      ? Math.sin(((220 - (turnPulseUntil - time)) / 220) * Math.PI) * .045
      : 0;
    ctx.scale(1 + breathe + stretch + turnSquash, 1 - breathe - stretch * .45 - turnSquash * .35);
    ctx.shadowColor = 'rgba(26,82,43,.25)'; ctx.shadowBlur = 10; ctx.shadowOffsetY = 5;
    const headGradient = ctx.createLinearGradient(0, -28, 0, 28);
    headGradient.addColorStop(0, '#cafa83'); headGradient.addColorStop(.5, '#82d64e'); headGradient.addColorStop(1, '#439a3c');
    ctx.fillStyle = headGradient;
    ctx.beginPath(); ctx.ellipse(0, 0, 31, 25, 0, 0, Math.PI * 2); ctx.fill();
    ctx.shadowColor = 'transparent'; ctx.strokeStyle = '#3b873b'; ctx.lineWidth = 2.2; ctx.stroke();

    if (blinking) {
      ctx.strokeStyle = '#245c36'; ctx.lineWidth = 3.5; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(7, -8); ctx.quadraticCurveTo(14, -3, 21, -8); ctx.moveTo(23, -7); ctx.quadraticCurveTo(29, -2, 35, -7); ctx.stroke();
    } else {
      ctx.fillStyle = '#fff';
      ctx.beginPath(); ctx.ellipse(13, -10, 9, 12, .08, 0, Math.PI * 2); ctx.ellipse(28, -8, 8, 10, .08, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#1e3929';
      ctx.beginPath(); ctx.arc(16, -8, 4, 0, Math.PI * 2); ctx.arc(30, -6, 3.6, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#fff';
      ctx.beginPath(); ctx.arc(17, -10, 1.5, 0, Math.PI * 2); ctx.arc(31, -8, 1.3, 0, Math.PI * 2); ctx.fill();
    }
    ctx.fillStyle = 'rgba(244,125,111,.42)';
    ctx.beginPath(); ctx.ellipse(7, 5, 4, 2.5, 0, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#275d38'; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(20, 7, 7, .1, 1.6); ctx.stroke();
    ctx.restore();
  }

  function drawParticles(time) {
    particles = particles.filter(p => time - p.born < (p.type === 'ring' ? 640 : 600));
    for (const p of particles) {
      const duration = p.type === 'ring' ? 640 : 600;
      const age = Math.max(0, Math.min(1, (time - p.born) / duration));
      ctx.globalAlpha = 1 - age;
      if (p.type === 'ring') {
        ctx.strokeStyle = p.color;
        ctx.lineWidth = Math.max(1, 4 * (1 - age));
        ctx.beginPath();
        ctx.arc(p.x, p.y, 8 + age * 54, 0, Math.PI * 2);
        ctx.stroke();
        continue;
      }
      ctx.fillStyle = p.color;
      const driftX = p.vx * age * 42;
      const driftY = p.vy * age * 42 + age * age * 18;
      ctx.beginPath();
      ctx.arc(p.x + driftX, p.y + driftY, p.size * (1 - age * .72), 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.globalAlpha = 1;
  }

  function spawnImpactRing(point, color = 'rgba(255,244,192,.95)') {
    if (!point || prefersReducedMotion) return;
    particles.push({ type: 'ring', x: point.x * tile + tile / 2, y: point.y * tile + tile / 2, born: performance.now(), color });
  }

  function animatedWorm(time) {
    if (!state) return [];
    if (!previousWorm || !animationStarted) return state.worm;
    const elapsed = time - animationStarted;
    const lastSegmentDelay = prefersReducedMotion ? 0 : Math.min(Math.max(state.worm.length - 1, 0) * 12, 72);
    if (elapsed >= moveAnimationDuration + lastSegmentDelay * .65) { previousWorm = null; return state.worm; }
    return state.worm.map((part, index) => {
      const from = previousWorm[Math.min(index, previousWorm.length - 1)] || part;
      // The head leads; each body segment follows a fraction later. This gives
      // turns a soft travelling wave while preserving one-tile grid results.
      const delay = prefersReducedMotion ? 0 : Math.min(index * 12, 72);
      const segmentProgress = ease((elapsed - delay) / Math.max(150, moveAnimationDuration - delay * .35));
      return { x: lerp(from.x, part.x, segmentProgress), y: lerp(from.y, part.y, segmentProgress) };
    });
  }

  function animatedBlocks(time) {
    if (!state) return [];
    if (!previousBlocks || !animationStarted) return state.blocks || [];
    const amount = ease((time - animationStarted) / (moveAnimationDuration + 35));
    if (amount >= 1) { previousBlocks = null; return state.blocks || []; }
    return (state.blocks || []).map((target, index) => {
      const transition = previousBlocks[index] || { from: target, to: target };
      if (transition.waypoint) {
        // A pushed crate first slides one tile, then settles vertically under gravity.
        if (amount < .42) {
          const phase = ease(amount / .42);
          return {
            x: lerp(transition.from.x, transition.waypoint.x, phase),
            y: lerp(transition.from.y, transition.waypoint.y, phase),
          };
        }
        const phase = ease((amount - .42) / .58);
        return {
          x: lerp(transition.waypoint.x, transition.to.x, phase),
          y: lerp(transition.waypoint.y, transition.to.y, phase),
        };
      }
      return {
        x: lerp(transition.from.x, transition.to.x, amount),
        y: lerp(transition.from.y, transition.to.y, amount),
      };
    });
  }

  function render(time = 0) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    drawBackground(time);
    drawTerrain();

    const portalReady = state && (state.apples || []).length === 0 && (state.goldenApples || []).length === 0 && (state.switches || []).length === 0;
    drawPortal(layout.portal, time, portalReady);
    if (!state?.gatesOpen) {
      (layout.gates || []).forEach(gate => drawGate(gate, time, false));
    } else {
      (layout.gates || []).forEach(gate => drawGate(gate, time, true));
    }
    (state?.switches || []).forEach(item => drawSwitch(item, time));
    animatedBlocks(time).forEach(block => drawBlock(block, time));
    (layout.spikes || []).forEach(spike => drawSpike(spike, time));
    (state?.apples || []).forEach((apple, index) => drawApple(apple, time, index));
    (state?.goldenApples || []).forEach((apple, index) => drawGoldenApple(apple, time, index));
    drawWorm(animatedWorm(time), time);
    drawParticles(time);
    requestAnimationFrame(render);
  }

  function spawnAppleParticles(point) {
    const x = point.x * tile + tile / 2;
    const y = point.y * tile + tile / 2;
    spawnImpactRing(point, 'rgba(255,218,139,.95)');
    for (let i = 0; i < 20; i++) {
      const angle = (Math.PI * 2 * i) / 20;
      const speed = 0.8 + (i % 4) * .16;
      particles.push({ x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, born: performance.now(), size: 3 + (i % 3), color: i % 2 ? '#fff0aa' : '#ff8a67' });
    }
  }

  function spawnGoldenAppleParticles(point) {
    const x = point.x * tile + tile / 2;
    const y = point.y * tile + tile / 2;
    spawnImpactRing(point, 'rgba(255,225,126,.98)');
    for (let i = 0; i < 26; i++) {
      const angle = (Math.PI * 2 * i) / 26;
      particles.push({ x, y, vx: Math.cos(angle) * 1.25, vy: Math.sin(angle) * 1.25, born: performance.now(), size: 3 + (i % 3), color: i % 2 ? '#fff2ad' : '#f6bd35' });
    }
  }

  function spawnSwitchParticles(point) {
    const x = point.x * tile + tile / 2;
    const y = point.y * tile + tile / 2;
    spawnImpactRing(point, 'rgba(206,177,255,.95)');
    for (let i = 0; i < 22; i++) {
      const angle = (Math.PI * 2 * i) / 22;
      particles.push({ x, y, vx: Math.cos(angle) * 1.2, vy: Math.sin(angle) * 1.2, born: performance.now(), size: 3 + (i % 3), color: i % 2 ? '#d7fff1' : '#5ed5c4' });
    }
  }

  function spawnPortalParticles(point) {
    if (!point || prefersReducedMotion) return;
    spawnImpactRing(point, 'rgba(207,174,255,.98)');
    const x = point.x * tile + tile / 2;
    const y = point.y * tile + tile / 2;
    for (let i = 0; i < 18; i++) {
      const angle = (Math.PI * 2 * i) / 18;
      const speed = .7 + (i % 3) * .18;
      particles.push({ x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, born: performance.now(), size: 2.5 + (i % 3), color: i % 2 ? '#f4e9ff' : '#b895f2' });
    }
  }

  function spawnDustParticles(point) {
    if (!point || prefersReducedMotion) return;
    const x = point.x * tile + tile / 2;
    const y = point.y * tile + tile - 8;
    for (let i = 0; i < 10; i++) {
      const angle = Math.PI + (Math.PI * i / 9);
      particles.push({
        x, y,
        vx: Math.cos(angle) * (.45 + (i % 3) * .16),
        vy: Math.sin(angle) * (.45 + (i % 3) * .16),
        born: performance.now(), size: 2.5 + (i % 3), color: i % 2 ? '#d5b18a' : '#9b7657',
      });
    }
  }

  async function move(direction) {
    if (busy || paused || completed || state?.won || state?.lost) return;
    busy = true;
    const oldFacing = facing;
    const oldState = state;
    try {
      const result = await request(app.dataset.moveUrl, { direction });
      if (result.blocked) {
        toast(result.reason || 'That move is blocked.');
        ui.status.textContent = result.reason || 'That move is blocked.';
        busy = false;
        return;
      }
      if (result.collected_apple) {
        const eaten = oldState.apples.find(point => !result.state.apples.some(item => keyOf(item) === keyOf(point)));
        if (eaten) spawnAppleParticles(eaten);
      }
      if (result.collected_golden_apple) {
        const eatenGolden = (oldState.goldenApples || []).find(point => !(result.state.goldenApples || []).some(item => keyOf(item) === keyOf(point)));
        if (eatenGolden) spawnGoldenAppleParticles(eatenGolden);
      }
      if (result.activated_switch) {
        const activated = (oldState.switches || []).find(point => !(result.state.switches || []).some(item => keyOf(item) === keyOf(point)));
        if (activated) spawnSwitchParticles(activated);
        (layout.gates || []).forEach(gate => spawnSwitchParticles(gate));
      }
      facing = direction;
      if (oldFacing !== direction) turnPulseUntil = performance.now() + 220;
      previousWorm = oldState.worm.map(p => ({ ...p }));
      const oldBlockPool = (oldState.blocks || []).map(p => ({ ...p }));
      const movedFromPoint = result.pushed
        ? oldBlockPool.find(block => !(result.state.blocks || []).some(next => keyOf(next) === keyOf(block)))
        : null;
      const movementDelta = {
        right: { x: 1, y: 0 }, left: { x: -1, y: 0 },
        up: { x: 0, y: -1 }, down: { x: 0, y: 1 },
      }[direction];
      previousBlocks = (result.state.blocks || []).map(target => {
        if (!oldBlockPool.length) return { from: { ...target }, to: { ...target } };
        let closestIndex = 0;
        let closestDistance = Number.POSITIVE_INFINITY;
        oldBlockPool.forEach((candidate, index) => {
          const distance = Math.abs(candidate.x - target.x) + Math.abs(candidate.y - target.y);
          if (distance < closestDistance) { closestDistance = distance; closestIndex = index; }
        });
        const from = oldBlockPool.splice(closestIndex, 1)[0];
        const isPushed = movedFromPoint && keyOf(from) === keyOf(movedFromPoint);
        return {
          from,
          to: { ...target },
          waypoint: isPushed ? { x: from.x + movementDelta.x, y: from.y + movementDelta.y } : null,
        };
      });
      if (result.pushed) {
        const movedBlock = (result.state.blocks || []).find(block =>
          !(oldState.blocks || []).some(previous => keyOf(previous) === keyOf(block))
        );
        if (movedBlock) {
          spawnDustParticles(movedBlock);
          spawnImpactRing(movedBlock, 'rgba(224,190,145,.75)');
        }
        const pushedFrom = (oldState.blocks || []).find(block => !(result.state.blocks || []).some(next => keyOf(next) === keyOf(block)));
        if (pushedFrom) spawnDustParticles(pushedFrom);
      }
      if ((result.gravity_steps || 0) > 0 && result.state.worm?.length) {
        spawnDustParticles(result.state.worm[result.state.worm.length - 1]);
      }
      animationStarted = performance.now();
      moveAnimationDuration = moveAnimationMs + Math.min(4, Number(result.gravity_steps || 0)) * 70;
      state = result.state;
      updateHud();
      if (result.activated_switch) toast('Rune awakened · the gate is open!');
      else if (result.pushed) toast('Crate pushed!');
      else if (result.collected_golden_apple) toast('Golden apple found · bonus coins secured!');
      else if (result.collected_apple) toast('Apple collected · the worm grew!');
      if (result.failed) {
        busy = false;
        showFailure(result.reason || 'The worm lost its footing.');
        return;
      }
      if (result.won) {
        ui.status.textContent = 'The portal is awake!';
        spawnPortalParticles(layout.portal);
        if (completionTimer) window.clearTimeout(completionTimer);
        completionTimer = window.setTimeout(() => {
          completionTimer = null;
          completeLevel();
        }, 230);
      }
    } catch (error) {
      toast(error.message || 'Connection issue. Please try again.');
    } finally {
      busy = false;
    }
  }

  async function completeLevel() {
    if (completed) return;
    completed = true;
    try {
      const result = await request(app.dataset.completeUrl);
      document.getElementById('puzzleResultScore').textContent = String(result.score ?? state.score ?? 0);
      document.getElementById('puzzleResultMoves').textContent = String(state.moves ?? 0);
      document.getElementById('puzzleResultCoins').textContent = `+${result.coins_earned ?? 0} 🪙`;
      document.getElementById('puzzleResultMessage').textContent = `You solved the puzzle in ${state.moves} moves and earned ${result.stars || 1} star${result.stars === 1 ? '' : 's'}.`;
      document.getElementById('puzzleResultTitle').textContent = 'Level complete!';
      document.getElementById('puzzleResultIcon').textContent = '🎉';
      ui.result.classList.remove('hidden');
      document.getElementById('puzzleContinue').textContent = app.dataset.hasNext === '1' ? 'Next level' : 'Level map';
      document.getElementById('puzzleRetry').textContent = 'Play again';
    } catch (error) {
      completed = false;
      document.getElementById('puzzleResultTitle').textContent = 'Progress not saved';
      document.getElementById('puzzleResultIcon').textContent = '⚠️';
      document.getElementById('puzzleResultMessage').textContent = error.message || 'The puzzle is solved, but the result could not be saved. Retry the save or restart.';
      document.getElementById('puzzleContinue').textContent = 'Retry save';
      document.getElementById('puzzleRetry').textContent = 'Restart puzzle';
      ui.result.classList.remove('hidden');
      toast('Your win is still on the server. Retry saving your progress.');
    }
  }

  function showFailure(message) {
    document.getElementById('puzzleErrorMessage').textContent = message;
    updateRewardPrompts();
    ui.error.classList.remove('hidden');
  }

  async function restart() {
    if (busy) return;
    busy = true;
    try {
      if (completionTimer) { window.clearTimeout(completionTimer); completionTimer = null; }
      const result = await request(app.dataset.restartUrl);
      previousWorm = null;
      previousBlocks = null;
      animationStarted = 0;
      moveAnimationDuration = moveAnimationMs;
      state = result.state;
      completed = false;
      paused = false;
      ui.pause.classList.add('hidden');
      ui.error.classList.add('hidden');
      ui.result.classList.add('hidden');
      ui.pauseButton.textContent = 'Ⅱ Pause';
      if (ui.hintPanel) ui.hintPanel.classList.add('hidden');
      if (ui.hintCount) ui.hintCount.textContent = `0/${hintTotal}`;
      if (ui.hintButton) { ui.hintButton.disabled = false; ui.hintButton.innerHTML = `💡 Hint <span id="puzzleHintCount">0/${hintTotal}</span>`; ui.hintCount = document.getElementById('puzzleHintCount'); }
      updateHud();
      toast('Puzzle restarted.');
    } catch (error) {
      toast(error.message || 'Could not restart this level.');
    } finally {
      busy = false;
    }
  }

  function togglePause(force = null) {
    paused = force === null ? !paused : Boolean(force);
    ui.pause.classList.toggle('hidden', !paused);
    ui.pauseButton.textContent = paused ? '▶ Resume' : 'Ⅱ Pause';
    ui.pauseButton.setAttribute('aria-pressed', paused ? 'true' : 'false');
  }

  async function revealHint() {
    if (!app.dataset.hintUrl || ui.hintButton.disabled) return;
    ui.hintButton.disabled = true;
    const originalLabel = ui.hintButton.innerHTML;
    ui.hintButton.textContent = 'Loading…';
    try {
      const result = await request(app.dataset.hintUrl);
      const total = Number(result.total || hintTotal);
      const stage = Number(result.stage || 0);
      const countLabel = `${Math.min(stage, total)}/${total}`;
      if (ui.hintPanel) ui.hintPanel.classList.remove('hidden');
      if (ui.hintStage) ui.hintStage.textContent = result.repeatable ? 'Full walkthrough · repeat anytime' : `Hint ${stage} of ${total}`;
      if (ui.hintText) ui.hintText.textContent = result.hint || 'Study the terrain and the worm’s support points.';
      if (ui.hintCount) ui.hintCount.textContent = countLabel;
      ui.hintButton.innerHTML = `💡 Hint <span id="puzzleHintCount">${countLabel}</span>`;
      ui.hintCount = document.getElementById('puzzleHintCount');
      ui.hintButton.disabled = false;
      ui.hintButton.setAttribute('aria-label', result.repeatable ? 'Repeat the full level walkthrough' : `Reveal hint ${stage + 1} of ${total}`);
      toast(result.repeatable ? 'Full walkthrough revealed · you can request it again.' : `Hint ${stage} of ${total} revealed.`);
    } catch (error) {
      ui.hintButton.innerHTML = originalLabel;
      ui.hintCount = document.getElementById('puzzleHintCount');
      ui.hintButton.disabled = false;
      toast(error.message || 'Could not load a hint. Please try again.');
    }
  }

  function bindControls() {
    document.addEventListener('keydown', event => {
      if (event.target?.closest?.('input, textarea, select, [contenteditable="true"]')) return;
      if (event.key === 'Escape') { togglePause(); return; }
      if (event.code === 'KeyR') { event.preventDefault(); restart(); return; }
      const direction = directions[event.code];
      if (direction) { event.preventDefault(); move(direction); }
    });
    document.querySelectorAll('[data-puzzle-dir]').forEach(button => {
      button.addEventListener('click', () => move(button.dataset.puzzleDir));
    });
    ui.pauseButton.addEventListener('click', () => togglePause());
    document.getElementById('puzzleResume').addEventListener('click', () => togglePause(false));
    ui.hintButton.addEventListener('click', revealHint);
    document.getElementById('puzzleRestart').addEventListener('click', restart);
    ui.extraMovesReward?.addEventListener('click', () => claimReward('extra_moves', ui.extraMovesReward));
    ui.doubleCoinsReward?.addEventListener('click', () => claimReward('double_coins', ui.doubleCoinsReward));
    ui.reviveReward?.addEventListener('click', () => claimReward('revive', ui.reviveReward));
    document.getElementById('puzzleRetry').addEventListener('click', () => {
      if (state?.won && !completed) restart();
      else window.location.reload();
    });
    // The failure popup uses a normal link to GET /play/{level}, which creates
    // a fresh session. It intentionally does not depend on JavaScript or AJAX.
    document.getElementById('puzzleContinue').addEventListener('click', () => {
      if (state?.won && !completed) {
        ui.result.classList.add('hidden');
        completeLevel();
        return;
      }
      window.location.href = app.dataset.nextUrl;
    });

    let touchStart = null;
    canvas.addEventListener('touchstart', event => {
      const touch = event.changedTouches[0];
      touchStart = { x: touch.clientX, y: touch.clientY };
    }, { passive: true });
    canvas.addEventListener('touchend', event => {
      if (!touchStart) return;
      const touch = event.changedTouches[0];
      const dx = touch.clientX - touchStart.x;
      const dy = touch.clientY - touchStart.y;
      touchStart = null;
      if (Math.max(Math.abs(dx), Math.abs(dy)) < 24) return;
      move(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up'));
    }, { passive: true });
    canvas.addEventListener('touchcancel', () => { touchStart = null; }, { passive: true });
  }

  async function init() {
    try {
      const response = await fetch(app.dataset.stateUrl || `/game/${sessionId}/state`, { headers: { 'Accept': 'application/json' } });
      if (response.ok) {
        const data = await response.json();
        state = data.state;
        facing = inferFacingFromWorm(state?.worm, facing);
      }
    } catch (_) { /* The first state is supplied by the initial page payload below. */ }
    if (!state) {
      const start = layout.start || { x: 2, y: 6, length: 3, facing: 'right' };
      const bodyOffset = start.facing === 'left' ? [1, 0]
        : start.facing === 'up' ? [0, 1]
          : start.facing === 'down' ? [0, -1] : [-1, 0];
      facing = start.facing || facing;
      state = {
        worm: Array.from({ length: start.length || 3 }, (_, index) => ({
          x: start.x + bodyOffset[0] * index,
          y: start.y + bodyOffset[1] * index,
        })),
        apples: layout.apples || [], blocks: layout.blocks || [], switches: layout.switches || [], gatesOpen: !(layout.gates || []).length, moves: 0, score: 0,
        pushes: 0, won: false, lost: false,
      };
    }
    updateHud();
    const usedHints = Math.min(Number(state.hint_stage || 0), hintTotal);
    if (ui.hintCount) ui.hintCount.textContent = `${usedHints}/${hintTotal}`;
    if (ui.hintButton) ui.hintButton.disabled = false;
    bindControls();
    requestAnimationFrame(render);
  }

  init();
})();
