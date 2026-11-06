# Purrfect Pet Care: Veterinary Clinic Management System

A complete web system for a veterinary clinic. It includes a public website, online booking, digital pet health records, vaccination reminders, M-Pesa and card payments with receipts, a community forum, adoption and lost & found. There are separate dashboards for **pet owners**, **veterinarians** and **administrators**.

Built with PHP 7.4+ and MySQL/MariaDB on the back end, and Bootstrap 5 with plain JavaScript on the front end. It runs on standard cPanel shared hosting and needs no frameworks and no build step.

---

## What each user can do

| Pet owner | Veterinarian | Administrator |
|---|---|---|
| Register and log in, reset a forgotten password | Everything a vet sees across the clinic | Everything a vet can do |
| Add, edit and delete pets | Confirm, start, complete, reschedule or cancel appointments | Manage users (clients, vets, admins), activate or deactivate accounts |
| Book appointments with live availability, then reschedule or cancel | Take unassigned appointments; write visit notes | Assign vets to appointments |
| Read health records and vaccination history | Add, edit and delete health records and vaccinations | Services & prices (shown on the website and in booking) |
| Get vaccination and appointment reminders | Record cash, M-Pesa or POS payments at the desk | Reports: revenue, services, vets, species. CSV export |
| Pay by M-Pesa (STK push) or card; download receipts | Send M-Pesa payment requests to clients | Forum moderation |
| Forum posts and replies; adoption applications; lost & found reports | Adoption listings and requests | Clinic settings: name, contacts, opening hours, closed days, auto log-out |

Every user also gets in-app notifications and a profile page (edit details, change password, delete account).

**Also included:**
- **Photos and files:** pet photos; lab results, X-rays and other PDFs on pet profiles and health records; photos on lost & found reports and adoption listings. Files are private: only the pet parent and clinic staff can open them.
- **Printable vaccination certificates and full health summaries** for each pet (Print / Save as PDF).
- **Vet working hours and time off:** each vet sets their weekly hours and leave, and the booking calendar only offers times when a vet is actually available.
- **Itemised invoices:** a visit can include the service, medicines, lab tests and other charges, with a printable invoice.
- **Inventory:** medicines, vaccines and supplies, with stock levels, low-stock warnings and a history of every change. Stock goes down automatically when a vet dispenses an item.
- **Sign-up for pet parents, vets and admins:** vet and admin accounts wait for approval by an existing admin.

---

## Install on cPanel (about 15 minutes)

1. **Create the database.** In cPanel open **MySQL® Databases**, create a database and a user with a strong password, and add the user to the database with **ALL PRIVILEGES**.
2. **Upload the files.** Upload the zip to `public_html` (or a subfolder) with **File Manager** and **Extract** it. All files should sit directly in that folder, with `index.html` next to `install.php`.
3. **Create `.env`.** Copy `.env.example` to a new file called `.env` and fill in:
   - `APP_URL`: your full address, e.g. `https://purrfectpetcare.co.ke`
   - `DB_NAME`, `DB_USER`, `DB_PASS`: from step 1 (`DB_HOST` is usually `localhost`)
