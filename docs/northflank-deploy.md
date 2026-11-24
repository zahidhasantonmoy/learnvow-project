# LearnVow — Northflank Sandbox-এ Deploy গাইড

টেস্টের জন্য। প্রোডাকশন লঞ্চের সিদ্ধান্ত এখনো বাকি আছে — এটা শুধু অ্যাপটা লাইভ দেখার উপায়।

## ০. আগে যা লাগবে (গুরুত্বপূর্ণ)

1. **`audit-fixes` ব্রাঞ্চ এখনো শুধু লোকালে আছে — GitHub-এ push করা হয়নি।** Northflank GitHub থেকে deploy করে, তাই Northflank-এ ব্রাঞ্চ সিলেক্ট করার আগে ব্রাঞ্চটা GitHub-এ push করতে হবে। Push করতে তোমার অনুমতি লাগবে (এখনো দেওয়া হয়নি)। Push না করে Northflank-এ `main` ব্রাঞ্চ deploy করলে অডিটের ফিক্স ছাড়া পুরনো ভার্সন চলবে।
2. **Northflank অ্যাকাউন্ট খুলতে কার্ড লাগবে কি না — নিশ্চিত নয়।** প্রাইসিং পেজে লেখা নেই; অন্য সোর্স বলছে লাগবে না। সাইনআপের সময় জানা যাবে।

## ১. অ্যাকাউন্ট + GitHub কানেক্ট

1. [northflank.com](https://northflank.com)-এ অ্যাকাউন্ট খোলো (GitHub দিয়ে সাইনআপ করলে সুবিধা)।
2. Northflank-এ GitHub অ্যাকাউন্ট কানেক্ট করো (নতুন প্রজেক্ট বানানোর সময় অপশন আসবে)।
3. একটা প্রজেক্ট বানাও (নাম: LearnVow)।

## ২. Service বানাও (Dockerfile থেকে)

1. Create → Service → Deployment → GitHub রিপো সিলেক্ট করো: `zahidhasantonmoy/learnvow-project`
2. Branch: `audit-fixes`
3. Build type: **Dockerfile**; Dockerfile path: `./Dockerfile`
4. Port: **8080** (Northflank `$PORT` env var সেট করে; কনটেইনার নিজে ওই পোর্টে শুনবে)
5. Deploy চাপো — প্রথম build-এ ৫–১০ মিনিট লাগতে পারে (composer install + npm build)।

## ৩. MySQL addon

1. একই প্রজেক্টে Addons → **MySQL** বানাও।
2. Northflank addon-এর connection details (host, port, database, username, password) দেখাবে — এগুলো নিচের env var-এ বসবে।
3. Service-এর Environment-এ addon-টা লিঙ্ক করলে অনেক সময় `DB_*` অটো সেট হয়ে যায়; না হলে হাতে বসাও।

## ৪. Persistent volume

1. Service → Storage → Add volume
2. Mount path: **`/var/www/html/storage`**
3. Size: **1 GB** (ডেমো ডাটার জন্য যথেষ্ট)

এই ভলিউমে ডেমো EPUB, আপলোড আর লগ থাকবে। না দিলে প্রতিবার restart-এ ফাইল মুছে যাবে।

## ৫. Environment variables

Service → Environment-এ এগুলো বসাও:

```
APP_KEY=base64:...        ← লোকাল ফাইল ~/workspace/.northflank-env.txt থেকে কপি করো (এই গাইডে নেই, কখনো GitHub-এ দিও না)
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<তোমার-app>.northflank.app    ← প্রথম deploy-এর পর URL পেয়ে বসাও, তারপর redeploy করো
DB_CONNECTION=mysql
DB_HOST=<MySQL addon host>
DB_PORT=3306
DB_DATABASE=<MySQL addon database>
DB_USERNAME=<MySQL addon username>
DB_PASSWORD=<MySQL addon password>
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

(SESSION/CACHE/QUEUE ডিফল্টই database — `.env.example`-এ তাই আছে; তবু স্পষ্ট করে বসিয়ে দাও।)

## ৬. Cron job (daily subscription)

1. একই প্রজেক্টে Create → Job → Cron job
2. Schedule: `* * * * *` (প্রতি মিনিটে)
3. Command: `php artisan schedule:run`
4. একই image/branch (`audit-fixes`) আর একই env vars দাও।

এটা প্রতিদিন ০০:০৫-এ (Asia/Dhaka) `subscriptions:mark-past-due` চালাবে।

হালকা বিকল্প: প্রতিদিন ০০:০৫-এ (cron: `5 0 * * *`) সরাসরি `php artisan subscriptions:mark-past-due` চালানো — একই কাজ, কম রান।

## ৭. প্রথম deploy-এর পর চেক

1. Northflank-এর দেওয়া URL খোলো — `/bn` পেজ আসা উচিত।
2. `APP_URL`-এ ওই URL বসিয়ে service restart/redeploy করো (না হলে লগইন/সেশন ঠিকমতো চলবে না)।
3. ডেমো লগইন চেক করো — ফোন **01700000000**, পাসওয়ার্ড **`password`** (Super Admin)। আরও: 01700000001 Admin, 01700000002 Reader, 01700000003 Author (পাসওয়ার্ড একই)।
4. একটা বই খুলে রিডার চেক করো (ডেমো EPUB সিডারেই তৈরি হয়, কোনো ফাইল আপলোড লাগে না)।

## ৮. জানা সীমাবদ্ধতা

- `public/fonts/*.woff2` আর `favicon.ico` GitHub-এ নেই (binary exclude) — Northflank-এ সিস্টেম ফন্ট দেখাবে, ডিজাইন ভাঙবে না।
- Sandbox-এ **$20/মাস রিসোর্স ক্যাপ** — টেস্টের জন্য যথেষ্ট; বেশি ট্রাফিক এলে সীমা পেরোতে পারে।
- কনটেইনারে upload লিমিট **64 MB** সেট করা আছে (EPUB 50 MB + কভার 5 MB চলে; 500 MB অডিও আপলোড ডেমোতে নেই)।
- Dockerfile এই মেশিনে build-test করা হয়নি (এখানে Docker নেই) — প্রথম Northflank build-ই আসল পরীক্ষা।

## ৯. Build/deploy ব্যর্থ হলে

Build log দেখো (Northflank dashboard → Service → Builds):

- **`npm ci` ফেল করলে:** `package-lock.json` ঠিক আছে কি না দেখো।
- **`composer install` ফেল করলে:** PHP 8.3 + এক্সটেনশন (pdo_mysql, gd, intl, zip, bcmath) — Dockerfile-এ আছে।
- **`migrate` ফেল করলে:** `DB_*` env var ঠিক আছে কি না, MySQL addon চালু আছে কি না দেখো।
- **প্রথম পেজে 500 এলে:** Runtime logs দেখো; সাধারণ কারণ — `APP_KEY` বসানো হয়নি, `APP_URL` ভুল, বা storage ভলিউম মাউন্ট হয়নি।
- **Seed ফেল করলে:** logs-এ দেখো কোন সিডার আটকালো। users টেবিলে ইউজার তৈরি হয়ে থাকলে আর auto-seed হবে না — হাতে ঠিক করতে হবে।
- **Container restart লুপে গেলে:** entrypoint-এর লগে "Waiting for MySQL" বা DB এরর আছে কি না দেখো।

---

তৈরি: ২০২৬-১১-২৪ (audit-fixes ব্রাঞ্চ, Northflank Sandbox টেস্টের জন্য)।
