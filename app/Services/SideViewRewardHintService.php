<?php

namespace App\Services;

use App\Game\SideViewLevelSolutions;
use App\Models\GameSession;

/**
 * Supplies one safe directional hint for the optional rewarded-assist flow.
 * The normal free hint button remains progressive and repeatable.
 */
class SideViewRewardHintService
{
    public function suggest(GameSession $session): array
    {
        $layout = $session->level?->config['sideView'] ?? [];
        $state = $session->state ?? [];
        $worm = array_values($state['worm'] ?? []);
        $head = $worm[0] ?? null;

        if (! $head || $layout === []) {
            return ['hint' => 'Restart the puzzle to get a fresh safe route.', 'direction' => null, 'target' => null];
        }

        $number = (int) ($session->level?->number ?? 0);
        $solution = SideViewLevelSolutions::for($number);
        $step = (int) ($state['moves'] ?? 0);
        $symbolMap = ['r' => 'right', 'l' => 'left', 'u' => 'up', 'd' => 'down'];

        // Prefer the authored, server-verified route while the player is still
        // following it. A one-step safety check prevents a stale route hint from
        // walking into the player's current body or a newly closed obstacle.
        if ($solution !== null && isset($solution[$step])) {
            $direction = $symbolMap[$solution[$step]] ?? null;
            if ($direction && $this->isImmediateMoveSafe($layout, $state, $direction)) {
                return $this->payload($direction, $this->nextTargetType($state), 'Follow the verified route: move ' . $direction . '.');
            }
        }

        // If the player has explored off-route, find a safe adjacent move that
        // reduces the distance to the next required objective. This is a hint,
        // not an auto-solver: normal movement and collision checks remain on the server.
        $targetType = $this->nextTargetType($state);
        $targets = $this->nextTargets($state, $layout);
        $safe = [];
        foreach (['right', 'down', 'left', 'up'] as $direction) {
            if (! $this->isImmediateMoveSafe($layout, $state, $direction)) {
                continue;
            }
            [$dx, $dy] = $this->vector($direction);
            $next = ['x' => (int) $head['x'] + $dx, 'y' => (int) $head['y'] + $dy];
            $distance = $targets === [] ? 0 : min(array_map(
                fn (array $target) => abs($target['x'] - $next['x']) + abs($target['y'] - $next['y']),
                $targets
            ));
            $safe[] = ['direction' => $direction, 'distance' => $distance];
        }

        usort($safe, fn (array $a, array $b) => $a['distance'] <=> $b['distance']);
        if ($safe === []) {
            return ['hint' => 'No safe directional hint is available from this position. Try the free walkthrough or restart.', 'direction' => null, 'target' => $targetType];
        }

        $direction = $safe[0]['direction'];
        return $this->payload($direction, $targetType, 'Try moving ' . $direction . ' to make progress toward the next ' . $targetType . '.');
    }

    private function payload(string $direction, string $target, string $hint): array
    {
        return ['hint' => $hint, 'direction' => $direction, 'target' => $target];
    }

    private function nextTargetType(array $state): string
    {
        foreach ([['switches', 'rune'], ['apples', 'apple'], ['goldenApples', 'golden apple']] as [$key, $label]) {
            if (! empty($state[$key])) {
                return $label;
            }
        }

        return 'portal';
    }

    private function nextTargets(array $state, array $layout): array
    {
        foreach (['switches', 'apples', 'goldenApples'] as $key) {
            if (! empty($state[$key])) {
                return array_values($state[$key]);
            }
        }

        return isset($layout['portal']) ? [$layout['portal']] : [];
    }

