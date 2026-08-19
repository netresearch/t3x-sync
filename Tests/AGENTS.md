<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — Tests

<!-- AGENTS-GENERATED:START overview -->
## Overview
PHPUnit unit test suite for `nr_sync`. Only `Tests/Unit/` exists — there is no functional test suite. Config: `../Build/phpunit.xml` (PHPUnit 10.5 schema, strict about output/risky/warnings). **Use the `typo3-testing` skill** for comprehensive guidance.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `Unit/Event/BeforeSyncEventTest.php` | Tests for the BeforeSync PSR-14 event |
| `Unit/Event/AfterSyncEventTest.php` | Tests for the AfterSync PSR-14 event |
| `Unit/Event/ModifyMenuItemsEventTest.php` | Tests for the ModifyMenuItems event |
| `Unit/Event/ModifyTableListEventTest.php` | Tests for the ModifyTableList event |
<!-- AGENTS-GENERATED:END filemap -->

<!-- AGENTS-GENERATED:START golden-samples -->
## Golden Samples (follow these patterns)
| Pattern | Reference |
|---------|-----------|
| Unit test | `Unit/Event/ModifyMenuItemsEventTest.php` |
<!-- AGENTS-GENERATED:END golden-samples -->

<!-- AGENTS-GENERATED:START setup -->
## Setup
- `composer install` at repo root — PHPUnit lands in `.build/bin/phpunit` (via `netresearch/typo3-ci-workflows` dev dependency)
- No database, TYPO3 instance, or ddev required: the suite is pure unit tests
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START structure -->
## Test Structure
```
Tests/
└── Unit/          # Fast, isolated unit tests (autoload-dev: Netresearch\Sync\Tests\)
    └── Event/     # PSR-14 event tests
```
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START commands -->
## Running Tests (from repo root)
| Type | Command |
|------|---------|
| Unit tests | `composer ci:test:php:unit` (= `make test-unit`) |
| Single file | `.build/bin/phpunit --configuration Build/phpunit.xml Tests/Unit/Event/BeforeSyncEventTest.php` |
| Filter by name | `.build/bin/phpunit --configuration Build/phpunit.xml --filter methodName` |
<!-- AGENTS-GENERATED:END commands -->

<!-- AGENTS-GENERATED:START patterns -->
## Key Patterns
- Test classes are `final` and extend `PHPUnit\Framework\TestCase` directly (no TYPO3 testing-framework base class in use)
- Test methods use the `#[Test]` attribute (`PHPUnit\Framework\Attributes\Test`)
- PHPUnit config is strict: `failOnRisky`, `failOnWarning`, `beStrictAboutOutputDuringTests` — keep test output pristine
- Coverage source is `../Classes/` (see `../Build/phpunit.xml`)
<!-- AGENTS-GENERATED:END patterns -->

<!-- AGENTS-GENERATED:START code-style -->
## Code Style
- Test class name matches source: `MyClass` → `MyClassTest`; namespace `Netresearch\Sync\Tests\Unit\…`
- One assertion concept per test
- Use data providers for multiple similar cases
- Mock external services, never real HTTP/FTP calls
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START security -->
## Security
- Never place real hostnames, credentials, or customer data in tests — use obvious dummies
- Mock filesystem/FTP/database interactions; tests must not touch the network or write outside temp dirs
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START examples -->
## Examples
> **Prefer looking at real tests in this repo over generic examples.**
> `Unit/Event/ModifyMenuItemsEventTest.php` shows the house style: final class, `#[Test]` attributes, one behavior per method.
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START checklist -->
## PR Checklist
- [ ] All tests pass: `composer ci:test:php:unit`
- [ ] New functionality has tests
- [ ] No hardcoded credentials or paths
- [ ] Tests produce no output (strict PHPUnit config fails on it)
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- PHPUnit 10 docs: https://docs.phpunit.de/en/10.5/
- Check `../Build/phpunit.xml` for suite/coverage configuration
- Review root AGENTS.md and `../docs/ARCHITECTURE.md` for component context
<!-- AGENTS-GENERATED:END help -->

<!-- AGENTS-GENERATED:START skill-reference -->
## Skill Reference
> For comprehensive TYPO3 testing guidance including fixtures, mocking, and CI setup:
> **Invoke skill:** `typo3-testing`
<!-- AGENTS-GENERATED:END skill-reference -->
