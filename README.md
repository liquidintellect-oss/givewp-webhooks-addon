# GiveWP Webhooks

A GiveWP add-on plugin that sends a signed outbound webhook to one or more
configured URLs whenever a donation completes.

## Requirements

- WordPress with [GiveWP](https://givewp.com/) active, version `2.20.0` or higher.

## Installation

1. Copy this directory into `wp-content/plugins/givewp-webhooks-addon`.
2. Activate **GiveWP Webhooks** from the Plugins screen. If GiveWP isn't active
   or is too old, the plugin will show an admin notice and deactivate itself.
3. Go to **Donations → Webhooks** in wp-admin to configure one or more webhook
   URLs, enable delivery, and view your signing secret.

## How it works

- Hooks into GiveWP's `give_complete_donation` action, which fires exactly
  once when a donation transitions to "complete" (covers one-time donations,
  the first donation of a subscription, and renewals).
- Loads the donation via `Give\Donations\Models\Donation::find()` and builds a
  JSON payload (donation id, amount, currency, donor, gateway, form, status,
  mode, timestamps).
- Delivery is scheduled through WP-Cron (fires within seconds) so the donor's
  checkout request is never blocked on an outbound HTTP call.
- Each request is signed with HMAC-SHA256 over `"{timestamp}.{raw JSON body}"`
  using a per-site secret, sent in the `X-GiveWP-Webhook-Signature` header
  (format `sha256=<hex>`), alongside `X-GiveWP-Webhook-Event` and
  `X-GiveWP-Webhook-Timestamp`.
- Failed deliveries (non-2xx response or request error) are retried with
  exponential backoff (1m, 5m, 30m, 2h) up to 5 attempts total.
- Every attempt is logged to a `{$wpdb->prefix}give_webhook_logs` table and
  viewable on the settings page; logs older than 30 days are pruned daily.

## Verifying signatures (receiver side, pseudocode)

```php
$expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
$valid = hash_equals($expected, str_replace('sha256=', '', $signatureHeader));
```

## Uninstall

Deleting the plugin (not just deactivating) removes its settings option and
the delivery log table.

## Development

Build, lint, and test are driven by Gradle (wraps Composer/PHPCS/PHPUnit):

```bash
./gradlew build       # composer install, phpcs, phpunit, then build the dist zip/tar
./gradlew phpcs        # WordPress Coding Standards check
./gradlew phpcbf       # auto-fix coding standard violations
./gradlew phpunit      # run the unit test suite (tests/Unit, results in build/test-results)
```

Tests run against `WP_Mock` plus lightweight stand-ins for GiveWP's `Donation`
model and `$wpdb` (see `tests/Fixtures/`), so the suite doesn't require a real
WordPress + GiveWP installation.

CI (`.circleci/config.yml`) runs `./gradlew build` (phpcs + phpunit as quality
gates via the `check` task) on every push, and on `main`, after manual
approval, packages and publishes a GitHub release from the resulting
distribution zip.
