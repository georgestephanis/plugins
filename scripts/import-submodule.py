#!/usr/bin/env python3
"""
Import a git submodule's full history as a subtree/subdirectory in the monorepo,
remove it as a submodule, and archive the original repository on GitHub.
"""

import argparse
import os
import re
import shutil
import sys
import subprocess

def run_cmd(args, check=True, capture=False, env=None, dry_run=False):
    """Helper to run a subprocess command with clean output and logging."""
    cmd_str = " ".join(args)
    if dry_run:
        print(f"[DRY RUN] Would run: {cmd_str}")
        return subprocess.CompletedProcess(args, 0, stdout="" if capture else None)
    
    result = subprocess.run(
        args,
        stdout=subprocess.PIPE if capture else None,
        stderr=subprocess.PIPE if capture else None,
        text=True,
        check=check,
        env=env
    )
    return result

def is_git_clean():
    """Check if the git working tree has no uncommitted changes."""
    res = subprocess.run(["git", "status", "--porcelain"], stdout=subprocess.PIPE, text=True, check=True)
    return len(res.stdout.strip()) == 0

def get_submodule_details(submodule_path):
    """
    Find the submodule name and url for the given path.
    Returns (name, url) or (None, None) if not found.
    """
    try:
        res = subprocess.run(
            ["git", "config", "-f", ".gitmodules", "--get-regexp", r"^submodule\..*\.path$"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=True
        )
    except subprocess.CalledProcessError:
        return None, None

    submodule_name = None
    for line in res.stdout.strip().split("\n"):
        if not line:
            continue
        parts = line.split(" ", 1)
        if len(parts) < 2:
            continue
        key, path_val = parts[0], parts[1].strip()
        if path_val == submodule_path:
            # key is submodule.<name>.path
            match = re.match(r"^submodule\.(.*)\.path$", key)
            if match:
                submodule_name = match.group(1)
                break

    if not submodule_name:
        return None, None

    try:
        url_res = subprocess.run(
            ["git", "config", "-f", ".gitmodules", "--get", f"submodule.{submodule_name}.url"],
            stdout=subprocess.PIPE,
            text=True,
            check=True
        )
        submodule_url = url_res.stdout.strip()
        return submodule_name, submodule_url
    except subprocess.CalledProcessError:
        return submodule_name, None

def parse_gh_repo(url):
    """
    Extract the github owner/repo slug from a git URL.
    Handles HTTPS and SSH formats.
    """
    if not url:
        return None
    # Matches github.com:owner/repo or github.com/owner/repo (allowing .git extension)
    match = re.search(r"(?:github\.com[:/])([^/]+/[^/.]+?)(?:\.git)?$", url)
    if match:
        return match.group(1)
    return None

def detect_default_branch(url):
    """Query the remote to detect the default branch using git ls-remote."""
    try:
        res = subprocess.run(
            ["git", "ls-remote", "--symref", url, "HEAD"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=True
        )
        for line in res.stdout.strip().split("\n"):
            if "ref: refs/heads/" in line and "HEAD" in line:
                # Format: ref: refs/heads/master  HEAD
                match = re.search(r"ref:\s+refs/heads/(\S+)\s+HEAD", line)
                if match:
                    return match.group(1)
    except subprocess.CalledProcessError:
        pass
    return None

def confirm(prompt, default_yes=False, bypass=False):
    """Prompt the user for confirmation."""
    if bypass:
        return True
    suffix = " [Y/n]" if default_yes else " [y/N]"
    while True:
        try:
            val = input(prompt + suffix + ": ").strip().lower()
        except KeyboardInterrupt:
            print("\nAborted.")
            sys.exit(1)
        if not val:
            return default_yes
        if val in ("y", "yes"):
            return True
        if val in ("n", "no"):
            return False
        print("Please answer yes or no.")

def main():
    parser = argparse.ArgumentParser(
        description="Convert a git submodule into a subdirectory subtree with full history, and archive the original repo on GitHub."
    )
    parser.add_argument("submodule_path", help="The path of the submodule directory to convert (e.g., tarot)")
    parser.add_argument("--branch", help="The branch of the submodule to import (default: auto-detected)")
    parser.add_argument("--no-archive", action="store_true", help="Do not archive the original GitHub repository")
    parser.add_argument("--dry-run", "-n", action="store_true", help="Show the commands that would be executed without running them")
    parser.add_argument("--yes", "-y", action="store_true", help="Automatically accept all confirmation prompts")
    parser.add_argument("--force", "-f", action="store_true", help="Proceed even if there are uncommitted changes in the repository")

    args = parser.parse_args()

    # Find repository root
    script_dir = os.path.dirname(os.path.abspath(__file__))
    repo_root = os.path.dirname(script_dir)
    os.chdir(repo_root)

    print(f"Working in repository root: {repo_root}")

    # Check git clean state
    if not args.force and not args.dry_run:
        if not is_git_clean():
            print("Error: Git working tree is not clean. Commit or stash your changes, or run with --force.")
            sys.exit(1)

    submodule_path = args.submodule_path.rstrip("/")
    if not os.path.exists(os.path.join(repo_root, ".gitmodules")):
        print("Error: No .gitmodules file found in the repository root.")
        sys.exit(1)

    sub_name, sub_url = get_submodule_details(submodule_path)
    if not sub_name:
        print(f"Error: Submodule with path '{submodule_path}' not found in .gitmodules.")
        sys.exit(1)

    if not sub_url:
        print(f"Error: Submodule URL for '{submodule_path}' could not be read from .gitmodules.")
        sys.exit(1)

    # Detect branch
    branch = args.branch
    if not branch:
        print(f"Auto-detecting default branch for remote: {sub_url}...")
        branch = detect_default_branch(sub_url)
        if not branch:
            # default fallback
            branch = "main"
            print(f"Warning: Could not auto-detect default branch. Defaulting to '{branch}'.")
        else:
            print(f"Detected default branch: '{branch}'")

    # Check GitHub Repo archive feasibility
    gh_repo = parse_gh_repo(sub_url)
    should_archive = not args.no_archive and gh_repo is not None

    # Print Summary of Planned Actions
    print("\n" + "=" * 60)
    print("PLANNED ACTIONS:")
    print(f"  1. Remove Git Submodule:")
    print(f"     - Path: {submodule_path}")
    print(f"     - Name: {sub_name}")
    print(f"     - URL:  {sub_url}")
    print(f"  2. Import Submodule History (git subtree):")
    print(f"     - Prefix: {submodule_path}")
    print(f"     - Branch: {branch}")
    print(f"  3. Ensure metadata files exist (phpcs.xml, package.json)")
    if should_archive:
        print(f"  4. Archive GitHub Repository:")
        print(f"     - Repository: {gh_repo}")
    else:
        print(f"  4. Archive GitHub Repository: SKIPPED (not a GitHub repo, or --no-archive passed)")
    print("=" * 60 + "\n")

    if not confirm("Do you want to proceed with these actions?", default_yes=False, bypass=args.yes):
        print("Aborted.")
        sys.exit(0)

    # 1. Deinit submodule
    print(f"\n---> Deinitializing submodule '{submodule_path}'...")
    run_cmd(["git", "submodule", "deinit", "-f", submodule_path], dry_run=args.dry_run)

    # 2. Remove from git
    print(f"---> Removing submodule '{submodule_path}' from git index...")
    run_cmd(["git", "rm", "-f", submodule_path], dry_run=args.dry_run)

    # 3. Clean up git metadata directory in .git/modules
    git_modules_dir = os.path.join(repo_root, ".git", "modules", sub_name)
    if os.path.exists(git_modules_dir):
        print(f"---> Cleaning up git metadata folder: {git_modules_dir}")
        if args.dry_run:
            print(f"[DRY RUN] Would remove directory: {git_modules_dir}")
        else:
            shutil.rmtree(git_modules_dir)

    # 4. Clean up any leftover untracked directory files
    sub_full_path = os.path.join(repo_root, submodule_path)
    if os.path.exists(sub_full_path):
        print(f"---> Cleaning up submodule folder files: {sub_full_path}")
        if args.dry_run:
            print(f"[DRY RUN] Would remove directory: {sub_full_path}")
        else:
            shutil.rmtree(sub_full_path)

    # 5. Clean up git config remnants
    print(f"---> Cleaning up git config references for submodule '{sub_name}'...")
    # This might fail if the config wasn't set, which is fine
    run_cmd(["git", "config", "--remove-section", f"submodule.{sub_name}"], check=False, dry_run=args.dry_run)

    # 6. Commit submodule removal
    print("---> Committing submodule removal...")
    if args.dry_run:
        print("[DRY RUN] Would commit submodule removal.")
    else:
        # Check if there are staged changes to commit
        diff_res = subprocess.run(["git", "diff", "--cached", "--quiet"])
        if diff_res.returncode != 0:
            run_cmd(["git", "commit", "-m", f"Remove submodule {submodule_path} in preparation for subtree history import"])
        else:
            print("No staged changes to commit for submodule removal (already removed).")

    # 7. Import history via git subtree
    print(f"---> Importing history from {sub_url} (branch: {branch}) as subtree under '{submodule_path}'...")
    run_cmd(["git", "subtree", "add", f"--prefix={submodule_path}", sub_url, branch], dry_run=args.dry_run)

    # 8. Ensure phpcs.xml and package.json exist in subtree
    print(f"---> Ensuring project metadata files (phpcs.xml, package.json) exist in '{submodule_path}'...")
    files_created = False

    phpcs_file = os.path.join(repo_root, submodule_path, "phpcs.xml")
    if not os.path.exists(phpcs_file):
        print(f"Creating default phpcs.xml in '{submodule_path}'...")
        phpcs_content = f"""<?xml version="1.0"?>
<ruleset name="{sub_name}">
	<description>WPCS config for {sub_name}.</description>

	<file>./</file>

	<exclude-pattern>*/vendor/*</exclude-pattern>
	<exclude-pattern>*/node_modules/*</exclude-pattern>

	<rule ref="WordPress-Extra"/>
	<rule ref="WordPress-Docs"/>

	<config name="minimum_supported_wp_version" value="6.0"/>
	<config name="testVersion" value="7.4-"/>

	<arg value="ps"/>
	<arg name="colors"/>
	<arg name="extensions" value="php"/>
</ruleset>
"""
        if args.dry_run:
            print(f"[DRY RUN] Would write default phpcs.xml to {phpcs_file}")
        else:
            with open(phpcs_file, "w", encoding="utf-8") as f:
                f.write(phpcs_content)
            files_created = True

    package_json = os.path.join(repo_root, submodule_path, "package.json")
    if not os.path.exists(package_json):
        print(f"Creating default package.json in '{submodule_path}'...")
        package_content = f"""{{
  "name": "{sub_name}",
  "version": "1.0.0",
  "private": true,
  "license": "GPL-2.0-or-later",
  "description": "Build the WordPress.org distribution ZIP via @wordpress/scripts. The shipped plugin version lives in the PHP header and readme; this version field is a static placeholder required only because plugin-zip rejects a non-semver value.",
  "scripts": {{
    "plugin-zip": "wp-scripts plugin-zip"
  }}
}}
"""
        if args.dry_run:
            print(f"[DRY RUN] Would write default package.json to {package_json}")
        else:
            with open(package_json, "w", encoding="utf-8") as f:
                f.write(package_content)
            files_created = True

    if files_created:
        print("---> Committing new project metadata files...")
        if args.dry_run:
            print("[DRY RUN] Would stage and commit metadata files.")
        else:
            run_cmd(["git", "add", phpcs_file, package_json])
            run_cmd(["git", "commit", "-m", f"Add default phpcs.xml and package.json configurations for {submodule_path}"])

    # 9. Archive GitHub repo
    if should_archive:
        print(f"---> Archiving GitHub repository '{gh_repo}'...")
        # Clear GITHUB_TOKEN to fallback to active keyring session if necessary
        env = os.environ.copy()
        if "GITHUB_TOKEN" in env:
            del env["GITHUB_TOKEN"]
        
        # Check if gh CLI is installed
        if not shutil.which("gh"):
            print("Warning: 'gh' command-line tool is not installed or not in PATH. Skipping archiving.")
        else:
            try:
                # We run gh repo archive -y.
                archive_args = ["gh", "repo", "archive", gh_repo, "--yes"]
                run_cmd(archive_args, env=env, dry_run=args.dry_run)
                print(f"Successfully archived GitHub repository '{gh_repo}'!")
            except subprocess.CalledProcessError as e:
                print(f"\nWarning: Failed to archive GitHub repository '{gh_repo}'.")
                print(f"Error: {e}")
                print("Note: The submodule was successfully integrated into the monorepo, but the archive step failed.")
                print("You may need to archive the repo manually on GitHub or configure 'gh' permissions.")

    print("\nConversion successfully completed!")

if __name__ == "__main__":
    main()
