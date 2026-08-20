# Nirog Bhumi WordPress Setup Guide

This package contains the Nirog Bhumi WordPress theme and the consultation workflow needed for:

- custom consultation form
- WooCommerce payment
- Cal.com slot booking
- Google Calendar sync
- customer confirmation and reminder emails

Theme folder: `wp-theme/nirog-bhumi`  
Uploadable zip: `nirog-bhumi-theme-upload.zip`

Important: upload only `nirog-bhumi-theme-upload.zip` in WordPress.

## 1. Install WordPress

1. Buy hosting with PHP 8.1+ and HTTPS enabled.
2. Install WordPress from your hosting panel.
3. Log in to `/wp-admin`.
4. Go to `Settings > Permalinks`.
5. Select `Post name`.
6. Click `Save Changes`.

## 2. Upload The Theme

1. Go to `Appearance > Themes`.
2. Click `Add New`.
3. Click `Upload Theme`.
4. Upload `nirog-bhumi-theme-upload.zip`.
5. Click `Install Now`.
6. Click `Activate`.

## 3. Create Required Pages

Create these pages in `Pages > Add New`. The title can be readable, but the slug must match.

| Page | Slug |
|---|---|
| Home | `home` |
| About | `about` |
| Approach | `approach` |
| Mission | `mission` |
| Founder's Story | `founder-story` |
| Brand Kit | `branding` |
| Careers | `careers` |
| FAQs | `faqs` |
| Terms and Conditions | `terms-and-conditions` |
| Privacy Policy | `privacy-policy` |
| Medical Disclaimer | `medical-disclaimer` |
| Education | `education` |
| Blog / Articles | `blog` |
| Our Programs | `programmes` |
| 6 Months Diabetes Reversal Program | `6-month-diabetes-reversal` |
| 6 Month Healing Redirect | `6-month-healing` |
| Yoga for Diabetes | `yoga-programme` |
| Interest Form | `interest-form` |
| Diabetes Interest Form | `diabetes-interest-form` |
| Yoga Interest Form | `yoga-interest-form` |
| Consultation | `consultation` |
| Consultation Intake | `consultation-intake` |
| Consultation Payment | `consultation-payment` |
| Consultation Calendar | `consultation-calendar` |
| Store | `store` |

## 4. Set The Home Page

1. Go to `Settings > Reading`.
2. Choose `A static page`.
3. Set `Homepage` to the page with slug `home`.
4. Save.

## 5. Create The Header Menu

1. Go to `Appearance > Menus`.
2. Create a menu called `Primary`.
3. Add:
   - Our Programs: `/programmes/`
   - Store: `/store/`
   - Education: `/education/`
4. Assign it to `Primary Menu`.
5. Save.

## 6. Install Required Plugins

Install these from `Plugins > Add New`.

Required:

1. `WooCommerce`
   - Products, cart, checkout, orders, customer accounts.
2. A WooCommerce PhonePe payment gateway plugin
   - This is the primary payment method for the consultation and store checkout. `Razorpay for WooCommerce` also works as a backup/alternate gateway since the automation below reacts to any WooCommerce gateway, not specifically to PhonePe.
3. `FluentSMTP`
   - For reliable WordPress and WooCommerce emails.
4. `Rank Math SEO` or `Yoast SEO`
   - Use only one.
5. `Site Kit by Google`
   - Google Analytics and Search Console.
6. `LiteSpeed Cache` or `WP Super Cache`
   - Use the one that suits your hosting.
7. `UpdraftPlus`
   - Backups.
8. `Wordfence Security` or `Solid Security`
   - Basic security.

Optional later:

- `Advanced Custom Fields`
- `Members`
- `WooCommerce PDF Invoices & Packing Slips`

Note: `Cal.com` does not need a WordPress plugin. It can be embedded directly into the calendar page content.

## 7. WooCommerce Setup

1. Open `WooCommerce > Home`.
2. Complete the basic store setup.
3. Set country, currency, tax and account preferences.
4. Go to `WooCommerce > Settings > Advanced`.
5. Confirm these pages are assigned:
   - Cart
   - Checkout
   - My Account
   - Shop
