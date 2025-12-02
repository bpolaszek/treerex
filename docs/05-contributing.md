# Contributing 🤝

After cloning the repository:

```bash
composer install
```

Static analysis:

```bash
composer types:check
```

Coding standards:

```bash
# Check CS issues
composer style:check

# Fix CS issues
composer style:fix
```

Run the test suite (Pest + PHPUnit) with 100% coverage requirement:

```bash
composer tests:run
```

Or run all checks (types, CS, tests) in one go: ✨

```bash
composer ci:check
```

---

⬅️ Previous: [Advanced usage](04-advanced-usage.md)  
