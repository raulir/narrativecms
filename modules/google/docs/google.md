# Google Play module

Play Billing for the Android app. The website checkout provider stays Stripe. This module is not a second checkout provider in shop settings.

Enable it under CMS settings → Modules.

**Tools → Google → Auth settings** holds the web client id used by the website Google button and by the app. The Android client stays in Google Cloud.

Then set **Google Play settings**:

- Package name `com.scoretutor.app`
- Service account JSON file path (Play Console API access). The file stays off the site.
- Notification audience: the exact push URL Play’s Pub/Sub subscription uses. Empty means `{base}google/notify/` ignores every call. A call is handled only when its Google-signed bearer token names that audience.

## Play subscription

One Play subscription has a monthly base plan and an annual base plan. Each **Google Play product ID** is `subscription id,base plan id`, and the same pair is pasted on every currency row of that period. The app buys that base plan. Play charges the country price from the base plan’s table. The currency selected on the page is not sent to Play. The subscription is stored under the shop default currency, so My subscription shows that package. A value with no comma is still a subscription id, and the app buys the first plan on it.

## App choice

In the Play app, Purchase asks **Google Play** or **ScoreTutor (Stripe)** only when Play Billing is connected and that card has a Play product id. Otherwise it still opens Stripe in the browser. The screen is drawn by `musicapp/www/js/billing.js`. The token comes from Play’s billing-choice API, which keeps Google Play available beside the website. A Play purchase is stored only when Google says it is active, the product matches the card, and the account matches this user. It is acknowledged only after that confirm. An inactive Play token does not remove a Stripe subscription. The website and `musicwrap.localhost` still open Stripe directly.

ScoreTutor (Stripe) asks Play for an external-transaction token and saves user meta `google_transaction` as one JSON object:

```json
{"token":"","time":0,"google_package_id":"","product_id":0,"currency":0,"active":0}
```

`currency` is the CMS currency id. `active` becomes `1` after the first sale is reported to Google. A newer tap replaces the object only while `active` is not set.

The reported amount is the tax-inclusive shop price the customer sees. The country is the currency row’s Country field. An empty country is left off the report.

## Reporting and checks

When Stripe records a purchase, the subscription domain asks this module to report it if the token is under 24 hours old and `product_id` plus `currency` match the package. A later extension is reported while `active` is set, including after those 24 hours. The first report’s id is kept on the same object as `external_id`, and `reported_ends` stops the same period being sent twice.

A Play purchase is written only after this module has read the purchase from Google’s servers. The phone message alone is not enough. Real-time notifications arrive at `{base}google/notify/` and are read from Google again. My subscription follows `subscription_provider` on the user (`stripe` or `google`) and runs that provider’s server check.

Turning automatic extension off calls Play cancel for that purchase token. Play keeps the subscription until the period ends and keeps its payment record. Account delete uses the same cancel. Turning extension back on, changing plan, and changing the card open the current Play subscription (`sku` and package when that product id is known). In the app that page opens outside the WebView. A later sync adopts Google’s product id when a shop price row has that id. The sync uses the Play line that is in force now, and an empty cancel success is accepted.