    private function isImmediateMoveSafe(array $layout, array $state, string $direction): bool
    {
        $worm = array_values($state['worm'] ?? []);
        $head = $worm[0] ?? null;
        if (! $head) {
            return false;
        }

        [$dx, $dy] = $this->vector($direction);
        $next = ['x' => (int) $head['x'] + $dx, 'y' => (int) $head['y'] + $dy];
        $width = (int) ($layout['width'] ?? 14);
        $height = (int) ($layout['height'] ?? 8);
        if ($next['x'] < 0 || $next['x'] >= $width || $next['y'] < 0 || $next['y'] >= $height) {
            return false;
        }

        $terrain = $this->index($layout['terrain'] ?? []);
        $spikes = $this->index($layout['spikes'] ?? []);
        $gates = ! empty($state['gatesOpen']) ? [] : $this->index($layout['gates'] ?? []);
        $blocks = array_values($state['blocks'] ?? $layout['blocks'] ?? []);
        $apples = array_values($state['apples'] ?? []);
        $golden = array_values($state['goldenApples'] ?? []);
        $switches = array_values($state['switches'] ?? $layout['switches'] ?? []);
        $nextKey = $this->key($next);

        if (isset($terrain[$nextKey]) || isset($gates[$nextKey]) || isset($spikes[$nextKey])) {
            return false;
        }

        $blockIndex = $this->find($blocks, $next);
        if ($blockIndex !== null) {
            $beyond = ['x' => $next['x'] + $dx, 'y' => $next['y'] + $dy];
            if ($beyond['x'] < 0 || $beyond['x'] >= $width || $beyond['y'] < 0 || $beyond['y'] >= $height) {
                return false;
            }
            $beyondKey = $this->key($beyond);
            if (isset($terrain[$beyondKey]) || isset($gates[$beyondKey]) || isset($spikes[$beyondKey])
                || $this->find($blocks, $beyond) !== null || $this->find($worm, $beyond) !== null
                || $this->find($apples, $beyond) !== null || $this->find($golden, $beyond) !== null
                || $this->find($switches, $beyond) !== null || $this->same($beyond, $layout['portal'] ?? null)) {
                return false;
            }
        }

        $grows = $this->find($apples, $next) !== null || $this->find($golden, $next) !== null;
        $tailStays = $grows || (int) ($state['growthPending'] ?? 0) > 0;
        $body = $tailStays ? $worm : array_slice($worm, 0, max(0, count($worm) - 1));
        if ($this->find($body, $next) !== null) {
            return false;
        }

        // Check whether this move would leave the entire rigid worm unsupported
        // and falling out of the board or into a thorn row.
        $moved = array_merge([$next], $worm);
        if (! $grows && (int) ($state['growthPending'] ?? 0) <= 0) {
            array_pop($moved);
        }
        $settledBlocks = $blocks;
        if ($blockIndex !== null) {
            unset($settledBlocks[$blockIndex]);
            $settledBlocks[] = $beyond;
        }

        // Mirror the engine's crate-settling order before deciding whether
        // the worm would remain supported after this move.
        for ($round = 0; $round < $height; $round++) {
            $blockMoved = false;
            usort($settledBlocks, fn (array $a, array $b) => $b['y'] <=> $a['y']);
            foreach ($settledBlocks as $index => $block) {
                $below = ['x' => (int) $block['x'], 'y' => (int) $block['y'] + 1];
                if ($below['y'] >= $height || $below['x'] < 0 || $below['x'] >= $width) {
                    continue;
                }

                $blockedBelow = isset($terrain[$this->key($below)])
                    || isset($gates[$this->key($below)])
                    || isset($spikes[$this->key($below)])
                    || $this->find($settledBlocks, $below) !== null
                    || $this->find($moved, $below) !== null
                    || $this->find($apples, $below) !== null
                    || $this->find($golden, $below) !== null
                    || $this->find($switches, $below) !== null;

                if (! $blockedBelow) {
                    $settledBlocks[$index] = $below;
                    $blockMoved = true;
                }
            }

            if (! $blockMoved) {
                break;
            }
        }

        $solid = $terrain + $gates;
        foreach ($settledBlocks as $block) {
            $solid[$this->key($block)] = true;
        }
        for ($fall = 0; $fall < $height; $fall++) {
            $supported = false;
            foreach ($moved as $part) {
                if (isset($solid[$this->key(['x' => $part['x'], 'y' => $part['y'] + 1])])) {
                    $supported = true;
                    break;
                }
            }
            if ($supported) {
                return true;
            }
            foreach ($moved as $part) {
                $below = ['x' => $part['x'], 'y' => $part['y'] + 1];
                if ($below['y'] >= $height || isset($spikes[$this->key($below)])) {
                    return false;
                }
            }
            $moved = array_map(fn (array $part) => ['x' => $part['x'], 'y' => $part['y'] + 1], $moved);
        }

        return false;
    }

    private function vector(string $direction): array
    {
        return match ($direction) {
            'right' => [1, 0], 'down' => [0, 1], 'left' => [-1, 0], default => [0, -1],
        };
    }

    private function index(array $points): array
    {
        $indexed = [];
        foreach ($points as $point) {
            $indexed[$this->key($point)] = true;
        }
        return $indexed;
    }

    private function key(array $point): string
    {
        return (int) $point['x'] . ':' . (int) $point['y'];
    }

    private function find(array $points, array $target): ?int
    {
        foreach ($points as $index => $point) {
            if ((int) $point['x'] === (int) $target['x'] && (int) $point['y'] === (int) $target['y']) {
                return $index;
            }
        }
        return null;
    }

    private function same(array $left, ?array $right): bool
    {
        return $right !== null && (int) $left['x'] === (int) ($right['x'] ?? -1) && (int) $left['y'] === (int) ($right['y'] ?? -1);
    }
}
