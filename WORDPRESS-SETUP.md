# Nirog Bhumi WordPress Setup Guide

This package contains a complete WordPress theme for the finished Nirog Bhumi frontend.

Theme folder: `wp-theme/nirog-bhumi`  
Uploadable zip: `nirog-bhumi-wordpress-theme.zip`

## 1. Install WordPress

1. Buy hosting with PHP 8.1+ and HTTPS enabled.
2. Install WordPress from your hosting panel.
3. Log in to `/wp-admin`.
4. Go to `Settings > Permalinks`.
5. Select `Post name`.
6. Click `Save Changes`.

Pretty permalinks are important because the theme templates are mapped to clean slugs such as `/about/`, `/education/`, `/yoga-programme/`, and `/6-month-diabetes-reversal/`.

## 2. Upload The Theme

1. In WordPress admin, go to `Appearance > Themes`.
2. Click `Add New`.
3. Click `Upload Theme`.
4. Upload `nirog-bhumi-wordpress-theme.zip`.
5. Click `Install Now`.
6. Click `Activate`.

If upload size is restricted by hosting, upload the extracted `nirog-bhumi` folder to:

`wp-content/themes/nirog-bhumi`

Then activate it from `Appearance > Themes`.

## 3. Create Required Pages

Create these pages in `Pages > Add New`. The page title can be readable, but the slug must match the list below.

| Page | Slug |
|---|---|
| Home | `home` |
| About | `about` |
| Approach | `approach` |
| Mission | `mission` |
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
| Consultation Calendar | `consultation-calendar` |
| Consultation Payment | `consultation-payment` |
| Store | `store` |

WordPress will automatically use the matching `page-{slug}.php` template from the theme.

## 4. Set The Home Page

1. Go to `Settings > Reading`.
2. Choose `A static page`.
3. Set `Homepage` to the page with slug `home`.
4. Save.

The theme also has `front-page.php`, so the homepage will display the Nirog Bhumi home design.

## 5. Create The Header Menu

1. Go to `Appearance > Menus`.
2. Create a menu called `Primary`.
3. Add these links:
   - Our Programs: `/programmes/`
   - Store: `/store/`
   - Education: `/education/`
4. Assign it to `Primary Menu`.
5. Save.

If you skip this, the theme has a fallback menu with the same three links.

## 6. Install Required Plugins

Install plugins from `Plugins > Add New`.

Required:

1. `WooCommerce`
   - Used for products, cart, checkout, orders, customers, taxes, coupons and My Account.
2. `Razorpay for WooCommerce`
   - Recommended for India payments: UPI, cards, netbanking and wallets.
3. `Site Kit by Google`
   - Connects Google Analytics and Google Search Console without editing code.
4. `Rank Math SEO` or `Yoast SEO`
   - Use one SEO plugin only. Rank Math is recommended for this project because it is generous in the free version.
5. `Simply Schedule Appointments`
   - Use this for consultation time-slot booking.
6. `Fluent Forms`, `Forminator`, or `WPForms Lite`
   - Use one form plugin for interest forms, resume upload and intake forms.
7. `LiteSpeed Cache` or `WP Super Cache`
   - Use the one best supported by your hosting.
8. `UpdraftPlus`
   - Backups before plugin/theme updates.
9. `Wordfence Security` or `Solid Security`
   - Basic firewall, login protection and malware monitoring.

Optional later:

- `Advanced Custom Fields`
  - Useful when you want staff to edit sections from WordPress admin instead of code.
- `Members`
  - Useful if future program resources need restricted access.
- `WooCommerce PDF Invoices & Packing Slips`
  - Useful for invoices and order operations.

## 7. WooCommerce Setup

1. Open `WooCommerce > Home`.
2. Complete store setup.
3. Set country, currency, shipping and taxes.
4. Go to `WooCommerce > Settings > Payments`.
5. Enable Razorpay after adding Razorpay Key ID and Secret.
6. Test checkout with Razorpay test mode.
7. Go live only after a successful test order.

