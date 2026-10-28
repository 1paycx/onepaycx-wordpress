# 1pay.cx Payments for WordPress

Add a "Pay by card or crypto" button to any WordPress page or post, with a block or a
shortcode. For sites that are **not** a store: service pages, invoices, donation pages,
one-off payment links. Buyers pay by card or crypto on the hosted
[1pay.cx](https://1pay.cx) checkout; you settle in crypto.

- **Version 1.3.0** for WordPress 6.0+ and PHP 7.4+
- **1pay.cx Pay Button** block, or `[onepaycx_button amount="25.00" label="Pay $25"]`
- Leave the amount blank and the buyer types their own: donations, "pay what you owe"
- Fixed prices are signed server-side, so a visitor editing the page cannot change the charge
- No card data ever touches your server
- Licence: GPL-2.0-or-later (see `LICENSE`)

## Install

1. Download the ready-made archive: https://1pay.cx/plugins/onepaycx-wordpress.zip
   (or zip the `onepaycx` folder in this repository).
2. WordPress admin: **Plugins → Add New → Upload Plugin**, upload the zip, activate.
3. **Settings → 1pay.cx**: paste your API key (1pay.cx dashboard → **Settings → API keys**)
   and save. The plugin verifies the key and fetches the webhook secret on its own.
4. Add the **1pay.cx Pay Button** block to a page, or use the shortcode.

Running WooCommerce? Use the dedicated
[WooCommerce gateway](https://github.com/1paycx/onepaycx-woocommerce) instead.

## Building the zip

```bash
zip -rq onepaycx-wordpress.zip onepaycx -x '*.DS_Store' '*/.*'
```

## Support

- Documentation and every integration: https://1pay.cx/docs
- Service status: https://1pay.cx/status
- Contact: `hello@1pay.cx` on Jabber/XMPP, or the chat on any 1pay.cx page
