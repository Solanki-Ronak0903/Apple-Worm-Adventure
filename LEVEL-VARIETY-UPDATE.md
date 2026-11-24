# Level design — 25 original side-view puzzles

The release uses individually authored 14×8 side-view tile maps. Each level has a clear teaching objective, a different arrangement of terrain and objectives, and a verified shortest-known route against the same PHP movement service used by the Laravel endpoint. These maps take inspiration from the broad mechanics of classic worm puzzles without copying their level layouts or artwork. Several later boards enter from the right with a left-facing worm, so the full set is not a repeated left-to-right corridor.

## Sunlit Meadow

| Level | Name | Main lesson |
|---:|---|---|
| 01 | First Bite | Collect the apple, then reach the shadow gate. |
| 02 | Long Reach | Grow before crossing the three-tile break in the ground. |
| 03 | Raised Garden | Use the high shelf to collect the apple and reach the raised exit. |
| 04 | Low Passage | A low stone canopy leaves only a narrow route beneath it. |
| 05 | Bridge Crate | Push the crate into the gap, then use the new foothold to cross. |

## Fernwood Trail

| Level | Name | Main lesson |
|---:|---|---|
| 06 | Two Bites | Collect both apples to grow enough for the two broken spans. |
| 07 | Thorn Canopy | Cross above the thorn bed using the raised canopy. |
| 08 | Stone Gate | Climb over the stone divider to reach the far ledge. |
| 09 | Twin Ledges | Use two staggered ledges to gather the high apples. |
| 10 | Crate Stair | Nudge the crate along the floor, then climb to the upper exit. |

## Mossy Ruins

| Level | Name | Main lesson |
|---:|---|---|
| 11 | Broken Causeway | The upper stone shelf links the broken causeway. |
| 12 | Side Pocket | Push the crate aside and route through the narrow upper pocket. |
| 13 | Stone Teeth | The spike line covers the floor. Keep the worm on the upper shelf. |
| 14 | Moonlit Gate | Activate the rune switch to open the gate on the upper route. |
| 15 | Switchback | Two turns and two heights make the exit route a switchback. |

## Crystal Hollow

| Level | Name | Main lesson |
|---:|---|---|
| 16 | Crystal Crossing | A long broken floor requires the high crystal shelf and careful descent. |
| 17 | Shard Passage | Two shard clusters guard the lower floor. Thread the upper passage. |
| 18 | Falling Gallery | Push the gallery crate off the ledge so it settles into the gap below. |
| 19 | Prism Steps | Three rising shelves demand careful turns and room for the growing body. |
| 20 | Crystal Lock | Push the first crystal crate into the gap, then use the high shelf to avoid the thorn landing. |

## Sky Temple

| Level | Name | Main lesson |
|---:|---|---|
| 21 | Cloudstep | Two gaps need two growth boosts. Keep the worm connected across both. |
| 22 | Skyward Crates | A crate must be pushed into the shaft before the two cloud apples can carry the worm across. |
| 23 | Cloud Garden | A long thorn bed forces a precise route along the upper cloud shelf. |
| 24 | Temple Machinery | Push the crate bridge into place, touch the rune to open the upper passage, then reach the exit beyond the thorn. |
| 25 | Last Nibble | The final puzzle combines a crate bridge, two growth boosts and a thorn-guarded climb. |

## Level quality checks

- Levels 08, 12, 14, 17, 21, 23 and 25 begin on the right with the worm facing left, changing the entry side and intended route.

- All 25 board signatures are distinct, including terrain, fruit, portal, crates, spikes, switches, gates and starting orientation.
- Initial start, fruit, crates, spikes, switches, gates and portal coordinates are checked for invalid overlaps.
- A known winning sequence for each level is replayed against the production PHP puzzle service. The sequence must collect every apple, activate required switches and reach the portal without a blocked or failed move.
- Move targets are set from the shortest-known verified route for each map.
- A valid solution demonstrates that a map can be completed; it does not prove that the puzzle is fun, fair or well-balanced. Human play-testing is still required.

## Future level authoring requirements

Every new level should have one primary teaching objective, geometry that makes the objective matter, an intended solution and a server-validated replay test. Avoid repeating a straight corridor with randomly placed hazards. Add a new mechanic only after implementing it in server rules, Canvas rendering, hints, completion validation and feature tests.
