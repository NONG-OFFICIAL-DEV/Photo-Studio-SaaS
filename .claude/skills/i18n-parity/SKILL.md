---
name: i18n-parity
description: Check or fix key parity and Khmer-digit issues between frontend/src/locales/en.json and km.json. Use whenever adding, removing, or renaming an i18n key in either file.
---

# i18n parity check

`en.json` and `km.json` must have **exact flattened-key parity** — every dotted key path in one must exist in the other. `km.json` must use plain Arabic numerals (`0-9`), never Khmer-script digits (`០-៩`).

## When to use this

Any time you add, remove, or rename a key under `frontend/src/locales/en.json` or `km.json` — a new form field label, a new `apiErrors.<CODE>` entry, a new `notifications.events.<event>` message, anything.

## How

1. Add the key to **both** files at the same path, with a real English string in `en.json` and a real Khmer translation (plain `0-9` digits) in `km.json`. Don't leave a placeholder in one and skip the other, even temporarily.
2. Run the check from `frontend/`:
   ```bash
   npm run check:i18n
   ```
   This flattens both files, diffs the key sets, and scans `km.json` for Khmer-script digits (`[០-៩]`). It exits non-zero and lists every offending key if anything's wrong.
3. Fix whatever it reports and re-run until it prints `OK`.

The script lives at `frontend/scripts/check-i18n.js` — it's plain Node (no deps), safe to read if you want to see exactly what it's checking.

Don't consider an i18n-touching change done until this passes.
