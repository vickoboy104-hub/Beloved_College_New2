# Finance and Payments Workflows

This release restores fee setup, invoice generation, finance-office collections, family checkout, receipts, reconciliation reports and verified online settlement.

## Finance permissions

- Super Admin has all finance permissions.
- Admin can operate Finance and configure gateways.
- Principal can supervise Finance and record payments, but cannot configure gateways.
- Accountant can operate Finance and record payments, but cannot configure gateways.
- Student and Parent accounts can pay only invoices belonging to their own student context.

## Fee catalogue and invoices

Finance staff can:

- create class, term, session or general fee items
- mark fees mandatory for automatic student invoice synchronization
- prevent duplicate fee items within the same scope
- generate one invoice for an individual student
- generate invoices for a complete class
- override amount or due date for a billing run
- skip existing student and fee-item invoice combinations
- delete only unused fee items

Invoice history cannot be removed through fee-item deletion.

## Manual finance-office payments

Manual entry is explicitly recorded as the `manual` provider. Staff select an actual office channel such as:

- cash
- bank transfer
- POS
- cheque
- another documented school-office channel

Manual payments receive a permanent receipt number, recorder identity and timestamp. Part payments and overpayments remain visible. A manual entry cannot impersonate Paystack, Flutterwave, Monnify or PalmPay verification.

## Student and Parent payment portal

Families can:

- switch between linked children
- review billed, paid and outstanding totals
- select one or several unpaid invoices
- continue only with enabled and completely configured gateways
- review completed payments
- open permanent authorized receipts

The school application never collects or stores card details. Checkout is completed on the provider-hosted payment page.

## Verified gateways

Supported automatic verification adapters:

- Paystack
- Flutterwave
- Monnify

PalmPay remains unavailable for automatic checkout and settlement until the school supplies a merchant-specific server verification and webhook signature contract. Saving PalmPay merchant fields does not make it available to families.

## Callback safety

A browser callback never marks a payment paid by itself. The backend requests authoritative verification from the provider and compares:

- successful provider status
- exact internal reference
- expected currency
- expected amount, with provider-specific lower-denomination handling

Mismatched verification marks the attempt failed and does not update invoice value.

## Webhook safety

Webhook endpoints are the only CSRF-exempt finance routes. Each provider webhook requires its configured signature rules before processing.

Accepted notifications are written to `payment_events` with:

- provider
- unique event ID
- event type
- payment reference
- signature fingerprint
- raw payload
- processing status
- linked payment
- error and processing timestamps

The provider transaction is reverified through the server API before settlement. The unique provider/event constraint and transactional payment lock make repeated delivery idempotent.

## Settlement and grouped allocations

All successful callbacks and webhooks use the same settlement service.

Settlement:

1. locks the payment row
2. exits safely when already paid
3. records the gateway reference, channel and paid time
4. generates a permanent receipt number
5. preserves initialization and verification payloads
6. synchronizes one invoice or allocates a grouped payment

Grouped payments allocate by invoice due date and never exceed an invoice balance.

## Finance Office workspaces

The responsive Classic and Dark interface includes:

- finance overview
- fee catalogue
- invoice generation
- manual payment entry
- student balances
- class bills
- payment provider and channel summaries
- daily collections
- recent payments
- overpayment tracking
- invoice payment progression
- encrypted gateway settings
- printable class fee lists
- permanent printable receipts

## Gateway settings

Secrets are stored using Laravel encryption and are blanked in administrative forms. Blank secret submissions preserve existing configured values.

Configured values include:

- enabled gateway list
- fallback finance email
- Paystack keys and webhook secret
- Flutterwave keys, webhook secret hash and payment options
- Monnify keys, contract code, environment and payment methods
- PalmPay merchant fields retained for future merchant-specific integration

## Automated verification

The release tests:

- duplicate fee prevention
- class invoice generation and duplicate skipping
- part payment and overpayment accounting
- receipt creation
- authoritative Paystack callback verification
- browser-status rejection
- grouped invoice allocation
- signed Paystack webhook settlement
- webhook replay idempotency
- invalid signature rejection
- encrypted gateway secrets and blank-value preservation
- Admin, Principal and Accountant Finance access
- gateway-settings role boundary
- Student and linked Parent payment portal access
