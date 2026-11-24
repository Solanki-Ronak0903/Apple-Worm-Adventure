<?php

namespace App\Services;

use App\Models\GameSession;

/** Server-authoritative rules for the side-view, tile-stepped worm puzzles. */
class SideViewPuzzleService
{
    public function move(GameSession $game, string $direction): array
    {
        $config = $game->level?->config ?? [];
        $level = $config['sideView'] ?? null;
        abort_unless(is_array($level), 422, 'This level does not use the side-view puzzle engine.');

        $state = $game->state ?? [];
        abort_if(! empty($state['won']) || ! empty($state['lost']), 409, 'This puzzle has already ended.');
        abort_unless(($state['mode'] ?? null) === 'side-view-puzzle', 409, 'This session uses an older board. Restart the level to load the new puzzle.');

        $vectors = [
            'left' => [-1, 0],
            'right' => [1, 0],
            'up' => [0, -1],
            'down' => [0, 1],
        ];
        abort_unless(isset($vectors[$direction]), 422, 'Choose a valid direction.');
        [$dx, $dy] = $vectors[$direction];

        $baseMoveLimit = max(1, (int) ($state['move_limit'] ?? $level['moveLimit'] ?? ((int) ($level['parMoves'] ?? 10) + 5)));
        $bonusMoves = max(0, (int) ($state['bonus_moves'] ?? 0));
        if ((int) ($state['moves'] ?? 0) >= ($baseMoveLimit + $bonusMoves)) {
            return $this->fail($game, $state, 'Move limit reached. Restart the level and plan a more efficient route.');
        }

        $worm = array_values($state['worm'] ?? []);
        abort_if(count($worm) < 2, 422, 'The worm state is invalid. Restart this level.');

        // Capture the last valid board before attempting a move. A rewarded
        // revive can restore this exact state if the attempted move is fatal.
        $state['checkpoint'] = $this->checkpoint($state);

        $width = max(1, (int) ($level['width'] ?? 14));
        $height = max(1, (int) ($level['height'] ?? 8));
        $terrain = $this->index($level['terrain'] ?? []);
        $spikes = $this->index($level['spikes'] ?? []);
        $gates = $this->index($level['gates'] ?? []);
        $gatesOpen = (bool) ($state['gatesOpen'] ?? empty($level['gates']));
        $closedGates = $gatesOpen ? [] : $gates;
        $apples = array_values($state['apples'] ?? []);
        $goldenApples = array_values($state['goldenApples'] ?? []);
        $blocks = array_values($state['blocks'] ?? []);
        $switches = array_values($state['switches'] ?? $level['switches'] ?? []);

        $head = $worm[0];
        $next = ['x' => (int) $head['x'] + $dx, 'y' => (int) $head['y'] + $dy];
        if (! $this->inside($next, $width, $height)) {
            return $this->fail($game, $state, 'The worm slipped beyond the edge. Try a safer route.');
        }

        $nextKey = $this->key($next);
        if (isset($terrain[$nextKey])) {
            return $this->blocked($state, 'Solid stone is in the way.');
        }
        if (isset($closedGates[$nextKey])) {
            return $this->blocked($state, 'The rune gate is closed. Find and touch the switch.');
        }

        // Crates move one tile at a time. They cannot be pushed into hazards,
        // collectibles, gates, the portal, terrain, another crate or the worm.
        $blockIndex = $this->findIndex($blocks, $next);
        if ($blockIndex !== null) {
            $beyond = ['x' => $next['x'] + $dx, 'y' => $next['y'] + $dy];
            $blockedBeyond = ! $this->inside($beyond, $width, $height)
                || isset($terrain[$this->key($beyond)])
                || isset($closedGates[$this->key($beyond)])
                || isset($spikes[$this->key($beyond)])
                || $this->findIndex($blocks, $beyond) !== null
                || $this->findIndex($worm, $beyond) !== null
                || $this->findIndex($apples, $beyond) !== null
                || $this->findIndex($goldenApples, $beyond) !== null
                || $this->findIndex($switches, $beyond) !== null
                || $this->same($beyond, $level['portal'] ?? null);

            if ($blockedBeyond) {
                return $this->blocked($state, 'There is no safe space to push the crate that way.');
            }

            $blocks[$blockIndex] = $beyond;
            $state['pushes'] = (int) ($state['pushes'] ?? 0) + 1;
        }

        $appleIndex = $this->findIndex($apples, $next);
        $goldenAppleIndex = $this->findIndex($goldenApples, $next);
        $growthPending = max(0, (int) ($state['growthPending'] ?? 0));
        $tailWillStay = $appleIndex !== null || $goldenAppleIndex !== null || $growthPending > 0;
        $bodyForCollision = $tailWillStay
            ? $worm
            : array_slice($worm, 0, max(0, count($worm) - 1));

        if ($this->findIndex($bodyForCollision, $next) !== null) {
            return $this->blocked($state, 'The worm cannot turn through its own body.');
        }
        if (isset($spikes[$nextKey])) {
            return $this->fail($game, $state, 'Ouch! The thorn tiles caught the worm.');
        }

        $state['moves'] = (int) ($state['moves'] ?? 0) + 1;
        array_unshift($worm, $next);

        if ($appleIndex !== null) {
            array_splice($apples, $appleIndex, 1);
            $state['score'] = min(100000, (int) ($state['score'] ?? 0) + 10);
            $state['applesCollected'] = (int) ($state['applesCollected'] ?? 0) + 1;
        } elseif ($goldenAppleIndex !== null) {
            array_splice($goldenApples, $goldenAppleIndex, 1);
            $state['goldenApplesCollected'] = (int) ($state['goldenApplesCollected'] ?? 0) + 1;
            $state['score'] = min(100000, (int) ($state['score'] ?? 0) + 50);
        } elseif ($growthPending > 0) {
            $state['growthPending'] = $growthPending - 1;
        } else {
            array_pop($worm);
        }

        $switchIndex = $this->findIndex($switches, $next);
        if ($switchIndex !== null) {
            array_splice($switches, $switchIndex, 1);
            $state['switchesActivated'] = (int) ($state['switchesActivated'] ?? 0) + 1;
            $gatesOpen = true;
            $closedGates = [];
            $state['score'] = min(100000, (int) ($state['score'] ?? 0) + 20);
        }

        $blocks = $this->settleBlocks(
            $blocks,
            $terrain,
            $spikes,
            $closedGates,
            $apples,
            $goldenApples,
            $switches,
            $worm,
            $width,
            $height
        );

        // If a crate is already sharing a cell with the worm (for example an
        // invalid legacy state), fail safely instead of saving an impossible board.
        foreach ($blocks as $block) {
            if ($this->findIndex($worm, $block) !== null) {
                $state['worm'] = $worm;
                $state['blocks'] = $blocks;
                $state['apples'] = $apples;
                $state['goldenApples'] = $goldenApples;
                $state['switches'] = $switches;
                return $this->fail($game, $state, 'A falling crate blocked the worm. Restart and try another route.');
            }
        }

        // The connected body stays suspended while at least one segment has a
        // solid tile directly below it. Otherwise the complete body falls.
        $gravitySteps = 0;
        while ($gravitySteps < $height) {
            $solidBelow = $terrain + $closedGates;
            foreach ($blocks as $block) {
                $solidBelow[$this->key($block)] = true;
            }

            $supported = false;
            foreach ($worm as $part) {
                $below = ['x' => (int) $part['x'], 'y' => (int) $part['y'] + 1];
                if (isset($solidBelow[$this->key($below)])) {
                    $supported = true;
                    break;
                }
            }
            if ($supported) {
                break;
            }

            $falling = array_map(fn (array $part) => [
                'x' => (int) $part['x'],
                'y' => (int) $part['y'] + 1,
            ], $worm);

            foreach ($falling as $part) {
                $fallKey = $this->key($part);
                if (! $this->inside($part, $width, $height)) {
                    $state['worm'] = $worm;
                    $state['blocks'] = $blocks;
                    $state['apples'] = $apples;
                    $state['goldenApples'] = $goldenApples;
                    $state['switches'] = $switches;
                    return $this->fail($game, $state, 'The whole worm lost its footing. Try again.');
                }
                if (isset($spikes[$fallKey])) {
                    $state['worm'] = $worm;
                    $state['blocks'] = $blocks;
                    $state['apples'] = $apples;
                    $state['goldenApples'] = $goldenApples;
                    $state['switches'] = $switches;
                    return $this->fail($game, $state, 'A fall into the thorns ended the run.');
                }
            }

            $worm = $falling;
            $gravitySteps++;
        }

        // A fruit reached during a fall still counts. Its growth is held as a
        // pending tail segment and materializes on the next successful move.
        $head = $worm[0];
        $landedApple = $this->findIndex($apples, $head);
        $landedGoldenApple = $this->findIndex($goldenApples, $head);
        if ($landedApple !== null) {
            array_splice($apples, $landedApple, 1);
            $state['growthPending'] = (int) ($state['growthPending'] ?? 0) + 1;
            $state['score'] = min(100000, (int) ($state['score'] ?? 0) + 10);
            $state['applesCollected'] = (int) ($state['applesCollected'] ?? 0) + 1;
        } elseif ($landedGoldenApple !== null) {
            array_splice($goldenApples, $landedGoldenApple, 1);
            $state['growthPending'] = (int) ($state['growthPending'] ?? 0) + 1;
            $state['goldenApplesCollected'] = (int) ($state['goldenApplesCollected'] ?? 0) + 1;
            $state['score'] = min(100000, (int) ($state['score'] ?? 0) + 50);
        }

        $state['worm'] = $worm;
        $state['apples'] = $apples;
        $state['goldenApples'] = $goldenApples;
        $state['blocks'] = $blocks;
        $state['switches'] = $switches;
        $state['gatesOpen'] = $gatesOpen;
        $state['gravitySteps'] = (int) ($state['gravitySteps'] ?? 0) + $gravitySteps;

        $portal = $level['portal'] ?? null;
        $atPortal = $portal && $this->same($state['worm'][0], $portal);
        if ($atPortal && empty($state['apples']) && empty($state['goldenApples']) && empty($state['switches'])) {
            $state['won'] = true;
            $state['score'] = min(100000, (int) ($state['score'] ?? 0) + 100);
        }

        $game->update([
            'state' => $state,
            'moves_used' => (int) $state['moves'],
            'apples_collected' => (int) ($state['applesCollected'] ?? 0),
            'score' => (int) ($state['score'] ?? 0),
        ]);

        return [
            'ok' => true,
            'state' => $state,
            'status' => 'active',
            'won' => (bool) ($state['won'] ?? false),
            'collected_apple' => $appleIndex !== null || $landedApple !== null,
            'collected_golden_apple' => $goldenAppleIndex !== null || $landedGoldenApple !== null,
            'activated_switch' => $switchIndex !== null,
            'pushed' => $blockIndex !== null,
            'gravity_steps' => $gravitySteps,
        ];
    }

