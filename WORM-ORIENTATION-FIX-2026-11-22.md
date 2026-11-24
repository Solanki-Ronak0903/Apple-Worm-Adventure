# Worm Orientation Fix — 22 November 2026

## What was wrong

The shared side-view renderer rotated the complete head by 180 degrees for left-facing starts. Since the eyes and highlight were part of that rotated drawing, they moved to the underside and made the worm look upside down. Body segment ellipses also used directional angles directly, so a left-facing horizontal body could flip its top highlight and shadow.

## What changed

- Left-facing heads are now mirrored horizontally instead of rotated by 180 degrees. The face points left while the eyes, back and highlights remain upright.
- Body-segment angles are normalized modulo 180 degrees because an oval's long axis is undirected. This prevents left-facing horizontal segments from inverting their lighting.
- On page load/resume, the renderer derives the current facing direction from the head and neck coordinates. It no longer blindly assumes the original spawn direction after a player has turned and refreshed the page.
- Right, up and down facing transforms remain directional; only the left-facing transform changes from a 180-degree rotation to a horizontal mirror.

## Scope

The correction is in the shared `public/js/puzzle-game.js` renderer, so it applies to the entire 25-level side-view campaign rather than patching individual levels. It particularly corrects the reverse-entry starts used by Levels 8, 12, 14, 23 and 25.

## Verification

- JavaScript syntax check: `node --check public/js/puzzle-game.js`
- PHP syntax checks: run `php -l` over the PHP source files.
- Browser visual check recommended: open a left-facing level (for example Level 25) and verify that the eyes and bright back/highlight remain on the top side of the worm while its face points left. Also turn the worm, refresh the page, and confirm the saved facing direction is retained.

The full browser rendering and Laravel test suite require the project's local dependencies and browser environment.
