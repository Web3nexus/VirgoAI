# DEC-0010: Wallet, Pricing, and Owner Console

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Author**: Claude Code

## Context

Users run paid models. The owner needs to set a margin, see what was paid, what providers cost, and
what was kept, manage users, and reach the console through a private route. Nothing of this existed.

## Decision

1. **Money** is stored as integer micro-dollars (1 USD = 1,000,000). No floats in the ledger.
2. **Wallet**: each user has a prepaid balance and a ledger. Only `Wallet` changes a balance, always
   inside a transaction that locks the user row.
3. **Run flow**: the price is quoted before the provider is called, and the charge is reserved in the
   same transaction that creates the run. A run that fails is refunded once. A text run settles to
   the tokens the provider reports. A queued render is settled or refunded by `GenerationCompleter`.
4. **Pricing**:
   - Provider cost for fal comes from `GET /v1/models/pricing` (read with the owner's key).
   - Provider cost for Anthropic comes from the public pricing page, copied into `config/pricing.php`
     (there is no Anthropic pricing API). Haiku 5.5 is not seeded: its row was not read clearly.
   - User price = provider cost × (100 + margin) / 100, rounded up. Default margin 40%, which keeps
     28.57% of each price. The owner changes it in the console.
   - A model with no price, or a fal unit this app cannot bill, is refused, not guessed.
   - A price the owner types in (`source = admin`) is never overwritten by a sync.
5. **Roles and access**: `role` is `user` or `super_admin`. Suspended accounts lose access at once.
   Admin routes return 404 to non-admins. The console sign-in at `/securegate` gives the same
   "Invalid credentials" answer to a wrong password, a non-admin, and an unknown email.
6. **Owner account**: created from the command line with `php artisan admin:create <email>`, which
   prompts for the password. No password is stored in the repository or in chat.
7. **Audit**: every admin action (login, role or suspension change, credit change, margin change,
   price change, price refresh) writes a row to `admin_audit_logs`.

## Consequences

- No payment processor is connected. Credit is added by the owner in the console. Taking real
  payments needs a provider decision (Stripe or similar) and is a separate task.
- Workflow model stages do not bill yet. Only tool runs bill. The default workflow templates use the
  test handler, so no provider spend happens through workflows today.
- Per-quality image pricing: fal returns one unit price per endpoint. GPT Image quality and size
  differences are not priced separately. The owner can override the price by hand.
- Seedance per-second pricing assumes fal's `second` unit. This is verified only by the live pricing
  response. Until a live sync runs, Seedance refuses to bill rather than guess.
- Margin is shown as the owner's cut of revenue (28.57% at 40%). Revenue minus provider cost is
  the earnings figure.
