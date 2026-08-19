<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — workflows

<!-- AGENTS-GENERATED:START overview -->
## Overview
GitHub Actions workflows of this repo. Every workflow is a **thin caller** of centrally maintained reusable workflows in `netresearch/typo3-ci-workflows` and `netresearch/.github` — action pinning, harden-runner, and job internals are maintained there, not here.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `ci.yml` | Test matrix (PHP 8.2-8.5 × TYPO3 ^13.4) via `typo3-ci-workflows/ci.yml` |
| `checks.yml` | Security/quality jobs (CodeQL, gitleaks, zizmor, …) with a `gate` job; byte-identical across t3x repos |
| `harness-verify.yml` | Agent-harness consistency check via `netresearch/.github` `script-check.yml` |
| `release.yml` | Release via `typo3-ci-workflows/release-typo3-extension.yml` |
| `republish.yml` | Republish via `typo3-ci-workflows/republish.yml` |
| `auto-merge-deps.yml` | Auto-merge dependency PRs (`netresearch/.github`) |
| `community.yml` | stale/lock/greetings (`netresearch/.github`) |
| `labeler.yml` | PR labeling (`netresearch/.github`, config `../labeler.yml`) |
<!-- AGENTS-GENERATED:END filemap -->

<!-- AGENTS-GENERATED:START structure -->
## Workflow files
```
.github/
  dependabot.yml   → dependency update config
  labeler.yml      → labeler rules
  workflows/       → thin callers of reusable workflows (see table above)
```
There are no composite actions, no CODEOWNERS, and no repo-local PR template (org-level via netresearch/.github).
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START code-style -->
## Workflow conventions
- Top-level `permissions: {}`; each `uses:` job grants exactly the reusable's caller contract (e.g. `contents: read`)
- Reference reusables `@main` — central repos control their own pinning; do not pin reusable refs here
- `checks.yml` is drift-enforced: any job added there **must** also be added to `gate.needs` (see the comment block in the file)
- The extension-specific CI matrix lives only in `ci.yml` (intentional drift); everything else stays byte-identical with sibling t3x repos
- Never use `secrets: inherit` — pass secrets explicitly (`CODECOV_TOKEN` in `ci.yml`)
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START patterns -->
## Common patterns

### Thin caller of a reusable workflow (the only job shape used here)
```yaml
permissions: {}

jobs:
  ci:
    uses: netresearch/typo3-ci-workflows/.github/workflows/ci.yml@main
    permissions:
      contents: read
    with:
        php-versions: '["8.2","8.3","8.4","8.5"]'
        typo3-versions: '["^13.4"]'
    secrets:
      CODECOV_TOKEN: ${{ secrets.CODECOV_TOKEN }}
```
Change matrix values in `with:`; never inline job steps into this repo when a central reusable exists.
<!-- AGENTS-GENERATED:END patterns -->

<!-- AGENTS-GENERATED:START security -->
## Security & safety
- Required checks come from repo rulesets: `All security checks`, `CodeQL`, `Opengrep OSS`, `ci / All CI checks`, `scorecard`
- A job missing from `gate.needs` in `checks.yml` silently loses merge-blocking power — keep the list in sync by hand
- Never expose secrets in logs; never widen a job's `permissions` beyond the reusable's documented contract
- Workflow-file changes require a PR (push to `master` is ruleset-protected)
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR/commit checklist
- [ ] Job added to `checks.yml` is also listed in `gate.needs`
- [ ] `permissions:` blocks unchanged or minimally extended per reusable contract
- [ ] No `secrets: inherit`
- [ ] Workflow syntax valid (`zizmor`/actionlint run in CI)
- [ ] Matrix values still match supported PHP/TYPO3 versions in the repo-root `composer.json`
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real workflows in this directory over generic examples.**
> `ci.yml` and `checks.yml` demonstrate the caller pattern and the gate convention.
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- Reusable workflow sources: https://github.com/netresearch/typo3-ci-workflows and https://github.com/netresearch/.github
- GitHub Actions reusable workflow docs: https://docs.github.com/en/actions/using-workflows/reusing-workflows
- Check sibling t3x repos for the byte-identical `checks.yml` reference
<!-- AGENTS-GENERATED:END help -->