    private function settleBlocks(
        array $blocks,
        array $terrain,
        array $spikes,
        array $closedGates,
        array $apples,
        array $goldenApples,
        array $switches,
        array $worm,
        int $width,
        int $height
    ): array {
        // Settle lower crates first, then repeat so stacked crates remain stable.
        for ($round = 0; $round < $height; $round++) {
            $moved = false;
            usort($blocks, fn (array $a, array $b) => $b['y'] <=> $a['y']);

            foreach ($blocks as $index => $block) {
                $below = ['x' => (int) $block['x'], 'y' => (int) $block['y'] + 1];
                if ($below['y'] >= $height || ! $this->inside($below, $width, $height)) {
                    continue;
                }

                $blocked = isset($terrain[$this->key($below)])
                    || isset($spikes[$this->key($below)])
                    || isset($closedGates[$this->key($below)])
                    || $this->findIndex($blocks, $below) !== null
                    || $this->findIndex($worm, $below) !== null
                    || $this->findIndex($apples, $below) !== null
                    || $this->findIndex($goldenApples, $below) !== null
                    || $this->findIndex($switches, $below) !== null;

                if (! $blocked) {
                    $blocks[$index] = $below;
                    $moved = true;
                }
            }

            if (! $moved) {
                break;
            }
        }

        return array_values($blocks);
    }

