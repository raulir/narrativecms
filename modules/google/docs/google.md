# Google Play module

Play Billing for the Android app. The website checkout provider stays Stripe. This module is not a second checkout provider in shop settings.

Enable it under CMS settings → Modules.

**Tools → Google → Auth settings** holds the web client id used by the website Google button and by the app. The Android client stays in Google Cloud.

Then set **Google Play settings**:

- Package name `com.scoretutor.app`
- Service account JSON file path (Play Console API access). The file stays off the site.
- Notification audience: the exact push URL Play’s Pub/Sub subscription uses. Empty means `{base}google/notify/` refuses every call. A call is handled only when its Google-signed bearer token names that audience.
- Notification sender (`notify_email`, since 2026-11-20): the service account the Pub/Sub push subscription authenticates as. Its token's `email` must be this. Any Google account can sign a token for any audience, so the audience alone proves nothing. Empty refuses every call: **set it before Play notifications go live.**

### Play notifications (`google/notify`)

- **Answers:** 403 when the request is not from our push subscription (`google_model::notification_refusal()`: not_configured, no_token, tokeninfo, audience, issuer, email, expired; logged at most once in 10 minutes, `log_limited`), 500 when the purchase can't be read from Play or the user can't be updated (Pub/Sub then retries), 200 otherwise, including a test notification without a purchase token. Until 2026-11-20 every answer was 200, so a failed Play read was never retried, and a refused request left no log line.
- **Access token:** Play's OAuth token is kept in `tmp/google_play_token.json` for 55 minutes (keyed by the service account) and dropped when Play answers 401, instead of one OAuth round trip per Play call.
- **Check:** `ai/tmp/play_recover_check.php` (cases 11–14).

## Play subscription

One Play subscription has a monthly base plan and an annual base plan. Each **Google Play product ID** is `subscription id,base plan id`, and the same pair is pasted on every currency row of that period. The app buys that base plan. Play charges the country price from the base plan’s table. The currency selected on the page is not sent to Play. The subscription is stored under the shop default currency, so My subscription shows that package. A value with no comma is still a subscription id, and the app buys the first plan on it.

## App choice

In the Play app (the native bridge `AppSplash` is there), Purchase on the plans page or My subscription follows **Google Play settings → Offer ScoreTutor beside Google Play**.

