<?php
/** Public privacy policy page (linked from Google Play and the app). */
require __DIR__ . '/lib/site.php';
$app = setting('app_name', 'Electrical Market Hub');
$email = setting('support_email');
$phone = setting('support_phone');
$updated = '18 November 2026';
?><?php site_head('Privacy Policy'); ?>
<div class="w nr doc">
<h1>Privacy Policy</h1><small>Last updated: <?= $updated ?></small>

<p><?= h($app) ?> ("we", "us") is an app that helps people find electrical shops, products and offers near them, and helps shop owners list their shops. This policy explains what information the app collects, why, and the choices you have.</p>

<h2>1. Information we collect</h2>
<ul>
<li><b>Account details</b> — your mobile number, name (optional), a 4-digit PIN (stored only in encrypted/hashed form) and your city.</li>
<li><b>Location</b> — with your permission, your device's approximate/precise location is used to show nearby shops and distances, and, for shop owners, to place the shop on the map and add a GPS stamp to live shop photos. Location is used while you use the app; we do not track your location in the background.</li>
<li><b>Photos and files</b> — shop photos that shop owners take or choose from their gallery, and material lists customers upload with a quote request. Camera and gallery are only opened when you tap to add a photo or file.</li>
<li><b>Shop details</b> (shop owners) — shop name, owner name, contact number, WhatsApp number, GSTIN (optional), address, timings, categories, brands and offers. Shop listings are public in the app.</li>
<li><b>Your activity</b> — quote requests, reviews and ratings, saved shops, and counts of views/calls/WhatsApp taps on shops (to show shop owners how many leads they get).</li>
<li><b>Technical data</b> — basic app logs (screen opened, app errors, app version, device type) used to fix problems.</li>
</ul>

<h2>2. How we use information</h2>
<ul>
<li>To create and secure your account and let you log in.</li>
<li>To show shops, products and offers near you and to send your quote request to nearby shops.</li>
<li>To show shop owners their listing performance and the quote requests sent to them.</li>
<li>To send in-app and phone notifications about offers and updates (you can turn notifications off in your phone settings).</li>
<li>To verify shop listings, prevent misuse, and improve the app.</li>
</ul>

<h2>3. What is shared</h2>
<ul>
<li>Shop listings (name, address, contact numbers, photos, offers, ratings) are public to app users.</li>
<li>When you send a quote request, your name, mobile number, area and requirement are shared with the shops it is sent to, so they can reply.</li>
<li>Reviews show your name and rating publicly.</li>
<li>When you tap Call, WhatsApp, Directions or Share, the matching app on your phone (Phone, WhatsApp, Maps) opens; their own privacy policies apply.</li>
<li>We use hosting/infrastructure providers to run the service. We do <b>not</b> sell your personal information and do not use it for third-party advertising.</li>
</ul>

<h2>4. Data retention and deletion</h2>
<p>We keep your information while your account is active. You can delete your account and its data at any time in the app (<b>Account → Delete my account</b>) or on our <a href="/delete-account.php">account deletion page</a>. Deletion removes your profile, PIN, saved shops, reviews, quote requests and — for shop owners — the shop listing, photos and offers. Anonymous usage counts may be kept without your identity.</p>

<h2>5. Security</h2>
<p>Data is sent over encrypted connections (HTTPS). PINs are stored only as secure hashes. Access to the admin panel is restricted to authorised staff.</p>

<h2>6. Children</h2>
<p>The app is meant for adults (18+) buying or selling electrical goods and is not directed at children.</p>

<h2>7. Changes</h2>
<p>We may update this policy; the "last updated" date above will change. Significant changes will be shown in the app.</p>

<h2>8. Contact</h2>
<p>For any privacy question or request, contact us<?= $email ? ' at <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '' ?><?= $phone ? ' or +91 ' . h($phone) : '' ?>.</p>
</div>
<?php site_foot();
