# TreeRex 🦖

[![CI Workflow](https://github.com/bpolaszek/treerex/actions/workflows/ci.yml/badge.svg)](https://github.com/bpolaszek/treerex/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/bpolaszek/treerex/graph/badge.svg?token=JvHp2bY165)](https://codecov.io/gh/bpolaszek/treerex)

Declaratively describe complex decision trees ("flowcharts") in *YAML* and run them against arbitrary subjects.

## TL;DR (what you get in practice) 🫵

- ✅ **Zero if‑else spaghetti** – complex validation / eligibility logic lives in *YAML*, not buried in controllers.
- 🧩 **Composable rules** – re‑use the same *checker services* across many flowcharts.
- 🔍 **Full observability** – inspect the last node, the *full decision history*, and enriched *context*.
- 🧪 **Test‑friendly** – feed any subject + context, assert the final result and the reasons attached in context.
- 🧠 **Business‑driven** – Product Owners can reason about the YAML flowchart *without reading PHP*.
- ⚡ **Side effects** – trigger external actions (dispatch messages, write to cache, …) alongside decisions.

## What it looks like

```yaml
# config/user_can_edit_post.yaml
options:
  defaultChecker: BenTools\TreeRex\Checker\ExpressionLanguageChecker
  
context:
  requiresApproval: ~
  
entrypoint:
  criteria: "subject.isAdmin()"
  when@true:
    end: true
  when@false:
    criteria: "subject.id === context.post.authorId"
    when@true:
      end: true 
    when@false:
      criteria: "subject.roles in ['ROLE_REVIEWER']"
      when@true: 
        end: 
          result: true
          context:
            requiresApproval: true
```

```php
use BenTools\TreeRex\Factory\FlowchartYamlFactory;
use BenTools\TreeRex\Runner\FlowchartRunner;
use BenTools\TreeRex\Runner\RunnerContext;

$flowchart = new FlowchartYamlFactory()->parseYamlFile(__DIR__.'/config/user_can_edit_post.yaml');
$runner = new FlowchartRunner();
$context = new RunnerContext(['post' => $post]); // <-- will be merged with `requiresApproval` above

$canEdit = $runner->satisfies($user, $flowchart, $context);
var_dump($canEdit); // bool
var_dump($context['requiresApproval']); // bool|null
var_dump($context->state); // RunnerState -> gives you the full history of decisions
```

## Installation 💾

```bash
composer require bentools/treerex
```

## Table of contents 📚

- 🚀 [Getting started](docs/01-getting-started.md)
- 🔍 [Flowchart state & context](docs/02-flowchart-state.md)
- 🧠 [Core concepts](docs/03-core-concepts.md)
- ⚙️ [Advanced usage](docs/04-advanced-usage.md)
- 🤝 [Contributing](docs/05-contributing.md)


# License 📄

MIT.
