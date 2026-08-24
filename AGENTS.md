<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-08-19 | Last verified: 2026-08-19 -->

# AGENTS.md

**Precedence:** the **closest `AGENTS.md`** to the files you're changing wins. Root holds global defaults only.

netresearch/nr-sync — TYPO3 v13.4 backend extension (key `nr_sync`) that synchronizes content from a production system to one or more target systems. Extension version: see `ext_emconf.php`. Component map: `docs/ARCHITECTURE.md`.

## Commands (verified against composer.json / Makefile)

<!-- AGENTS-GENERATED:START commands -->
| Task | Command |
|------|---------|
| PHP lint | `composer ci:test:php:lint` |
| Static analysis | `composer ci:test:php:phpstan` (= `make phpstan`) |
| Code style check | `composer ci:test:php:cgl` (= `make cgl`) |
| Code style fix | `composer ci:cgl` (= `make cgl-fix`) |
| Rector dry-run | `composer ci:test:php:rector` (= `make rector`) |
| Unit tests | `composer ci:test:php:unit` (= `make test-unit`) |
| All checks | `composer ci:test` |
| Any suite in Docker (verified 2026-08-24) | `./Build/Scripts/runTests.sh -s unit\|lint\|phpstan\|rector\|cgl` |

`Build/Scripts/runTests.sh` is the bootstrap stub of `netresearch/typo3-ci-workflows`; the runner comes from the package and is linked into `.build/bin`. It runs the suites in Docker against a chosen PHP version (`-p 8.2`). **Do not run `-s functional` here:** this extension has no functional tests and no functional config, and the runner silently falls back to `Build/phpunit.xml`, so the suite re-runs the 21 unit tests and reports them as functional (netresearch/typo3-ci-workflows#212). `-s lint` uses `php -l` over `Classes Configuration Tests`, not `phplint` with `Build/.phplint.yml` (netresearch/typo3-ci-workflows#217).
| Harness check | `bash scripts/verify-harness.sh` |
<!-- AGENTS-GENERATED:END commands -->

> Tool binaries land in `.build/bin/` (composer `bin-dir`); tool configs live in `Build/`. If commands fail, verify against `composer.json` / `Makefile` and update this table.

## Response Style
- Answer first, elaborate only if needed. No sycophantic openers ("Great question!", "Absolutely!").
- For yes/no or status questions, lead with the answer.
- Skip preamble. Match response length to task complexity.

## Workflow
1. **Before coding**: Read nearest `AGENTS.md` + check Golden Samples for the area you're touching
2. **After each change**: Run the smallest relevant check (lint → phpstan → single test)
3. **Before committing**: Run `composer ci:test` if changes affect >2 files or touch shared code
4. **Before claiming done**: Run verification and **show output as evidence** — never say "try again", "should work now", "tested", "verified", or "all green" without pasted command output in the same turn

## File Map
<!-- AGENTS-GENERATED:START filemap -->
```
Classes/         → PHP classes (PSR-4: Netresearch\Sync\)
Configuration/   → backend module registration, Services.yaml, TypoScript, icons
Resources/       → Fluid templates/partials, XLIFF translations, CSS, icons
Tests/           → PHPUnit unit tests (Tests/Unit/ only, no functional suite)
Build/           → tool configs (phpstan, rector, php-cs-fixer, phplint, phpunit)
Documentation/   → images for README (no RST docs)
scripts/         → repo scripts (clean-lock.sh, verify-harness.sh)
docs/            → agent-facing docs (ARCHITECTURE.md, exec-plans/)
```
<!-- AGENTS-GENERATED:END filemap -->

## Golden Samples (follow these patterns)
<!-- AGENTS-GENERATED:START golden-samples -->
| For | Reference | Key patterns |
|-----|-----------|--------------|
| Controller | `Classes/Controller/AssetSyncModuleController.php` | backend sync module controller |
| Service | `Classes/Service/StorageService.php` | DI service via `Configuration/Services.yaml` |
| Event | `Classes/Event/BeforeSyncEvent.php` | PSR-14 event |
| Test | `Tests/Unit/Event/ModifyMenuItemsEventTest.php` | final class, plain PHPUnit `TestCase`, `#[Test]` attributes |
<!-- AGENTS-GENERATED:END golden-samples -->

## Heuristics (quick decisions)
<!-- AGENTS-GENERATED:START heuristics -->
| When | Do |
|------|-----|
| Adding class | Follow PSR-4 in `Classes/` (`Netresearch\Sync\`) |
| Adding controller | Create in `Classes/Controller/`, extend `BaseSyncModuleController` |
| Adding service | Create in `Classes/Service/` |
| Running tasks | Check `make help` for available commands |
| Adding dependency | Ask first - we minimize deps |
| Unsure about pattern | Check Golden Samples above |
<!-- AGENTS-GENERATED:END heuristics -->

## Repository Settings
<!-- AGENTS-GENERATED:START repo-settings -->
- **Default branch:** `master`
- **Merge strategy:** merge
- **Signed commits:** required
- **Required checks (rulesets):** `All security checks`, `CodeQL`, `Opengrep OSS`, `ci / All CI checks`, `scorecard`
- **Active rulesets:** require-signed-commits, t3x-baseline, t3x-pull-request
<!-- AGENTS-GENERATED:END repo-settings -->

## Boundaries

### Always Do
- Run pre-commit checks before committing
- Add tests for new code paths
- Use conventional commit format: `type(scope): subject`
- Use **atomic commits** (one logical change per commit); preserve signatures, keep bisection useful
- **Show test output as evidence before claiming work is complete** — never say "try again", "should work now", "tested", "verified", or "all green" without pasted command output
- Before any edit, verify `pwd` resolves inside the intended repo worktree — not `.bare/`, not `~/.claude/skills/…`, not `~/.claude/plugins/cache/…` (those are read-only caches that get clobbered on update)
- For upstream dependency fixes: run **full** test suite, not just affected tests
- Force-push only with `--force-with-lease`
- Follow PSR-12 + TYPO3 CGL; `declare(strict_types=1);` in every PHP file

### Ask First
- Adding new dependencies
- Modifying CI/CD configuration
- Changing public API signatures
- Repo-wide refactoring or rewrites
- Operations that touch >3 repos (produce a dry-run plan first)

### Never Do
- Commit secrets, credentials, or sensitive data
- Modify `.build/`, vendor/, or generated files
- Push directly to master — open a PR
- Merge a PR before all review threads are resolved
- Squash commits during merge or rebase unless the user explicitly asked
- Edit installed skill/plugin cache paths (`~/.claude/skills/`, `~/.claude/plugins/cache/`, `**/.bare/**`) — always the source worktree
- Reply to review comments with bare "Addressed" or "Fixed" — cite the resolving commit SHA
- Commit `composer.lock` — extension repos stay lock-free (it is gitignored)
- Use `secrets: inherit` in reusable GitHub Actions workflows (pass secrets explicitly)
- Modify core framework files

## Contributing (for AI agents)
- **Comprehension**: Understand the problem before submitting code. Read the linked issue, understand *why* the change is needed, not just *what* to change.
- **Context**: Every PR must explain the trade-offs considered and link to the issue it addresses. Disclose AI assistance if the project requires it.
- **Continuity**: Respond to review feedback. Drive-by PRs without follow-up will be closed.

## Codebase State
<!-- AGENTS-GENERATED:START codebase-state -->
- Deprecated code exists: `Classes/Helper/Area.php` carries `@deprecated` (grep for `@deprecated`)
- PHPStan runs at level 6 with baseline `Build/phpstan-baseline.neon` — do not grow the baseline
<!-- AGENTS-GENERATED:END codebase-state -->

## Scoped AGENTS.md (MUST read when working in these directories)
<!-- AGENTS-GENERATED:START scope-index -->
- `./Classes/AGENTS.md` — PHP classes: sync modules, services, events, DI
- `./Tests/AGENTS.md` — PHPUnit unit test suite
- `./Resources/AGENTS.md` — Fluid templates, XLIFF translations, CSS, icons
- `./.github/workflows/AGENTS.md` — thin callers of netresearch reusable CI workflows
<!-- AGENTS-GENERATED:END scope-index -->

> **Agents**: When you read or edit files in a listed directory, you **must** load its AGENTS.md first. It contains directory-specific conventions that override this root file.

## When instructions conflict
The nearest `AGENTS.md` wins. Explicit user prompts override files.
- For PHP-specific patterns, follow PSR standards
