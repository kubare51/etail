---
description: Propagate specific fix commit(s) from current feature branch to Retail_1.1.1.0001, QA, and support branches using cherry-pick + PR — safe, isolated, no cross-contamination
version: 3.0
last_updated: 2026-05-18
---

# Propagate Fix Workflow

## Purpose

After completing a task on a feature branch, propagate **only your specific commit(s)** to the three target branches (`Retail_1.1.1.0001`, `QA`, `support`) by creating **pull requests** — so you can review and merge on the web.

> **Uses `cherry-pick` onto a temporary branch, then creates a PR** — no direct pushes to target branches. You control what merges via the GitHub web UI.

## Lifecycle Position

```
  /start-task            ← create feature branch from PRODUCTION
  → (develop locally)
► /propagate-fix         ← YOU ARE HERE — cherry-pick to temp branches, create PRs
  → (review & merge PRs on GitHub)
  → /promote-to-prod     ← PR to merge into PRODUCTION after approval
```

## Prerequisites

- You are on a feature branch (created via `/start-task`)
- You have code changes ready (committed or uncommitted — workflow handles both)
- `gh` CLI is installed and authenticated (`gh auth status`)

## Target Branches (in order)

1. `Retail_1.1.1.0001`
2. `QA`
3. `support`

## Naming Convention

Temporary branches follow this pattern:
```
propagate/{FEATURE_BRANCH}-to-{TARGET_BRANCH}
```

Example: `propagate/task-123-billing-fix-to-QA`

## Steps

### Step 1: PRE-FLIGHT CHECKS [Antigravity]

1. Record the current branch name:

```powershell
git branch --show-current
```

- If on `PRODUCTION`, `Retail_1.1.1.0001`, `QA`, or `support` → **STOP**. "You're on a target branch, not a feature branch. Switch to your feature branch first."

2. Check working tree status:

```powershell
git status --short
```

- If there are **uncommitted changes** → proceed to Step 2 (Stage & Commit)
- If working tree is **clean** (all committed) → skip to Step 3 (identify commits to propagate)

### Step 2: STAGE & COMMIT [Antigravity — requires user approval]

> This step only runs if there are uncommitted changes.

1. Show all changed files with details:

```powershell
git diff --name-status
```

2. Present the file list to the user:

```
📝 Changed files on [BRANCH]:

  M  clients/kallarackals/views/tagging/print/tag_prn_default.php
  M  admin/application/models/ret_tag_model.php
  A  some/new/file.php

Which files to commit?
  (a) All of the above
  (b) Select specific files — list the files you want
```

3. Stage the selected files:

```powershell
git add {SELECTED_FILES}
```

4. Generate a commit message based on the changes and present to user:

```
Suggested commit message:
  fix(kallarackals): remove dia_wt conversion in tag print

Use this message? (yes / edit / provide your own)
```

5. Commit:

```powershell
git commit -m "{COMMIT_MESSAGE}"
```

6. Push to feature branch:

```powershell
git push origin {FEATURE_BRANCH}
```

7. Confirm: `✅ Committed and pushed to {FEATURE_BRANCH}`

### Step 3: IDENTIFY COMMITS TO PROPAGATE [Antigravity]

1. Show recent commits on this branch that are NOT in `support` (the most up-to-date target):

```powershell
git log --oneline HEAD --not origin/support -n 10
```

2. Present the commit list to the user:

```
📋 Commits on [BRANCH] not yet in target branches:

  [COMMIT_HASH_1] [MESSAGE_1]
  [COMMIT_HASH_2] [MESSAGE_2]
  ...

Which commits to propagate?
  (a) All of the above
  (b) Only the latest commit: [HASH]
  (c) Specific commit(s) — please provide hash(es)
```

3. Record the selected commit hash(es) as `{CHERRY_PICKS}`.

### Step 4: CLEAN UNTRACKED BLOCKERS [Antigravity]

Before switching branches, clean untracked files in `.agent/` that may block checkout:

```powershell
git clean -f .agent/skills/bug-fix-engine/recipes/
```

> [!NOTE]
> These recipe files are gitignored in the local workspace but may exist on target branches. Cleaning them prevents "untracked working tree files would be overwritten" errors during checkout.

### Step 5: CREATE PR FOR Retail_1.1.1.0001 [Antigravity — requires user permission]

1. Fetch latest remote state:

```powershell
git fetch origin Retail_1.1.1.0001
```

2. Create a temporary branch from the target branch:

```powershell
git checkout -b propagate/{FEATURE_BRANCH}-to-Retail origin/Retail_1.1.1.0001
```

3. Cherry-pick the selected commit(s):

```powershell
git cherry-pick {CHERRY_PICKS}
```

- If conflict → follow **Conflict Resolution** procedure below
- If clean → proceed

4. Push the temporary branch to remote:

```powershell
git push origin propagate/{FEATURE_BRANCH}-to-Retail
```

5. Create a Pull Request via `gh` CLI:

```powershell
gh pr create --base Retail_1.1.1.0001 --head propagate/{FEATURE_BRANCH}-to-Retail --title "propagate: {COMMIT_MESSAGE} → Retail_1.1.1.0001" --body "Cherry-picked from `{FEATURE_BRANCH}`\n\nCommit(s): {CHERRY_PICKS}\n\nSource workflow: /propagate-fix"
```

