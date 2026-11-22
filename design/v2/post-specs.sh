#!/usr/bin/env bash
# Posts the v2 design spec (screenshots + short spec) as a comment on each
# sub-issue of #60. Run from the repository root after committing and pushing
# design/v2 to GitHub, so the screenshot links resolve.
#
#   design/v2/post-specs.sh              # uses the current branch
#   design/v2/post-specs.sh release/2.0  # or name the pushed branch
#   DRY_RUN=1 design/v2/post-specs.sh    # print what would be posted
#
# Issues that already have a comment with the marker below are skipped, so the
# script is safe to re-run.
set -euo pipefail

REPO="ArtisanPack-UI/ecommerce-storefront-livewire"
BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"
DIR="design/v2/specs"
MARKER="<!-- apui-design-spec-v2 -->"

command -v gh >/dev/null || { echo "gh is not installed: https://cli.github.com" >&2; exit 1; }
gh auth status >/dev/null 2>&1 || { echo "Run 'gh auth login' first." >&2; exit 1; }

git fetch --quiet origin "$BRANCH"
if ! git cat-file -e "origin/$BRANCH:design/v2/screens/header.png" 2>/dev/null; then
  echo "design/v2/screens isn't on origin/$BRANCH yet. Commit and push it first." >&2
  exit 1
fi

posted=0; skipped=0
for file in $(ls "$DIR"/*.md | sort -V); do
  number="$(basename "$file" .md)"
  if gh api "repos/$REPO/issues/$number/comments" --paginate --jq '.[].body' | grep -qF "$MARKER"; then
    echo "#$number already has the design spec, skipping"; skipped=$((skipped + 1)); continue
  fi
  body="$MARKER"$'\n'"$(sed "s#{{BRANCH}}#${BRANCH}#g" "$file")"
  if [[ -n "${DRY_RUN:-}" ]]; then
    echo "----- #$number -----"; echo "$body" | head -5; continue
  fi
  gh issue comment "$number" --repo "$REPO" --body "$body" >/dev/null
  echo "Posted the design spec to #$number"
  posted=$((posted + 1))
  sleep 1
done
echo "Done: $posted posted, $skipped skipped."
