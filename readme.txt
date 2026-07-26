=== eCommerce Wunderkiste ===
Contributors: michaelkanda
Homepage: https://designare.at
Tags: woocommerce, pricing, shipping, accessories, image, cart, info-box
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
WC requires at least: 9.0
WC tested up to: 11.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extended product options: Price on Request, Shipping per Product, Accessories, Image Resizer, Order Recovery, Tiered Pricing & Cart Info Box.

== Description ==

eCommerce Wunderkiste adds product management features to WooCommerce. Every module can be enabled or disabled individually under WooCommerce → Product Extras.

= Features =

**Price on Request** — hide the price and the add-to-cart button per product, with a custom display text and custom CSS.

**Disable Shipping Methods** — switch off individual shipping methods per product; the method disappears at checkout as soon as the product is in the cart.

**Accessories Tab** — an extra product tab for cross-selling, filled via product search in the sidebar.

**Image Resizer 800px / 1200px** — scale images down from the media library, single or bulk. The original file is overwritten; users need edit rights on the individual attachment.

**Order Recovery** — reminder mail one hour after an unpaid order, immediate mail on payment failure, and a manual resend button on the order screen. Offline payment methods are excluded by default.

**Tiered Pricing** — quantity based prices per product, with a price table on the product page that follows the shop's tax display setting.

**Cart Info Box** — configurable info box for the classic cart and the WooCommerce Cart Block.

== Installation ==

1. Deactivate and delete any previous version of this plugin (the folder and main file were renamed in 1.3.0).
2. Upload the `ecommerce-wunderkiste` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins menu.
4. Go to **WooCommerce → Product Extras** and enable the modules you need.

Settings and product data are stored in options and post meta and survive the reinstall.

== Frequently Asked Questions ==

= Does the plugin require WooCommerce? =
Yes. Since 1.3.0 this is declared via the `Requires Plugins` header, so WordPress enforces it.

= Is the Image Resizer destructive? =
Yes, it overwrites the original file. Keep backups. Developers can opt into a safety copy with the `wpe_image_resizer_keep_backup` filter.

= Why does no reminder mail arrive? =
Order Recovery relies on WP-Cron. If `DISABLE_WP_CRON` is set, a real system cron has to call `wp-cron.php`. The settings screen warns about this.

== Changelog ==

= 1.3.0 =
* Security: the Image Resizer now requires edit rights on the individual attachment, not just `upload_files`. Previously any user with upload rights could overwrite any image in the media library.
* Security: the resize endpoint no longer lets the request decide which nonce is verified.
* Security: the accessories AJAX search now requires the `edit_products` capability.
* Security: product titles and SKUs in the accessories search are inserted as text nodes instead of concatenated HTML.
* Removed a hard-coded third-party contact address from the recovery e-mails; the address is now a setting and defaults to the WordPress admin e-mail.
* Removed the shipped `uninstall.php.bak` and added a real uninstall routine (options, product meta, scheduled events, multisite aware).
* HPOS: admin order links use `get_edit_order_url()` instead of the legacy `post.php` URL.
* Declared `cart_checkout_blocks` and `product_block_editor` compatibility for the whole plugin, not just one module.
* Shop managers can now actually save the settings pages (`option_page_capability` filters).
* Tiered pricing: the price table respects the shop's tax display setting, variation-level rules are supported, and invalid ranges are dropped on save.
* Order Recovery: no fatal error when the invoice mail class is missing, offline gateways are excluded, wording matches the actual reason, reminders are cancelled when the order is paid or cancelled.
* Custom CSS is output via `wp_add_inline_style()` so child selectors are no longer mangled.
* CodeMirror on the settings screen works with `DISALLOW_FILE_EDIT` enabled.
* Cart Info Box module stands down with a notice when the standalone plugin is still active.
* Unified the text domain to `ecommerce-wunderkiste`; renamed the main file to match the slug.
* Updated compatibility headers to WordPress 7.0 and WooCommerce 11.0.

= 1.2.0 =
* Added Cart Info Box module (formerly the standalone "WooCommerce Cart Info Box" plugin)
* HPOS and Cart/Checkout Blocks compatibility declared

= 1.1.0 =
* Added Tiered Pricing and Order Recovery modules
* Improved input sanitization

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.3.0 =
Security release. Fixes a privilege issue in the Image Resizer and removes a hard-coded contact address from customer e-mails. The plugin folder and main file were renamed: delete the old version before installing.
