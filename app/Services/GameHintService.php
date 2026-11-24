<?php

namespace App\Services;

use App\Models\GameSession;

class GameHintService
{
    /**
     * Find the shortest safe first move from the authoritative server state.
     * Required keys and switches are targets first, followed by normal apples,
     * optional golden apples, and finally the portal.
     */
    public function suggest(GameSession $session): array
    {
        $level = $session->level;
        $config = $level?->config ?? [];
        $state = $session->state ?? [];
        $worm = array_values($state['worm'] ?? []);
        $head = $worm[0] ?? null;

        if (! $head) {
            return [
                'hint' => 'Restart the puzzle to get a fresh safe route.',
                'direction' => null,
                'target' => null,
            ];
        }

        $rows = (int) ($config['grid']['rows'] ?? $level->grid_rows ?? 8);
        $cols = (int) ($config['grid']['cols'] ?? $level->grid_cols ?? 8);
        $targets = array_values($state['keys'] ?? []);
        $targetType = 'key';

        if ($targets === []) {
            $targets = array_values($state['switches'] ?? []);
            $targetType = 'switch';
        }

        if ($targets === []) {
            $targets = array_values($state['apples'] ?? []);
            $targetType = 'apple';
        }

        if ($targets === []) {
            $targets = array_values($state['megaApples'] ?? []);
            $targetType = 'mega apple';
        }

        if ($targets === []) {
            $targets = array_values($state['mysteryApples'] ?? []);
            $targetType = 'mystery apple';
        }

        if ($targets === []) {
            $targets = array_values($state['goldenApples'] ?? []);
            $targetType = 'golden apple';
        }

        if ($targets === []) {
            $portal = $config['portal'] ?? null;
            $targets = $portal ? [$portal] : [];
            $targetType = 'portal';
        }

        if ($targets === []) {
            return [
                'hint' => 'You have collected everything. Your adventure is complete!',
                'direction' => null,
                'target' => null,
            ];
        }

        $targetKeys = [];
        foreach ($targets as $target) {
            $targetKeys[$this->key((int) $target['x'], (int) $target['y'])] = true;
        }

        $blocked = [];
        foreach (array_merge($config['obstacles'] ?? [], $config['thorns'] ?? [], $config['mudPits'] ?? []) as $obstacle) {
            $blocked[$this->key((int) $obstacle['x'], (int) $obstacle['y'])] = true;
        }

        // A gate remains blocked while one or more keys are still required.
        if (! empty($state['keys']) || ! empty($state['switches'])) {
            foreach (($config['gates'] ?? []) as $gate) {
                $blocked[$this->key((int) $gate['x'], (int) $gate['y'])] = true;
            }
        }

        // Match movement collision rules: the tail cell is allowed to vacate
        // on an ordinary move, but not when the destination contains food and
        // the worm therefore grows. In that case the current tail remains and
        // must stay blocked for the first move of the hint.
        $tail = $worm[count($worm) - 1] ?? null;
        $tailKey = $tail
            ? $this->key((int) $tail['x'], (int) $tail['y'])
            : null;
        $tailContainsFood = $tailKey !== null
            && collect(array_merge($state['apples'] ?? [], $state['goldenApples'] ?? [], $state['megaApples'] ?? [], $state['mysteryApples'] ?? []))
                ->contains(fn ($food) => $this->key((int) $food['x'], (int) $food['y']) === $tailKey);

        $bodyToBlock = array_slice(
            $worm,
            0,
            $tailContainsFood ? count($worm) : max(0, count($worm) - 1)
        );
        foreach ($bodyToBlock as $part) {
            $blocked[$this->key((int) $part['x'], (int) $part['y'])] = true;
        }

        $startX = (int) $head['x'];
        $startY = (int) $head['y'];
        $startKey = $this->key($startX, $startY);
        unset($blocked[$startKey]);

        $directions = [
            ['name' => 'right', 'dx' => 1, 'dy' => 0],
            ['name' => 'down', 'dx' => 0, 'dy' => 1],
            ['name' => 'left', 'dx' => -1, 'dy' => 0],
            ['name' => 'up', 'dx' => 0, 'dy' => -1],
        ];

        $queue = [[$startX, $startY]];
        $queueIndex = 0;
        $visited = [$startKey => true];
        $firstDirection = [];
        $foundKey = null;

        while ($queueIndex < count($queue)) {
            [$x, $y] = $queue[$queueIndex++];
            $currentKey = $this->key($x, $y);

            if ($currentKey !== $startKey && isset($targetKeys[$currentKey])) {
                $foundKey = $currentKey;
                break;
            }

            foreach ($directions as $direction) {
                $nextX = $x + $direction['dx'];
                $nextY = $y + $direction['dy'];

                if ($nextX < 0 || $nextY < 0 || $nextX >= $cols || $nextY >= $rows) {
                    continue;
                }

                $nextKey = $this->key($nextX, $nextY);
                if (isset($visited[$nextKey]) || isset($blocked[$nextKey])) {
                    continue;
                }

                $visited[$nextKey] = true;
                $firstDirection[$nextKey] = $currentKey === $startKey
                    ? $direction['name']
                    : ($firstDirection[$currentKey] ?? null);
                $queue[] = [$nextX, $nextY];
            }
        }

        if ($foundKey === null || empty($firstDirection[$foundKey])) {
            // The legacy board's authored route is a safe fallback for existing
            // sessions whose starting cell is still on the planned route. This
            // keeps rewarded hints useful even when legacy obstacle metadata
            // makes a generic grid search overly conservative.
            $plannedRoute = array_values($config['plannedRoute'] ?? []);
            foreach ($plannedRoute as $index => $point) {
                if ((int) ($point['x'] ?? -1) !== $startX || (int) ($point['y'] ?? -1) !== $startY) {
                    continue;
                }

                $nextPoint = $plannedRoute[$index + 1] ?? null;
                if ($nextPoint) {
                    $routeDirection = (int) $nextPoint['x'] > $startX ? 'right'
                        : ((int) $nextPoint['x'] < $startX ? 'left'
                        : ((int) $nextPoint['y'] > $startY ? 'down' : 'up'));

                    return [
                        'hint' => 'Follow the safe route: move ' . $routeDirection . ' toward the next ' . $targetType . '.',
                        'direction' => $routeDirection,
                        'target' => $targetType,
                    ];
                }
                break;
            }

            return [
                'hint' => 'Look for an open cell around the worm, then work toward the next target.',
                'direction' => null,
                'target' => $targetType,
            ];
        }

        $direction = $firstDirection[$foundKey];
        $targetLabel = $targetType === 'portal' ? 'the portal' : 'the next ' . $targetType;

        return [
            'hint' => 'Move ' . $direction . ' to take the next safe step toward ' . $targetLabel . '.',
            'direction' => $direction,
            'target' => $targetType,
        ];
    }

    private function key(int $x, int $y): string
    {
        return $x . ':' . $y;
    }
}