6. Go to `WooCommerce > Settings > Payments`.
7. Enable the PhonePe gateway.
8. Add PhonePe test/UAT keys (merchant ID, salt key, salt index) and confirm the plugin's callback/webhook URL is reachable over HTTPS.
9. Run one test payment and confirm the order status moves to `Processing` or `Completed` automatically.
10. Switch to live PhonePe keys only after a successful end-to-end test payment.

Never share PhonePe API keys, salt keys or Razorpay Key Secrets in screenshots, support messages or source code.

## 8. Products To Create

Go to `Products > Add New`.

Recommended product structure:

1. `Diabetes Reversal Kit`
2. Food products
3. Individual cure kit items
4. Combos
5. `Consultation Booking Amount`

Consultation product settings:

- Price: `Rs 500`
- Product type: `Virtual`
- Mark `Sold individually`
- Recommended slug: `founder-consultation`
- Recommended SKU: `CONSULT-500`

Important: do not use the slug `consultation`, because that conflicts with the consultation page.

## 9. Automated Consultation Workflow

This is now a fully automated flow. Nobody on the team needs to send payment details, verify a payment, type an appointment time into WordPress, or send an invoice by hand.

1. User fills the custom consultation form on `/consultation/`
2. The form is stored inside WordPress under `Consultations`
3. User lands on `/consultation-payment/` and clicks `Pay Rs. 590 securely`, which sends them into a normal WooCommerce checkout carrying only the consultation product, prefilled with their name, email and phone
4. User pays with PhonePe (or whichever gateway is enabled) inside that checkout
5. As soon as WooCommerce marks the order paid, the theme automatically:
   - marks the matching consultation entry as `Verified`
   - records the transaction reference and payment time
   - generates the sequential tax invoice number and PDF
   - emails the invoice to the customer with a link to their private status page
   - redirects the customer straight to `/consultation-calendar/`
6. On the calendar page, the customer books their own 30-minute slot directly in an embedded Cal.com widget (no plugin, no manual page content) that is already prefilled with their name and email
7. As soon as the booking is confirmed in Cal.com, a webhook automatically writes the date, time and meeting link back into the consultation entry and onto the customer's private status page - no admin step required
8. The customer can return to their private status link at any time to see payment confirmation, appointment details, the meeting link and the invoice PDF

A `Need help? Message us on WhatsApp` link stays on the payment and status pages as a fallback for anyone who runs into trouble, but it is no longer part of the primary flow. If the consultation product or a payment gateway is not configured yet, the payment page automatically falls back to the old `Continue on WhatsApp` button so the site never breaks.

## 10. Consultation Setup In WordPress

There is no manual verification step for a normal payment. `WordPress Dashboard > Consultations` is where you review what happened automatically:

1. Open `WordPress Dashboard > Consultations`
2. Open the matching entry using the consultation reference or customer name
3. The `Payment and Appointment` panel shows the payment status, transaction reference, invoice number, and (once Cal.com confirms it) the booked date, time and meeting link - all filled in automatically
4. Use `Resend invoice email` in the same panel only if a copy needs to be sent again
5. The `Payment status` dropdown and manual `Verified` save are still there as a manual override for edge cases (a payment confirmed outside WooCommerce, a support fix), but they are no longer needed for the normal PhonePe flow

Invoice numbers use one global financial-year sequence for all Nirog Bhumi products and services: `2026-27/001`, `2026-27/002`, `2026-27/003`, and so on. The sequence resets to `001` when the new Indian financial year begins on 1 April. Issued invoice numbers are permanent and cannot be deleted or reused.

Before the first live invoice goes out, open `Settings > Nirog Bhumi Setup` and complete the Invoice Identity fields using details confirmed by your accountant: legal business name, address, GSTIN if applicable, SAC if applicable, email and phone.

The invoice email contains a private invoice link. `View or print invoice` opens a dedicated A4 invoice page; the customer can print it or choose `Save as PDF` in the browser print dialog.

The theme automatically creates the private `/consultation-status/` page. Do not add it to menus. Customers should access it only through their secure status link.

## 11. Payment Gateway Setup (PhonePe)

