# DEC-0012: Flutterwave and Paddle Payments

- **Status**: PROPOSED
- **Date**: 2026-10-16

## Context

Customers buy plans (monthly or yearly) and top-up credit. Flutterwave and Paddle collect the money. A
duplicate webhook, a retried request, or a double click must never grant credit twice.

## Decision

- **Paddle** is the merchant of record for international card payments. It creates the subscription and
  its renewals. Each purchase is a transaction opened as a hosted checkout.
- **Flutterwave** collects card and local payments. A plan is a Flutterwave payment plan. Its first charge
  starts the subscription, and later charges arrive as webhooks.
- **Keys** are stored encrypted in the provider key store, set on the Payments page, never on disk or in
  the database in plain text.

## Verified against the providers' docs

- Flutterwave: `POST /v3/payments` returns `data.link`. Verify with `GET /v3/transactions/{id}/verify`.
  Webhooks carry the secret hash in `verif-hash`. Payment plans use `POST /v3/payment-plans`. Cancel uses
  `PUT /v3/subscriptions/{id}/cancel`.
- Paddle: `POST /transactions` with `items` and `custom_data`, returning `data.checkout.url`. Webhooks carry
  `Paddle-Signature: ts=…;h1=…`, an HMAC-SHA256 of `ts:rawBody`. Cancel uses
  `POST /subscriptions/{id}/cancel` with `effective_from`.

## Idempotency

1. **One checkout per purchase.** The purchase row is locked. A stored link is returned as it was.
   Flutterwave's `tx_ref` is the purchase id, which Flutterwave also rejects if reused.
2. **Every webhook event is recorded once.** `payment_events` has a unique (provider, event id). The record
   and the change it causes commit together. A concurrent duplicate loses the unique race and rolls back.
   Flutterwave events are keyed by transaction id. Paddle events by `event_id`.
3. **A renewal is a purchase with a unique provider reference** (`flw:<id>` or the Paddle transaction id).
   A second event for the same charge finds the purchase and does nothing.
4. **Credit is granted once.** Fulfilment moves a pending purchase to paid under a row lock. A paid
   purchase is a no-op.
5. **Amounts are checked before anything is granted.** Flutterwave's amount comes from the verify call,
   not the webhook. Paddle uses the transaction subtotal, so tax does not block a valid payment.
   A mismatch or non-USD currency fails the purchase.
6. **Signatures are checked first.** A bad or stale signature is refused before any work. Paddle's
   timestamp tolerance is 300 seconds.
7. **Retries are asked for when they should be.** Flutterwave gets a 503 if its verify call fails, so
   it retries. A signed-but-refused event gets 200, so it is not retried forever.

## Consequences

- Live payments are not tested. No provider keys have been provided. The tests use recorded response
  shapes from the docs.
- Flutterwave auto-renew cannot be cancelled from here yet. Its subscription id is not in the events,
  so the cancel response tells the customer to cancel in Flutterwave too. A verified lookup is needed.
- Flutterwave plans are created with a fixed duration (120 monthly cycles, 10 yearly) so they run until
  cancelled. Confirm this works with Flutterwave's sandbox.
- Paddle prices are created in the Paddle dashboard and their ids pasted into the plan and top-up editors.
- The Paddle API base is `https://api.paddle.com`. Set `PADDLE_API_BASE` to the sandbox for testing.