6. Confirm with PR URL:

```
✅ PR created for Retail_1.1.1.0001
   🔗 {PR_URL}
   Merge when ready on GitHub.
```

### Step 6: CREATE PR FOR QA [Antigravity — requires user permission]

1. Clean untracked blockers again (in case target branch has different tracked files):

```powershell
git clean -f .agent/skills/bug-fix-engine/recipes/
```

2. Fetch latest remote state:

```powershell
git fetch origin QA
```

3. Create a temporary branch from the target branch:

```powershell
git checkout -b propagate/{FEATURE_BRANCH}-to-QA origin/QA
```

4. Cherry-pick the selected commit(s):

```powershell
git cherry-pick {CHERRY_PICKS}
```

- If conflict → follow **Conflict Resolution** procedure below
- If clean → proceed

5. Push the temporary branch to remote:

```powershell
git push origin propagate/{FEATURE_BRANCH}-to-QA
```

6. Create a Pull Request via `gh` CLI:

```powershell
gh pr create --base QA --head propagate/{FEATURE_BRANCH}-to-QA --title "propagate: {COMMIT_MESSAGE} → QA" --body "Cherry-picked from `{FEATURE_BRANCH}`\n\nCommit(s): {CHERRY_PICKS}\n\nSource workflow: /propagate-fix"
```

7. Confirm with PR URL:

```
✅ PR created for QA
   🔗 {PR_URL}
   Merge when ready on GitHub.
```

### Step 7: CREATE PR FOR support [Antigravity — requires user permission]

1. Clean untracked blockers:

```powershell
git clean -f .agent/skills/bug-fix-engine/recipes/
```

2. Fetch latest remote state:

```powershell
git fetch origin support
```

3. Create a temporary branch from the target branch:

```powershell
git checkout -b propagate/{FEATURE_BRANCH}-to-support origin/support
```

4. Cherry-pick the selected commit(s):

```powershell
git cherry-pick {CHERRY_PICKS}
```

- If conflict → follow **Conflict Resolution** procedure below
- If clean → proceed

5. Push the temporary branch to remote:

```powershell
git push origin propagate/{FEATURE_BRANCH}-to-support
```

6. Create a Pull Request via `gh` CLI:

```powershell
gh pr create --base support --head propagate/{FEATURE_BRANCH}-to-support --title "propagate: {COMMIT_MESSAGE} → support" --body "Cherry-picked from `{FEATURE_BRANCH}`\n\nCommit(s): {CHERRY_PICKS}\n\nSource workflow: /propagate-fix"
```

7. Confirm with PR URL:

```
✅ PR created for support
   🔗 {PR_URL}
   Merge when ready on GitHub.
```

### Step 8: RETURN TO FEATURE BRANCH [Antigravity]

Switch back to the original feature branch:

```powershell
git checkout {ORIGINAL_BRANCH}
```

### Step 9: CLEANUP TEMP BRANCHES (after PRs are merged) [Antigravity]

> [!NOTE]
> This step is **optional** and should only run after the user confirms they have merged all PRs on GitHub.

Delete the local temporary branches:

```powershell
git branch -D propagate/{FEATURE_BRANCH}-to-Retail propagate/{FEATURE_BRANCH}-to-QA propagate/{FEATURE_BRANCH}-to-support
```

> Remote temp branches are auto-deleted by GitHub when PRs are merged (if repo is configured for it), or can be deleted from the PR page.

### Step 10: COMPLETION REPORT [Antigravity]

Present the summary:

```
✅ Propagation PRs Created

Source: {ORIGINAL_BRANCH}
Commit(s): {CHERRY_PICKS}

| Target Branch      | PR Status | PR Link    |
|--------------------|-----------|------------|
| Retail_1.1.1.0001  | 🔗 Open   | {PR_URL_1} |
| QA                 | 🔗 Open   | {PR_URL_2} |
| support            | 🔗 Open   | {PR_URL_3} |

Current branch: {ORIGINAL_BRANCH}

⚠️ Next steps:
  1. Review and merge each PR on GitHub
  2. After manager approval, run /promote-to-prod
```

## Conflict Resolution

If a cherry-pick produces conflicts:

1. Show conflicting files:

```powershell
git diff --name-only --diff-filter=U
```

2. Ask user which approach:
   - **(a) Resolve manually** — user edits files, then `git add .; git cherry-pick --continue`
   - **(b) Abort this branch** — `git cherry-pick --abort; git checkout {ORIGINAL_BRANCH}; git branch -D propagate/{FEATURE_BRANCH}-to-{TARGET}` and skip to next target branch
   - **(c) Accept theirs** — `git checkout --theirs .; git add .; git cherry-pick --continue`
   - **(d) Accept ours** — `git checkout --ours .; git add .; git cherry-pick --continue`

## Safety Rules

> [!CAUTION]
> - **NEVER push directly to target branches** (`Retail_1.1.1.0001`, `QA`, `support`). Always use PRs.
> - **NEVER use `git merge`** in this workflow. Always `cherry-pick`.
> - **NEVER force-push** (`git push -f`). If push is rejected, investigate why.
> - **NEVER skip the fetch** before creating temp branches. Stale branches cause unnecessary conflicts.
> - Each `git push` and `gh pr create` requires **user approval** (SafeToAutoRun = false).