Create core WooCommerce pages if WooCommerce does not create them automatically:

| WooCommerce Page | Slug |
|---|---|
| Cart | `cart` |
| Checkout | `checkout` |
| My Account | `my-account` |
| Shop | `shop` |

Then go to `WooCommerce > Settings > Advanced` and assign these pages.

## 8. Products To Create

Go to `Products > Add New`.

Recommended product structure:

1. `Diabetes Reversal Kit`
   - Product type: Simple product
   - Add all included kit items in description.
2. Food products
   - Individual diabetic-friendly foods.
3. Individual cure kit items
   - Jal Neti Pot, Vijaysar Tumbler, acupressure tools, etc.
4. Combos
   - Kit + food items
   - Food combinations
   - Yoga + acupressure tools
   - Cure Kit + 6 Months Diabetes Reversal Program
5. Consultation Booking Amount
   - Price: `₹500`
   - Product type: Virtual product.

## 9. Consultation Flow Setup

Recommended flow:

1. User visits `/consultation/`.
2. User clicks CTA.
3. User fills intake form on `/consultation-intake/`.
4. Form redirects to `/consultation-calendar/`.
5. User selects preferred time slot using Simply Schedule Appointments.
6. Confirmation redirects to `/consultation-payment/`.
7. Payment page sends user to WooCommerce checkout with the `Consultation Booking Amount` product.

If you prefer payment before calendar:

1. Intake form redirects to checkout.
2. After payment success, redirect to calendar.

For Nirog Bhumi, calendar-before-payment is smoother for users; payment-before-calendar is stricter operationally.

## 10. Forms Setup

Replace the static demo forms with plugin forms:

- Diabetes interest form
- Yoga interest form
- General interest form
- Consultation intake form
- Careers resume upload form

Each form should email the admin and store entries inside WordPress.

Important fields:

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

## 11. SEO And GEO Setup

1. Install Rank Math or Yoast.
2. Set site name: `Nirog Bhumi`.
3. Set organization logo using the brand logo.
4. Submit sitemap to Google Search Console.
5. Connect Google Analytics using Site Kit.
6. Add local business details if Nirog Bhumi has a public physical center.
7. Add page titles and meta descriptions for:
   - Home
   - Consultation
   - 6 Months Diabetes Reversal Program
   - Yoga for Diabetes
   - Store
   - Education
8. Use keywords naturally:
   - diabetes reversal program
   - reverse diabetes naturally
   - yoga for diabetes
   - naturopathy for diabetes
   - diabetes consultation
   - diabetes reversal kit

## 12. Future Content Changes

For text-only changes:

1. Edit the matching PHP template in the theme.
2. Example: Yoga page is `page-yoga-programme.php`.
3. Upload the changed file by FTP/File Manager.
4. Clear cache.

For design/system changes:

1. Update `assets/css/styles.css`.
2. Update `assets/js/main.js` only if interaction changes.
3. Clear cache.
4. Test mobile, tablet and desktop.

For WooCommerce pages:

- Cart template: `woocommerce/cart/cart.php`
- Empty cart template: `woocommerce/cart/cart-empty.php`
- Checkout template: `woocommerce/checkout/form-checkout.php`
- My Account template: `woocommerce/myaccount/my-account.php`

## 13. Safe Update Workflow

Before any update:

1. Take a full backup.
2. Update plugins on staging first if available.
3. Check:
   - Home page
   - Consultation flow
   - Product add-to-cart
   - Cart
   - Checkout
   - My Account
   - Mobile navigation
4. Clear cache.
5. Only then update live.

## 14. Important Notes

- This theme is a classic PHP WordPress theme, not a block theme.
- The completed frontend pages are converted into PHP templates.
- WooCommerce templates are dynamic and ready for real products/orders.
- Static demo forms must be replaced with a real WordPress form plugin before launch.
- Do not edit WordPress core files.
- Keep a child theme only if another developer will make frequent live edits.

