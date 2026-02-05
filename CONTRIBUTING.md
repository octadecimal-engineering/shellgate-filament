# Contributing Guidelines

> This document defines the project's working standards and conventions.
>
> **Shell Gate is a standalone product.** It does not depend on any external workspace. If this repo is cloned or developed inside another workspace, that is only for **local testing during development** (e.g. via Composer path repository); the product itself remains independent.
>
> This repository is the **production source** for Shell Gate. Keep it model-quality: no junk, no private drafts. Internal and private documentation (e.g. selling guides, internal plans) belong in the main workspace at `docs/plugins/shell-gate/pl/`, not here.

---

## Git Workflow

### Branching strategy

```
main ──────────────────────────────► (production, protected)
  │
  └── develop ─────────────────────► (integration)
        │
        ├── feature/stage-XX-name ─► PR ─► merge
        │
        ├── fix/description-of-problem ─────► PR ─► merge
        │
        └── hotfix/description ───────────► PR ─► merge (urgent)
```

### Branch naming

```bash
feature/stage-01-docker-setup
feature/stage-02-jwt-authentication
fix/reservation-conflict-validation
refactor/extract-conflict-checker
hotfix/ssl-certificate-renewal
```

---

## Commit convention

### Format (Conventional Commits)

```
<type>(<scope>): <description in English>

[optional: body]

[optional: footer]
```

### Types

| Type | Description |
|------|-------------|
| `feat` | New feature |
| `fix` | Bug fix |
| `refactor` | Refactoring |
| `docs` | Documentation |
| `test` | Tests |
| `chore` | Configuration / maintenance |
| `style` | Formatting |

### Scope

`backend`, `frontend`, `api`, `db`, `docker`, `ui`, `content`, `deploy`, `generator`, `marketplace`

### Examples

```bash
feat(content): add content versioning
feat(api): implement GraphQL endpoint for media
fix(deploy): fix timeout during SSL generation
docs(readme): add Docker installation instructions
test(e2e): visual tests for media gallery
chore(docker): configure healthcheck for MySQL
```
# End of Selection
```
```

### Rules

1. **Atomic commits** — one commit = one logical change
2. **English** — commit descriptions in English
3. **Types in English** — types (feat, fix, etc.) remain in English
4. **Max 72 characters** — in the first line
5. **No period** — at the end of the description

---

## Quality gates

### Before commit

```bash
# Backend
./vendor/bin/phpstan analyse --level=8
./vendor/bin/php-cs-fixer fix --dry-run
./vendor/bin/phpunit

# Frontend
npm run lint
npm run type-check
npm test

# E2E (optional before PR)
npx playwright test --config=playwright.pr.config.ts
```

### Requirements

- PHPStan level 8 — zero errors
- ESLint — zero warnings
- Tests — 100% pass
- No `console.log` / `dd()` / `dump()` in code
- No secrets in code

---

## Test structure

```
tests/
├── Unit/              # PHP unit tests
│   ├── Models/
│   ├── Services/
│   └── Helpers/
├── Feature/           # PHP feature tests
│   ├── Api/
│   ├── Filament/
│   └── GraphQL/
├── e2e/               # Playwright tests
│   ├── admin/         # Admin panel
│   ├── generated/     # Generated pages
│   └── visual/        # Visual regression
└── fixtures/          # Test data
```

### Test requirements

- **Unit tests:** min 80% coverage for new code
- **Feature tests:** for every API endpoint
- **E2E tests:** for every critical flow
- **Visual regression:** for generated pages

---

## Docker

### Development

```bash
# Start all services
./vendor/bin/sail up -d

# Artisan commands
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail artisan queue:work

# Tests
./vendor/bin/sail test
./vendor/bin/sail phpstan

# Logs
./vendor/bin/sail logs -f app

# Stop
./vendor/bin/sail down
```

### Docker files

```
docker/
├── docker-compose.yml          # Production
├── docker-compose.dev.yml      # Development override
├── docker-compose.ci.yml       # CI/CD testing
├── php/
│   ├── Dockerfile
│   └── php.ini
├── nginx/
│   └── default.conf
└── mysql/
    └── my.cnf
```

---

## Code documentation

### PHP (PHPDoc)

```php
/**
 * Service managing content.
 */
final readonly class ContentService
{
    /**
     * Publishes content.
     *
     * @param SiteContent $content Content to publish
     * @return bool Whether publishing succeeded
     * @throws ContentNotReadyException When content is not ready
     */
    public function publish(SiteContent $content): bool
    {
        // ...
    }
}
```

### TypeScript (JSDoc)

```tsx
/**
 * Component displaying a media gallery.
 */
interface MediaGalleryProps {
  /** List of media items to display */
  media: Media[];
  /** Callback on click */
  onSelect?: (media: Media) => void;
}

export function MediaGallery({ media, onSelect }: MediaGalleryProps) {
  // ...
}
```

---

## Naming conventions

### PHP

| Element | Convention | Example |
|---------|------------|---------|
| Class | PascalCase | `ContentService` |
| Interface | PascalCase + Interface | `ContentRepositoryInterface` |
| Trait | PascalCase + prefix | `BelongsToTenant`, `HasVersions` |
| Method | camelCase | `getPublishedContent()` |
| Variable | camelCase | `$templateId` |
| Constant | UPPER_SNAKE_CASE | `MAX_UPLOAD_SIZE` |

### TypeScript

| Element | Convention | Example |
|---------|------------|---------|
| Component | PascalCase + .tsx | `HeroSection.tsx` |
| Hook | use + camelCase | `useMediaGallery` |
| Interface/Type | PascalCase | `SiteContent` |
| Function | camelCase | `fetchContent()` |
| Variable | camelCase | `projectSlug` |
| Constant | UPPER_SNAKE_CASE | `API_BASE_URL` |

---

## Pull requests

### Template

```markdown
## [Stage X] Stage name

### Summary
- Description of main changes

### Changes
- `src/app/Modules/Content/...` - description of change
- `tests/Feature/...` - description of change

### Technical decisions
- Rationale for architectural choices

### Testing
- [ ] Unit tests pass
- [ ] Feature tests pass
- [ ] E2E tests pass (if applicable)
- [ ] Manual testing done

### Checklist
- [ ] PHPStan level 8 — zero errors
- [ ] ESLint — zero warnings
- [ ] Documentation updated
- [ ] No console.log/dd() in code
- [ ] No secrets in code
- [ ] Definition of Done from phase plan satisfied
```

### Example PR titles

```
[Stage 1] Docker setup and infrastructure
[Stage 2] Content Module - models and migrations
[Stage 3] AI Template Generator - Claude integration
[Fix] Reservation conflict validation fix
```

### PR requirements

- Min 1 approval before merge
- All checks PASS
- Branch up to date with develop
- Merge via "Squash and merge" (preferred)

---

## Stage workflow

### Starting work

1. Check the current stage in `docs/fazy/FAZA-1-MVP.md`
2. Create branch: `git checkout -b feature/stage-XX-name`
3. Read the Definition of Done for the stage

### While working

1. Commit small, atomic changes
2. Write tests in parallel with code
3. Run quality gates regularly

### Finishing

1. Ensure all tests PASS
2. Update TODO in the phase plan
3. Open a PR using the template
4. Wait for code review

---

## Contact

- **Documentation:** `/docs/`
- **Architecture:** `docs/ARCHITECTURE.md`
- **AI rules:** `.cursorrules`