1. Install and activate a WooCommerce-compatible PhonePe gateway plugin.
2. Create or log in to your PhonePe Business account and generate the merchant ID, salt key and salt index for the gateway.
3. Put those keys into `WooCommerce > Settings > Payments > PhonePe`, using UAT/test credentials first.
4. Confirm the plugin's payment callback URL is publicly reachable over HTTPS - PhonePe calls it to confirm payment status, and that callback is what makes automatic verification below possible.
5. Test a full consultation order end to end and confirm the WooCommerce order moves to `Processing` or `Completed` on its own, then confirm the matching entry under `Consultations` flips to `Verified` and the invoice email arrives, without touching wp-admin.
6. Switch to live PhonePe keys only after that test succeeds.

The automation in `functions.php` (`nirog_bhumi_auto_verify_consultation_payment`) listens for the standard WooCommerce `payment_complete` / `processing` / `completed` events, so it works with PhonePe, Razorpay, or any other properly-behaved WooCommerce gateway without any gateway-specific code. If a payment never reaches one of those statuses (e.g. the gateway plugin only marks orders `On hold`), the consultation entry will stay `Pending` until it does.

After payment, the customer will receive:

- WooCommerce order email
- the automatic Nirog Bhumi invoice email with the PDF invoice attached
- your admin order notification

## 12. Calendar Booking Setup With Cal.com + Google Calendar (Automated)

Slot booking is now a live embed with no manual page-content editing, and confirmed bookings write themselves back into WordPress.

### A. Cal.com setup

1. Create a free Cal.com account
2. Connect your Google Calendar
3. Create one consultation event type and note its booking link, e.g. `cal.com/gautam-khandelwal/consultation`
4. Set duration to `30 minutes`
5. Add pre and post buffers if needed
6. Enable confirmation emails
7. Enable reminder workflows

Recommended reminders:

1. immediately after booking
2. 24 hours before
3. 2 hours before

### B. Turn on the embed and webhook in WordPress

1. Open `Settings > Nirog Bhumi Setup` and find `Automated booking (Cal.com)`
2. In `Cal.com event link`, enter the part of your booking URL after `cal.com/`, e.g. `gautam-khandelwal/consultation`
3. In Cal.com, go to `Settings > Developer > Webhooks`, add an endpoint, and paste in the URL shown under the webhook secret field on the same settings page (it looks like `https://yoursite.com/wp-json/nirogbhumi/v1/calcom-webhook`)
4. Subscribe that webhook to `Booking Created`, `Booking Rescheduled` and `Booking Cancelled`
5. Set a webhook secret in Cal.com and paste the exact same value into `Cal.com webhook secret` in WordPress, then save
6. Save the WordPress settings page

Once both fields are filled in, `/consultation-calendar/` automatically shows the live Cal.com widget instead of the placeholder, and every booking made there writes its date, time and meeting link straight into the matching consultation entry. Leaving the event link blank keeps the old behaviour (manually pasted page content, or the placeholder message).

### C. How Google Calendar fits in

With Cal.com connected to Google Calendar:

- Google Calendar controls availability
- blocked Google Calendar time stays unavailable
- confirmed bookings are written back to Google Calendar
- customer gets booking confirmation and reminders from Cal.com, and their Nirog Bhumi status page updates automatically via the webhook

## 13. SMTP Email Setup

Do this before launch.

1. Install `FluentSMTP`
2. Connect a real sending service
   - Google Workspace SMTP
   - Gmail SMTP
   - Brevo
   - Mailgun
   - SendGrid
3. Send a test email

This is important because WordPress default email is often unreliable.

With FluentSMTP connected, these emails become much more dependable:

- consultation form admin notifications
- WooCommerce customer emails
- WooCommerce admin emails
- password reset emails

## 14. What The Theme Already Does For You

The theme already supports:

1. custom consultation form saved in WordPress
2. consultation entries in admin
3. payment handoff from `/consultation-payment/` into WooCommerce checkout, working with PhonePe or any WooCommerce gateway
4. automatic payment verification, invoice generation and invoice email as soon as WooCommerce marks the order paid - no admin step
5. automatic redirect from a successful paid consultation order to the calendar page
6. an automatic Cal.com booking widget on the calendar page once you set the event link in `Settings > Nirog Bhumi Setup`
7. a Cal.com webhook that writes the confirmed date, time and meeting link back into the consultation entry automatically
8. checkout prefill for name, email and phone from the consultation form
9. consultation entry ID attached to the WooCommerce order and to the Cal.com booking

