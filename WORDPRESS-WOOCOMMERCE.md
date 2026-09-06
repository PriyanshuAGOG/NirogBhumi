# WooCommerce Store

The store runs on WooCommerce inside the `nirog-bhumi` theme. The theme
supplies the catalogue, the product and shelf design, a launch gate and a
waitlist. WooCommerce supplies the cart, checkout, payments, orders and
accounts.

## Where the code lives

| File | What it does |
| --- | --- |
| `inc/store-settings.php` | Store status, dispatch note, tax defaults. Stored in the `nirog_bhumi_store_settings` option, separate from the existing consultation and invoice settings. |
| `inc/store-catalogue.php` | The launch catalogue: shelves, products, prices, copy, safety notes. Edit this to change what the seeder creates. |
| `inc/store-seeder.php` | The `WooCommerce > Nirog Bhumi Store` admin screen and the one-click catalogue seeder. |
| `inc/store.php` | Launch gate, per-product launch state, HSN and GST fields, waitlist, cart link, checkout acknowledgement. |
| `woocommerce/content-product.php` | The product card, drawn as the theme's `.store-tile`. |
| `woocommerce/content-single-product.php` | The product page, drawn as the theme's `.product-page`. |
| `woocommerce/archive-product.php` | Shop and product category pages. |
| `woocommerce/loop/` | Grid wrapper and the empty-shelf message. |
| `template-parts/store-shelf-nav.php` | The shelf filter row. |
| `assets/css/store.css` | Styles for the markup WooCommerce generates. `styles.css` has none. |
| `page-store.php` | The `/store/` landing page, now driven by the live catalogue. |

## Store status

Set in `WooCommerce > Nirog Bhumi Store`. Three states:

- **Coming soon** (default). Visitors see only the Coming soon panel. Shop
  managers see the full catalogue, so it can be built and checked in public
  without anyone being able to browse or buy. Product and shop URLs redirect
  to `/store/` for everyone else, and products are kept out of site search.
- **Preview.** Everyone can browse the catalogue. Nothing can be bought.
  Products that are not on sale show a Notify me form.
- **Open.** Products marked *On sale* that carry a price can be added to the
  cart and bought.

The consultation flow is exempt from the gate. Consultation checkout keeps
working in all three states, because it predates the store and runs through
the same cart.

## Launch state per product

Each product carries a launch state on the `Nirog Bhumi` tab of the product
data panel:

- **On sale** — buyable once the store status is Open.
- **Coming soon** — visible, not buyable, shows the waitlist. The price area
  reads "Coming soon".
- **Enquiry only** — visible, not buyable, shows a button to another page
  instead of a price. Not currently used by any seeded product (see note
  below), but available for a future physical product that should point
  somewhere else instead of selling directly.

A product with no launch state set falls back to *On sale* if it has a price
and *Coming soon* if it does not.

**Programmes and consultations are not sold here.** The 6-month and 99-day
programmes and the consultation booking amount were removed from the
catalogue - the store sells physical goods only. A `nirog_bhumi_render_store_promo_card()`
banner (in `inc/store.php`, placed between shelves in `page-store.php`) points
shoppers at `/consultation/` and `/programmes/` instead, without ever
appearing as a catalogue product, cart line or order.

## Seeding the catalogue

`WooCommerce > Nirog Bhumi Store > Create missing products` creates the four
shelves and physical-goods products defined in `store-catalogue.php`,
importing the product images from the theme.

The seeder matches on SKU. Running it again only adds what is missing and never
overwrites a product you have edited, so it is safe after a theme update that
adds new catalogue entries.

## The waitlist

Products that are not on sale show a Notify me form. Sign-ups are stored as
ordinary `Form Entries`, so the existing CSV export and the erasure tools in
`inc/data-admin.php` already cover them. Turn the waitlist off in the store
settings if you would rather not collect them.

## Tax on goods

The existing invoice settings were built for services and use SAC `999319` with
a single GST rate. Goods are different: they are invoiced against an HSN code,
and the rate is not the same for a wooden tumbler, a steel pot and a packaged
food.

The theme therefore stores an HSN code and a GST rate **per product**, with a
store-wide default, and stamps both onto the order line at checkout so that a
later price or rate change cannot rewrite an invoice already issued.

Nothing here is pre-filled. Blank means nothing is printed, rather than
something wrong. Have your accountant confirm each HSN code and rate before the
store goes to Open.

**Still to do:** WooCommerce orders are given an invoice number from the same
financial-year sequence as consultations, and the number appears on the order
email. The PDF invoice renderer in `inc/invoice-pdf.php` is still
consultation-only, so goods orders do not yet produce a PDF tax invoice. The
per-line HSN and rate needed to build one are already being captured.

## Required plugins

- **WooCommerce** — products, cart, checkout, accounts.
- Your active **PhonePe payment gateway plugin** — the same one already
  wired up for consultation checkout. Store orders share the same
  WooCommerce cart and checkout, so no separate gateway setup is needed.
- **FluentSMTP** or similar — reliable order and invoice email.

The theme removes WooCommerce's own stylesheets and draws the whole store
itself. Do not add a second WooCommerce theme or a page builder over these
templates.

## Before switching to Open

1. Confirm HSN codes and GST rates per product with your accountant.
2. Review product names, packaging and page copy against FSSAI rules for
   packaged food and AYUSH rules for herbal products, and against the Drugs and
   Magic Remedies (Objectionable Advertisements) Act, which restricts
   advertising that claims to cure or treat diabetes. Have a regulatory adviser
   read the wording before launch.
3. Set up shipping zones, rates and a courier.
4. Confirm the PhonePe gateway is in live (not test) mode and run one real
   low-value order end to end.
5. Publish shipping, returns, refunds and cancellation terms. Indian payment
   gateways require these before going live.
6. Set stock quantities so the store cannot oversell.

## Note on the static mirror

`store/` at the repository root is the older static HTML mirror of the site. It
has not been changed. The WordPress theme is now the source of truth for the
store.
