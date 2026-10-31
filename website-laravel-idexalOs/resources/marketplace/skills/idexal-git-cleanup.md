---
name: idexal-git-cleanup
description: Safe recipes to squash messy branches, split a stalled commit, and prepare a clean pull request without losing work. Use before opening a PR or after a failed merge.
---

# Git History Cleanup

## Squash a messy branch into one commit

```bash
# Keep the exact branch tip as a backup tag first
git tag backup/<branch> <branch>

git checkout <base>
git checkout -b <branch>-clean
git merge --squash <branch>
git commit
```

## Split a stalled commit (keep the good part)

```bash
git reset --soft HEAD~1          # changes return to the index
git restore --staged <bad-file>  # drop the broken file from the commit
git commit -m "the good part"
```

The discarded part stays on disk, unstaged. Nothing is deleted.

## Prepare the pull request

1. `git fetch origin <base>` and rebase onto it; resolve conflicts one commit
   at a time, never the whole branch at once.
2. Run the project's test command locally before pushing.
3. Push with `--force-with-lease`, never bare `--force`, so you cannot erase a
   teammate's new commits.

## Recovery

Every recipe above is reversible until you delete the backup tag:

```bash
git reset --hard <branch>-backup-tag
```

Never rewrite history on a shared branch. Only on your own feature branch.