## 15. Forms Setup

Important:

- The main consultation form is already custom-built in the theme and already stores entries in WordPress.
- The interest forms and careers form are already wired to WordPress storage in this theme.
- You do not need Fluent Forms for those custom designed forms unless you deliberately want to replace them.

If you still want plugin-based replacements later, you can replace:

- Diabetes interest form
- Yoga interest form
- General interest form
- Careers resume upload form

Important fields for any replacement form:

- Name
- Email
- Phone / WhatsApp
- Diabetes stage
- HbA1c
- Fasting sugar
- Post-meal sugar
- Blood pressure
- Current medication / insulin
- Sleep, stress, food rhythm and movement notes
- Consent checkbox for medical disclaimer

## 16. SEO And GEO Setup

1. Install Rank Math or Yoast
2. Set site name to `Nirog Bhumi`
3. Set the organization logo
4. Submit sitemap to Google Search Console
5. Connect Google Analytics using Site Kit
6. Add local business details if you have a public center
7. Add page titles and meta descriptions for:
   - Home
   - Consultation
   - 6 Months Diabetes Reversal Program
   - Yoga for Diabetes
   - Store
   - Education

Suggested keyword themes:

- diabetes reversal program
- reverse diabetes naturally
- yoga for diabetes
- naturopathy for diabetes
- diabetes consultation
- diabetes reversal kit

## 17. Future Content Changes

For text-only changes:

1. Edit the matching PHP template in the theme
2. Example: yoga page is `page-yoga-programme.php`
3. Upload the changed file
4. Clear cache

For design or interaction changes:

1. Update `assets/css/styles.css`
2. Update `assets/js/main.js` if behavior changes
3. Clear cache
4. Test desktop, tablet and mobile

For WooCommerce pages:

- Cart: `woocommerce/cart/cart.php`
- Empty cart: `woocommerce/cart/cart-empty.php`
- Checkout: `woocommerce/checkout/form-checkout.php`
- My Account: `woocommerce/myaccount/my-account.php`

## 18. Safe Update Workflow

Before any update:

1. Take a full backup
2. Update plugins on staging first if possible
3. Check:
   - home page
   - consultation flow
   - payment
   - calendar booking
   - add to cart
   - checkout
   - my account
   - mobile navigation
4. Clear cache
5. Only then update live

## 19. Important Notes

- This is a classic PHP WordPress theme, not a block theme.
- The frontend pages are converted into PHP templates.
- WooCommerce pages are dynamic and ready for real orders.
- Do not edit WordPress core files.

## 20. Publishing Education Articles

The Education page shows six fixed categories with curated external links.
Articles you publish in WordPress appear automatically inside the matching
category, next to the external links — no template editing needed.

### How to publish

1. In the WordPress admin sidebar, open **Education Articles → Add New**.
2. **Title** — this becomes the article heading and link text on the page.
3. **Content** — write the full article in the main editor. This is the page
   readers land on when they click "Read article".
4. **Excerpt** — open the *Excerpt* panel (Screen Options → Excerpt if hidden)
   and write 1–2 sentences. This is the summary shown on the card. If left
   blank, the first ~26 words of the content are used.
5. **Education Topic** — in the *Education Topics* box, tick **exactly one** of
   the six categories. This decides which category section the article shows up
   under:
   - Foundations of Health
   - Movement
   - How to Eat
   - What to Eat
   - What to Avoid
   - Recovery, Stress & Tracking
   These six topics are created automatically; just pick one. (Ticking more than
   one shows the article under each ticked category.)
6. **Featured image** (optional) — used for SEO/social sharing.
7. Click **Publish**.

The article now appears on `/education/` inside its category, the on-page
search includes it, and the reference count updates on its own. Editing or
moving an article to another topic updates the page immediately.

### Notes

- Only **Published** articles appear. Drafts and pending posts stay hidden.
- Articles with no topic selected fall back to *Foundations of Health* so they
  are never lost — but always pick a topic for correct placement.
- External curated links are hard-coded in `page-education.php` and are not
  affected by anything you publish.
