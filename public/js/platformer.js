(() => {
  'use strict';

  const root = document.getElementById('platformerPrototype');
  if (!root) return;

  const canvas = document.getElementById('platformerCanvas');
  const ctx = canvas?.getContext('2d');
  if (!canvas || !ctx) return;

  const courseNode = document.getElementById('platformerLevelConfig');
  let COURSE = {};
  try {
    COURSE = courseNode ? JSON.parse(courseNode.textContent || '{}') : {};
  } catch (error) {
    console.error('Unable to read platformer level data.', error);
  }

  const W = Number(COURSE.canvas?.width || 1100);
  const H = Number(COURSE.canvas?.height || 620);
  const WORLD_WIDTH = Number(COURSE.worldWidth || 2380);
  const GRAVITY = Number(COURSE.physics?.gravity || 1680);
  const MOVE_ACCEL = Number(COURSE.physics?.moveAcceleration || 1850);
  const MAX_SPEED = Number(COURSE.physics?.maxSpeed || 300);
  const JUMP_SPEED = Number(COURSE.physics?.jumpSpeed || 640);
  const PLAYER_RADIUS = Number(COURSE.player?.radius || 19);
  const INITIAL_SEGMENTS = Number(COURSE.player?.initialSegments || 5);
  const INITIAL_LIVES = Number(COURSE.player?.lives || 3);
  const START = COURSE.start || { x: 105, y: 501 };
  const COURSE_CHECKPOINT = COURSE.checkpoint || START;
  const PORTAL = COURSE.portal || { x: WORLD_WIDTH - 160, y: 411 };
  const WORLD = COURSE.world || {};
  const REDUCED_MOTION = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
  const LIVE_SESSION = Boolean(root.dataset.completeUrl);
  const MAX_RECORDING_FRAMES = Number(COURSE.maxFrames || 18000);
  const FIXED_STEP = 1 / 60;
  const platforms = Array.isArray(COURSE.platforms) ? COURSE.platforms : [];
  const originalApples = (COURSE.apples || []).map(apple => ({ ...apple, collected: false, kind: 'regular' }));
  const originalGoldenApples = (COURSE.goldenApples || []).map(apple => ({ ...apple, collected: false, kind: 'golden' }));
  const spikes = Array.isArray(COURSE.spikes) ? COURSE.spikes : [];
  let inputLog = '';
  let completionRequestActive = false;

  const input = { left: false, right: false, jumpQueued: false };
  const state = {
    apples: originalApples.map(apple => ({ ...apple })),
    goldenApples: originalGoldenApples.map(apple => ({ ...apple })),
    score: 0,
    lives: INITIAL_LIVES,
    segments: INITIAL_SEGMENTS,
    goldenCollected: 0,
    cameraX: 0,
    paused: false,
    completed: false,
    gameOver: false,
    checkpoint: { x: START.x, y: START.y },
    checkpointReached: false,
    toastUntil: 0,
    previousTime: 0,
    animationFrame: 0,
    shake: 0,
    particles: [],
  };

  const player = {
    x: START.x,
    y: START.y,
    vx: 0,
    vy: 0,
    grounded: true,
    coyote: 0.12,
    facing: 1,
    distance: 0,
    history: [{ x: 100, y: 500, distance: 0 }],
  };

  const el = {
    apples: document.getElementById('platformerAppleCount'),
    score: document.getElementById('platformerScore'),
    length: document.getElementById('platformerLength'),
    lives: document.getElementById('platformerLives'),
    status: document.getElementById('platformerStatus'),
    toast: document.getElementById('platformerToast'),
    pause: document.getElementById('platformerPause'),
    overlay: document.getElementById('platformerOverlay'),
    overlayEmoji: document.getElementById('platformerOverlayEmoji'),
    overlayEyebrow: document.getElementById('platformerOverlayEyebrow'),
    overlayTitle: document.getElementById('platformerOverlayTitle'),
    overlayMessage: document.getElementById('platformerOverlayMessage'),
    overlayAction: document.getElementById('platformerOverlayAction'),
  };

  let toastTimer = null;
  let lastFrame = 0;
  let resizeObserver = null;

  function showToast(message, duration = 1900) {
    if (!el.toast) return;
    el.toast.textContent = message;
    el.toast.classList.remove('is-hidden');
    state.toastUntil = performance.now() + duration;
    if (toastTimer) window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => el.toast?.classList.add('is-hidden'), duration);
  }

  function updateHud() {
    const collected = state.apples.filter(apple => apple.collected).length;
    if (el.apples) el.apples.textContent = `${collected} / ${state.apples.length}`;
    if (el.score) el.score.textContent = String(state.score).padStart(4, '0');
    if (el.length) el.length.textContent = String(state.segments + 1);
    if (el.lives) el.lives.textContent = String(state.lives);
    if (el.status) {
      el.status.textContent = state.completed
        ? 'Portal reached — adventure complete!'
        : collected === state.apples.length
          ? 'The portal is open — make your way to the exit!'
          : `${state.apples.length - collected} apple${state.apples.length - collected === 1 ? '' : 's'} left to find`;
    }
  }

  function showOverlay({ emoji, eyebrow, title, message, action, callback }) {
    state.paused = true;
    if (el.overlay) el.overlay.hidden = false;
    if (el.overlayEmoji) el.overlayEmoji.textContent = emoji;
    if (el.overlayEyebrow) el.overlayEyebrow.textContent = eyebrow;
    if (el.overlayTitle) el.overlayTitle.textContent = title;
    if (el.overlayMessage) el.overlayMessage.textContent = message;
    if (el.overlayAction) {
      el.overlayAction.textContent = action;
      el.overlayAction.onclick = callback;
    }
    if (el.pause) {
      el.pause.setAttribute('aria-pressed', 'true');
      el.pause.innerHTML = '<span>▶</span> Resume';
    }
  }

  function hideOverlay() {
    state.paused = false;
    if (el.overlay) el.overlay.hidden = true;
    if (el.pause) {
      el.pause.setAttribute('aria-pressed', 'false');
      el.pause.innerHTML = '<span>Ⅱ</span> Pause';
    }
    lastFrame = performance.now();
  }

  function pauseGame() {
    if (state.completed || state.gameOver) return;
    if (state.paused) {
      hideOverlay();
      return;
    }
    showOverlay({
      emoji: '⏸️', eyebrow: 'TAKE A BREATHER', title: 'Adventure paused',
      message: 'Your little worm is catching its breath.', action: 'Resume adventure', callback: hideOverlay,
    });
  }

  function resetPlayer(position = state.checkpoint) {
    player.x = position.x;
    player.y = position.y;
    player.vx = 0;
    player.vy = 0;
    player.grounded = true;
    player.coyote = 0.12;
    player.distance = INITIAL_SEGMENTS * 25;
    player.history = [];
    for (let index = 0; index <= INITIAL_SEGMENTS; index += 1) {
      player.history.push({
        x: player.x - ((INITIAL_SEGMENTS - index) * 25),
        y: player.y,
        distance: index * 25,
      });
    }
    player.facing = 1;
    input.jumpQueued = false;
  }

  function restartGame() {
    state.apples = originalApples.map(apple => ({ ...apple }));
    state.goldenApples = originalGoldenApples.map(apple => ({ ...apple }));
    state.score = 0;
    state.lives = INITIAL_LIVES;
    state.segments = INITIAL_SEGMENTS;
    state.goldenCollected = 0;
    inputLog = '';
    completionRequestActive = false;
    state.cameraX = 0;
    state.completed = false;
    state.gameOver = false;
    state.checkpoint = { x: START.x, y: START.y };
    state.checkpointReached = false;
    state.particles = [];
    state.shake = 0;
    state.paused = false;
    resetPlayer({ x: START.x, y: START.y });
    hideOverlay();
    updateHud();
    showToast('A fresh start. You’ve got this!');
  }

  function addBurst(x, y, color, count = 12) {
    for (let i = 0; i < count; i += 1) {
      const angle = (Math.PI * 2 * i) / count + Math.random() * 0.3;
      const speed = 45 + Math.random() * 125;
      state.particles.push({
        x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed - 35,
        life: 0.55 + Math.random() * 0.45, maxLife: 1, radius: 2 + Math.random() * 3, color,
      });
    }
  }

  function loseLife(reason) {
    if (state.completed || state.gameOver || state.paused) return;
    state.lives -= 1;
    state.shake = 12;
    addBurst(player.x, player.y, '#ff8b77', 16);
    if (state.lives <= 0) {
      state.gameOver = true;
      updateHud();
      showOverlay({
        emoji: '💫', eyebrow: 'ONE MORE TRY?', title: 'That was a close one!',
        message: `${reason} Your worm needs a little rest. Give the meadow another go.`,
        action: 'Try again', callback: restartGame,
      });
      return;
    }
    resetPlayer(state.checkpoint);
    updateHud();
    showToast(`${reason} ${state.lives} ${state.lives === 1 ? 'heart' : 'hearts'} remaining.`, 2200);
  }

  function setCanvasSize() {
    const rect = canvas.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const width = Math.max(1, Math.round(rect.width * dpr));
    const height = Math.max(1, Math.round(rect.height * dpr));
    if (canvas.width !== width || canvas.height !== height) {
      canvas.width = width;
      canvas.height = height;
    }
    ctx.setTransform(width / W, 0, 0, height / H, 0, 0);
    ctx.imageSmoothingEnabled = true;
  }

  function roundedRect(context, x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height / 2);
    context.beginPath();
    context.moveTo(x + r, y);
    context.arcTo(x + width, y, x + width, y + height, r);
    context.arcTo(x + width, y + height, x, y + height, r);
    context.arcTo(x, y + height, x, y, r);
    context.arcTo(x, y, x + width, y, r);
    context.closePath();
  }

  function drawCloud(x, y, scale = 1, alpha = 1) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(scale, scale);
    ctx.globalAlpha = alpha;
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.ellipse(0, 8, 40, 15, 0, 0, Math.PI * 2);
    ctx.ellipse(-22, 3, 20, 14, 0, 0, Math.PI * 2);
    ctx.ellipse(1, -5, 23, 21, 0, 0, Math.PI * 2);
    ctx.ellipse(24, 2, 21, 15, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  function drawTree(x, y, scale = 1, alpha = 1) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(scale, scale);
    ctx.globalAlpha = alpha;
    ctx.fillStyle = '#9c7956';
    roundedRect(ctx, -8, -55, 16, 62, 6);
    ctx.fill();
    const blobs = [
      [-19, -61, 25, '#82bf83'], [10, -76, 29, '#6eb77a'],
      [28, -53, 23, '#8bc98a'], [-3, -43, 29, '#78bd7c'],
    ];
    blobs.forEach(([bx, by, radius, color]) => {
      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.arc(bx, by, radius, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.restore();
  }

  function drawBackground() {
    const sky = ctx.createLinearGradient(0, 0, 0, H);
    sky.addColorStop(0, WORLD.skyTop || '#a9dff0');
    sky.addColorStop(0.62, WORLD.skyBottom || '#d4f0e9');
    sky.addColorStop(1, WORLD.ground || '#edf4d9');
    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, W, H);

    ctx.save();
    ctx.fillStyle = WORLD.sun || '#fff3b6';
    ctx.shadowColor = WORLD.sun || '#fff4c6';
    ctx.shadowBlur = 38;
    ctx.beginPath();
    ctx.arc(900 - state.cameraX * 0.035, 105, 44, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    const cloudOffset = state.cameraX * 0.12;
    for (let i = 0; i < 5; i += 1) {
      const x = ((i * 285 + 110 - cloudOffset) % (W + 180) + (W + 180)) % (W + 180) - 60;
      drawCloud(x, 95 + (i % 3) * 42, 0.72 + (i % 2) * 0.2, 0.76);
    }

    ctx.save();
    ctx.translate(-state.cameraX * 0.22, 0);
    ctx.fillStyle = WORLD.far || '#a5d4b0';
    ctx.beginPath();
    ctx.moveTo(-100, 420);
    for (let x = -100; x <= WORLD_WIDTH + 150; x += 180) {
      ctx.quadraticCurveTo(x + 90, 280 + Math.sin(x * 0.008) * 30, x + 180, 420);
    }
    ctx.lineTo(WORLD_WIDTH + 250, H);
    ctx.lineTo(-100, H);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = WORLD.near || '#91c89f';
    ctx.beginPath();
    ctx.moveTo(-100, 470);
    for (let x = -100; x <= WORLD_WIDTH + 150; x += 230) {
      ctx.quadraticCurveTo(x + 110, 350 + Math.cos(x * 0.006) * 27, x + 230, 470);
    }
    ctx.lineTo(WORLD_WIDTH + 250, H);
    ctx.lineTo(-100, H);
    ctx.closePath();
    ctx.fill();
    ctx.restore();

    for (let i = 0; i < 12; i += 1) {
      const worldX = i * 230 + 55;
      const screenX = worldX - state.cameraX * 0.48;
      if (screenX > -100 && screenX < W + 100) drawTree(screenX, 475 + (i % 3) * 10, 0.72 + (i % 2) * 0.16, 0.88);
    }

    ctx.fillStyle = WORLD.ground || '#b1dca2';
    ctx.fillRect(0, 550, W, 70);
    ctx.fillStyle = WORLD.grass || '#9acb91';
    ctx.fillRect(0, 550, W, 4);
  }

  function drawPlatform(platform) {
    const x = platform.x - state.cameraX;
    if (x + platform.w < -40 || x > W + 40) return;
    const body = ctx.createLinearGradient(0, platform.y, 0, platform.y + platform.h);
    body.addColorStop(0, WORLD.dirtTop || '#b77c4d');
    body.addColorStop(0.35, WORLD.dirtMid || '#9c6542');
    body.addColorStop(1, WORLD.dirtBottom || '#744a37');
    ctx.fillStyle = body;
    roundedRect(ctx, x, platform.y + 5, platform.w, platform.h, 12);
    ctx.fill();

    const grass = ctx.createLinearGradient(0, platform.y - 7, 0, platform.y + 9);
    grass.addColorStop(0, WORLD.grassLight || '#a4dc79');
    grass.addColorStop(1, WORLD.grass || '#58a95c');
    ctx.fillStyle = grass;
    roundedRect(ctx, x, platform.y - 8, platform.w, 19, 9);
    ctx.fill();
    ctx.fillStyle = WORLD.grassLight || '#d0ee91';
    roundedRect(ctx, x + 5, platform.y - 7, Math.max(0, platform.w - 10), 5, 3);
    ctx.fill();

    ctx.save();
    ctx.globalAlpha = 0.24;
    for (let px = Math.ceil(platform.x / 43) * 43; px < platform.x + platform.w; px += 43) {
      const sx = px - state.cameraX + 12;
      ctx.fillStyle = '#f0c28a';
      ctx.beginPath();
      ctx.ellipse(sx, platform.y + 28 + (Math.floor(px / 43) % 3) * 12, 3 + (px % 2), 2, 0, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = '#543b31';
      ctx.beginPath();
      ctx.arc(sx + 16, platform.y + 56, 2.4, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.restore();

    ctx.fillStyle = WORLD.grass || '#4f984f';
    for (let i = 0; i < Math.floor(platform.w / 37); i += 1) {
      const gx = x + 17 + i * 37;
      ctx.beginPath();
      ctx.moveTo(gx, platform.y - 5);
      ctx.quadraticCurveTo(gx - 5, platform.y - 15, gx - 8, platform.y - 14);
      ctx.quadraticCurveTo(gx - 6, platform.y - 5, gx, platform.y - 5);
      ctx.fill();
    }
  }

  function drawApple(apple, index) {
    if (apple.collected) return;
    const bob = REDUCED_MOTION ? 0 : Math.sin(state.animationFrame * 0.0028 + index * 1.7) * 5;
    const x = apple.x - state.cameraX;
    const y = apple.y + bob;
    if (x < -40 || x > W + 40) return;

    ctx.save();
    ctx.translate(x, y);
    if (!REDUCED_MOTION) ctx.rotate(Math.sin(state.animationFrame * 0.002 + index) * 0.055);
    const golden = apple.kind === 'golden';
    ctx.shadowColor = golden ? '#e9b83f88' : '#bd584d66';
    ctx.shadowBlur = golden ? 18 : 12;
    ctx.shadowOffsetY = 5;
    const appleGradient = ctx.createRadialGradient(-7, -9, 2, 0, 0, 21);
    appleGradient.addColorStop(0, golden ? '#fff6b0' : '#ffaaa0');
    appleGradient.addColorStop(0.42, golden ? '#ffd45c' : '#f04f4e');
    appleGradient.addColorStop(1, golden ? '#d88a22' : '#b92736');
    ctx.fillStyle = appleGradient;
    ctx.beginPath();
    ctx.moveTo(0, -3);
    ctx.bezierCurveTo(-21, -20, -27, 0, -17, 15);
    ctx.bezierCurveTo(-8, 28, -1, 21, 0, 20);
    ctx.bezierCurveTo(12, 27, 21, 18, 22, 4);
    ctx.bezierCurveTo(24, -12, 11, -21, 0, -3);
    ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.strokeStyle = '#7b3d2e';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(1, -10);
    ctx.quadraticCurveTo(0, -19, 5, -23);
    ctx.stroke();
    ctx.fillStyle = golden ? '#7baf3f' : '#74bd61';
    ctx.beginPath();
    ctx.ellipse(11, -20, 8, 4, -0.55, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#ffffffb0';
    ctx.beginPath();
    ctx.ellipse(-10, -3, 3, 6, -0.4, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  function drawSpikes(spike) {
    const x = spike.x - state.cameraX;
    if (x + spike.w < -30 || x > W + 30) return;
    const count = Math.max(2, Math.floor(spike.w / 18));
    ctx.fillStyle = WORLD.hazard || '#a94e47';
    roundedRect(ctx, x, spike.y - 5, spike.w, 9, 4);
    ctx.fill();
    for (let i = 0; i < count; i += 1) {
      const sx = x + (i + 0.5) * (spike.w / count);
      const spikeGradient = ctx.createLinearGradient(sx, spike.y - spike.h, sx + 7, spike.y);
      spikeGradient.addColorStop(0, '#fff0cf');
      spikeGradient.addColorStop(1, WORLD.hazard || '#d87563');
      ctx.fillStyle = spikeGradient;
      ctx.beginPath();
      ctx.moveTo(sx - 8, spike.y - 4);
      ctx.quadraticCurveTo(sx - 4, spike.y - spike.h - 4, sx, spike.y - spike.h);
      ctx.quadraticCurveTo(sx + 4, spike.y - spike.h - 2, sx + 8, spike.y - 4);
      ctx.closePath();
      ctx.fill();
    }
  }

  function drawPortal() {
    const x = PORTAL.x - state.cameraX;
    const y = PORTAL.y;
    const ready = state.apples.every(apple => apple.collected);
    const pulse = REDUCED_MOTION ? 0 : Math.sin(state.animationFrame * 0.004) * 3;
    if (x < -90 || x > W + 90) return;

    ctx.save();
    ctx.translate(x, y);
    ctx.globalAlpha = ready ? 1 : 0.42;
    ctx.shadowColor = ready ? (WORLD.accent || '#a45dff') : '#829e99';
    ctx.shadowBlur = ready ? 27 + pulse : 8;
    ctx.strokeStyle = ready ? (WORLD.accent || '#8d5ce3') : '#819b91';
    ctx.lineWidth = 12;
    ctx.beginPath();
    ctx.ellipse(0, 0, 34 + pulse, 52 + pulse, 0, 0, Math.PI * 2);
    ctx.stroke();
    ctx.shadowBlur = 0;
    const portalGradient = ctx.createRadialGradient(0, 0, 5, 0, 0, 34);
    portalGradient.addColorStop(0, ready ? '#f7e9ff' : '#cbded5');
    portalGradient.addColorStop(0.55, ready ? (WORLD.accent || '#9e79e8') : '#9bb9ac');
    portalGradient.addColorStop(1, ready ? '#453278' : '#648b7d');
    ctx.fillStyle = portalGradient;
    ctx.beginPath();
    ctx.ellipse(0, 0, 26, 43, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#ffffffa8';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.ellipse(-2, 0, 12, 31, 0, 0, Math.PI * 2);
    ctx.stroke();
    ctx.restore();

    ctx.fillStyle = ready ? '#6747a2' : '#718b80';
    ctx.font = '900 11px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(ready ? 'EXIT OPEN' : 'LOCKED', x, y + 70);
  }

  function pointAtDistance(distance) {
    const history = player.history;
    if (distance <= 0 || history.length < 2) return history[0] || { x: player.x, y: player.y };
    for (let i = history.length - 1; i > 0; i -= 1) {
      const newer = history[i];
      const older = history[i - 1];
      if (older.distance <= distance && newer.distance >= distance) {
        const span = Math.max(0.001, newer.distance - older.distance);
        const t = (distance - older.distance) / span;
        return { x: older.x + (newer.x - older.x) * t, y: older.y + (newer.y - older.y) * t };
      }
    }
    return history[0];
  }

  function drawWormSegment(point, next, index, isHead) {
    const angle = isHead
      ? Math.atan2(point.y - next.y, point.x - next.x)
      : Math.atan2(next.y - point.y, next.x - point.x);
    const radiusX = isHead ? 22 : Math.max(11, 17 - index * 0.18);
    const radiusY = isHead ? 19 : Math.max(10, 15 - index * 0.13);
    ctx.save();
    const breathing = REDUCED_MOTION ? 1 : 1 + Math.sin(state.animationFrame * 0.006 - index * 0.42) * 0.025;
    const idleBob = REDUCED_MOTION ? 0 : Math.sin(state.animationFrame * 0.004 - index * 0.55) * (isHead ? 1.6 : 0.9);
    ctx.translate(point.x - state.cameraX, point.y + idleBob);
    ctx.rotate(angle);
    const jumpStretch = isHead ? 1 + Math.min(0.12, Math.abs(player.vy) / 5200) : 1;
    ctx.scale(breathing * jumpStretch, (1 / breathing) / jumpStretch);
    ctx.shadowColor = '#2f68434a';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetY = 6;

    const skin = ctx.createLinearGradient(0, -radiusY, 0, radiusY);
    skin.addColorStop(0, '#d9ff9b');
    skin.addColorStop(0.25, '#9ae968');
    skin.addColorStop(0.66, '#55bd54');
    skin.addColorStop(1, '#25824c');
    ctx.fillStyle = skin;
    ctx.beginPath();
    ctx.ellipse(0, 0, radiusX, radiusY, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.shadowColor = 'transparent';

    ctx.strokeStyle = '#297b48';
    ctx.lineWidth = 1.6;
    ctx.beginPath();
    ctx.ellipse(0, 0, radiusX - 1, radiusY - 1, 0, 0, Math.PI * 2);
    ctx.stroke();

    ctx.fillStyle = '#ffffff82';
    ctx.beginPath();
    ctx.ellipse(-3, -radiusY * 0.45, radiusX * 0.52, 3.2, -0.12, 0, Math.PI * 2);
    ctx.fill();

    if (!isHead) {
      ctx.strokeStyle = '#267b4b88';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.moveTo(radiusX * 0.55, -radiusY * 0.62);
      ctx.quadraticCurveTo(radiusX * 0.82, 0, radiusX * 0.55, radiusY * 0.62);
      ctx.stroke();
      ctx.fillStyle = '#f0b4a0';
      ctx.globalAlpha = 0.38;
      ctx.beginPath();
      ctx.ellipse(-radiusX * 0.15, radiusY * 0.35, 5, 2.6, 0, 0, Math.PI * 2);
      ctx.fill();
      ctx.globalAlpha = 1;
    } else {
      ctx.save();
      ctx.fillStyle = '#ffffff';
      ctx.beginPath();
      const blink = !REDUCED_MOTION && (state.animationFrame % 4300) > 4180;
      const eyeHeight = blink ? 1.7 : 8.5;
      ctx.ellipse(8, -7, 6.6, eyeHeight, 0.1, 0, Math.PI * 2);
      ctx.ellipse(18, -5, 6.1, blink ? 1.7 : 7.8, 0.1, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = '#183e32';
      ctx.beginPath();
      ctx.ellipse(10, -6, 2.7, 4.2, 0, 0, Math.PI * 2);
      ctx.ellipse(20, -4, 2.6, 4, 0, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = '#fff';
      ctx.beginPath();
      ctx.arc(11, -8, 1.3, 0, Math.PI * 2);
      ctx.arc(21, -6, 1.2, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = '#315f43';
      ctx.lineWidth = 1.8;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.arc(15, 5, 5, 0.15, 1.45);
      ctx.stroke();
      ctx.fillStyle = '#ed9a9b';
      ctx.globalAlpha = 0.6;
      ctx.beginPath();
      ctx.ellipse(3, 5, 4, 2.6, 0, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }
    ctx.restore();
  }

  function drawWorm() {
    const points = [];
    for (let i = 0; i < state.segments; i += 1) {
      points.push(pointAtDistance(Math.max(0, player.distance - (i + 1) * 25)));
    }
    const tail = points[points.length - 1];
    for (let i = points.length - 1; i >= 0; i -= 1) {
      const current = points[i];
      const next = i === 0 ? { x: player.x, y: player.y } : points[i - 1];
      drawWormSegment(current, next, i, false);
    }
    drawWormSegment({ x: player.x, y: player.y }, points[0] || { x: player.x - player.facing * 10, y: player.y }, 0, true);
    if (tail && state.segments < 1) return;
  }

  function drawParticles() {
    state.particles.forEach(particle => {
      ctx.save();
      ctx.globalAlpha = Math.max(0, particle.life / particle.maxLife);
      ctx.fillStyle = particle.color;
      ctx.beginPath();
      ctx.arc(particle.x - state.cameraX, particle.y, particle.radius, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    });
  }

  function render() {
    ctx.save();
    if (state.shake > 0) {
      const amount = state.shake * (REDUCED_MOTION ? 0 : 0.5);
      ctx.translate((Math.random() - 0.5) * amount, (Math.random() - 0.5) * amount);
    }
    drawBackground();
    platforms.forEach(drawPlatform);
    spikes.forEach(drawSpikes);
    state.apples.forEach(drawApple);
    state.goldenApples.forEach(drawApple);
    drawPortal();
    drawParticles();
    drawWorm();
    ctx.restore();
  }

  function collectApple(apple) {
    if (apple.collected) return;
    apple.collected = true;
    const golden = apple.kind === 'golden';
    state.score += golden ? 500 : 100;
    state.segments += golden ? 1 : 2;
    if (golden) state.goldenCollected += 1;
    addBurst(apple.x, apple.y, golden ? '#ffd45c' : '#ffcf61', golden ? 24 : 18);
    updateHud();
    const remaining = state.apples.filter(item => !item.collected).length;
    showToast(golden
      ? 'Golden treasure! A bonus is yours.'
      : (remaining === 0 ? 'All apples found! The portal is open!' : 'Yummy! Your worm grew longer.'));
  }

  function finishLevel() {
    if (state.completed || completionRequestActive) return;
    state.completed = true;
    state.score += 250;
    addBurst(PORTAL.x, PORTAL.y, WORLD.accent || '#c2a1ff', 30);
    updateHud();

    if (!LIVE_SESSION) {
      showOverlay({
        emoji: '🏆', eyebrow: `${(WORLD.name || 'WORLD').toUpperCase()} COMPLETE`, title: 'You made it!',
        message: `All apples collected and the portal reached. Your little worm scored ${state.score} points!`,
        action: 'Play again', callback: restartGame,
      });
      return;
    }

    completionRequestActive = true;
    showToast('Portal reached! Checking your adventure…', 6000);
    fetch(root.dataset.completeUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': root.dataset.csrf || '',
      },
      body: JSON.stringify({ input_log: inputLog }),
    })
      .then(async response => {
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'The server could not verify this run.');
        return payload;
      })
      .then(result => {
        const nextUrl = root.dataset.nextUrl || '/levels';
        const stars = '⭐'.repeat(Math.max(1, Math.min(3, Number(result.stars || 1))));
        showOverlay({
          emoji: '🏆', eyebrow: 'LEVEL CLEARED', title: 'You made it!',
          message: `${stars} · ${Number(result.score || state.score)} points · ${Number(result.coins_earned || 0)} coins earned. Your next adventure is ready!`,
          action: 'Continue adventure', callback: () => { window.location.assign(nextUrl); },
        });
      })
      .catch(error => {
        state.completed = false;
        completionRequestActive = false;
        showOverlay({
          emoji: '🛡️', eyebrow: 'RUN NOT VERIFIED', title: 'Let’s try that again',
          message: error.message || 'Your run could not be verified. Restart the level and try once more.',
          action: 'Restart level', callback: restartGame,
        });
      });
  }

  function update(dt) {
    if (state.paused || state.completed || state.gameOver) return;
    if (LIVE_SESSION && inputLog.length < MAX_RECORDING_FRAMES) {
      const frameBits = (input.left ? 1 : 0) | (input.right ? 2 : 0) | (input.jumpQueued ? 4 : 0);
      inputLog += String(frameBits);
    }
    const previousX = player.x;
    const previousY = player.y;
    const direction = (input.right ? 1 : 0) - (input.left ? 1 : 0);

    if (direction !== 0) {
      player.vx += direction * MOVE_ACCEL * dt;
      player.facing = direction;
    } else {
      const friction = player.grounded ? 2100 : 520;
      if (Math.abs(player.vx) <= friction * dt) player.vx = 0;
      else player.vx -= Math.sign(player.vx) * friction * dt;
    }
    player.vx = Math.max(-MAX_SPEED, Math.min(MAX_SPEED, player.vx));

    if (input.jumpQueued) {
      if (player.grounded || player.coyote > 0) {
        player.vy = -JUMP_SPEED;
        player.grounded = false;
        player.coyote = 0;
        addBurst(player.x, player.y + 15, '#d5ef99', 6);
      }
      input.jumpQueued = false;
    }

    player.x += player.vx * dt;
    player.x = Math.max(PLAYER_RADIUS, Math.min(WORLD_WIDTH - PLAYER_RADIUS, player.x));

    const wasGrounded = player.grounded;
    player.vy += GRAVITY * dt;
    player.y += player.vy * dt;
    player.grounded = false;

    if (player.vy >= 0) {
      for (const platform of platforms) {
        const horizontalOverlap = player.x + PLAYER_RADIUS * 0.7 > platform.x && player.x - PLAYER_RADIUS * 0.7 < platform.x + platform.w;
        const crossedTop = previousY + PLAYER_RADIUS <= platform.y + 8 && player.y + PLAYER_RADIUS >= platform.y;
        if (horizontalOverlap && crossedTop) {
          player.y = platform.y - PLAYER_RADIUS;
          player.vy = 0;
          player.grounded = true;
          break;
        }
      }
    }

    if (player.grounded) player.coyote = 0.12;
    else player.coyote = Math.max(0, player.coyote - dt);

    const moved = Math.hypot(player.x - previousX, player.y - previousY);
    player.distance += moved;
    player.history.push({ x: player.x, y: player.y, distance: player.distance });
    const minDistance = player.distance - (state.segments + 4) * 25 - 100;
    while (player.history.length > 2 && player.history[1].distance < minDistance) player.history.shift();

    for (const apple of [...state.apples, ...state.goldenApples]) {
      if (!apple.collected && Math.hypot(player.x - apple.x, player.y - apple.y) < (apple.kind === 'golden' ? 36 : 35)) collectApple(apple);
    }

    for (const spike of spikes) {
      const horizontal = player.x + PLAYER_RADIUS * 0.7 > spike.x && player.x - PLAYER_RADIUS * 0.7 < spike.x + spike.w;
      const vertical = player.y + PLAYER_RADIUS > spike.y - spike.h + 4 && player.y + PLAYER_RADIUS * 0.55 < spike.y + 7;
      if (horizontal && vertical) {
        loseLife('Ouch! Watch out for those thorns.');
        return;
      }
    }

    if (!state.checkpointReached && player.x > COURSE_CHECKPOINT.x && player.grounded) {
      state.checkpoint = { x: COURSE_CHECKPOINT.x, y: COURSE_CHECKPOINT.y };
      state.checkpointReached = true;
      addBurst(COURSE_CHECKPOINT.x, COURSE_CHECKPOINT.y - 6, '#a9e8a6', 18);
      showToast('Checkpoint reached! You are doing great.');
    }

    if (player.y > H + 120) {
      loseLife('The gap was a little too wide.');
      return;
    }

    const applesReady = state.apples.every(apple => apple.collected);
    if (applesReady && Math.hypot(player.x - PORTAL.x, player.y - PORTAL.y) < 56) {
      finishLevel();
    }

    const targetCamera = Math.max(0, Math.min(WORLD_WIDTH - W, player.x - 360));
    state.cameraX += (targetCamera - state.cameraX) * Math.min(1, dt * 5.5);
    state.particles = state.particles.filter(particle => particle.life > 0);
    state.particles.forEach(particle => {
      particle.life -= dt;
      particle.x += particle.vx * dt;
      particle.y += particle.vy * dt;
      particle.vy += 280 * dt;
    });
    state.shake = Math.max(0, state.shake - 34 * dt);
    state.animationFrame += dt * 1000;

    if (!wasGrounded && player.grounded && Math.abs(player.vx) > 80) addBurst(player.x, player.y + 14, '#b5da8d', 4);
  }

  let accumulator = 0;

  function frame(time) {
    if (!lastFrame) lastFrame = time;
    const elapsed = Math.min(0.1, Math.max(0, (time - lastFrame) / 1000));
    lastFrame = time;
    accumulator = Math.min(0.12, accumulator + elapsed);
    while (accumulator >= FIXED_STEP) {
      update(FIXED_STEP);
      accumulator -= FIXED_STEP;
    }
    state.animationFrame = time;
    render();
    requestAnimationFrame(frame);
  }

  function setControl(name, pressed) {
    if (name === 'left' || name === 'right') input[name] = pressed;
    if (name === 'jump' && pressed) input.jumpQueued = true;
  }

  window.addEventListener('keydown', event => {
    const key = event.key.toLowerCase();
    if (['arrowleft', 'arrowright', 'arrowup', ' ', 'spacebar'].includes(key)) event.preventDefault();
    if (key === 'arrowleft' || key === 'a') setControl('left', true);
    if (key === 'arrowright' || key === 'd') setControl('right', true);
    if (!event.repeat && (key === 'arrowup' || key === 'w' || key === ' ' || key === 'spacebar')) setControl('jump', true);
    if (key === 'escape' || key === 'p') pauseGame();
  }, { passive: false });

  window.addEventListener('keyup', event => {
    const key = event.key.toLowerCase();
    if (key === 'arrowleft' || key === 'a') setControl('left', false);
    if (key === 'arrowright' || key === 'd') setControl('right', false);
    if (key === 'arrowup' || key === 'w' || key === ' ' || key === 'spacebar') input.jumpQueued = false;
  });
  window.addEventListener('blur', () => {
    input.left = false;
    input.right = false;
    input.jumpQueued = false;
  });

  document.querySelectorAll('[data-platformer-control]').forEach(button => {
    const name = button.dataset.platformerControl;
    const release = () => {
      setControl(name, false);
      button.classList.remove('is-active');
    };
    button.addEventListener('pointerdown', event => {
      event.preventDefault();
      button.setPointerCapture?.(event.pointerId);
      setControl(name, true);
      button.classList.add('is-active');
    });
    button.addEventListener('pointerup', release);
    button.addEventListener('pointercancel', release);
    button.addEventListener('lostpointercapture', release);
    button.addEventListener('contextmenu', event => event.preventDefault());
  });

  el.pause?.addEventListener('click', pauseGame);
  document.getElementById('platformerRestart')?.addEventListener('click', restartGame);

  if ('ResizeObserver' in window) {
    resizeObserver = new ResizeObserver(setCanvasSize);
    resizeObserver.observe(canvas);
  } else {
    window.addEventListener('resize', setCanvasSize);
  }

  resetPlayer({ x: START.x, y: START.y });
  setCanvasSize();
  updateHud();
  showToast(`Run, jump and find all ${state.apples.length} apples!`);
  requestAnimationFrame(frame);

  window.addEventListener('pagehide', () => {
    if (resizeObserver) resizeObserver.disconnect();
    if (toastTimer) window.clearTimeout(toastTimer);
  }, { once: true });
})();
