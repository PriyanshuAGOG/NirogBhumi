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
2. `Razorpay for WooCommerce`
   - Recommended for India payments.
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
7. Enable Razorpay.
8. Add Razorpay test keys.
9. Run one test payment.
10. Switch to live keys only after successful testing.

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

## 9. Recommended Consultation Workflow

Use this exact flow:

1. User fills the custom consultation form on `/consultation/`
2. The form is stored inside WordPress under `Consultations`
3. User lands on `/consultation-payment/`
4. User can use `Edit response` to reopen the saved form; submitting it updates the same consultation entry
5. User clicks the payment CTA
6. The theme adds the consultation WooCommerce product to cart and sends the user to checkout
7. User pays `Rs 500`
8. After successful payment, WooCommerce automatically redirects the user to `/consultation-calendar/`
9. The protected `Consultation Calendar` page shows your Cal.com booking embed
10. User picks a slot
11. Cal.com sends confirmation and reminder emails and writes the event into Google Calendar

This flow is already supported by the theme.

## 10. Consultation Setup In WordPress

After activating the theme:

1. Go to `Settings > Nirog Bhumi Setup`
2. Fill:
   - `Consultation product ID`
   - `Calendar page URL`
   - `Empty cart before consultation checkout`
3. Save

Recommended values:

- `Consultation product ID`: the WooCommerce product ID of your `Consultation Booking Amount` product
- `Calendar page URL`: `https://yourdomain.com/consultation-calendar/`
- `Empty cart before consultation checkout`: enabled

The theme blocks direct access to the payment page, consultation checkout and calendar. The calendar becomes available only through a verified paid consultation order. Keep Cal.com embedded inside the protected WordPress calendar page; do not redirect customers to a public Cal.com URL.

The theme verifies WooCommerce's configured checkout page automatically. If WooCommerce still points to a deleted page ID, the theme reconnects an existing `/checkout/` page or creates a standard WooCommerce checkout page and updates the setting.

How to find the product ID:

1. Go to `Products > All Products`
2. Hover over the consultation product
3. Note the `ID: ...`
4. Put that number into `Settings > Nirog Bhumi Setup`

## 11. Payment Gateway Setup

For India, the easiest free practical route is `Razorpay for WooCommerce`.

1. Install and activate the plugin
2. Create or log in to Razorpay
3. Put test keys into `WooCommerce > Settings > Payments > Razorpay`
4. Test a full order
5. Switch to live keys

After payment, the customer will receive:

- WooCommerce order email
- payment confirmation email
- your admin order notification

## 12. Calendar Booking Setup With Cal.com + Google Calendar

This is the recommended free workflow.

### A. Cal.com setup

1. Create a free Cal.com account
2. Connect your Google Calendar
3. Create one consultation event type
4. Set duration to `30 minutes`
5. Add pre and post buffers if needed
6. Enable confirmation emails
7. Enable reminder workflows

Recommended reminders:

1. immediately after booking
2. 24 hours before
3. 2 hours before

### B. Embed Cal.com into WordPress

1. Open the `Consultation Calendar` page
2. Paste your Cal.com embed code into the page content
3. Update the page

The theme already renders page content inside the calendar slot on that page.

### C. How Google Calendar fits in

With Cal.com connected to Google Calendar:

- Google Calendar controls availability
- blocked Google Calendar time stays unavailable
- confirmed bookings are written back to Google Calendar
- customer gets booking confirmation and reminders from Cal.com

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
3. payment handoff from `/consultation-payment/` into WooCommerce checkout
4. automatic redirect from successful paid consultation order to the calendar page
5. a setup screen in `Settings > Nirog Bhumi Setup`
6. checkout prefill for name, email and phone from the consultation form
7. consultation entry ID attached to the WooCommerce order

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
