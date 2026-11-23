<?php
/** Public website home page (needed for the Google Play listing). Admin panel is at /admin/, app at /app/. */
require __DIR__ . '/lib/site.php';
$s = site_info();
site_head('');
?>
<style>
.hero{background:var(--k);color:#fff;padding:56px 0 64px;overflow:hidden}.hero .w{display:grid;grid-template-columns:1.15fr .85fr;gap:40px;align-items:center}
.hero h1{font-size:44px;line-height:1.15;margin:0 0 14px}.hero h1 b{color:var(--o)}.hero p{color:#ccc;font-size:18px;margin:0 0 26px}
.hero .cta{display:flex;gap:12px;flex-wrap:wrap}.phone{justify-self:center;width:260px;border-radius:30px;border:8px solid #2a2a2a;box-shadow:0 30px 60px #0008;display:block}
.tag{display:inline-block;background:#FF7A0022;color:var(--o);font-weight:700;font-size:13px;padding:5px 12px;border-radius:99px;margin-bottom:14px}
section{padding:60px 0}section h2{font-size:30px;margin:0 0 8px;text-align:center}.sub{text-align:center;color:var(--mut);margin:0 auto 34px;max-width:620px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px}
.card .ic{font-size:28px}.card h3{margin:8px 0 4px;font-size:18px}.card p{margin:0;color:#4a4540;font-size:15px}
.shots{display:flex;gap:16px;overflow-x:auto;padding:4px 4px 14px;scroll-snap-type:x mandatory}.shots img{width:210px;border-radius:20px;border:1px solid var(--line);scroll-snap-align:start;flex:none}
.own{background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}.own .w{display:grid;grid-template-columns:.8fr 1.2fr;gap:40px;align-items:center}
.own ol{padding-left:20px;margin:0 0 22px}.own li{margin-bottom:8px}
.ct{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;max-width:820px;margin:0 auto}.ct a{text-decoration:none;color:inherit}
@media(max-width:800px){.hero .w,.own .w{grid-template-columns:1fr}.hero h1{font-size:34px}.grid,.ct{grid-template-columns:1fr}.phone{width:220px}.own img{display:none}}
</style>

<div class="hero"><div class="w">
  <div><span class="tag">⚡ Free app for India</span>
    <h1>Find <b>electrical shops</b>, products &amp; offers near you</h1>
    <p>Wires, switches, fans, lights, MCBs and more — see nearby shops on the map, check their brands and timings, and call or WhatsApp them in one tap. Shop owners can list their shop for free.</p>
    <div class="cta"><a class="btn" href="<?= h($s['play']) ?>">▶ Get it on Google Play</a><a class="btn o" href="/app/">Open web app</a></div>
  </div>
  <img class="phone" src="/assets/site/s1.jpg" alt="Electrical Market Hub app home screen">
</div></div>

<section id="features"><div class="w">
  <h2>Everything electrical, nearby</h2><p class="sub">Built for customers, electricians and contractors who need the right part from a shop close by.</p>
  <div class="grid">
    <div class="card"><div class="ic">📍</div><h3>Shops near you</h3><p>Search any city or area and see electrical shops sorted by distance, with directions on the map.</p></div>
    <div class="card"><div class="ic">🏷️</div><h3>Brands &amp; products</h3><p>Find which shops stock the brands and products you need — Havells, Polycab, Anchor and more.</p></div>
    <div class="card"><div class="ic">📞</div><h3>Call or WhatsApp</h3><p>Talk to the shop directly in one tap. No middleman, no extra charge.</p></div>
    <div class="card"><div class="ic">📝</div><h3>Get quotes</h3><p>Send your material list once and get price quotes from nearby shops.</p></div>
    <div class="card"><div class="ic">🎁</div><h3>Local offers</h3><p>See current offers and discounts from shops in your city.</p></div>
    <div class="card"><div class="ic">✅</div><h3>Verified shops</h3><p>Shops are checked by our team with live photos and location before they go live.</p></div>
  </div>
</div></section>

<section style="padding-top:0"><div class="w"><div class="shots">
  <?php foreach ([1, 2, 3, 4, 5] as $i): ?><img src="/assets/site/s<?= $i ?>.jpg" alt="App screenshot <?= $i ?>" loading="lazy"><?php endforeach; ?>
</div></div></section>

<section class="own" id="owners"><div class="w">
  <img class="phone" src="/assets/site/s5.jpg" alt="Shop owner dashboard">
  <div><span class="tag">For shop owners</span><h2 style="text-align:left">List your shop — free</h2>
    <p>Get calls and WhatsApp enquiries from customers near your shop.</p>
    <ol><li>Download the app and log in with your mobile number and a 4-digit PIN.</li>
      <li>Add your shop details, categories, brands and location.</li>
      <li>Upload up to 10 shop photos from the camera or gallery.</li>
      <li>Our team verifies your shop and it goes live, usually within 24 hours.</li></ol>
    <a class="btn" href="<?= h($s['play']) ?>">List my shop</a></div>
</div></section>

<section id="contact"><div class="w">
  <h2>Contact us</h2><p class="sub">Questions, shop verification or support — we're happy to help.</p>
  <div class="ct">
    <?php if ($s['phone']): ?><a class="card" href="tel:+91<?= h($s['phone']) ?>"><div class="ic">📞</div><h3>Phone</h3><p>+91 <?= h($s['phone']) ?></p></a><?php endif; ?>
    <?php if ($s['wa']): ?><a class="card" href="https://wa.me/91<?= h($s['wa']) ?>"><div class="ic">💬</div><h3>WhatsApp</h3><p>+91 <?= h($s['wa']) ?></p></a><?php endif; ?>
    <?php if ($s['email']): ?><a class="card" href="mailto:<?= h($s['email']) ?>"><div class="ic">✉️</div><h3>Email</h3><p><?= h($s['email']) ?></p></a><?php endif; ?>
  </div>
</div></section>
<!-- First-visit choice: Android app or continue in the browser -->
<style>
.ch-bg{position:fixed;inset:0;background:#000a;z-index:50;display:none;align-items:flex-end;justify-content:center}
.ch-bg.show{display:flex}
.ch{background:#fff;width:100%;max-width:440px;border-radius:22px 22px 0 0;padding:26px 22px 22px;text-align:center;animation:up .25s ease-out}
@media(min-width:600px){.ch-bg{align-items:center}.ch{border-radius:22px}}
@keyframes up{from{transform:translateY(40px);opacity:0}to{transform:none;opacity:1}}
.ch img{width:64px;height:64px;border-radius:16px}.ch h3{margin:10px 0 4px;font-size:21px}.ch p{margin:0 0 20px;color:var(--mut);font-size:15px}
.ch a{display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;font-weight:700;font-size:16px;padding:15px;border-radius:14px;margin-top:10px}
.ch .ap{background:var(--o);color:#fff}.ch .wb{background:#fff;color:#141414;border:2px solid var(--line)}
.ch .x{display:inline-block;margin-top:14px;color:var(--mut);font-size:14px;background:none;border:0;cursor:pointer}
</style>
<div class="ch-bg" id="chBg"><div class="ch" role="dialog" aria-labelledby="chT">
  <img src="/assets/site/icon.png" alt="">
  <h3 id="chT">How would you like to continue?</h3>
  <p>Use the Android app for the best experience, or continue right here in your browser.</p>
  <a class="ap" id="chApp" href="<?= h($s['play']) ?>">📱 Open Android app</a>
  <a class="wb" id="chWeb" href="/app/">🌐 Continue in browser</a>
  <button class="x" id="chX">Just view the website</button>
</div></div>
<script>
(function () {
  var bg = document.getElementById('chBg');
  function get() { try { return localStorage.getItem('eh_choice'); } catch (e) { return null; } }
  function set(v) { try { localStorage.setItem('eh_choice', v); } catch (e) {} }
  if (/android/i.test(navigator.userAgent)) {   // on Android, open the installed app (or the Play Store if not installed)
    document.getElementById('chApp').href = 'intent://details?id=in.electricalhub.app#Intent;scheme=market;package=com.android.vending;S.browser_fallback_url=' + encodeURIComponent('<?= h($s['play']) ?>') + ';end';
  }
  // returning visitors who chose the browser go straight to the web app (add ?site to see the website)
  if (get() === 'web' && !/[?&]site/.test(location.search)) { location.replace('/app/'); return; }
  if (!get()) setTimeout(function () { bg.classList.add('show'); }, 600);
  document.getElementById('chApp').onclick = function () { set('app'); bg.classList.remove('show'); };
  document.getElementById('chWeb').onclick = function () { set('web'); };
  document.getElementById('chX').onclick = function () { set('site'); bg.classList.remove('show'); };
  bg.onclick = function (e) { if (e.target === bg) bg.classList.remove('show'); };
})();
</script>
<?php site_foot();
