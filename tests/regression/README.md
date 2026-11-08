# Checkout result-page regression

Run from repository root with PHP and the bundled Twig dependencies:

```sh
php tests/regression/checkout_result_pages.php
```

Prepared cloud environment alternative:

```sh
docker exec -u 1000 -w /var/www/html opencartvn-web php tests/regression/checkout_result_pages.php
```

The standalone runner uses real result-page controllers, Registry/Controller base classes and Twig templates. Services, identity and order persistence are fakes/spies. It does not connect to a database, send email or call gateways. It exits nonzero on any failed case and uses explicit checks rather than PHP assertions (which may be disabled).

Fifteen cases cover repeated success/failure/terms requests with guest/member and pending/no-pending sessions, legacy marketplace success, and unauthenticated checkout 401/login behavior. A pending-session visit reproduced an order write before the PAY-02 fix. Existing order history links remain covered; historical invoice retrieval and full startup/provider/DB integration are outside this suite. These tests do not certify PAY-01, CSRF protection or idempotency.

## Payment completion boundary (P0-B)

```sh
php -d disable_functions=curl_init,curl_exec tests/regression/payment_confirmation.php
set -o pipefail
php tests/regression/render_payment_templates.php | node tests/regression/payment_template_callbacks.js
```

Use `docker exec -u 1000 -w /var/www/html opencartvn-web php ...` for PHP in the prepared environment. Keep Node on the host for the rendered-template pipe. Provider tests refuse to run with cURL enabled; transport fixtures cannot accidentally send real charge requests. They exercise the real gateway validators/controller with order spies, not provider sandbox acceptance. The JS runner evaluates actual Twig output with fake jQuery/browser/provider services and captures redirects for regular/3DS confirm callbacks.

No migration, real charge, mail delivery or concurrent idempotency guarantee is part of these tests. Test keys/IDs are synthetic fixtures. See docs/improvements/P0-B-payment-confirmation.md for compatibility limitations (including PayPal unavailable and manual capture).

The provider/controller suite now includes 14 malformed-proof cases (65 total): Stripe amount fields must be JSON integers; both transaction IDs must be nonempty strings without whitespace/control characters; currencies must be strings; Braintree monetary responses must use decimal notation within the supported precision. These are provider contract robustness checks, not a claim of live gateway interception. Existing successful USD/VND and Braintree fixtures must still pass.

## Frontend CSRF (P0-C)

```sh
php tests/regression/frontend_csrf.php
node tests/regression/frontend_csrf_client.js
python3 tests/regression/frontend_csrf_browser.py
```

PHP uses real startup/identity/router/controllers with in-memory spies, including valid/invalid tokens, destructive actions, method aliases and PIN/IP/read navigation. Node validates the shared-policy helper. Optional browser regression requires Python Playwright and `/usr/bin/chromium` (available in the prepared cloud environment); it runs actual bundled jQuery/CSRF JS with every HTTP request intercepted, never against app data. As with the other PHP runners, use `docker exec -u 1000 -w /var/www/html opencartvn-web php ...` if PHP is only installed in the development container.