4. **Make `storage/` writable.** Right-click `storage`, choose **Change Permissions**, and set it to `755` (use `775` if the install says it isn't writable).
5. **Run the installer.** Visit `https://your-domain/install.php`. It checks your server and then asks for **your** admin name, email and password. Tick "Add demo data" if you want sample data to explore.
6. **Delete `install.php`** from the server. (It locks itself after one run, but removing it is safer.)
7. **Turn on SSL.** In cPanel, run **SSL/TLS Status → Run AutoSSL**, then un-comment the HTTPS redirect lines in `.htaccess`.

Then open your site and log in with the admin account you just created.

### Demo accounts (only if you ticked demo data)

All demo accounts use the password `Demo1234`:
- Owner: `owner@demo.local`, `amina@demo.local`
- Vets: `vet@demo.local`, `vet2@demo.local`

Delete or deactivate them under **Users** before going live.

### Backups (important on free hosting)

Go to **Settings & Backups** in the admin dashboard:
- **Download database backup (.sql):** everything in the system. Do this at least weekly; the Overview page reminds you after 7 days.
- **Download uploaded photos & files (.zip):** pet photos and documents.

To restore a database backup: phpMyAdmin → your database → **Import** → choose the `.sql` file.

## Staff roles

| Role | Can do |
| --- | --- |
| Administrator | Everything, including users, prices, settings, backups, reports and the activity log |
| Veterinarian | Patients, health records, vaccines and treatments, invoices, stock, own working hours |
| Receptionist | Calendar, bookings, walk-ins, check-in, pet parents, invoices, discounts and payments. Cannot read medical notes (sees allergy alerts only) |
| Vet assistant | Patients, health records (read), vaccines, deworming and flea/tick, check-in, stock |
| Pet parent | Own pets, bookings, records, invoices and payments |

Staff can sign up on the website (their account waits for admin approval) or be added by an admin under **Users**.

## Clinic workflow (v3)

- **Calendar**: day (one column per vet), week and month views, coloured by service type.
- **Walk-ins**: *New walk-in* registers a new or existing pet parent and pet and checks them in straight away. Pet parents without email get a hidden placeholder address.
- **Check-in**: an *Arrived* button on today's appointments puts the pet in the *Waiting room* tab.
- **Health alerts**: allergies, conditions, medication, diet and handling notes on each pet, shown in red on visits, invoices and certificates.
- **Vaccines & treatments**: vaccines, deworming and flea/tick, each with its own next-due date.
- **Record locking**: health records can be edited for 24 hours, then only an administrator can correct them. Every change keeps the previous version (*Edited* link).
- **Billing**: discounts per invoice line (amount or %), unpaid invoices and balance per pet parent.
- **Inventory**: cost price, stock value, food and grooming product categories.
- **Activity log** (admin): who did what and when.
- **Reports**: unpaid total, vaccination compliance, returning clients and visits by type.

### Loading and removing demo data

Go to **Settings & Backups → Demo data**.
- **Load demo data** adds 2 vets, 3 pet parents, 4 pets, 6 appointments (one today), payments, health records, vaccinations, forum posts, adoption listings, a lost-and-found report and 6 stock items. Each is shown with a **Demo** label. All demo accounts use the password `Demo1234`.
- **Remove demo data** deletes only the `@demo.local` accounts, everything linked to them and the demo stock items. Real accounts and data are not touched.

### Database upgrades

When you upload a newer version of the code, the database upgrades itself automatically the next time anyone opens the site. There is nothing to run by hand.

### Reminders, SMS and email

Pet parents are reminded when a vaccine, deworming or flea & tick treatment is due (a week before, and again while overdue, up to 30 days) and the day before an appointment. Each reminder is sent once. Reminders always appear in the app; SMS and email go out as well once you add their keys to `.env`. Demo accounts never get SMS or email.

**No cron needed.** Free hosting such as InfinityFree has no scheduled jobs, so the reminders run automatically on the first visit by anyone logged in after 7:00 each day. Where your host does have cron, you can also add a daily job:
```
php /home/YOUR_CPANEL_USER/public_html/cron/reminders.php
```
Admins manage this in **Settings → Reminders & messages**: turn SMS, email or automatic sending on or off, edit the message wording, send due reminders now, send a test message, and see a log of everything sent.

**SMS (Africa's Talking).** Create an account at africastalking.com, then add to `.env`:
```
AT_USERNAME=your-app-username     # use "sandbox" to test for free
AT_API_KEY=your-api-key
AT_SENDER_ID=                     # optional, your approved sender name
```

**Email (Brevo, recommended on free hosting).** InfinityFree blocks PHP `mail()`, so use Brevo's free plan (300 emails a day). Create an account at brevo.com, verify your sender address, create an API key, then add:
```
BREVO_API_KEY=xkeysib-...
MAIL_FROM=you@your-domain
```
On paid cPanel hosting you can instead use the server's own mail: `MAIL_ENABLED=true` and `MAIL_FROM=no-reply@your-domain` (create that address in cPanel **Email Accounts** so messages aren't flagged as spam). Email is used for password resets, payment receipts, reminders and enquiry replies.

### Enquiries and the contact form

The website has a **Send us a message** form. Messages land in **Enquiries** for reception and admin (with a notification). Logged-in pet parents can also message the clinic from **Messages to clinic**. Staff reply in the app; replies reach the pet parent in the app, and by SMS or email when set up. For website visitors without an account, replies go by SMS or email, or staff call them back.

### Weight history and digital health card

Every weight entered on a pet's profile or in a health record is kept, and the pet's **Weight** tab shows a growth chart. On a pet's profile, **Health card** creates a QR code linking to a public card with the pet's photo, vaccine status, allergies and a phone number to call if the pet is found (the clinic's number unless the owner chooses to show theirs). Medical notes and addresses are never shown. **Print tag** gives collar-tag sized QR codes. The card can be turned off, or given a new link, at any time.

### Part payments

Pet parents and staff can pay part of a bill online (M-Pesa or card). The rest stays on the invoice as the balance.

---

## Payments

Both payment methods start in **test mode**: payments succeed automatically and no money moves. The admin dashboard shows a yellow banner while test mode is on.

### M-Pesa (Safaricom Daraja STK Push)

1. Create an account at <https://developer.safaricom.co.ke>, create an app and enable **Lipa Na M-Pesa Sandbox**.
2. Put these in `.env`:
   ```
   MPESA_ENV=sandbox
   MPESA_CONSUMER_KEY=...
   MPESA_CONSUMER_SECRET=...
   MPESA_SHORTCODE=174379
   MPESA_PASSKEY=...          (the sandbox passkey from the Daraja simulator page)
   MPESA_CALLBACK_SECRET=pick-a-long-random-string
   MPESA_CALLBACK_URL=https://your-domain/api/mpesa_callback.php?key=pick-a-long-random-string
   ```
3. Test a payment with the Daraja sandbox test numbers.
4. **Go live:** apply for **Go Live** on the Daraja portal with your Paybill or Till. Safaricom emails you production keys and a passkey. Then set `MPESA_ENV=production`, your real `MPESA_SHORTCODE`, and the new keys. For a Till number use `MPESA_TRANSACTION_TYPE=CustomerBuyGoodsOnline` and `MPESA_TILL_NUMBER=...`.

Notes:
- The callback URL **must be HTTPS** and publicly reachable.
- If Safaricom's callback can't reach your server, the system also asks Safaricom for the payment status while the customer waits, so payments still confirm.

### Card payments (Paystack)

1. Create a Paystack account (Kenya), then go to **Settings → API Keys & Webhooks**.
2. Set `PAYSTACK_SECRET_KEY=sk_test_...` to test, or `sk_live_...` to take real payments.
3. Set the webhook URL to `https://your-domain/api/paystack_webhook.php`.

Customers are sent to Paystack's secure page to enter their card details. Card numbers never touch your server.

### Cash and desk payments

Vets and admins can record cash, Till or POS payments against any appointment that still has a balance. Every payment produces a printable receipt (`receipt.php`).

---

## Security built in

- Session-based login with secure, HttpOnly cookies; idle auto log-out (set in **Settings**)
- Passwords hashed with `password_hash()`; minimum 8 characters with letters and numbers
- Login lock after repeated failed attempts (15 minutes)
- CSRF token on every change; all database queries use prepared statements
- Every action checks the user's role **and** ownership on the server, so owners only ever see their own pets, appointments and payments
- Public sign-up always creates a pet-owner account; staff accounts are created by an admin
- All user text is escaped before display; CSV exports are protected against formula injection
- `.htaccess` blocks `.env`, the database files, logs and internal folders
- Password-reset links are single-use and expire after 1 hour

---

## Project structure

```
index.html            Public website (services, live pricing, login/register)
app.html              Dashboard (owner / vet / admin)
reset-password.html   Password reset page
receipt.php           Printable payment receipt
invoice.php           Printable itemised invoice
certificate.php       Printable vaccination certificate / health summary
file.php              Serves uploaded photos and documents (with permission checks)
install.php           One-time installer (delete after use)
.env.example          Configuration template -> copy to .env
api/
  index.php           JSON API router (all actions listed at the top)
  mpesa_callback.php  Safaricom STK callback
  paystack_*.php      Card payment return page and webhook
  lib/                Bootstrap (config, DB, sessions), HTTP helpers, payment gateways, migrations
  handlers/           auth, pets & appointments, medical, payments, community, admin, features
assets/               CSS, JavaScript and images
database/             schema.mysql.sql
cron/reminders.php    Daily reminders (optional cron job)
storage/              Install lock, error log, uploaded files (uploads/) and SQLite file if used
```

### Trying it on your own computer

You don't need MySQL for a quick local trial. In `.env` set `DB_DRIVER=sqlite`, then run:
```
php install.php --name="Admin" --email=admin@example.com --password=Admin1234 --demo
php -S localhost:8000
```
Then open <http://localhost:8000>.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| "The system is not installed yet" | Run `install.php`. Check that `storage/install.lock` was created. |
| Database connection failed | Check `DB_NAME`, `DB_USER` and `DB_PASS` in `.env`. On cPanel the names include your cPanel username prefix. |
| "Something went wrong" | Read `storage/php-errors.log`, or set `APP_DEBUG=true` briefly to see the exact error. |
| M-Pesa prompt doesn't arrive | Check the keys, shortcode and passkey match the same environment (sandbox or production), and that the phone is Safaricom. |
| Payment stays pending | The callback URL must be HTTPS and public. The system checks Safaricom itself after 20 seconds. |
| No emails | Set `MAIL_ENABLED=true` and use a `MAIL_FROM` address that exists on your domain. |
