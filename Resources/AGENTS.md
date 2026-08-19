<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — Resources

<!-- AGENTS-GENERATED:START overview -->
## Overview
Static resources of the `nr_sync` backend extension: Fluid templates and partials for the sync backend modules, XLIFF translations (Crowdin-managed), CSS, and SVG icons.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START setup -->
## Setup & environment
- No build/preprocessing step — files are shipped as-is (no bundler, no SCSS pipeline)
- Templates are rendered by the backend module controllers in `../Classes/Controller/`
- Translations: `en` is the source language; other `*.locallang*.xlf` files come from Crowdin (project `typo3-extension-nr-sync`) — do not hand-edit non-English files
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START structure -->
## Directory structure
```
Resources/
  Private/
    Language/    → XLIFF: locallang / locallang_mod / locallang_mod_sync / locallang_scheduler, English source + per-locale prefixed copies
    Partials/    → Fluid partials (SyncList, SyncStatus, WaitList, Checkboxes, …)
    Templates/   → Fluid templates (Backend/)
  Public/
    Css/         → Administration.css (backend module styling)
    Icons/       → Extension.svg, Module.svg
```
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START commands -->
## Build & tests
- No dedicated resource build or test commands exist
- After template changes, run `composer ci:test` at repo root to keep the overall gate green
- Verify rendering manually in a TYPO3 backend (module: nr_sync)
<!-- AGENTS-GENERATED:END commands -->

<!-- AGENTS-GENERATED:START code-style -->
## Code style & conventions
- Fluid: keep logic in controllers/ViewHelpers, not in templates
- Reuse existing partials (`Private/Partials/`) before adding new markup
- Translation keys: add to English `locallang*.xlf` only; reference via `LLL:EXT:nr_sync/Resources/Private/Language/…`
- SVG icons: registered in `../Configuration/Icons.php`
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START security -->
## Security & safety
- Fluid `{variable}` output auto-escapes — use `<f:format.raw>` only for values that are provably safe
- Never store secrets or target-system credentials in resource files
- Backend templates render sync/table data — treat record values as untrusted input
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR/commit checklist
- [ ] New translation keys added to the English XLIFF source files only
- [ ] XLIFF files are valid XML
- [ ] Templates keep logic-free (no business logic in Fluid)
- [ ] No sensitive data in resources
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real code in this repo over generic examples.**
> Existing partials in `Private/Partials/` demonstrate the current Fluid conventions.
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- Check how templates are consumed by controllers in `../Classes/Controller/`
- Fluid reference: https://docs.typo3.org/other/typo3fluid/fluid/main/en-us/
- XLIFF handling: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Localization/
- Check root AGENTS.md for project conventions
<!-- AGENTS-GENERATED:END help -->
