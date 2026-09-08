# AGENTS.md

Project-wide instructions for agents working on this Laravel HIMS repository. More specific nested instructions apply within their scope. Higher-priority platform and explicit user instructions always take precedence.

## Working Method

- Establish the requested outcome and success criteria. Inspect relevant code, configuration, documentation, and installed versions before editing.
- Make reasonable low-risk assumptions and mention only those that affect the result. Ask only when missing information would materially change the outcome or authorization is required.
- Carry authorized work through implementation and verification. If blocked, state the exact blocker and the minimum needed to continue.
- Prefer the simplest correct solution. Do not add unrequested features, dependencies, abstractions, or unrelated cleanup.
- Diagnose root causes, follow existing architecture and style, and keep every change traceable to the request.
- Preserve unrelated and uncommitted work. Remove only unused code created by your own changes.
- Never expose secrets, credentials, or sensitive `.env` values.

## Laravel and HIMS Standards

- Follow the installed PHP, Laravel, and dependency versions. Prefer framework conventions, built-in features, and existing project patterns.
- Validate inputs using the established validation pattern. Authorize protected actions with policies, gates, or middleware, and guard against mass assignment, injection, and sensitive-data exposure.
- Keep controllers focused. Move domain logic to services, actions, jobs, or other classes only when the complexity justifies it.
- Use Eloquent relationships and scopes appropriately, avoid N+1 queries, and use transactions for multi-step writes.
- Review migrations for reversibility, constraints, indexes, data integrity, and production safety.
- For HIMS workflows, prioritize least privilege, privacy, auditability, data accuracy, and traceability. Do not claim regulatory compliance unless it has been formally verified.

## Repository and Change Safety

- Keep edits surgical and verify exact targets before destructive or irreversible file, database, or external-system actions. Prefer reversible actions when practical.
- The user and GitHub Desktop manage Git. Unless the user explicitly requests a specific Git action, do not stage, commit, push, pull, switch branches, merge, rebase, reset, clean, restore, modify `.git`, or rewrite history. Use read-only Git inspection only when materially necessary.
- Antigravity's "Accept All" applies only to requested working-tree file edits; it must not change Git metadata or history. After the requested edits and checks, leave Git management to the user.
- If Git reports corruption, stop Git operations, preserve the working tree, and report the exact error. Do not repair or recreate `.git` data, clone a replacement, or reset files without explicit recovery authorization.
- Do not deploy, publish, contact people, or modify external services unless the user explicitly requests it.

## Truth and Research

- Never fabricate facts, sources, citations, files, code behavior, tool output, or test results.
- Distinguish confirmed observations from assumptions, interpretations, and suspected causes. Say when a material point cannot be verified.
- Verify time-sensitive, unfamiliar, disputed, or high-risk claims with current authoritative sources when tools are available, and cite sources that directly support the claims.
- State accurately what was inspected or executed. Do not claim that work is fixed, tested, deployed, compliant, or complete without supporting evidence.

## Verification

- Run checks proportionate to the change: focused tests, regression coverage, linting, static analysis, builds, or manual checks as relevant.
- Verify important failure paths and edge cases when they are material to the task.
- Report exactly what passed, failed, or could not be run. Do not hide unrelated failures or change unrelated code merely to make a check pass.

## Communication

- Match the user's language; use natural Taglish when appropriate. Lead with the result and use plain, direct language.
- Keep responses concise. Do not require persona introductions, fixed headings, or repeated summaries. Use structure only when it improves clarity.
- For completed work, report only the relevant outcome, changed files, verification, and remaining blocker or risk. Provide brief progress updates during longer tool-based tasks.
