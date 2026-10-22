# Publishing individual packages

Work in this repository. Each configured directory is split into the root of
its corresponding GitHub repository, preserving its package-specific history.
Only committed files are published. Tags and releases are not created.

## Run locally

Requirements: Python 3, Git with `git subtree`, and GitHub CLI (`gh`).
Run from anywhere inside this repository:

```sh
# Preview all configured packages without accessing or changing GitHub:
python3 scripts/sync-packages.py

# Preview just core:
python3 scripts/sync-packages.py core

# Authenticate once, then publish a reviewable sync PR:
gh auth login
python3 scripts/sync-packages.py --pull-request core

# Publish directly, when your credentials may push to the protected branch:
python3 scripts/sync-packages.py --push core
```

The script defaults to a dry run. Publishing requires a clean working tree and
full Git history. It skips repositories whose file trees are already current,
never force-pushes, never creates repositories, and refuses divergent histories.
It processes all configured packages on each run, so a retry catches missed updates.
Existing repositories with unrelated history need a separate, reviewed migration.

`scripts/package-repositories.json` maps folder names to existing repositories.
For example, `actions` maps to `gcego/action`. Add other folders only after creating
their destination repositories and deciding how to initialize their history.

## Enable GitHub Actions

1. Create a fine-grained personal access token scoped to the destination package
   repositories with **Contents: read and write** and **Pull requests: read and
   write**. If package subtrees contain GitHub workflows, also grant the required
   workflow write permission. Do not put the token in a file or commit it.
2. In `gcego/packages`, open **Settings → Secrets and variables → Actions** and
   add the token as repository secret `PACKAGE_SYNC_TOKEN`.
3. Merge the script, configuration, documentation, and workflow into `packages/main`.

On each push to `main`, the workflow opens sync PRs in the configured repositories.
These still require your review and manual merge under the existing protection rules.
Use **Create a merge commit** for sync PRs. Squashing or rebasing discards the shared
split ancestry and will make a later sync fail its history check. A newer run can
create a newer sync PR while an earlier one is open; merge the newest and close
superseded PRs. Do not edit generated sync branches.

For fully automatic publication after the source PR is approved, set repository
variable `PACKAGE_SYNC_MODE` to `direct`. This also requires an explicitly authorized
publishing identity with a direct-push exception in the destination branch rules.
The current PR-only administrator bypass does not permit direct pushes. This script
does not change any protections or enable auto-merge. Without that exception,
GitHub rejects direct publication and the workflow reports the failure.

A dedicated GitHub App with short-lived installation tokens is preferable for a
long-running publishing service. Replace the PAT step with App-token generation
and pass its token as `GH_TOKEN` when you provision the App.

## Packages with their own repository

`registry/` is developed in its own repository (`gcego/registry`) and is not
tracked or synced here: the monorepo ignores the folder, so commit and push
from inside `registry/` itself. Other packages keep using their local copy
through Composer path repositories (`../registry`).
