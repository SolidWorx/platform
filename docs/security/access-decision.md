# Per-Attribute Access Decision Strategies

Symfony decides every permission with **one** strategy. That is usually fine, and occasionally
wrong: the default `affirmative` strategy returns on the first voter that grants and never looks at
the rest, so a voter that refuses can be silently outvoted. If some permissions in your application
mean "everyone must agree", you previously had to change the strategy for the *whole* application.

The platform lets you name those permissions instead:

```yaml
# platform.yaml
platform:
    security:
        access_decision:
            strategies:
                INVITE_MEMBER: unanimous
                PUBLISH_ARTICLE: consensus
```

Everything else keeps the strategy you configured under
`security.access_decision_manager.strategy` — or Symfony's `affirmative` default if you configured
nothing.

## The problem it solves

Two voters answer `INVITE_MEMBER`: one grants because the user is an administrator, one refuses
because the workspace has hit its seat limit.

```php
final class AdminVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'INVITE_MEMBER';
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return in_array('ROLE_ADMIN', $token->getRoleNames(), true);
    }
}

final class SeatLimitVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'INVITE_MEMBER';
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->seats->remaining() < 1) {
            $vote?->addReason('Your plan has no seats left.');

            return false;
        }

        return true;
    }
}
```

Under `affirmative`, whichever voter runs first decides: register `AdminVoter` at a higher priority
and the seat limit stops existing. Naming `INVITE_MEMBER` as `unanimous` makes the refusal
decisive, and leaves the rest of the application alone.

This is also how the platform's own `TENANT_CREATE` works — see
[Multi-Tenancy](../multi-tenancy/index.md#deciding-who-may-create-one).

## Available strategies

The four Symfony ships, named exactly as in `security.access_decision_manager.strategy`:

| Strategy | Grants access when |
|---|---|
| `affirmative` | any voter grants (Symfony's default) |
| `consensus` | more voters grant than refuse |
| `unanimous` | at least one voter grants and none refuses |
| `priority` | the first voter that does not abstain grants |

Anything else fails at compile time with the list of valid names.

## Refusals carry a reason

Since Symfony 7.3 a voter can explain itself with `$vote->addReason()`, and the platform's own
voters do. `#[IsGranted]` puts those reasons in the 403 message, so a refusal tells the user why
without you writing a message per controller:

```php
#[IsGranted('INVITE_MEMBER')]   // 403: "Access Denied. Your plan has no seats left."
public function invite(): Response
```

Reasons come only from votes that agree with the verdict, so an outvoted grant never leaks into a
denial message.

## How it works

`PerAttributeAccessDecisionManager` holds one `AccessDecisionManager` per configured strategy, plus
the default, and routes each decision by attribute.

It is installed by rewriting the **definition** of `security.access.decision_manager`, not by
pointing that service id somewhere else, and that distinction is the whole trick:
`AddSecurityVotersPass` gives up when the id is an alias — which is what
`security.access_decision_manager.service` produces — and takes the collected voters and their
profiler tracing with it. Rewriting the definition keeps Symfony's wiring intact:

- argument 0 — the voters, still collected, sorted by tag priority and (in debug) wrapped in
  `TraceableVoter` by Symfony's own pass, so the profiler's Security panel keeps working;
- argument 1 — the strategy your application configured, used as the default;
- argument 2 — the per-attribute strategies, the platform's only addition.

## Rules and limits

- **Single attributes only.** A decision carrying several attributes at once — an `access_control`
  rule listing roles, say — uses the default strategy. Picking a strategy from one member of a set
  would be arbitrary.
- **String attributes only.** Expressions and other objects cannot key the map and fall back to the
  default.
- **`allow_if_all_abstain` is Symfony's default (`false`)** for per-attribute strategies, whatever
  you configured globally. An attribute nobody votes on is therefore refused; give it a voter that
  always votes if you need otherwise.
- **The platform's own attributes are always merged in**, and win over an entry with the same name.
  `TENANT_CREATE: unanimous` cannot be dropped or downgraded by configuration, because the platform
  guarantees that a refusal from your voter counts.
- **Bringing your own manager turns this off, loudly.** If your application sets
  `security.access_decision_manager.service`, the platform throws at compile time rather than let
  these decisions quietly revert to your global strategy. Empty the `strategies` map to take that
  responsibility on yourself.

## See also

- [Authentication & Security](./index.md) — the default form-login stack.
- [Multi-Tenancy](../multi-tenancy/index.md#deciding-who-may-create-one) — `TENANT_CREATE`, the
  platform's own use of this.
- [Symfony: How to Use Voters to Check User Permissions](https://symfony.com/doc/current/security/voters.html)
