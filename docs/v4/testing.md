# Testing

Run this command inside your project's root:

```bash
docker-compose up
```

Now run the tests with:

```bash
composer test
```

## Quality tools

```bash
composer lint        # fix the code style with Pint
composer lint:check  # check the code style without changing files
composer stan        # run PHPStan (Larastan), level 5
```

The same checks, plus `composer validate --strict`, run in the CI workflow on every pull request.
