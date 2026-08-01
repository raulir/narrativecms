# Stripe backlog

Provider-side work for ScoreTutor subscriptions. Domain UX lives in [`subscription/docs/todo.md`](../../subscription/docs/todo.md).

## User subscription management (provider)

Support the **manage subscription** page actions via Stripe (not Dashboard-only):

- [x] **Upgrade monthly → yearly** — `change_subscription_price_for_user` + proration; one item / one sub per user
- [x] **Cancel / resume auto-renew** — `set_auto_renew_for_user` → `cancel_at_period_end`
- [x] **Sync** — `sync_user_subscription` / retrieve for user
- [ ] **Customer Portal** alternative — hosted UI if we prefer it later
- [x] Period end from item-level `current_period_end` after upgrades/cancels

## Other

- [ ] Live mode keys + Price ids checklist (see [`stripe_checklist.md`](stripe_checklist.md))
- [ ] Harden webhook secret (reject unsigned in non-dev)
