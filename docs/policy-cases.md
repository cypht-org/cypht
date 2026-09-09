# Policy cases

[policy-cases.json](policy-cases.json) is the machine-readable collection of
Cypht behaviors that are treated as policies and verified by tests.

## When to add a policy case

Add a policy entry when a test protects a requirement that users, operators,
mail recipients, or security reviewers should be able to rely on. In practice,
the behavior should be at least one of these:

- externally observable application behavior;
- a security, privacy, or data-protection invariant;
- a user or administrator control with a defined safety guarantee; or
- a project process requirement that is explicitly adopted by Cypht.

Do not add an entry for every PHPUnit test. Repository helpers, internal data
structures, mock behavior, parser mechanics, and other implementation details
remain ordinary tests unless the project has adopted them as requirements.

## Adding an entry

1. Decide whether the behavior is a policy rather than an implementation detail.
2. Add or update a policy object in [policy-cases.json](policy-cases.json).
3. Give the policy a unique kebab-case `id`, a `title`, a concise
   `description`, and a `tests` array naming the PHPUnit test methods that
   execute it.
4. Add cases with unique kebab-case `id` values, a description, an `input`
   object, and an `expected` object.
5. Change the relevant test to obtain its cases through
   `PolicyCases::forPolicy()`.
6. Run the focused PHPUnit test and validate the JSON against
   [policy-cases.schema.json](policy-cases.schema.json).

A case should describe the behavior in domain terms where possible. Test
adapters may translate the normalized `input` and `expected` objects into
HTTP requests, handler inputs, or other framework-specific values.

## Example

```json
{
  "id": "message-external-resource-blocking",
  "title": "Message external-resource blocking",
  "description": "Remote resources in message HTML are not loaded automatically.",
  "tests": [
    "Hm_Test_Core_Message_Functions::test_sanitize_email_html_blocks_css_tracking"
  ],
  "cases": [
    {
      "id": "block-css-tracking-resource",
      "description": "A remote CSS tracking resource is removed before rendering.",
      "input": {
        "html": "<ul style=\"list-style-image: url(https://tracker.example/test)\"><li>x</li></ul>"
      },
      "expected": {
        "blocked_count": 1,
        "must_not_contain": "tracker.example"
      }
    }
  ]
}
```

The `tests` metadata makes ownership explicit. It is deliberately separate
from the test adapter so a policy can later be covered by PHPUnit, Selenium,
or another executable test without changing the policy vocabulary.
