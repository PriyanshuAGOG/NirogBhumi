---
id: admin-enrollments-and-payments
title: Enrollments and payments
summary: Selling through WooCommerce, payment links after consultation, manual access, pause, extend and refunds.
audience: admin
order: 40
last_reviewed: 2026-11-24
related: admin-batches-and-release, admin-device-requests
---
# In short

When a WooCommerce order for a programme product is **paid**, the buyer is enrolled automatically and receives a welcome email. **Refunded** or **cancelled** orders close that access. Everything else (complimentary places, pauses, extensions) is done in **Courses → Enrollments** and on each participant's page.

# Link a product to a programme

1. **Products → edit** the programme product (or add one).
2. Tick **Virtual** (no shipping).
3. Open the **Courses** tab and tick the programme.
4. **Update**.

The theme's store **launch state** still decides whether the product can be bought: set it to **On sale** for direct purchase (Yoga programme), or **Enquiry only** for consult-first programmes.

# Direct purchase (for example the Yoga programme)

The participant buys on the website and pays with Razorpay. Within seconds of payment:

- an account is created if they did not have one,
- they are enrolled with access for the programme's length,
- they get the welcome email with a "Choose your password" button.

# After a consultation: send a payment link

**Courses → Enrollments → Send a payment link**: email, name, programme, and an agreed price if different. This creates a pending order and emails the standard WooCommerce payment link. Access opens automatically when they pay, and your normal invoice is generated.

# Give access without payment

**Courses → Enrollments → Enroll someone** — for complimentary places or payments received another way.

# Change someone's access

Open the participant (click their name anywhere on the course pages). Under each programme:

| Button | What it does |
| --- | --- |
| Pause access | Stops access; paused days are added back on resume. |
| Resume access | Restarts access and extends the end date. |
| Extend | Adds (or removes, with a minus number) days. |
| Move to batch | Changes batch; pending participants become active. |
| Revoke access | Closes access, for example after a refund handled outside WooCommerce. |
| Reactivate | Reopens a revoked or ended enrollment. |

# Refunds

- **Full refund / cancel** in WooCommerce → access closes automatically, with a note on the order.
- **Partial refund** → access stays open and the enrollment is flagged **Needs review**. Decide and press **Mark reviewed**.
- Buying the same programme again while access is open **extends** the existing access.

# If this doesn't work

| Problem | Fix |
| --- | --- |
| Paid order but no access | Is the product linked on its Course tab? Is the order Processing/Completed? Check order notes for "Courses:". |
| Participant did not get the welcome email | Check email setup (Setup checklist step 2). They can use "Email me a login link". |
| Enrollment shows **Pending** | Batch programme with no open batch. Create a batch and use **Move to batch**. |
