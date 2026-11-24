<?php

namespace App\Services;

use App\Game\SideViewLevelSolutions;
use App\Models\GameSession;

/** Progressive clues with a repeatable, complete walkthrough as the final help step. */
class SideViewHintService
{
    public function next(GameSession $game): array
    {
        $layout = $game->level?->config['sideView'] ?? [];
        $stages = array_values(array_filter(
            $layout['hintStages'] ?? [$layout['hint'] ?? 'Look at the terrain and plan where the worm will be supported.'],
            fn ($hint) => is_string($hint) && trim($hint) !== ''
        ));

        if ($stages === []) {
            $stages = ['Look at the terrain and plan where the worm will be supported.'];
        }

        $solution = SideViewLevelSolutions::for((int) ($game->level?->number ?? 0));
        if ($solution !== null) {
            $stages[] = $this->formatWalkthrough($solution);
        }

        $state = $game->state ?? [];
        $total = count($stages);
        $currentStage = min(max(0, (int) ($state['hint_stage'] ?? 0)), $total);
        $stageIndex = min($currentStage, $total - 1);
        $repeatable = $stageIndex === $total - 1;

        // Once the full route is unlocked, keep returning it. The player can
        // request it as often as needed instead of permanently losing the Hint button.
        $state['hint_stage'] = min($currentStage + 1, $total);
        $game->update(['state' => $state]);

        return [
            'hint' => $stages[$stageIndex],
            'stage' => $stageIndex + 1,
            'total' => $total,
            'exhausted' => false,
            'has_more' => true,
            'repeatable' => $repeatable,
        ];
    }

    private function formatWalkthrough(string $solution): string
    {
        $directions = ['r' => 'Right', 'l' => 'Left', 'u' => 'Up', 'd' => 'Down'];
        $groups = [];
        foreach (str_split($solution) as $symbol) {
            $name = $directions[$symbol] ?? null;
            if ($name === null) {
                continue;
            }

            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['direction'] === $name) {
                $groups[$last]['count']++;
            } else {
                $groups[] = ['direction' => $name, 'count' => 1];
            }
        }

        $route = array_map(
            fn (array $group) => $group['direction'] . ($group['count'] > 1 ? ' × ' . $group['count'] : ''),
            $groups
        );

        return 'Full walkthrough (' . strlen($solution) . ' moves): ' . implode(' → ', $route)
            . '. Follow one tile per move. You can reopen this walkthrough whenever you need it.';
    }
}