    private function blocked(array $state, string $reason): array
    {
        return ['ok' => true, 'blocked' => true, 'reason' => $reason, 'state' => $state, 'status' => 'active'];
    }

    private function fail(GameSession $game, array $state, string $reason): array
    {
        // A move-limit failure occurs between moves, so the current board is
        // the latest safe checkpoint. Preserve it before marking the run lost.
        if (str_contains(strtolower($reason), 'move limit')) {
            $state['checkpoint'] = $this->checkpoint($state);
        }

        $state['lost'] = true;
        $state['failure_reason'] = $reason;
        $game->update([
            'status' => 'failed',
            'state' => $state,
            'moves_used' => (int) ($state['moves'] ?? 0),
            'score' => (int) ($state['score'] ?? 0),
            'finished_at' => now(),
        ]);

        return ['ok' => true, 'failed' => true, 'reason' => $reason, 'state' => $state, 'status' => 'failed'];
    }

    private function checkpoint(array $state): array
    {
        $keys = [
            'worm', 'apples', 'goldenApples', 'blocks', 'switches', 'gatesOpen',
            'growthPending', 'moves', 'score', 'applesCollected',
            'goldenApplesCollected', 'pushes', 'gravitySteps', 'switchesActivated',
        ];

        $checkpoint = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $state)) {
                $checkpoint[$key] = $state[$key];
            }
        }

        return $checkpoint;
    }

    private function index(array $points): array
    {
        $indexed = [];
        foreach ($points as $point) {
            $indexed[$this->key($point)] = true;
        }
        return $indexed;
    }

    private function findIndex(array $points, array $target): ?int
    {
        foreach ($points as $index => $point) {
            if ((int) $point['x'] === (int) $target['x'] && (int) $point['y'] === (int) $target['y']) {
                return $index;
            }
        }
        return null;
    }

    private function inside(array $point, int $width, int $height): bool
    {
        return $point['x'] >= 0 && $point['x'] < $width && $point['y'] >= 0 && $point['y'] < $height;
    }

    private function same(array $left, ?array $right): bool
    {
        return $right !== null
            && (int) ($left['x'] ?? -1) === (int) ($right['x'] ?? -2)
            && (int) ($left['y'] ?? -1) === (int) ($right['y'] ?? -2);
    }

    private function key(array $point): string
    {
        return (int) $point['x'] . ':' . (int) $point['y'];
    }
}
