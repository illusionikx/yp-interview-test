#!/usr/bin/env bash
#
# UI-03 acceptance gate.
#
# Proves the Flowbite 4 token vocabulary ported into tailwind.config.js (v3.0 Decision #5)
# actually emits real CSS rules under this project's Tailwind 3 build. UI-03's failure mode
# is a SILENTLY-UNSTYLED PAGE, not a build error — Tailwind's JIT emits nothing for a utility
# it does not recognize, and it does not complain. A green PHPUnit suite proves nothing here
# (AuthenticationTest asserts the class *names* are in the HTML, which stays true whether or
# not they resolve to any CSS), and neither does a browser tab, which may hold a cached
# stylesheet. This script is the only reproducible check against the compiled CSS bundle.
#
# Re-runnable: builds assets fresh, then greps the compiled bundle for every token below.
# Extend the TOKENS array to add checks in a later phase (e.g. Phase 14's dark-mode sweep).

set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

echo "Running npm run build..."
BUILD_OUTPUT=$(npm run build 2>&1)
BUILD_EXIT=$?
if [ "$BUILD_EXIT" -ne 0 ]; then
    echo "FAIL: npm run build did not exit 0 (exit code $BUILD_EXIT)"
    echo "$BUILD_OUTPUT"
    exit 1
fi
echo "Build OK."
echo

# label|pattern — pattern is matched as a literal substring (grep -F) against the compiled bundle.
TOKENS=(
    "bg-brand rule|.bg-brand{"
    "bg-neutral-primary-soft rule|.bg-neutral-primary-soft{"
    "bg-neutral-secondary-medium rule|.bg-neutral-secondary-medium{"
    "text-heading rule|.text-heading{"
    "text-body rule|.text-body{"
    "text-fg-brand rule|.text-fg-brand{"
    "border-default rule|.border-default{"
    "border-default-medium rule|.border-default-medium{"
    "rounded-base rule|.rounded-base{"
    "rounded-xs rule|.rounded-xs{"
    "shadow-xs rule|.shadow-xs{"
    "bg-brand-strong (hover: variant)|bg-brand-strong"
    "ring-brand (focus: variant)|ring-brand"
    "ring-brand-medium (focus: variant)|ring-brand-medium"
    "ring-brand-soft (focus: variant)|ring-brand-soft"
    "border-brand (focus: variant)|border-brand"
    "--color-brand light value (:root)|--color-brand: 37 99 235"
    "--color-brand dark value (.dark)|--color-brand: 59 130 246"
)

FAILED=0
for entry in "${TOKENS[@]}"; do
    label="${entry%%|*}"
    pattern="${entry#*|}"
    count=$(grep -o -F -- "$pattern" public/build/assets/*.css 2>/dev/null | wc -l)
    if [ "$count" -gt 0 ]; then
        printf "PASS  %-45s %-35s matches=%s\n" "$label" "$pattern" "$count"
    else
        printf "FAIL  %-45s %-35s matches=0\n" "$label" "$pattern"
        FAILED=1
    fi
done

echo
if [ "$FAILED" -ne 0 ]; then
    echo "UI-03 TOKEN GATE: FAIL — one or more tokens emitted zero CSS rules."
    exit 1
fi

echo "UI-03 TOKEN GATE: PASS — all ${#TOKENS[@]} tokens emit real CSS rules."
exit 0
