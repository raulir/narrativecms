# Subscription module

Reusable **subscription catalogue + pricing UI** on top of the **shop** product tree and **shop currencies**, with checkout via a **provider** (Stripe).

## Catalogue shape

| Shop entity | Role |
|-------------|------|
| **Category** `type=subscription` | One pricing catalogue |
| **Subcategory** | Plan name (Basic, Premium) |
| **Product** | One offer: free / month / year |
| **Currency** | `shop/currency` — heading (3-letter code), sign, rate |

## Product prices

- **`price`** — in shop **default currency**.
- **`prices` repeater** — optional rows: `currency_id`, `price`, **`stripe_price_id`**.
- Missing currency row → display = `price × currency.rate` (rounded 2 dp).

Resolver: `shop_model::get_product_price_in_currency($product, $currency_id)` → `{ price, formatted, stripe_price_id, … }`.

## Subscription product fields

`billing_interval` (`none` / `month` / `year`), `period_months`, `featured`, `features` (HTML).

## Settings (`subscription/subscription`)

Admin **Shop → Subscription settings**. Shared labels (monthly/yearly, CTA, errors, manage messages). Merged into pricing + manage via `merge_subscription_pricing_settings()`.

## Panel `subscription/pricing` — card model

Instance fields: **category**, **currencies**, **heading** only. Labels from settings.

Cards are **pre-built combinations**:

| Card type | Rule |
|-----------|------|
| Free | `billing_interval=none` and price 0 → **one** card, always visible |
| Paid | each product × each panel currency → **one** card |

Filters (period + currency) only show/hide cards. Free CTA → CMS **Free CTA link**. Paid CTA → login intent or checkout provider.

## Panel `subscription/manage` — My subscription

Instance: **category**, **currencies**, **heading**. Settings: labels + **My subscription page (fallback)** link.

### The My subscription address

One function gives it: `subscription_model::manage_page_url()`, the visible page with a `subscription/manage` block, else the setting **My subscription page (fallback)** (`manage_page_link`), else `''`. Every caller uses it:

