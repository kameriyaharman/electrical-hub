# Electrical Hub — shop directory app

| Part | Folder | Tech |
|---|---|---|
| Admin panel + API | `backend/` | PHP 8 + MySQL (no framework — runs on any cPanel / BigRock Linux hosting) |
| Mobile UI (customer + shop owner) | `backend/app/` | HTML/JS, also served at `/app/` for browser testing |
| Android app | `android/` | Java WebView shell (camera-only photos, GPS, call/WhatsApp/UPI intents) |

## URLs
- Admin: `https://<domain>/admin/` — first login `admin@electricalhub.in` / `Admin@123` (change it in **Settings**)
- API for the app: `https://<domain>/api/`
- Web version of the app: `https://<domain>/app/`

The database tables + sample data are created automatically on first visit.

## Move to BigRock (cPanel) later
1. cPanel → **MySQL Databases**: create a database + user, give the user ALL privileges.
2. Edit `backend/config.php` — put DB name / user / password in the fallback values (`your_db`, …) and set `APP_SECRET`.
3. Upload everything inside `backend/` (except `Dockerfile`, `docker-start.sh`) to `public_html/` (or a sub-folder) with File Manager / FTP.
4. Make sure `uploads/` is writable (755). PHP version 8.0+ (cPanel → Select PHP Version), extensions `pdo_mysql`, `curl`, `zip`.
5. Open `https://yourdomain/admin/` — tables are created automatically.
6. To move existing data from Railway: export the Railway MySQL DB (mysqldump) and import in phpMyAdmin; copy the `uploads/` folder.
7. Point the Android app to the new domain: change `apiBase` in `android/gradle.properties` (or the `API_BASE` repo variable) and rebuild.

## OTP SMS
Default is **demo mode** (OTP shown on screen). For real SMS: Settings → OTP mode = MSG91, add auth key + OTP template ID.

## Android build
GitHub Actions (`.github/workflows/android.yml`) builds a signed APK on every push and publishes it to the `apk` branch (`ElectricalHub.apk`).
Local build: Android Studio → open `android/` → Build APK.
For Play Store, create your own keystore and set `EH_KEYSTORE`, `EH_KEYSTORE_PASSWORD`, `EH_KEY_ALIAS`, `EH_KEY_PASSWORD`.
