# DEC-0011: Subscriptions, Credit Buckets, and Top-ups

- **Status**: PROPOSED
- **Date**: 2026-10-16

## Context

Users pay for plans (monthly or yearly) and can top up. The owner needs a public pricing table and
to see what each plan keeps after the provider budget.

## Decision

- **Plans**: three plans (Starter, Pro, Studio), each with a monthly and a yearly price. Yearly is paid
  up front, but its monthly credit still arrives each month.
- **Credit unit**: 1 credit = $1 of usage at sell price. Sell price already includes the margin.
- **Monthly credit** = the plan's monthly credit amount. A user gets the same credit per month on
  either billing period. A yearly plan is worth twelve months of credit.
- **What the owner keeps** per plan = price − provider budget, where provider budget = credit ÷
  (1 + margin). This assumes the whole credit is used, the worst case.
- **Two credit buckets**:
  - Subscription credit: granted monthly, expires at the end of the cycle, spent first.
  - Purchased credit (top-ups, grants, refunds): never expires.
- **Top-ups**: packs of credit. Subscribers pay the pack's subscriber price (a discount).
- **Detection rules** (applied hourly by `billing:renew`):
  - Entitled = status `active` and before `current_period_end`.
  - Each cycle, unused subscription credit expires and the monthly credit is granted.
  - When the period ends without a paid renewal, the plan becomes `past_due`. It grants nothing.
  - After a 3-day grace window, the plan ends and its credit expires.
  - A cancelled plan stays active until its period ends, then ends.
  - A paid renewal restores a past-due plan at once.
- **Payments**: a purchase is `pending` until paid. Only a paid purchase grants anything, and it
  grants once. A signed webhook (`X-Virgo-Signature`, HMAC-SHA256, `BILLING_WEBHOOK_SECRET`) marks
  purchases paid or failed. Until a provider is connected, an admin marks them paid.

## Consequences

- No payment processor is connected, so no real money moves. The webhook is ready for one.
- Refunds of a failed run return to purchased credit, not to the expiring bucket.
- The public page shows what each plan buys (images, video seconds, scripts), never the margin.
- Admin cannot start a plan for a user from the UI yet. The endpoint exists.