- **No** (the default, and any empty value): there is no selection screen. Purchase opens the Play payment sheet for that plan. Play not connected, a plan with no Google Play product id, or a purchase that does not finish, is said on the plans page.
- **Yes:** the **payment choice** screen opens (panel `google/payment_choice`). It replaces the plans in place; the header, the settings menu and the footer stay, and Back (or the phone's back button, through `music_app_back`) shows the plans again. Use this when Play billing choice is active.

- **Ways** (only when the setting is Yes): **Google Play**, selected by default, and **ScoreTutor (Stripe)**, which continues in the browser. Continue starts the selected way.
- **Google Play greyed:** when that price row has no Google Play product id ("This plan is not available in Google Play."); Stripe is then selected.
- **No Play Billing:** when Play Billing is not connected, both ways are greyed with "Not available right now. Please try again later, or subscribe on the website." Nothing is sold in the app without Play: Stripe, too, needs Play's token. The screen asks the bridge every 2 s, which also reconnects, and enables the ways when Play is back.
- **Errors** (cancelled, not confirmed, no token, link not opened) show on the screen in its own translated words, and Continue works again. The app's English detail goes to the console.
- **Pending** Play payments say "Your payment is pending. Premium starts when Google confirms it."
- **After paying:** a Play purchase is confirmed on the server and acknowledged, then "Payment received" and My subscription (`pricing_paid_refresh`). Stripe opens Play's link screen and the browser; the plans page's paid-status watch, started on Continue, opens My subscription when the payment arrives.
- **Texts:** Shop → Google Play settings, § App payment choice (translated).
- **Code:** `google/js/payment_choice.js` (the screen and the bridge replies `app_play_purchase_result`, `app_play_external_token`, `app_play_link_result`). It reaches the plans pages through this module's `extends` of `subscription/pricing` and `subscription/manage`, and sets the CMS hook `payment_choice`, which `pricing.js` calls; the subscription module does not name this one. Without the hook (module off), Android sells nothing. Music themes the screen (`music/css/google_payment_choice.scss`). The app shell has no payment code, so changes need no app release.
- **Native side** (`PlayBilling.java`): reconnects after a disconnect (1 s, 2 s … up to 30 s), when the page asks `playReady()`, and before a purchase or token request.

The token comes from Play’s billing-choice API, which keeps Google Play available beside the website. A Play purchase is stored only when Google says it is active, the product matches the card, and the account matches this user. It is acknowledged only after that confirm. An inactive Play token does not remove a Stripe subscription. The website, the installed web app and `musicwrap.localhost` (in-app pages without the bridge) never offer Google Play: they open Stripe Checkout directly (`subscription.md` § The installed web app).

A debug build gets no billing-choice token from Play, so on the emulator Stripe answers "ScoreTutor (Stripe) could not be started." A real check needs the internal testing track.

ScoreTutor (Stripe) asks Play for an external-transaction token and saves user meta `google_transaction` as one JSON object:

```json
{"token":"","time":0,"google_package_id":"","product_id":0,"currency":0,"active":0}
```

`currency` is the CMS currency id. `active` becomes `1` after the first sale is reported to Google. A newer tap replaces the object only while `active` is not set.

The reported amount is the tax-inclusive shop price the customer sees. The country is the currency row’s Country field. An empty country is left off the report.

## Reporting and checks

When Stripe records a purchase, the subscription domain asks this module to report it if the token is under 24 hours old and `product_id` plus `currency` match the package. A later extension is reported while `active` is set, including after those 24 hours. The first report’s id is kept on the same object as `external_id`, and `reported_ends` stops the same period being sent twice.

A Play purchase is written only after this module has read the purchase from Google’s servers. The phone message alone is not enough. Real-time notifications arrive at `{base}google/notify/` and are read from Google again. My subscription follows `subscription_provider` on the user (`stripe` or `google`) and runs that provider’s server check.

### A Play purchase is not lost

Google refunds a purchase that is not acknowledged within 3 days. The app's confirm (`google/purchase` after Play's sheet) can fail: no network at that moment, the app closed, a server error. So the purchase reaches the server three ways, and the server acknowledges it itself (`google_model::acknowledge`, Play's `purchases.subscriptions.acknowledge`) once the subscription is written:

- **The app's confirm**, as before. The app also acknowledges, which is harmless twice.
- **The Play notification:** an active purchase becomes the user's current subscription when Play already bills this user, or when no Stripe subscription is current. The plan is found from Play's product and base plan (`match_play_product`) when the confirm never brought it.
- **The app resends it:** when the plans page or My subscription opens in the Android app, `payment_choice.js` asks the app for this Google account's purchases (`AppSplash.queryPlayPurchases` → `app_play_purchases`). Each one that is bought but not acknowledged goes to `google/purchase` with Play's product only; the server finds the plan. When that changes the subscription, "Payment received" shows and My subscription opens.
- **My subscription** (`sync_user`) also acknowledges a granted purchase that is still unacknowledged.

**The plan comes from Google's product.** A confirm that names a plan (`product_id`) must name the one whose price row has the posted Play id (`google_model::play_product_for()`: the full `product,plan` value, then the product part). Otherwise it is refused with "This Play purchase is for a different plan" before Google is asked, and Google's answer must then show that same Play product. The purchase token is 1–1,000 visible ASCII characters and the Play id is `product` or `product,plan` (`play_token_ok()`, `play_id_ok()`). The external-transaction save (`google/transaction`, `save_transaction()`) checks the same: with a Play id, its plan must match it; without one, the plan must be a `shop/product`. Each refusal writes one error-log line without the token. Until 2026-11-20 a posted plan was kept as sent, so a monthly buyer could store the yearly plan.

A Play purchase made while a Stripe subscription is current is not taken (the notification keeps it aside, and a resend is refused with "Another subscription is current"). It stays unacknowledged, and Google refunds it. A failed acknowledge is logged and tried again by the next confirm, notification or sync.

Turning automatic extension off calls Play cancel for that purchase token. Play keeps the subscription until the period ends and keeps its payment record. Account delete uses the same cancel. Turning extension back on opens the current Play subscription (`sku` and package when that product id is known). My subscription shows no change-plan cards and no card update for a Play subscriber: one **Manage in Google Play** link goes to the same page, and "Billed through Google Play" says who bills (subscription `subscription.md` § My subscription). In the app that page opens outside the WebView. A subscription on hold or paused in Play is stored as `on_hold` / `paused`, and My subscription explains it. A later sync adopts Google’s product id when a shop price row has that id. The sync uses the Play line that is in force now, and an empty cancel success is accepted.
