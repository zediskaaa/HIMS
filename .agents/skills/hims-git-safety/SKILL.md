---
name: hims-git-safety
description: Enforces HIMS repository boundaries for Git, GitHub Desktop, branches, commits, push, pull, reset, restore, checkout, merge, rebase, hooks, configuration, `.git`, index locks, and repository corruption. Use whenever Git state or an operation on Git metadata is requested, proposed, or encountered.
---

# HIMS Git Safety

`AGENTS.md` defines the repository's general Git policy. This skill is the operational checklist for applying it to HIMS, where the index and refs have previously produced errors such as:

- `fatal: .git/index: index file smaller than expected`
- `fatal: cannot lock ref 'HEAD'`

These errors do not establish their cause. Do not claim that an agent, `AGENTS.md`, GitHub Desktop, or any other component caused them without evidence.

## Default Ownership

- **Coding agent:** edits only the requested working-tree source files and verifies application behavior.
- **User / GitHub Desktop:** reviews diffs and manages staging, commits, branches, history, remotes, push, pull, fetch, merge, and rebase.

Accepting or implementing code changes authorizes working-tree edits only. It does not authorize Git operations.

## Default Prohibitions

Unless the user explicitly requests the specific Git operation, do not:

- edit, delete, replace, recreate, or otherwise manipulate `.git`, its index, `HEAD`, refs, locks, hooks, or configuration;
- stage or unstage files; create, amend, or delete commits or tags;
- create, switch, rename, merge, rebase, or delete branches;
- push, pull, fetch, change remotes, or rewrite history;
- reset, clean, restore, or use checkout to overwrite working-tree changes;
- initialize or clone a replacement repository;
- run background or repeated Git status/diff polling.

Never use Git to undo user changes or as a shortcut for resolving a coding problem.

## Read-Only Inspection

Prefer ordinary filesystem inspection for project structure and changed files. Use a single read-only Git command only when Git-specific state is necessary to fulfill the request and cannot be established another way.

Before running it, confirm that:

1. the command cannot write metadata or trigger a maintenance/recovery operation;
2. it does not use optional locks or background refresh behavior where a safer form exists;
3. its output is actually needed rather than customary.

Do not run Git merely to prove that source edits exist or tests pass.

## Explicit User Git Requests

An explicit request authorizes only the named operation and its necessary, non-destructive inspection. Verify the exact repository, branch/ref, paths, and expected effect first. Preserve unrelated and uncommitted work.

If completing the request would require an additional destructive, history-changing, remote, or recovery action that the user did not name, stop and explain the extra action rather than inferring permission.

## Corruption or Lock Error Protocol

On an index, ref, lock, or corruption error:

1. Stop all Git-related activity; do not retry in a loop.
2. Preserve the working tree and capture the exact error without inspecting or modifying Git internals unnecessarily.
3. Report the operation that surfaced it and whether a Git client may also be active, if known. Do not speculate about the cause.
4. Do not delete lock files, rebuild the index, rewrite refs, reset, clean, reclone, or modify `.git`.
5. Ask for a separate, explicit repository-recovery request before planning any repair.

Recovery authorization must be specific. Before an approved repair, re-establish the exact failure and describe how working-tree changes will be protected and how success will be checked. If safe recovery cannot be established, stop.

## Verification and Completion

- For ordinary coding work, verify application behavior without Git and report only the source changes made.
- For an explicitly requested read-only check, report the single relevant observation without turning it into a mutation.
- For an explicitly requested Git mutation, verify only the requested outcome and stop; do not chain unrelated cleanup, synchronization, or history changes.
- Do not claim files were staged, committed, pushed, synchronized, or clean unless the user requested and the operation was actually performed.
