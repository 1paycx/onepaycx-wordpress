=== 1pay.cx Payments ===
Contributors: 1pay
Tags: payments, pay button, donations, bitcoin, monero
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a "Pay by card or crypto" button to any page or post with a block or shortcode. Buyers pay by card, Bitcoin or Monero; you settle in crypto.

== Description ==

1pay.cx Payments adds a payment button to any WordPress site that is **not** a
WooCommerce store — service pages, invoices, donation pages, one-off payment links.
Drop in a block or a shortcode and you can take a payment by card, Bitcoin or Monero.

Your buyer pays on a hosted, branded checkout page: by card, or from any crypto
wallet with no ID check. No card details ever reach your server, so your site stays
out of scope for card data entirely. Card sales settle to you in USDC; crypto is
credited to your 1pay.cx balance at a flat 1% and can be withdrawn to your own wallet.

**Features**

* "1pay.cx Pay Button" block, or `[onepaycx_button amount="25.00" label="Pay $25"]`.
* Leave the amount blank and the buyer types their own — useful for donations and
  "pay what you owe" invoices.
* Fixed prices are signed server-side, so a visitor editing the page in their browser
  cannot change what they are charged.
* Fires `do_action( 'onepaycx_paid', $data )` when a payment is confirmed, so other
  plugins and CRMs can grant access, send a receipt, or update a record.
* Payment confirmations arrive as an HMAC-signed webhook and are verified before the
  hook fires — a forged request cannot mark an order paid.
* Optional direct mode sends buyers straight to the card processor, skipping the
  1pay.cx page (and with it the Bitcoin/Monero option).

Running a WooCommerce store? Install "1pay.cx for WooCommerce" instead — it puts
1pay.cx directly into the WooCommerce checkout.

== External services ==

This plugin connects to 1pay.cx, the payment service that processes your payments.
It will not function without a 1pay.cx account and API key.

**What it sends and when**

* When you save your API key: the key is sent to `https://api.1pay.cx/v1/me` so it can
  be verified and your webhook signing secret retrieved.
* When a buyer clicks a Pay button: the payment amount, currency, your optional
  reference, the return URL on your site, and — only in direct mode — the buyer's
  email address are sent to `https://api.1pay.cx/v1/payments` to create the payment.
  The buyer is then sent to the hosted checkout on `https://1pay.cx`.
* When a payment completes: 1pay.cx sends a signed confirmation back to your site.

No data is sent to any other host, and nothing is transmitted until you have entered
an API key. Card details are entered on the payment provider's own page and never
pass through your site or this plugin.

Service provided by 1pay.cx:
Terms of Service: https://1pay.cx/terms
Privacy Policy: https://1pay.cx/privacy

== Installation ==

1. Plugins → Add New → Upload Plugin, upload the ZIP, then Activate.
2. Settings → 1pay.cx, paste your API key (from your 1pay.cx dashboard under
   Settings → API keys), then Save. The key is verified as you save it.
3. Add the "1pay.cx Pay Button" block to a page, or use the shortcode.

== Frequently Asked Questions ==

= Do I need a 1pay.cx account? =

Yes. The plugin talks to the 1pay.cx service and needs an API key from your dashboard.

= Fixed price or buyer-entered? =

Set `amount` for a fixed price; leave it out and the buyer enters their own amount.

= Is the price safe from tampering? =

Yes. A fixed amount is signed with a key that only your site holds, and the signature
is checked before the payment is created. Editing the page in a browser cannot change
what a buyer is charged.

= Does this work with page caching? =

Yes. Buttons carry no per-visitor state, so a cached page keeps working indefinitely.

= Can I run something when a payment succeeds? =

Hook `onepaycx_paid`:

`add_action( 'onepaycx_paid', function ( $data ) { /* $data: paymentId, reference, amount, status */ } );`

It fires once per payment, only for payments created by this site's buttons, after the
webhook signature has been verified and the payment re-read from 1pay.cx as paid in the
expected amount and currency. `$data` also carries `currency` and `livemode`.

= What is direct mode? =

Buyers skip the 1pay.cx payment page and go straight to the card processor. It needs
the buyer's email, so the button shows an email field. Without a valid email the buyer
sees the standard payment page instead. Because the 1pay.cx page is skipped, direct
mode does not offer Bitcoin or Monero.

== Changelog ==

= 1.3.0 =
* Webhook hardening: verifies the timestamped signature header (deliveries older than five
  minutes are rejected) when 1pay.cx sends it, and still accepts the original header.
* The paid notification only fires `onepaycx_paid` for payments this site created, after
  re-reading the payment from 1pay.cx and checking it is paid in the recorded amount and
  currency, and only once per payment.
* A live API key never accepts a test payment.
* Payments created before this version have no local record, so their late webhooks no
  longer fire `onepaycx_paid`.

= 1.2.2 =
* API requests now identify the plugin, so payments it creates are labelled WordPress in
  your 1pay.cx dashboard.

= 1.2.1 =
* Plugin directory review: compatibility is declared in the readme only; no other changes to behaviour.

= 1.2.0 =
* The hosted checkout now also takes Bitcoin and Monero; the `onepaycx_paid` hook fires
  the same way for crypto payments. Default button label is now "Pay by card or crypto".

= 1.1.0 =
* Pay buttons now carry a signed token instead of server-stored state. Fixes buttons
  breaking on sites with full-page caching, where the stored token was consumed by the
  first buyer and every later visitor on the cached page saw "invalid button".
* No database writes when a button renders (previously one row per page view).
* The buyer's return URL is now restricted to your own site.
* Front-end messages are translatable.
* Deleting the plugin now removes all of its options.

= 1.0.1 =
* Optional direct-to-processor mode with an email field on the button.

= 1.0.0 =
* Initial release: Pay button block and shortcode, signed paid webhook, onepaycx_paid hook.

== Upgrade Notice ==

= 1.2.0 =
Buyers can now pay with Bitcoin or Monero as well as by card. No configuration changes needed.

= 1.1.0 =
Fixes Pay buttons failing on cached pages and removes per-view database writes.
Recommended for all sites, essential if you use a caching plugin.
