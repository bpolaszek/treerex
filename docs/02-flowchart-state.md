# Flowchart state & context 🔍

The runner keeps track of its internal **state** in a `RunnerState` object. 
This state is always available under the special `_state` key in the **context**.

```php
use BenTools\TreeRex\Runner\RunnerState;

// After calling $runner->satisfies(...)
/** @var RunnerState $state */
$state = $context['_state'];

// Last decision node reached
$lastNode = $state->decisionNode;

// Last result for that node
$lastResult = $state->lastResult;

// History of all decisions taken
// array of [<node id>, <result>] entries
$history = $state->history;

// For example:
// [
//     ['stock_check', true],
//     ['blacklist_check', false],
// ]
```

You can enrich the context at different stages:

- Initial context – the array/`ArrayObject` you pass to `FlowchartRunner::satisfies()`.
- Flowchart root `context` – defined at the root of the YAML file (merged on top of the initial context).
- Decision‑node `context` – each node can add/override keys in the context.
- `end`, `goto`, and `error` actions can also define `context` that will be merged into the final context.

Every time the context is extended, `_state` is automatically updated to point to the latest `RunnerState` instance.

---

⬅️ Previous: [Getting started](01-getting-started.md)  
➡️ Next: [Core concepts](03-core-concepts.md)
