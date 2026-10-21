#!/usr/bin/env python3
"""Publish package subtrees. Dry-run by default; never force-push."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import sys


def run(*args, capture=True):
    result = subprocess.run(args, text=True, stdout=subprocess.PIPE if capture else None, check=True)
    return result.stdout.strip() if capture else ""


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("packages", nargs="*", help="Folders to sync (default: all configured folders)")
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--push", action="store_true", help="Publish directly to destination main branches")
    mode.add_argument("--pull-request", action="store_true", help="Publish sync branches and open pull requests")
    parser.add_argument("--config", type=Path, default=Path(__file__).with_name("package-repositories.json"))
    args = parser.parse_args()
    root = Path(run("git", "rev-parse", "--show-toplevel"))
    os.chdir(root)
    mapping = json.loads(args.config.resolve().read_text())
    selected = args.packages or list(mapping)
    unknown = set(selected) - mapping.keys()
    if unknown:
        parser.error("Unconfigured package(s): " + ", ".join(sorted(unknown)))
    if run("git", "rev-parse", "--is-shallow-repository") == "true":
        parser.error("Full history required. Run git fetch --unshallow first.")
    if (args.push or args.pull_request) and run("git", "status", "--porcelain"):
        parser.error("Commit or stash local changes before publishing. Only committed files are split.")
    source = run("git", "rev-parse", "HEAD")
    failures = []
    for package in selected:
        repo = mapping[package]
        if not re.fullmatch(r"[A-Za-z0-9_.-]+", package) or not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", repo):
            parser.error("Invalid folder or repository in configuration")
        try:
            if run("git", "cat-file", "-t", f"{source}:{package}") != "tree":
                raise ValueError(f"{package} is not a committed package directory")
            print(f"\n{package}/ -> {repo}", flush=True)
            split = run("git", "subtree", "split", f"--prefix={package}", source)
            print(f"  Split commit: {split}", flush=True)
            if not (args.push or args.pull_request):
                print("  Dry run: no remote changes")
                continue
            # Metadata also verifies repository access. Missing repositories are never created.
            metadata = json.loads(run("gh", "api", f"repos/{repo}"))
            base = metadata["default_branch"]
            url = f"https://github.com/{repo}.git"
            # Scope authentication to this command; never write credentials into remote URLs.
            git = ["git", "-c", "credential.helper=", "-c", "credential.helper=!gh auth git-credential"]
            run(*git, "fetch", "--no-tags", url, f"refs/heads/{base}", capture=False)
            remote = run("git", "rev-parse", "FETCH_HEAD")
            if run("git", "rev-parse", f"{remote}^{{tree}}") == run("git", "rev-parse", f"{split}^{{tree}}"):
                print("  Already up to date")
                continue
            ancestor = subprocess.run(["git", "merge-base", "--is-ancestor", remote, split])
            publish = split
            if ancestor.returncode != 0:
                common = run("git", "merge-base", remote, split)
                if run("git", "rev-parse", f"{remote}^{{tree}}") != run("git", "rev-parse", f"{common}^{{tree}}"):
                    raise ValueError("Destination history diverged. Reconcile it with packages first; refusing to overwrite it.")
                # A previous sync PR adds a merge commit absent from the split history.
                # Preserve both histories, only when the destination has no extra edits.
                publish = run("git", "commit-tree", f"{split}^{{tree}}", "-p", remote, "-p", split,
                              "-m", f"Sync {package} from packages {source}")
            target = base if args.push else f"sync/packages-{split}"
            run(*git, "push", url, f"{publish}:refs/heads/{target}", capture=False)
            if args.pull_request:
                existing = json.loads(run("gh", "pr", "list", "--repo", repo, "--state", "open", "--head", target, "--base", base, "--json", "url"))
                if existing:
                    print(f"  Existing PR: {existing[0]['url']}")
                else:
                    body = (f"Publish the `{package}/` subtree from gcego/packages commit `{source}`.\n\n"
                            "Make package changes in gcego/packages. Merge this PR with a merge commit "
                            "to preserve the split history; do not squash or rebase it.")
                    print(run("gh", "pr", "create", "--repo", repo, "--base", base, "--head", target,
                              "--title", f"Sync {package} from packages", "--body", body))
        except (subprocess.CalledProcessError, ValueError, KeyError) as error:
            failures.append(package)
            print(f"  FAILED: {error}", file=sys.stderr, flush=True)
    if failures:
        print("Failed packages: " + ", ".join(failures), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
