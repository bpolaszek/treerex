# Advanced usage ⚙️

Once you're comfortable with the basics, TreeRex offers several powerful options to fine‑tune how your flowcharts behave.

## Flowchart options

Options can be defined:

- At runtime, the 2nd argument of the flowchart factory.
- At build time, in the `options` key from the YAML (merged with options passed at runtime, runtime options take precedence).

### Unhandled steps

If a branch (`when@true` or `when@false`) is missing entirely, that branch is considered **unhandled**. When the runner reaches it, it throws an `UnhandledStepException`, which also exposes the `RunnerState`.

This simplifies your YAML definitions, but can lead to unexpected results at runtime if you forget to handle some branches.

To avoid this, you can ask `FlowchartFactory` to validate that no branches are left unhandled by passing `allowUnhandledSteps` to `false`:

```yaml
options:
    allowUnhandledSteps: false
```

### Default checker service

If you're using the same checker for multiple decision nodes, you can define a default checker service that will be used for all nodes that don't specify their own checker.

```yaml
options:
    defaultChecker: BenTools\TreeRex\Checker\ExpressionLanguageChecker
```

## Using the ExpressionLanguageChecker

The library ships with `ExpressionLanguageChecker`, which uses Symfony's [ExpressionLanguage component](https://symfony.com/doc/current/components/expression_language.html). 
It allows you to express criteria as strings or arrays of strings instead of writing your own checker logic.

```php
use ArrayObject;
use BenTools\TreeRex\Checker\ExpressionLanguageChecker;
use BenTools\TreeRex\Factory\TreeRexFactory;
use BenTools\TreeRex\Runner\TreeRexRunner;
use BenTools\TreeRex\Utils\ServiceLocator;
use Symfony\Component\Yaml\Yaml;

$definition = Yaml::parse(<<<'YAML'
entrypoint:
  id: stock_check
  checker: product.checker
  criteria: product.stock > 0
  when@true:
    end: true
  when@false:
    end: false
YAML);

$flowchart = new FlowchartFactory()->create($definition);

$runner = new FlowchartRunner(new ServiceLocator([
    'product.checker' => new ExpressionLanguageChecker('product'),
]));

$product = new Product(stock: 10, blacklisted: false);
$context = new ArrayObject();

$result = $runner->satisfies($product, $flowchart, $context);
```

Expressions have access to:

- the subject via the variable you configure (here `product`),
- the context via a `context` variable.

Example: `context.user.role === 'ADMIN' && product.stock > 0`.

## Loading flowcharts from a container

`FlowchartRunner::satisfies()` accepts either:

- a `Flowchart` instance, **or**
- a **service id** of a `Flowchart` registered in your container.

```php
use BenTools\TreeRex\Factory\TreeRexFactory;
use BenTools\TreeRex\Runner\TreeRexRunner;
use BenTools\TreeRex\Utils\ServiceLocator;
use Symfony\Component\Yaml\Yaml;

$definition = Yaml::parse(<<<'YAML'
entrypoint:
  checker: checker.default
  criteria: product.stock > 0
  when@true:
    end: true
YAML);

$factory = new FlowchartFactory();
$flowchart = $factory->create($definition);

$container = new ServiceLocator([ // <-- Ideally, this is your framework's DI container.
    'flowchart.product_is_salable' => $flowchart,
    'checker.default' => new ExpressionLanguageChecker('product'),
]);

$runner = new FlowchartRunner($container);

// Here we pass the flowchart *id* instead of the Flowchart *instance*
$result = $runner->satisfies($product, 'flowchart.product_is_salable');
```

## Declaring blocks at the root level

You can also declare blocks at the root level of the YAML file, and invoke them from decision nodes:

```yaml
blocks:
  stock_check:
    checker: app.checker.product
    criteria: product.stock > 0
  category_check:
    checker: app.checker.product
    criteria: product.category === 'electronics'
  blacklist_check:
    checker: app.checker.product
    criteria: product.blacklisted

entrypoint:
  use: stock_check
  when@true:
    use: category_check
  when@false:
    use: blacklist_check
```

## Using strings or integers instead of booleans

While this library is primarily designed for `true` / `false` decisions, 
you can also use strings or integers by providing the possible values in the `cases` config option:

```yaml
entrypoint:
    checker: app.checker.warehouse
    criteria: product.warehouse # <-- This will return a string
    cases: ['main', 'secondary', 'offsite'] # <-- Possible values
    when@main:
        checker: app.checker.product
        criteria: product.stock > 0
    when@secondary:
        checker: app.checker.restocking
        criteria: product.restocking
    when@offsite:
    # ...
```

---

⬅️ Previous: [Core concepts](03-core-concepts.md)  
➡️ Next: [Contributing](05-contributing.md)
