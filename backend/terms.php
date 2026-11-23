<?php
/** Public Terms of Use page. */
require __DIR__ . '/lib/site.php';
$s = site_info(); $app = $s['app'];
site_head('Terms of Use');
?>
<div class="w nr doc">
<h1>Terms of Use</h1><small>Last updated: 23 November 2026</small>

<p>These terms apply when you use the <?= h($app) ?> mobile app, web app and website (together, the "service"). By using the service you agree to these terms.</p>

<h2>1. What the service does</h2>
<p><?= h($app) ?> is a directory that helps people find electrical shops, products, brands and offers near them, and lets shop owners list their shops. We do not sell products ourselves. Any purchase, price, delivery, warranty or payment is a matter between you and the shop.</p>

<h2>2. Your account</h2>
<ul><li>You log in with your mobile number and a 4-digit PIN. Keep your PIN private; you are responsible for activity on your account.</li>
<li>You must be 18 or older to use the service.</li>
<li>Give correct information. We may block accounts that give false details or misuse the service.</li></ul>

<h2>3. Shop listings</h2>
<ul><li>Shop owners must list only a real shop they own or manage, with correct address, location, timings, photos and contact numbers.</li>
<li>Listings are checked by our team before going live. We may edit, reject, hide or remove a listing that is false, misleading or breaks these terms.</li>
<li>Listing a shop is free. Shop owners are responsible for the products, prices and offers they show.</li></ul>

<h2>4. Reviews, quotes and content</h2>
<ul><li>Reviews must be honest and based on a real experience. No abusive, false or promotional reviews.</li>
<li>Do not upload content that is illegal, offensive, or that you do not have the right to share.</li>
<li>By uploading photos or text you allow us to show it in the service.</li></ul>

<h2>5. Things you must not do</h2>
<ul><li>Use the service for spam, fraud or harassment.</li>
<li>Copy or scrape shop data in bulk, or try to break or overload the service.</li>
<li>Pretend to be another person or shop.</li></ul>

<h2>6. No guarantee</h2>
<p>We try to keep information accurate, but shop details, stock, prices and timings are provided by shops and can change. The service is provided "as is". We are not responsible for any loss arising from dealings between users and shops.</p>

<h2>7. Ending your account</h2>
<p>You can delete your account any time from the app (Account → Delete my account) or on our <a href="/delete-account.php">account deletion page</a>. We may suspend accounts that break these terms.</p>

<h2>8. Privacy</h2>
<p>How we handle your data is explained in our <a href="/privacy.php">Privacy Policy</a>.</p>

<h2>9. Changes and law</h2>
<p>We may update these terms; the date above will change. These terms are governed by the laws of India.</p>

<h2>10. Contact</h2>
<p>Questions about these terms: <?= $s['email'] ? '<a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : '' ?><?= $s['phone'] ? ($s['email'] ? ' · ' : '') . '+91 ' . h($s['phone']) : '' ?>.</p>
</div>
<?php site_foot();
