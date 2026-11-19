# Pixelforge Studio

A Laravel, Vue 3 and Inertia app for generating images with Pixelforge. Users can estimate a price, submit a generation, follow its status, and review their history. Credits and purchases are simulated. An admin report compares local provider costs with Pixelforge usage.

## Run locally

Requirements: PHP 8.3+ with the extensions required by Laravel, Composer, Node 22, npm, and MySQL (or SQLite). The default `.env.example` uses a local MySQL database named `pixelforge` with user `root` and an empty password. Create the database first, or configure another database in `.env`.

```sh
composer install
cp .env.example .env
php artisan key:generate
# Configure the database and Pixelforge settings in .env first.
php artisan migrate
npm install
npm run build
composer run dev
```

Open the local URL, register, and use **Credits** to add a fake balance before generating. The development command runs the app, queue listener, logs and Vite. The database queue must have a worker running for submissions to reach Pixelforge. Run `php artisan schedule:work` separately for the reconciliation fallback.

Set `PIXELFORGE_BASE_URL` to `https://pixelforge.promptedit.dev/v1`, `PIXELFORGE_API_KEY` to your **local** key, `PIXELFORGE_WEBHOOK_SECRET` to that key's signing secret, and `PIXELFORGE_MARKUP_PERCENT` to `25`. Keep `.env` private. Use a different key and secret in each environment: usage and webhook configuration belong to the key. `php artisan pixelforge:check` checks pricing and authenticated usage.

For local webhooks, expose the app through a public HTTPS tunnel and configure the exact URL `https://<your-host>/webhooks/pixelforge` in the Pixelforge dashboard. Set the matching signing secret in `.env` and send a dashboard ping. The endpoint must be reachable without authentication, CSRF protection or redirects. Deployments also need a queue worker, the Laravel scheduler, database migrations, and their own key and webhook settings.

## Pricing and credits

All stored money is integer nanodollars (one billionth of a dollar). Provider cost is the sum of each token count multiplied by its rate in nanodollars per token. Rates are decimal dollars per million tokens; the parser accepts at most three decimal places, so the conversion is exact. This preserves the nine decimal places in Pixelforge usage, including half-microdollar reasoning token costs. For example, 15 text, 765 image, 420 output and 233 reasoning tokens at the documented rates cost $0.027437500.

The actual charge adds 25% and rounds up to the next nanodollar if necessary. The provider cost is never rounded. Estimates use the cached pricing endpoint's token tables and rates, Unicode character counts, the text-token variance and the reasoning-token range. The displayed range includes markup; actual usage and the generation's own rate snapshot determine the final charge. Pricing may change between estimate and acceptance, and actual usage is known only on success.

A ledger records fake purchases and successful generation charges. Available balance is the ledger balance less the maximum estimates reserved by pending, queued and processing generations. The user row is locked when checking and reserving funds. Actual charges can exceed estimates, and a late success after an unconfirmed submission can leave a negative balance. Failed generations have zero cost and no charge.

## Generation lifecycle

A local pending row is created before the queued submission job runs. Its ULID is both the Pixelforge idempotency key and `metadata.local_id`, so retries use the same request and a webhook can find the row before the POST response arrives. Replayed POST responses, webhooks and polling all pass through the same update action.

Status rank prevents older updates from overwriting later ones; succeeded and failed provider states are terminal. A locally failed submission without a provider ID is the exception: a later provider update can resolve an ambiguous submission. Updates lock the user and generation rows in a transaction. A unique webhook event ID, the terminal-state guard, and a unique generation/transaction type constraint protect against duplicate charges.

The webhook verifies HMAC-SHA256 over the timestamp and **raw** request body, checks the timestamp tolerance and uses a constant-time comparison. Event insertion and application share a transaction, so an error rolls back the event and permits redelivery. Duplicate events and signed pings receive a 204; unknown generations are logged and acknowledged.

Temporary submission errors and connection failures retry up to eight attempts with increasing delays, jitter and `Retry-After` as a minimum. Daily limits and permanent request errors are not retried. A connection failure can leave acceptance unconfirmed; a provider webhook may later resolve it. The scheduled `pixelforge:reconcile` command checks up to 20 queued or processing rows unchanged for three minutes, stopping its batch on a rate limit or overload. It cannot discover a generation whose submission remains unconfirmed and has no provider ID. The history page polls this app while generations are active. Pixelforge image URLs expire; history shows a placeholder after expiry.

## Admin report

Set `is_admin` to true for a trusted user in the database to access `/admin/report`. The server checks this flag. The report groups successful generations by the provider's UTC completion day, showing charges, costs and profit by day and user. It compares exact nine-decimal local daily costs with `/v1/usage` for the configured key, over at most 92 inclusive days. A missing or unavailable usage response is not a match. Reconciliation requires the database to contain all generations billed to that key.

## Tests and checks

```sh
php artisan test
composer ci:check
```

Tests use SQLite in memory and fake Pixelforge HTTP responses; they do not need a provider key or network access. CI uses SQLite as well. SQLite serializes writes and does not provide MySQL's row-level `lockForUpdate` behavior, so concurrency should also be exercised on the deployment database. `composer ci:check` includes formatting, static analysis, frontend checks and the test suite.

## Before submitting

The repository is public, but deployment, live webhook delivery, production generation and production usage reconciliation have not been verified here. Deploy the app, configure the production key and webhook, worker and scheduler, run migrations, and leave a few successful `[succeed]` generations. Verify the dashboard deliveries and admin usage totals, then submit the public repository URL, deployed app URL and Pixelforge account email through the assessment form.
