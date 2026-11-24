<?php

namespace App\Game;

/**
 * Hand-authored side-view puzzle maps for the initial 25-level release.
 * Coordinates are grid cells. Layouts intentionally combine gaps, shelves,
 * low ceilings, crates, thorns, rune switches and locked passages.
 */
class SideViewLevelCatalog
{
    public static function all(): array
    {
        $levels = [
            // World 1 — Sunlit Meadow: learn the movement, growth and support rules.
            1 => ['title' => 'First Bite', 'lesson' => 'A stone divider blocks the direct path. Find a way over it, then land safely on the far side.', 'hint' => 'The divider reaches down to the floor. Look closely at the open space above its top.', 'ground' => [[0, 13]], 'platforms' => [], 'walls' => [[6, 5, 6]], 'apples' => [[9, 6]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => []],
            2 => ['title' => 'Long Reach', 'lesson' => 'A narrow upper passage is the only safe connection between the two ledges. Plan where to descend.', 'hint' => 'The far ledge has a low stone lip. Do not drop as soon as you reach the upper shelf.', 'ground' => [[0, 4], [9, 13]], 'platforms' => [[5, 8, 4]], 'walls' => [[6, 5, 6], [9, 5, 6], [10, 5, 6], [11, 5, 6]], 'apples' => [[3, 6], [8, 3]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => []],
            3 => ['title' => 'Raised Garden', 'lesson' => 'Two staggered shelves and a tall divider make this crossing a body-position puzzle. Collect both apples before descending.', 'hint' => 'The first shelf is not connected to the second at head height. Look for a route above the divider.', 'ground' => [[0, 2], [10, 13]], 'platforms' => [[3, 5, 5], [7, 9, 4]], 'walls' => [[6, 3, 6], [9, 2, 3]], 'apples' => [[4, 4], [8, 3]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => []],
            4 => ['title' => 'Low Passage', 'lesson' => 'Two stone fins and a thorn strip make the lower route a trap. Climb to the narrow upper lane, then choose the right moment to descend.', 'hint' => 'The spikes sit in the obvious lower route. The first fin is shorter; use the space above it to reach the protected lane.', 'ground' => [[0, 13]], 'platforms' => [], 'walls' => [[5, 5, 6], [8, 4, 6]], 'apples' => [[4, 6], [9, 6]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => [[6, 6], [7, 6]]],
            5 => ['title' => 'The Long Return', 'lesson' => 'Grow before the four-tile gap, push the crate into the shaft, collect the far apple, then turn around in the exit chamber and return above the low roof.', 'hint' => 'The portal is behind the starting ledge. The low roof is not a dead end: it creates the safe upper route home.', 'ground' => [[0, 6], [11, 13]], 'platforms' => [[4, 10, 5]], 'walls' => [], 'apples' => [[5, 6], [12, 6]], 'portal' => [0, 6], 'blocks' => [[6, 6]], 'spikes' => []],

            // World 2 — Fernwood Trail: longer routes, ledges and first hazards.
            6 => ['title' => 'Two Bites', 'lesson' => 'Collect both apples to grow enough for the two broken spans.', 'hint' => 'The first apple gives reach for the high shelf; the second prepares the next gap.', 'ground' => [[0, 3], [8, 10], [12, 13]], 'platforms' => [[5, 6, 5]], 'walls' => [], 'apples' => [[4, 6], [6, 4]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => []],
            7 => ['title' => 'Thorn Canopy', 'lesson' => 'Cross above the thorn bed using the raised canopy.', 'hint' => 'Do not drop into the thorn row. Use the shelf above it.', 'ground' => [[0, 13]], 'platforms' => [[4, 9, 5]], 'walls' => [], 'apples' => [[7, 4]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => [[6, 6], [7, 6], [8, 6]]],
            8 => ['title' => 'Stone Gate', 'lesson' => 'Climb over the divider, awaken its rune, then pass through the opened gate.', 'hint' => 'The wall blocks the direct lane. Find the upper route and look for the rune before the sealed passage.', 'ground' => [[0, 5], [9, 13]], 'platforms' => [], 'walls' => [[7, 5, 6]], 'apples' => [[5, 6], [10, 6]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => [], 'switches' => [[6, 5]], 'gates' => [[9, 4]]],
            9 => ['title' => 'Twin Ledges', 'lesson' => 'Use two staggered ledges to gather the high apples.', 'hint' => 'The second shelf is higher. Plan the turns before leaving the first.', 'ground' => [[0, 13]], 'platforms' => [[4, 6, 5], [8, 10, 4]], 'walls' => [], 'apples' => [[5, 4], [9, 3]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => []],
            10 => ['title' => 'Crate Stair', 'lesson' => 'Nudge the crate along the floor, then climb to the upper exit.', 'hint' => 'The crate can be moved more than once. Use the space it creates to reach the raised route.', 'ground' => [[0, 13]], 'platforms' => [[9, 12, 5]], 'walls' => [], 'apples' => [[6, 6], [10, 4]], 'portal' => [12, 4], 'blocks' => [[7, 6]], 'spikes' => []],

            // World 3 — Mossy Ruins: controlled drops, corners and switch logic.
            11 => ['title' => 'Broken Causeway', 'lesson' => 'The upper stone shelf links the broken causeway.', 'hint' => 'The platform is above the pit. Approach it from the left side.', 'ground' => [[0, 4], [8, 13]], 'platforms' => [[5, 7, 5]], 'walls' => [], 'apples' => [[6, 4]], 'portal' => [11, 6], 'blocks' => [], 'spikes' => []],
            12 => ['title' => 'Side Pocket', 'lesson' => 'Clear the crate, slip through the upper pocket, and trigger the rune to unlock the exit lane.', 'hint' => 'The crate is only the first obstacle. The upper pocket hides a rune that opens the final passage.', 'ground' => [[0, 13]], 'platforms' => [[7, 10, 5]], 'walls' => [[9, 4, 4]], 'apples' => [[5, 6], [10, 4]], 'portal' => [12, 6], 'blocks' => [[6, 6]], 'spikes' => [], 'switches' => [[6, 4]], 'gates' => [[11, 4]]],
            13 => ['title' => 'Stone Teeth', 'lesson' => 'The spike line covers the floor. Keep the worm on the upper shelf.', 'hint' => 'The safe route is above the thorn row; do not drop until you are past it.', 'ground' => [[0, 13]], 'platforms' => [[5, 8, 5]], 'walls' => [], 'apples' => [[6, 4], [8, 4]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => [[5, 6], [6, 6], [7, 6], [8, 6]]],
            14 => ['title' => 'Moonlit Gate', 'lesson' => 'Activate the rune switch to open the gate on the upper route.', 'hint' => 'The switch is on the raised shelf. The far passage stays blocked until you touch it.', 'ground' => [[0, 13]], 'platforms' => [[4, 9, 5], [11, 12, 5]], 'walls' => [[10, 0, 3], [10, 5, 6]], 'apples' => [[5, 4], [9, 4]], 'portal' => [12, 4], 'blocks' => [], 'spikes' => [], 'switches' => [[7, 4]], 'gates' => [[10, 4]]],
            15 => ['title' => 'Switchback', 'lesson' => 'Two turns and two heights make the exit route a switchback.', 'hint' => 'The wall divides the shelves. Use the higher route to get around it.', 'ground' => [[0, 13]], 'platforms' => [[4, 6, 5], [8, 11, 4]], 'walls' => [[7, 5, 6]], 'apples' => [[5, 4], [9, 3]], 'portal' => [12, 3], 'blocks' => [], 'spikes' => []],

            // World 4 — Crystal Hollow: tight passages and combined mechanics.
            16 => ['title' => 'Crystal Crossing', 'lesson' => 'A long broken floor requires the high crystal shelf and careful descent.', 'hint' => 'The shelf bridges the middle. Keep at least one segment supported before dropping.', 'ground' => [[0, 3], [10, 13]], 'platforms' => [[4, 8, 5]], 'walls' => [], 'apples' => [[6, 4], [9, 4]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => []],
            17 => ['title' => 'Shard Switchway', 'lesson' => 'Touch the crystal rune to open the narrow gate, then cross the raised shard passage.', 'hint' => 'The glowing switch sits just before the sealed crystal gate.', 'ground' => [[0, 13]], 'platforms' => [[3, 6, 5], [8, 10, 5]], 'walls' => [[7, 5, 6]], 'apples' => [[4, 4], [9, 4]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => [[5, 6], [9, 6]], 'switches' => [[6, 4]], 'gates' => [[7, 4]]],
            18 => ['title' => 'Falling Gallery', 'lesson' => 'Push the gallery crate off the ledge so it settles into the gap below.', 'hint' => 'The crate begins on the final supported tile. Nudge it into the open shaft, then follow the foothold it creates.', 'ground' => [[0, 5], [8, 13]], 'platforms' => [[6, 7, 5]], 'walls' => [], 'apples' => [[6, 4], [9, 6]], 'portal' => [12, 6], 'blocks' => [[5, 6]], 'spikes' => []],
            19 => ['title' => 'Prism Steps', 'lesson' => 'Climb the three prism shelves, collect their fruit, then activate the distant rune to open the return gate.', 'hint' => 'The staircase is only half the puzzle. Watch for a rune on the far side before heading back to the portal.', 'ground' => [[0, 13]], 'platforms' => [[4, 5, 5], [6, 7, 4], [8, 9, 3]], 'walls' => [], 'apples' => [[5, 4], [7, 3], [9, 2]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => [], 'switches' => [[5, 3]], 'gates' => [[10, 2]]],
            20 => ['title' => 'Crystal Lock', 'lesson' => 'Push the first crystal crate into the gap, then use the high shelf to avoid the thorn landing.', 'hint' => 'The crate is supported at the left edge. Push it into the shaft before collecting the lower crystal fruit; the exit is above the thorn.', 'ground' => [[0, 3], [8, 13]], 'platforms' => [[9, 11, 5]], 'walls' => [], 'apples' => [[5, 6], [6, 6], [7, 6], [7, 5], [10, 4]], 'portal' => [12, 4], 'blocks' => [[3, 6]], 'spikes' => [[8, 6]]],

            // World 5 — Sky Temple: multi-gap routes and a final combined test.
            21 => ['title' => 'Cloudstep Gauntlet', 'lesson' => 'Climb three floating tiers, collect every cloud fruit, and cross the spike-filled chasm without losing support.', 'hint' => 'The route climbs twice before descending to the far portal.', 'ground' => [[0, 5], [10, 13]], 'platforms' => [[3, 5, 5], [7, 9, 4], [10, 11, 5]], 'walls' => [], 'apples' => [[4, 4], [8, 3], [11, 4]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => [[6, 6], [7, 6], [8, 6], [9, 6]]],
            22 => ['title' => 'Skyward Crates', 'lesson' => 'A crate must be pushed into the shaft before the two cloud apples can carry the worm across.', 'hint' => 'Push the crate from the left ledge into the gap. The settled crate keeps the tail supported long enough to reach the far bank.', 'ground' => [[0, 3], [9, 13]], 'platforms' => [], 'walls' => [], 'apples' => [[6, 6], [7, 6]], 'portal' => [12, 6], 'blocks' => [[3, 6]], 'spikes' => []],
            23 => ['title' => 'Cloud Garden', 'lesson' => 'Cross the thorn bed on the cloud shelf, collect both fruits, and find the rune that opens the final descent.', 'hint' => 'Stay above the spikes, but do not rush to the portal. A rune near the far edge opens the return gate.', 'ground' => [[0, 13]], 'platforms' => [[4, 9, 5]], 'walls' => [], 'apples' => [[6, 4], [8, 4]], 'portal' => [12, 6], 'blocks' => [], 'spikes' => [[5, 6], [6, 6], [7, 6], [8, 6], [9, 6]], 'switches' => [[3, 4]], 'gates' => [[10, 4]]],
            24 => ['title' => 'Temple Machinery', 'lesson' => 'Open the temple gate after pushing the crate bridge into place and collecting the upper fruit.', 'hint' => 'Push the crate from the ledge, gather the lower apples, touch the rune above the thorn, then pass through the opened gate.', 'ground' => [[0, 3], [8, 13]], 'platforms' => [[9, 11, 5]], 'walls' => [], 'apples' => [[5, 6], [6, 6], [7, 6], [7, 5], [9, 4]], 'portal' => [12, 4], 'blocks' => [[3, 6]], 'spikes' => [[8, 6]], 'switches' => [[8, 4]], 'gates' => [[10, 4]]],
            25 => ['title' => 'Last Nibble', 'lesson' => 'The final puzzle combines a crate bridge, two growth boosts and a thorn-guarded climb.', 'hint' => 'Push the crate off the starting ledge first. Collect both low apples while the crate supports the tail, then climb around the thorn to the portal.', 'ground' => [[0, 3], [8, 13]], 'platforms' => [[9, 11, 5]], 'walls' => [], 'apples' => [[5, 6], [6, 6], [7, 6], [7, 5], [10, 4]], 'portal' => [12, 4], 'blocks' => [[3, 6]], 'spikes' => [[8, 6], [9, 6]]],
        ];

        // A few later puzzles intentionally enter from the right. Mirroring
        // the complete authored geometry changes the player's first read of
        // the board and prevents every level from becoming a left-to-right run.
        $reverseEntryLevels = [8, 12, 14, 23, 25];
        foreach ($reverseEntryLevels as $number) {
            $level = &$levels[$number];
            $level['ground'] = array_map(
                fn (array $range) => [13 - $range[1], 13 - $range[0]],
                $level['ground']
            );
            $level['platforms'] = array_map(
                fn (array $platform) => [13 - $platform[1], 13 - $platform[0], $platform[2]],
                $level['platforms']
            );
            $level['walls'] = array_map(
                fn (array $wall) => [13 - $wall[0], $wall[1], $wall[2]],
                $level['walls']
            );
            foreach (['apples', 'blocks', 'spikes', 'switches', 'gates'] as $field) {
                $level[$field] = array_map(
                    fn (array $point) => [13 - $point[0], $point[1]],
                    $level[$field] ?? []
                );
            }
            $level['portal'][0] = 13 - $level['portal'][0];
            unset($level);
        }

        // World 2–5 difficulty expansion: each later level adds a required
        // detour collectible, and most also add a thorn tile that blocks an
        // obvious shortcut. The added objectives are deliberately solvable
        // without any rewarded action.
        $challengeUpgrades = [
            6 => ['apple' => [12, 1], 'spike' => [11, 6], 'title' => 'High Orchard', 'lesson' => 'Two apples are not enough: take the high detour for the cloud fruit, then descend without touching the thorn on the direct return lane.', 'hint' => 'The last fruit is high above the far portal. Do not drop to the floor until you have collected it.'],
            7 => ['apple' => [2, 2], 'spike' => [11, 6], 'title' => 'Thorn Echo', 'lesson' => 'The safest-looking floor route hides a thorn. Double back for the fruit above the starting ledge before crossing the canopy.', 'hint' => 'The extra fruit is near the starting side, high above the ground. Plan the return before taking the canopy.'],
            8 => ['apple' => [3, 2], 'spike' => [4, 6], 'title' => 'Rune Detour', 'lesson' => 'The rune opens the gate, but a second high fruit sits on the opposite side of the obvious route. Collect it before leaving the upper passage.', 'hint' => 'The gate is not the only objective. Look back toward the high left pocket for the missing fruit.'],
            9 => ['apple' => [2, 2], 'spike' => [9, 6], 'title' => 'Twin Ledge Switchback', 'lesson' => 'The first high fruit is a decoy for the route: return toward the left shelf for the hidden fruit, then climb the higher ledge and avoid the thorn below.', 'hint' => 'You need to revisit the left side after reaching the first shelf. The high fruit is not on the shortest line to the portal.'],
            10 => ['apple' => [9, 6], 'title' => 'Crate Staircase', 'lesson' => 'Collect the low fruit on both sides of the crate, then climb to the upper exit. The crate must be repositioned rather than pushed blindly.', 'hint' => 'The extra fruit is beside the crate route. Think about which side of the crate you need to stand on before pushing.'],
            11 => ['apple' => [3, 2], 'spike' => [9, 6], 'title' => 'Causeway Return', 'lesson' => 'Cross the broken causeway, then return to the high left pocket for one more fruit before descending beyond the thorn.', 'hint' => 'The far apple is not the final objective. There is a second high pocket near the starting bank.'],
            12 => ['apple' => [6, 6], 'title' => 'Side Pocket Lock', 'lesson' => 'The crate, upper rune and gate are only half the puzzle. Revisit the lower pocket for its fruit before taking the opened exit.', 'hint' => 'Do not push the crate and leave immediately. There is a required fruit tucked beside its starting lane.'],
            13 => ['apple' => [3, 2], 'spike' => [11, 6], 'title' => 'Stone Teeth Return', 'lesson' => 'The thorn line forces an upper crossing, but the route begins with a detour back into the high left alcove.', 'hint' => 'The extra fruit is high and left of the first shelf. Collect it before committing to the long crossing.'],
            14 => ['apple' => [11, 2], 'spike' => [9, 6], 'title' => 'Moonlit Reversal', 'lesson' => 'From the right-hand start, collect the high far fruit, activate the rune, then reverse through the left-side gate while avoiding the lower thorn.', 'hint' => 'The high fruit is near the starting side. The rune and gate are farther left, so plan a route that changes direction.'],
            15 => ['apple' => [8, 5], 'spike' => [11, 6], 'title' => 'Ruins Switchback', 'lesson' => 'This long switchback has a mid-height fruit and a separate golden reward. Do not descend to the lower lane until the upper return is clear.', 'hint' => 'The new fruit rests on the middle shelf. A direct climb to the golden apple will leave an objective behind.'],
            16 => ['apple' => [2, 2], 'spike' => [10, 6], 'title' => 'Crystal Backtrack', 'lesson' => 'Cross the crystal shelf, then return to the high entry pocket for the final fruit before descending past the thorn.', 'hint' => 'The extra crystal fruit is behind the starting side. You will need to change direction after reaching the shelf.'],
            17 => ['apple' => [1, 2], 'spike' => [11, 6], 'title' => 'Shard Reversal', 'lesson' => 'Activate the rune, pass the crystal gate, then revisit the high left pocket for the last shard while keeping clear of the thorn lane.', 'hint' => 'The switch is not the last high objective. Check the upper-left corner before taking the final descent.'],
            18 => ['apple' => [6, 6], 'title' => 'Falling Gallery: Second Load', 'lesson' => 'The crate creates a foothold, but the lower fruit beside the shaft must be collected before the far bank can be reached.', 'hint' => 'The new fruit sits beside the crate shaft. Make sure the crate has settled before turning into that lower pocket.'],
            19 => ['apple' => [5, 6], 'spike' => [10, 6], 'title' => 'Prism Return', 'lesson' => 'Three shelves, three upper apples and a lower return fruit create a long route. Activate the rune before the thorn-guarded descent.', 'hint' => 'The added fruit is below the first prism step. Do not climb the whole staircase before checking the lower pocket.'],
            20 => ['apple' => [6, 3], 'spike' => [11, 6], 'title' => 'Crystal Lockdown', 'lesson' => 'Use the crate and five original fruits to reach the upper crystal, then return to the high middle alcove for the final fruit.', 'hint' => 'The last fruit is above the lower apple line. The crate is a tool for support, not just an obstacle to clear.'],
            21 => ['apple' => [1, 2], 'spike' => [3, 6], 'title' => 'Cloudstep Reversal', 'lesson' => 'The gauntlet now begins with a high detour behind the entry, then climbs three cloud tiers over the spike chasm.', 'hint' => 'Before climbing the cloud steps, look into the upper-left pocket for the extra fruit.'],
            22 => ['apple' => [6, 3], 'spike' => [10, 6], 'title' => 'Skyward Crate Return', 'lesson' => 'The crate bridge is not a straight crossing. Collect the high cloud fruit, then return to the lower shaft for both apples before the far bank.', 'hint' => 'There is a fruit above the crate route. Plan the crate push so it does not block your return to the lower apples.'],
            23 => ['apple' => [11, 2], 'spike' => [3, 6], 'title' => 'Cloud Garden Reversal', 'lesson' => 'From the right edge, collect the high cloud fruit, activate the rune, and cross the thorn shelf back toward the gate.', 'hint' => 'The extra fruit is high near your starting side. The gate and portal are to the left, so avoid descending too early.'],
            24 => ['apple' => [5, 3], 'spike' => [11, 6], 'title' => 'Temple Machinery: Upper Cache', 'lesson' => 'The crate bridge, lower fruit, rune gate and an upper cache must all be handled in the correct sequence.', 'hint' => 'The new fruit is above the first lower detour. Do not activate the rune and rush to the gate before collecting it.'],
            25 => ['apple' => [5, 1], 'spike' => [3, 6], 'title' => 'Last Nibble: Final Reversal', 'lesson' => 'The final trial adds an upper fruit behind the entry to the crate bridge, then forces a reversal through the thorn-guarded chamber.', 'hint' => 'The upper fruit is behind the first crate route. Collect it before committing to the final lower crossing.'],
        ];

        foreach ($challengeUpgrades as $number => $upgrade) {
            $levels[$number]['title'] = $upgrade['title'];
            $levels[$number]['lesson'] = $upgrade['lesson'];
            $levels[$number]['hint'] = $upgrade['hint'];
            $levels[$number]['apples'][] = $upgrade['apple'];
            if (isset($upgrade['spike'])) {
                $levels[$number]['spikes'][] = $upgrade['spike'];
            }
        }

        $worlds = [
            1 => ['code' => 'meadow', 'name' => 'Sunlit Meadow', 'theme' => 'meadow'],
            2 => ['code' => 'fernwood', 'name' => 'Fernwood Trail', 'theme' => 'forest'],
            3 => ['code' => 'ruins', 'name' => 'Mossy Ruins', 'theme' => 'ruins'],
            4 => ['code' => 'crystal', 'name' => 'Crystal Hollow', 'theme' => 'crystal'],
            5 => ['code' => 'cloud', 'name' => 'Sky Temple', 'theme' => 'sky'],
        ];

        // Par targets are intentionally close to the shortest verified route,
        // so three stars reward efficient play rather than a simple completion.
        $parMoves = [
            1 => 12, 2 => 15, 3 => 19, 4 => 16, 5 => 25,
            6 => 27, 7 => 27, 8 => 20, 9 => 25, 10 => 26,
            11 => 21, 12 => 31, 13 => 23, 14 => 21, 15 => 39,
            16 => 25, 17 => 23, 18 => 24, 19 => 28, 20 => 21,
            21 => 25, 22 => 26, 23 => 25, 24 => 22, 25 => 20,
        ];

        // Completion should remain challenging, but players need room to experiment.
        // Three-star par is unchanged; the wider completion buffer rewards learning.
        $moveLimitSlack = [1 => 6, 2 => 5, 3 => 4, 4 => 4, 5 => 4];
        $moveLimits = [];
        foreach ($parMoves as $number => $par) {
            $worldNumber = intdiv($number - 1, 5) + 1;
            $moveLimits[$number] = $par + $moveLimitSlack[$worldNumber];
        }

        // Preserve the original project's milestone Golden Apple bonus at
        // levels 05, 10, 15, 20 and 25. These positions sit on each puzzle's
        // verified route but are separate from the required red apples.
        $goldenAppleCells = [
            5 => [[9, 4]],
            10 => [[8, 5]],
            15 => [[6, 4]],
            20 => [[8, 4]],
            25 => [[4, 4]],
        ];

        // Progressive, authored hints: each click reveals a little more,
        // without exposing the whole solution at once. These are guidance,
        // not client-trusted moves; all movement remains server-authoritative.
        $hintStages = [
            1 => [
                'The divider reaches the ground, so there is no direct path through it.',
                'The stone stops below the open top row. Keep the worm supported while you climb.',
                'Move to the divider, climb above its top, cross over it, then let the worm settle on the far floor.',
            ],
            2 => [
                'The upper shelf connects the two ledges, but its far end is not the exit.',
                'Collect the near apple first. The tall divider can be crossed only from above.',
                'Stay on the upper line until you pass the low stone lip at the far end, then descend at the portal side.',
            ],
            3 => [
                'The first and second shelves are at different heights, with a tall divider between them.',
                'The first apple is on the near shelf. The second shelf can only be reached by crossing above the divider.',
                'Use the left shelf to climb over the divider, land on the higher right shelf, collect the second apple, then find the far descent.',
            ],
            4 => [
                'The spikes cover the obvious lower crossing. Look for a route above the stone fins.',
                'The first apple is before the thorn strip. Collect it, then stay above the spikes until the taller fin is behind you.',
                'Climb beside the first fin, cross the upper lane over both hazards, then descend on the far side for the last apple and portal.',
            ],
            5 => [
                'The portal is on the starting side, but the second apple is beyond a wide floor gap.',
                'Collect the near apple before pushing the crate. The crate must settle into the shaft so the longer worm can keep its footing across the gap.',
                'After the far apple, turn in the open right chamber, travel left above the low roof, collect the Golden Apple, then descend at the far-left opening to the portal.',
            ],
            6 => [
                'This route needs both apples; one growth boost is not enough for the whole course.',
                'The first apple helps you reach the raised shelf. Look for the second apple before leaving it.',
                'Collect the apples in sequence and keep at least one body segment supported at each gap.',
            ],
            7 => [
                'The thorn bed makes the lower floor unsafe.',
                'A safe shelf runs above the spikes. Reach it before moving across the thorn section.',
                'Stay on the raised canopy until you have passed the full spike row, then descend toward the portal.',
            ],
            8 => [
                'The stone divider blocks the direct lane, so study the open cells above it.',
                'A rune on the far side controls the sealed passage. Touch it before approaching the gate.',
                'Climb over the divider, collect both apples, activate the rune at the upper route, then pass the opened gate to the portal.',
            ],
            9 => [
                'The two shelves are at different heights; treat them as steps.',
                'Collect the first apple from the lower shelf before moving to the higher one.',
                'Use the lower ledge to line up with the higher shelf, collect both apples, then return to the portal route.',
            ],
            10 => [
                'The crate can be pushed more than once, and the upper exit is not reachable from the floor alone.',
                'Move the crate along the lower route to create a useful step near the raised platform.',
                'Collect the lower apple, reposition the crate as needed, then climb to the upper apple and portal.',
            ],
            11 => [
                'The broken causeway is linked by the shelf above the pit.',
                'Approach the upper shelf from the left ledge and collect the apple there.',
                'Use the shelf to keep the worm supported across the broken floor, then descend on the far side.',
            ],
            12 => [
                'The crate blocks the first approach; the upper pocket is the safer way around it.',
                'After moving past the crate, look for the rune on the upper route. It controls the far gate.',
                'Collect both apples, touch the rune to open the gate, and then take the newly unlocked passage to the portal.',
            ],
            13 => [
                'The spike line covers the lower floor, so crossing at ground level is dangerous.',
                'The safe route follows the raised shelf above the thorn row.',
                'Collect both apples from the upper route and do not drop until the worm is completely beyond the spikes.',
            ],
            14 => [
                'The gate is controlled by a rune switch on the raised route.',
                'Collect the first apple while moving across the shelf, then touch the rune before approaching the gate.',
                'The rune opens the far passage. Collect the remaining apple on that side, then enter the portal.',
            ],
            15 => [
                'The wall splits the two shelves, so the route needs a deliberate change of height.',
                'Use the first shelf to line up with the higher route beyond the divider.',
                'Collect the first apple, pass around the wall using the higher shelf, then collect the second apple before the portal.',
            ],
            16 => [
                'The long floor break is bridged by the high crystal shelf.',
                'Move up to the shelf before crossing the middle section.',
                'Collect both crystal apples along the shelf and keep the worm supported until the far bank is reached.',
            ],
            17 => [
                'The crystal gate blocks the only safe passage through the shard wall.',
                'The rune switch is on the raised path just before the gate.',
                'Move across the first shelf, activate the switch at x6, then pass through the opened gate and collect the far apple.',
            ],
            18 => [
                'The gallery crate starts at the edge of the broken floor.',
                'Push it toward the shaft so gravity can settle it into the gap below.',
                'After the crate lands, use it as a foothold, collect the far apple and continue to the portal.',
            ],
            19 => [
                'The three shelves form a staircase. Plan your turns before the worm grows longer.',
                'The rune is not at the top of the stairs; look for it as you follow the upper return route.',
                'Collect all three apples, activate the rune near the far end to open the gate, then descend to the portal.',
            ],
            20 => [
                'The crate must be placed before the thorn landing becomes relevant.',
                'Push the crate from the left ledge toward the shaft, then use the upper shelf to avoid the spike.',
                'Collect the lower crystal fruit, keep clear of the thorn tile, then collect the upper apple and reach the portal.',
            ],
            21 => [
                'The route is a staircase: lower cloud shelf, higher middle shelf, then a final descent.',
                'Collect the first fruit before climbing to the higher shelf; the spikes punish a drop into the chasm.',
                'Cross above the spike row, collect the middle and far fruits, then descend at the far-right edge to the portal.',
            ],
            22 => [
                'The crate is intended to settle into the shaft between the ledges.',
                'Push it from the left ledge into the gap before collecting the two cloud apples.',
                'Once it has settled, collect both apples and use the crate-supported route to reach the far bank.',
            ],
            23 => [
                'The thorn bed is too long to cross safely at floor level; use the cloud shelf.',
                'The far edge hides a rune that controls a gate on the return route.',
                'Collect both apples, activate the rune beyond the thorn bed, then use the opened gate to complete the descent.',
            ],
            24 => [
                'This temple puzzle combines a crate bridge with a rune-controlled gate.',
                'Push the crate into the gap first, then collect the lower apples and approach the upper rune.',
                'Touch the rune to open the gate, collect the upper fruit beyond it, and then enter the portal.',
            ],
            25 => [
                'The final chamber combines a narrow route, a crate and a hazard; avoid committing the tail too early.',
                'Use the crate to create support before taking the route toward the golden apple.',
                'Collect the golden apple on the safe route, use the opened space to get around the hazard, and finish at the portal.',
            ],
        ];

        $hintStages = array_replace($hintStages, [
            6 => ['The first crossing is only the start; one fruit is high above the far portal.', 'Take the upper route, then change direction to reach the high cloud fruit before descending.', 'Collect the far high fruit, avoid the thorn on the floor lane, and return to the portal.'],
            7 => ['There is a required fruit above the starting side, not just the apple over the canopy.', 'Collect the high-left fruit before crossing the spike bed.', 'Use the raised canopy to cross the thorns, then descend only after the last spike.'],
            8 => ['The rune gate is not the only objective; a high pocket contains another fruit.', 'With the right-hand start, look toward the upper-left area before committing to the final crossing.', 'Collect both lower apples and the upper detour fruit, touch the rune, then pass the opened gate.'],
            9 => ['The extra fruit is near the upper-left side, away from the most obvious ledge route.', 'You must revisit the first shelf after reaching the higher shelf.', 'Collect the high-left fruit, then use both staggered shelves and stay clear of the thorn below.'],
            10 => ['The crate divides the lower route into two decisions.', 'Collect the lower fruits from the correct side of the crate before climbing.', 'Reposition the crate, collect the far upper fruit, then reach the elevated portal.'],
            11 => ['The far bank is not the end of the causeway; a high pocket remains behind you.', 'Use the middle shelf to cross, then turn back toward the upper-left fruit.', 'Collect both apples, avoid the thorn on the far floor, and descend to the portal.'],
            12 => ['The crate, rune and gate are not the only objectives; check the lower pocket beside the crate.', 'Move the crate safely, collect the low fruit, then climb to the rune shelf.', 'Activate the rune, collect the upper fruit, and pass through the opened gate.'],
            13 => ['The thorns cover the lower route, but the first extra fruit is high near the left bank.', 'Collect the upper-left fruit before crossing the long shelf.', 'Stay above the thorn row, collect the remaining fruit, and descend beyond the hazard.'],
            14 => ['The high fruit is near the right-hand entry; the rune and gate are farther left.', 'Collect the upper fruit first, then reverse direction to reach the rune.', 'Activate the switch, avoid the lower thorn lane, collect the left fruit, and enter the gate-side portal.'],
            15 => ['The middle shelf contains a required fruit in addition to the golden reward.', 'Do not climb directly toward the upper apple; collect the middle fruit and plan the switchback.', 'Gather all fruit, use the high route around the divider, then descend only after the thorn is behind you.'],
            16 => ['The extra crystal fruit is behind the starting side, above the lower approach.', 'Cross the high shelf, then reverse into the upper-left pocket.', 'Collect both upper fruits, avoid the thorn on the far floor, and descend to the portal.'],
            17 => ['The rune opens the shard gate, but a high-left fruit remains.', 'Activate the rune and cross the gate before reversing toward the upper-left pocket.', 'Collect every fruit, avoid the far thorn, and return to the portal.'],
            18 => ['The crate must settle into the shaft, but a lower fruit is beside that shaft.', 'Push the crate only when the space below is clear, then turn into the lower pocket.', 'Collect both fruits, use the crate foothold, and continue to the far portal.'],
            19 => ['The new fruit is below the first prism step; the upper staircase is not the first move.', 'Collect the lower fruit, then climb the three shelves and touch the rune.', 'Gather all four apples, activate the rune, avoid the far thorn and descend to the portal.'],
            20 => ['The crate and lower fruit line are only the opening; a fruit is hidden above the middle.', 'Use the crate to establish support, collect the lower row, then reverse toward the high fruit.', 'Avoid the thorn, collect all six fruits, and climb to the upper portal.'],
            21 => ['Before climbing the cloud steps, check the high-left entry pocket.', 'Collect the upper-left fruit, then climb the lower and middle cloud shelves.', 'Cross above the spike chasm, gather the far fruits, and descend at the portal side.'],
            22 => ['The crate bridge is not a straight push: a high fruit sits above the shaft.', 'Collect the upper fruit before committing to the lower crate route.', 'Use the crate as support, collect both lower apples, avoid the far thorn and reach the portal.'],
            23 => ['The high fruit is near the right-hand start; the rune and gate are on the left.', 'Collect the high fruit, activate the rune, then reverse over the cloud shelf.', 'Stay above the thorn bed, collect the remaining fruit and pass through the opened gate.'],
            24 => ['The new upper fruit is separate from the lower crate-and-apple sequence.', 'Collect the upper cache before activating the rune gate.', 'Push the crate into position, gather every apple, touch the rune, avoid the thorn and enter the portal.'],
            25 => ['The final trial begins with a high fruit behind the crate route.', 'Collect the upper fruit before committing to the lower bridge and thorn chamber.', 'Use the crate for support, gather every fruit, reverse around the thorn, and reach the final portal.'],
        ]);

        $customStarts = [
            5 => ['x' => 3, 'y' => 6, 'facing' => 'right', 'length' => 3],
        ];

        foreach ($levels as $number => &$level) {
            $worldNumber = intdiv($number - 1, 5) + 1;
            $level['world'] = $worlds[$worldNumber];
            $level['difficulty'] = match ($worldNumber) {
                1 => 'tutorial',
                2 => 'easy',
                3 => 'medium',
                4 => 'hard',
                default => 'advanced',
            };
            $level['width'] = 14;
            $level['height'] = 8;
            $level['start'] = $customStarts[$number] ?? (in_array($number, $reverseEntryLevels, true)
                ? ['x' => 11, 'y' => 6, 'facing' => 'left', 'length' => 3]
                : ['x' => 2, 'y' => 6, 'facing' => 'right', 'length' => 3]);
            $level['terrain'] = self::terrain($level);
            $level['apples'] = self::points($level['apples'] ?? []);
            $level['goldenApples'] = self::points($goldenAppleCells[$number] ?? []);
            $level['portal'] = ['x' => $level['portal'][0], 'y' => $level['portal'][1]];
            $level['blocks'] = self::points($level['blocks'] ?? []);
            $level['spikes'] = self::points($level['spikes'] ?? []);
            $level['switches'] = self::points($level['switches'] ?? []);
            $level['gates'] = self::points($level['gates'] ?? []);
            $level['parMoves'] = $parMoves[$number];
            $level['moveLimit'] = $moveLimits[$number];
            $level['hintStages'] = $hintStages[$number] ?? [$level['hint']];
        }
        unset($level);

        return $levels;
    }

    private static function points(array $points): array
    {
        return array_map(fn (array $point) => ['x' => $point[0], 'y' => $point[1]], $points);
    }

    private static function terrain(array $level): array
    {
        $cells = [];
        foreach ($level['ground'] as [$from, $to]) {
            for ($x = $from; $x <= $to; $x++) {
                $cells[$x . ':7'] = ['x' => $x, 'y' => 7];
            }
        }
        foreach ($level['platforms'] as [$from, $to, $y]) {
            for ($x = $from; $x <= $to; $x++) {
                $cells[$x . ':' . $y] = ['x' => $x, 'y' => $y];
            }
        }
        foreach ($level['walls'] as [$x, $fromY, $toY]) {
            for ($y = $fromY; $y <= $toY; $y++) {
                $cells[$x . ':' . $y] = ['x' => $x, 'y' => $y];
            }
        }
        return array_values($cells);
    }
}