- Stripe Checkout success and cancel (`get_checkout_return_urls`; cancel goes back to the page the user came from after a login, or to the Stripe module's own **Cancel link** setting when that is set);
- the card-update portal return (`action_update_payment_method`);
- "already subscribed" in `checkout_start`;
- the browser hand-off from the Android app (`browser_enter_path`: a missing or bad path goes there);
- the watch's `data-manage_url`, and `data-page_path` of My subscription.

The page is found by itself, so the setting matters only on a site without such a page. (It pointed at Login on live until 2026-11-15, and every website payment then returned to the login page.)

Stripe's **cancel** address carries `subscription_checkout=cancel` (also after `app_web_rebase` into the web app). The page it returns to ends its paid-status watch (`pricing.js` `pricing_watch_resume`) instead of asking for 30 minutes.

The web app's success page **Web app: checkout success page** (`app_success_link`) is a separate, public page on the website (the tab Stripe returns to is not signed in), not My subscription.

| View | UI |
|------|-----|
| `login` | Message + log in |
| `basic` | Status (Basic) + action area **Upgrade** — month+year cards, currency switcher |
| `basic`, Google Play on hold or paused | The plan, a notice ("on hold because the payment did not go through …" / "paused …") and **Manage in Google Play**. No Purchase cards: the subscription is not over. |
| `premium` | Status (plan + renews/ends) + **who bills** ("Billed by card (Stripe)" / "Billed through Google Play") + action area **Change** (when allowed) + **settings card** (auto-renew On/Off). **No currency switcher** — uses `meta.subscription.currency_id` |
| `premium`, Google Play | No change-plan cards and no card update: Play changes plan and payment on its own page, at its own prices. One **Manage in Google Play** link instead (`play_manage_url`; the device browser from the Android app, a new tab elsewhere). Auto-renew **Off** works in place; **On** opens Play. |
| `premium` (elevated) | Status only (plan title from product). No auto-renew, payment portal, or change-plan |

Rules: **one subscription per user**. Month→year anytime (Stripe proration). Year→month only in last 30 days of period. Auto-renew = `cancel_at_period_end` inverted.

### Elevated plan (manual, no payment)

Admin **Site users** → field **Elevated plan** (FK `shop/product`, optional). Added by subscription extend of `user/user`.

| | |
|--|--|
| Source of truth | User panel field `elevated_plan` (not meta) |
| Access | `user_has_active_subscription()` = paid Stripe row **or** elevated product exists |
| Precedence | Active Stripe row wins; else elevated synthetic sub (`source=elevated`) |
| Clear | Set FK empty in admin |
| Use | Testing / comps, or when Stripe no longer has the subscription |

Manage-page Stripe sync (and webhooks):

| Stripe | CMS |
|--------|-----|
| `canceled` / `incomplete_expired` / `customer.subscription.deleted` | Archive paid row. **No** elevated grant (natural end). |
| `resource_missing` / 404 (id in CMS, Stripe cannot confirm) | Same product → `elevated_plan`, archive paid row. |
| Network / other API error | No change. |

Checkout is blocked only for **paid** Stripe (`user_has_paid_subscription`); elevated users can buy again.

Manage UI for elevated: a card-sized banner (plan title + “administratively set”, 35.0rem, centred) then the purchase block. The granted plan is the user's plan, so no "Basic" line shows under it.

Stripe webhook and `meta.subscription` stay separate so sync cannot wipe an elevated grant.

Ajax: `sync_subscription` (on load), `set_auto_renew`, `change_plan`. Checkout success returns to My subscription (§ The My subscription address).

**Who bills** (`subscription_model::subscription_billing`): a row with a Stripe id is billed by Stripe, a row without one is a Google Play row (when the google module is on). `get_effective_subscription` sets `source` to `stripe` or `google` for a paid subscription, `elevated` for a grant. Texts: Subscription settings `manage_billed_card`, `manage_billed_play`, `manage_play_help`, `manage_play_cta`, `manage_play_on_hold`, `manage_play_paused`.

**On hold / paused:** the google module stores Play's `SUBSCRIPTION_STATE_ON_HOLD` / `_PAUSED` as row status `on_hold` / `paused` (inactive, still current), not `canceled`.

**Music user menu:** User header → **Subscription** link → this page.

## Checkout provider (`subscription_checkout`)

Mirrors **shop** cart’s `shop_checkout` pattern:

| Piece | Role |
|-------|------|
| Shop settings field **`subscription_checkout`** | Added by subscription extend of `shop/shop` |
| Stripe `provides.subscription_checkout` | `stripe/subscription_checkout` |
| FE entry | Always **`subscription/checkout_start`** (domain picks provider) |
| Guest Purchase | Session intent → login (subscription extends user login/register/google) → return → auto-start |
| Logged-in Purchase | `checkout_start` → provider → Stripe Checkout Session |

### Guest purchase / login intent

1. Guest CTA → ajax `set_checkout_intent` stores `product_id`, `currency_id`, and a **same-site path** `resume_url` (e.g. `/pricing/`) in `$_SESSION['subscription_checkout_intent']` (TTL 1h).
2. Redirect to login. `subscription` extends `user/login`, `user/register`, `user/auth_google` and sets `success_url` from `get_post_auth_redirect_url()` when intent is present.
3. After auth:
   - **No active subscription** → resume path (pricing) → `data-auto_checkout` → `checkout_start`.
   - **Already subscribed** (`meta.subscription.active`) → clear intent → normal user landing (`get_user_redirect_url()`, e.g. `/start/`) — no payment.
4. Resume URLs are sanitized: path-only or absolute same-host only; external hosts rejected (no open redirect). Full `https://…` values from older clients are normalized to path.
5. `checkout_start` also refuses checkout when already subscribed (returns `already_subscribed` + redirect).

Stripe Dashboard steps: [`../stripe/docs/stripe_checklist.md`](../stripe/docs/stripe_checklist.md).

The Android app can also pay with Google Play. That path is the `google` module, called from the app. Shop settings still have one checkout provider, Stripe. See [`../../google/docs/google.md`](../../google/docs/google.md).

### The installed web app

The web app (`scoretutor.com/app/`, pages from the in-app backend `app.scoretutor.com`) has `_cms_inapp` like the Android app, but no native bridge (`window.AppSplash`). Only the bridge decides the app path: the payment choice screen of the google module (CMS hook `payment_choice`, google `google.md` § App choice). Without it, `pricing.js` pays as the website does:

- **Purchase** posts `subscription/checkout_start` with `app_root_url` = `location.origin + cms_app_root()` (e.g. `https://scoretutor.com/app/`), then goes to the Stripe Checkout address in the same window. A path answer (already subscribed) opens through `cms_position_goto`, so it stays in the web app.
- **Return addresses:** the backend's own addresses are on `app.scoretutor.com`, outside the web app. So `subscription_model::app_web_root()` accepts the posted root, and `app_web_rebase()` moves Stripe's success and cancel URLs (`get_checkout_return_urls($app_root)`) and the card-update portal return (`manage.js` → `action_update_payment_method`) to that root, e.g. `https://scoretutor.com/app/pricing/`. The shell reopens that page there (deep link).
- **Accepted roots:** the root is accepted only when its origin is in config `app_web_origins` (array). Without that key, the origin is `base_site` without its leading `app.` / `app_` label (`https://app.scoretutor.com` → `https://scoretutor.com`, `http://app_music.localhost` → `http://music.localhost`). The path must be plain segments ending in `/`. Any other root is ignored, and the addresses stay on the backend (e.g. `musicwrap.localhost`, unless listed).
- Change plan and auto-renew work in place. A Play-bought subscription still answers with the Play manage link wherever it is managed.
- **Success page:** the tab Stripe returns to is usually not the app window: a browser tab, a custom tab, or the external browser. It is not signed in, because the app's sign-in is its own (`app_session`). So with a web app root, success goes to the setting **Web app: checkout success page** (`app_success_link`) on the web app's origin, the website, e.g. `https://scoretutor.com/subscription-change/`. That is a public page (music: panel `music/subscription_change`, guest header and footer). It says the change went through, links back to the app, and offers the login CTA. Without the setting, success is the rebased manage link, as above.

### The plans page watches the checkout

A payment may be written after the user is back: in the app, Stripe runs outside the app window; on the website, Stripe returns to My subscription at once, but the plan is written by the webhook, which may come later. So `pricing.js` watches (`pricing_watch_paid`) on all three platforms:

- **Starts:**
  - on the website and in the web app, on the Stripe address (the start time is kept in `localStorage` `subscription_checkout_started`, so the page Stripe returns to watches on);
  - in the Android app, when Continue on the payment choice screen starts Google Play or Stripe.
- **Asks:** `subscription/pricing` `cms_do=paid_status` → `{ok, paid}` (`user_has_paid_subscription`, a database read). It asks at once on a page that picks the watch up (back from Stripe), then every 5 s for the first 2 minutes, then every 15 s, and at once when the page is visible again. After 30 minutes the start time is dropped.
- **Already paid on return:** a My subscription page that already shows a paid plan only says "Payment received"; nothing is asked.
  - The webhook writes the payment, so a Stripe sync would not see it sooner: it needs `stripe_subscription_id`.
- **Paid:**
  - a notice with the text of setting **App: payment received** (`paid_message`), on `body` so the page load under it keeps it; it closes itself after 5 s. It is the site's popup when a module sets the CMS hook `popup` (music does), else the module's own bar `.pricing_paid_notice`. Errors (`pricing_notify`, `manage_notify`) go the same way, never to `alert`;
  - **My subscription** opens, because it shows the new plan. Its path is `data-manage_url` on both containers (`subscription_model::manage_page_url()`): the visible page with a `subscription/manage` block, else the `manage_page_link` setting.
  - When My subscription is already the page shown, it loads again: in the app `cms_position_goto(…, {history: 'none', reload: true})`, on the website a reload. The website says "Payment received" on the new page (`sessionStorage` `subscription_paid_notice`), also when My subscription's own sync loads it once more (`manage.js` `manage_reload_page` → `pricing_paid_carry`).
- **Ends:**
  - when paid;
  - after 30 min;
  - when the page changes. The start time is kept in `localStorage` `subscription_checkout_started`, so the plans page or My subscription (both are `.pricing_container`) picks the watch up again within the 30 min.
- **Re-runs:** an app page can run `pricing.js` again, so `pricing_watch` keeps `window.pricing_watch` instead of resetting it.
- **My subscription and `pricing.js`:** the manage panel loads `pricing.js` too, for Purchase and the watch.
  - `pricing_init` leaves `.pricing_manage` to `manage.js`, which calls `pricing_purchase_click` and `pricing_watch_resume`. Before, both bound Purchase when both files were on the page, so one click started checkout twice.
  - `pricing_init` still resumes the watch there, whichever file runs last.
- **The success page** `/subscription-change/` makes no claim about the plan. Whoever lands on it is told to check the subscription page in the app or to log in.

Check:
- `grok/tmp/pwa_return_check.php` (CLI);
- `grok/tmp/pwa_payment_check.js` (Playwright, `MUSIC_TEST_PASS`: checkout, returns, the watch);
- `grok/tmp/subscription_change_check.js` (the public page, desktop and phone).

Architecture follow-ups: [`stripe_vs_subscription_issues.md`](stripe_vs_subscription_issues.md).

### Storage after payment (webhook / manage)

Current entitlement lives in table **`subscription_subscription`** (schema JSON in this module), not user meta.

| Column | API key (helpers) | Meaning |
|--------|-------------------|---------|
| `user_id` | — | user panel id |
| `status` | `status` | Stripe status (`active`, `canceled`, …) / elevated |
| `active` | `active` | 0/1 entitlement |
| `billing_interval` | `interval` | month / year |
| `ends` | `ends` | period end ISO |
| `price_id` | `price_id` | Stripe price id |
| `product_id` | `product_id` | CMS product id |
| `plan` | `plan` | Optional plan title |
| `stripe_subscription_id` | `stripe_subscription_id` | Stripe sub id |
| `stripe_customer_id` | `stripe_customer_id` | Stripe customer |
| `currency_id` | `currency_id` | locked manage currency |
| `cancel_at_period_end` | `cancel_at_period_end` | auto-renew inverted |
| `archived` | — | `0` = current row; `1` = history when Stripe sub id changes |

Helpers still return the same array shape: `get_user_subscription()`, `merge_user_subscription()`, `user_has_active_subscription()`.

One-time migrate from old `meta.subscription`: `php grok/migrate_subscription_meta_to_table.php` (optional `--clear-meta`).

`merge_user_subscription` and `_save_identified_subscription`, the only writers of this table, write nothing for a user that no longer exists. So a Stripe or Play event that arrives after an account deletion leaves no rows behind.

## Account deletion

**Rule (intentional, a legal need):** a user can always delete the account. Deletion never waits on, or is refused because of, Stripe or Google Play.

ScoreTutor first tries to set every paid subscription not to renew. Then it deletes the account in every case. Then it tells the user what happened. The payment provider keeps its own subscription and data: if the renewal could not be stopped, or the user wants their payment data removed, they do that with Stripe or Google Play themselves.

**Flow:**

1. **The page:** `music/settings_account` (My account) shows the warning before confirming: deletion goes ahead even if the subscription can't be stopped, and the payment provider keeps its own data.
2. **The request:** `music/account_delete` (`cms_do=delete`, signed-in user only) runs `subscription_model::stop_renewal_for_user`, then `music_user_model::delete_user_data`, then clears the session.
3. **`stop_renewal_for_user`** returns `result` and `providers`:
   - **Stripe:** every distinct `stripe_subscription_id` of the user's rows with `active = 1`, archived rows included (a Stripe subscription bought while Play was the provider is stored archived and still bills). Each goes through `stripe_subscription_model::stop_renewal_by_id`:
     - already ended or gone at Stripe → nothing to stop;
     - already set to end → stopped;
     - otherwise `cancel_at_period_end` is set;
     - a Stripe error → failed. It is not read as "no subscription".
   - **Google Play:** a stored purchase token (`google_model::META_PLAY`) → `google_model::cancel_renewal_for_user`.
   - **`result`:** `failed` if any attempt failed, else `stopped` if any subscription was set to end, else `none`.
   - **`providers`:** those that held a subscription (`stripe`, `google`).

   Provider errors go to the log, never to the user.
4. **The reply** is `{ok, renewal, providers}` (definition `ajax.output`). The page then shows the result in the site's popup (a system popup, since the account is gone; the confirm before it is a content popup):
   - the result line;
   - one line per provider: Stripe (cancel or remove card details through the receipt email, or contact us), or Google Play (link to Google Play subscriptions);
   - an always-shown line that the providers keep their own payment records.

   All texts are block fields of `music/settings_account`. **OK** goes home on the website, or to the app login (`logout_return_to_app`).
5. **After deletion:**
   - later webhook events write no rows (above);
   - a pending email confirmation link answers `invalid_token`.

**Checks** (local):
- `grok/tmp/account_delete_check.php` (CLI): none, missing at Stripe, Stripe error, a Stripe test-mode subscription, Play unreadable, webhook after delete, confirmation link after delete;
- `grok/tmp/account_delete_ui_check.js site|pwa|emu`: registration with the dev test email, then deletion on the website (Stripe test subscription → stopped), in the web app (no subscription) and in the Android emulator (Play → failed).

## Music theme

`music` extends `subscription/pricing` with SCSS only.

## Later

See [`todo.md`](todo.md).
