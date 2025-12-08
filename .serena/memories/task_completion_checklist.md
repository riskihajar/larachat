# Task Completion Checklist

## Before Completing Any Task

### 1. Code Quality (MANDATORY)

#### PHP Code
```bash
# ALWAYS run Laravel Pint before finalizing
./vendor/bin/pint --dirty

# Or format all files
./vendor/bin/pint
```

#### TypeScript/React Code
```bash
# Format code with Prettier
npm run format

# Fix linting issues
npm run lint

# Type check
npm run types
```

### 2. Testing (MANDATORY)

#### Run Relevant Tests
```bash
# Run specific test file
php artisan test tests/Feature/ChatTest.php

# Run tests with filter
php artisan test --filter=testChatCreation

# Run all tests (after specific tests pass)
composer test
```

#### Test Coverage
- Write tests for new features
- Update existing tests for modified features
- Ensure all happy paths, failure paths, and edge cases are covered
- Never disable or skip tests to make builds pass

### 3. Build Verification

#### Frontend Build
```bash
# Ensure frontend builds without errors
npm run build
```

#### Type Checking
```bash
# Ensure no TypeScript errors
npm run types
```

### 4. Code Review Checklist

#### PHP/Laravel
- [ ] All controller methods use Form Request classes for validation
- [ ] Models use `casts()` method (not `$casts` property)
- [ ] Explicit return type declarations on all methods
- [ ] Constructor property promotion used where applicable
- [ ] Follows Laravel 12 structure (no Kernel.php)
- [ ] Eloquent relationships properly defined with type hints
- [ ] No `env()` calls outside config files
- [ ] No TODO comments for core functionality

#### TypeScript/React
- [ ] All components properly typed with TypeScript
- [ ] Inertia.js `useForm` used for forms (not native state)
- [ ] Path aliases (`@/`) used for imports
- [ ] Components follow file naming convention (kebab-case files, PascalCase components)
- [ ] No unused imports or variables
- [ ] Props interfaces defined for all components
- [ ] Tailwind CSS v4 utilities used (no deprecated v3 syntax)

#### General
- [ ] No files created that should use existing directories
- [ ] Tests placed in `tests/` directory (not alongside source)
- [ ] No temporary scripts or debug files left behind
- [ ] Git status clean (no untracked temporary files)
- [ ] Changes align with existing project patterns

### 5. Documentation Updates (If Applicable)
- [ ] Update README.md if feature changes setup/usage
- [ ] Update CLAUDE.md if architectural patterns change
- [ ] Add comments for complex logic (PHPDoc preferred)

### 6. Performance & Security
- [ ] No N+1 query problems (use eager loading)
- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities
- [ ] CSRF protection maintained
- [ ] Authentication/authorization properly enforced

## Specific Workflows

### After Creating a New Model
```bash
# 1. Create factory and seeder
php artisan make:factory ModelNameFactory
php artisan make:seeder ModelNameSeeder

# 2. Create tests
php artisan make:test ModelNameTest

# 3. Format code
./vendor/bin/pint
```

### After Creating a New Inertia Page
```bash
# 1. Type check
npm run types

# 2. Format
npm run format

# 3. Lint
npm run lint

# 4. Test build
npm run build
```

### After Database Changes
```bash
# 1. Run migrations
php artisan migrate

# 2. Run tests
composer test

# 3. Check for N+1 queries in relevant features
```

### Before Git Commit
```bash
# 1. Check status
git status
git diff

# 2. Run quality checks
./vendor/bin/pint --dirty
npm run format
npm run lint

# 3. Run tests
composer test

# 4. Build frontend
npm run build
```

## Quality Gates Summary

1. **Code Formatting**: ALWAYS run Pint (PHP) and Prettier/ESLint (TS/React)
2. **Type Safety**: ALWAYS run TypeScript type checking
3. **Testing**: ALWAYS run relevant tests before completion
4. **Build Verification**: ALWAYS ensure frontend builds successfully
5. **Pattern Compliance**: ALWAYS follow existing project conventions
6. **No Temporary Files**: ALWAYS clean up debug scripts and temporary files

## Failure Recovery

If tests fail:
1. ❌ DO NOT skip, disable, or comment out tests
2. ✅ Investigate root cause with `php artisan test --filter=testName`
3. ✅ Fix the underlying issue
4. ✅ Re-run tests to verify fix

If build fails:
1. ❌ DO NOT bypass quality checks
2. ✅ Read error messages carefully
3. ✅ Fix issues systematically
4. ✅ Use `npm run types` to identify TypeScript errors