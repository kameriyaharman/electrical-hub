/* Electrical Hub — customer + shop-owner app (runs in the Android WebView and in any browser) */
(function () {
'use strict';

// ---------- platform ----------
const NATIVE = window.Android || null;
const API = (NATIVE && NATIVE.apiBase && NATIVE.apiBase()) || window.EH_API || (location.origin + location.pathname.replace(/\/app\/.*$|\/app$/, '') + '/api/');
const $app = document.getElementById('app');
const store = {
  get(k, d) { try { const v = localStorage.getItem('eh_' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
  set(k, v) { try { localStorage.setItem('eh_' + k, JSON.stringify(v)); } catch (e) {} },
  del(k) { try { localStorage.removeItem('eh_' + k); } catch (e) {} },
};
const S = { token: store.get('token', ''), user: store.get('user', null), city: store.get('city', null), loc: store.get('loc', null), meta: null, role: store.get('role', 'customer') };

// ---------- helpers ----------
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const inr = n => '₹' + Number(n || 0).toLocaleString('en-IN');
const qs = o => Object.entries(o).filter(([, v]) => v !== undefined && v !== null && v !== '' && v !== false).map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v)).join('&');
function toast(msg, ms) { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), ms || 2600); }
function go(h) { if (location.hash === h) route(); else location.hash = h; }
function back() { if (history.length > 1) history.back(); else go('#/home'); }

async function api(route, opts = {}) {
  const params = opts.params ? '&' + qs(opts.params) : '';
  const init = { method: opts.body || opts.form ? 'POST' : 'GET', headers: {} };
  if (S.token) init.headers['X-Auth-Token'] = S.token;
  if (opts.form) init.body = opts.form;
  else if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
  let res, data;
  try { res = await fetch(API + '?r=' + route + params, init); data = await res.json(); }
  catch (e) { throw new Error('No internet / server not reachable'); }
  if (res.status === 401) { logout(true); throw new Error(data.error || 'Please login'); }
  if (!data.ok) throw new Error(data.error || 'Something went wrong');
  return data;
}
async function meta() { if (!S.meta) S.meta = await api('meta'); return S.meta; }

function icon(n, cls) {
  const p = {
    bolt: '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>',
    home: '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    store: '<path d="M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    tag: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    pin: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
    wa: '<path d="M3 21l1.6-4.8A8.5 8.5 0 1 1 8 19.6z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8c-1-.4-2-1.4-2.4-2.4l.8-1-1-2L9 9.5z"/>',
    nav: '<path d="m3 11 19-9-9 19-2-8z"/>',
    share: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
    back: '<path d="M15 18l-6-6 6-6"/>',
    chev: '<path d="m9 18 6-6-6-6"/>',
    heart: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
    file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    star: '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
    help: '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
    camera: '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    vcheck: '<path d="M12 2l2.4 2.2 3.2-.4.6 3.2 2.8 1.6-1.4 2.9 1.4 2.9-2.8 1.6-.6 3.2-3.2-.4L12 22l-2.4-2.2-3.2.4-.6-3.2-2.8-1.6L4.4 12 3 9.1l2.8-1.6.6-3.2 3.2.4z"/><path d="m8.5 12 2.5 2.5 4.5-5" stroke="#fff"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    map: '<path d="m1 6 7-3 8 3 7-3v15l-7 3-8-3-7 3z"/><path d="M8 3v15M16 6v15"/>',
    filter: '<path d="M22 3H2l8 9.5V19l4 2v-8.5z"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    rupee: '<path d="M6 3h12M6 8h12M6 13l8.5 8M6 13h3c6.7 0 6.7-10 0-10"/>',
    crown: '<path d="m2 18 2-12 5 5 3-7 3 7 5-5 2 12z"/>',
    gps: '<circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
  };
  return '<svg class="ic ' + (cls || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (p[n] || '') + '</svg>';
}
const BOLT = '<svg viewBox="0 0 24 24"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/></svg>';
const CAT_EMOJI = { 'Wires': '🔌', 'Switch': '🎚️', 'Light': '💡', 'MCB': '⚡', 'Fan': '🌀', 'Conduit': '🧰', 'Motor': '⚙️', 'Tool': '🛠️', 'Solar': '☀️' };
const emojiFor = name => { for (const k in CAT_EMOJI) if ((name || '').indexOf(k) >= 0) return CAT_EMOJI[k]; return '⚡'; };

function hd(title, opts = {}) {
  return '<div class="hd">' + (opts.noBack ? '' : '<button class="bk" data-act="back">' + icon('back') + '</button>') + '<h1>' + esc(title) + '</h1>' + (opts.right || '') + '</div>';
}
function bottomNav(active) {
  const items = [['home', 'Home', '#/home'], ['store', 'Shops', '#/shops'], ['grid', 'Products', '#/products'], ['tag', 'Offers', '#/offers'], ['user', 'Account', '#/account']];
  return '<nav class="nav">' + items.map(i => '<a href="' + i[2] + '" class="' + (active === i[1] ? 'on' : '') + '">' + icon(i[0]) + i[1] + '</a>').join('') + '</nav>';
}
const loading = () => '<div class="spin"></div>';
function render(html) { $app.innerHTML = html; window.scrollTo(0, 0); }

// ---------- location ----------
function getLocation(force) {
  return new Promise(resolve => {
    if (S.loc && !force && Date.now() - (S.loc.t || 0) < 10 * 60 * 1000) return resolve(S.loc);
    const done = (lat, lng) => { if (lat) { S.loc = { lat: +lat, lng: +lng, t: Date.now() }; store.set('loc', S.loc); resolve(S.loc); } else resolve(S.loc || null); };
    if (NATIVE && NATIVE.getLocation) {
      const cb = '__loc' + Date.now();
      window[cb] = (lat, lng) => { delete window[cb]; done(lat, lng); };
      try { NATIVE.getLocation(cb); } catch (e) { done(); }
      setTimeout(() => { if (window[cb]) { delete window[cb]; done(); } }, 15000);
    } else if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(p => done(p.coords.latitude, p.coords.longitude), () => done(), { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
    } else done();
  });
}
function cityId() { return (S.city && S.city.id) || (S.user && S.user.city_id) || 1; }

// ---------- external actions ----------
function openExt(url) {
  if (NATIVE && NATIVE.openUrl) NATIVE.openUrl(url); else window.open(url, '_blank');
}
function lead(id, type) { api('lead', { body: { shop_id: id, type } }).catch(() => {}); }
function callShop(s) { lead(s.id, 'call'); openExt('tel:+91' + s.mobile); }
function waShop(s, text) { lead(s.id, 'whatsapp'); openExt('https://wa.me/91' + (s.whatsapp || s.mobile) + '?text=' + encodeURIComponent(text || ('Hi ' + s.name + ', I found your shop on Electrical Hub. '))); }
function dirShop(s) { lead(s.id, 'directions'); openExt(s.lat ? 'https://www.google.com/maps/dir/?api=1&destination=' + s.lat + ',' + s.lng : 'https://www.google.com/maps/search/' + encodeURIComponent(s.name + ' ' + (s.address || ''))); }
function shareText(text) {
  if (NATIVE && NATIVE.share) NATIVE.share(text);
  else if (navigator.share) navigator.share({ text }).catch(() => {});
  else { navigator.clipboard && navigator.clipboard.writeText(text); toast('Copied to clipboard'); }
}
function shareShop(s) { lead(s.id, 'share'); shareText(s.name + ' — ' + (s.address || s.area || '') + '\nCall: +91 ' + s.mobile + (s.lat ? '\nMap: https://maps.google.com/?q=' + s.lat + ',' + s.lng : '') + '\n\nFound on Electrical Hub app'); }

// ---------- shop card ----------
function tags(s) {
  return '<div class="tags"><span class="t rt">' + (s.rating ? s.rating.toFixed(1) : 'New') + ' ★</span>' +
    (s.open ? '<span class="t op">Open</span>' : '<span class="t cl">Closed</span>') +
    (s.plan === 'premium' ? '<span class="t pm">★ PREMIUM</span>' : s.featured ? '<span class="t ft">★ FEATURED</span>' : '') + '</div>';
}
function shopCard(s) {
  const ph = s.photo ? 'style="background-image:url(\'' + esc(s.photo) + '\')"' : '';
  return '<div class="card shop" data-shop="' + s.id + '"><div class="ph" ' + ph + '>' + (s.photo ? '' : icon('store')) + '</div><div class="in">' +
    '<h4>' + esc(s.name) + '</h4><div class="ad">' + icon('pin') + esc([s.area, s.city].filter(Boolean).join(', ')) + (s.distance_km != null ? ' · ' + s.distance_km + ' km' : '') + '</div>' + tags(s) +
    '<div class="bt"><div class="ico-btns"><button class="cb call" data-call="' + s.id + '">' + icon('phone') + '</button><button class="cb wa" data-wa="' + s.id + '">' + icon('wa') + '</button></div>' +
    '<button class="btn sm">View shop</button></div></div></div>';
}
let CARD_CACHE = {};
function bindCards(list) {
  list.forEach(s => CARD_CACHE[s.id] = s);
}
document.addEventListener('click', e => {
  const t = e.target.closest('[data-call],[data-wa],[data-shop],[data-act],[data-href]');
  if (!t) return;
  if (t.dataset.call) { e.stopPropagation(); callShop(CARD_CACHE[t.dataset.call]); return; }
  if (t.dataset.wa) { e.stopPropagation(); waShop(CARD_CACHE[t.dataset.wa]); return; }
  if (t.dataset.act === 'back') { back(); return; }
  if (t.dataset.href) { go(t.dataset.href); return; }
  if (t.dataset.shop) { go('#/shop/' + t.dataset.shop); return; }
});

// ---------- sheet ----------
function sheet(html) { const s = document.getElementById('sheet'); s.querySelector('.sheet-box').innerHTML = html; s.classList.add('show'); return s.querySelector('.sheet-box'); }
function closeSheet() { document.getElementById('sheet').classList.remove('show'); }
document.getElementById('sheet').addEventListener('click', e => { if (e.target.id === 'sheet') closeSheet(); });

async function pickCity() {
  const m = await meta();
  const box = sheet('<h3>Choose city</h3><button class="btn o blk" id="useLoc" style="margin-bottom:10px">' + icon('gps') + ' Use my current location</button>' +
    m.cities.map(c => '<div class="li" data-city="' + c.id + '"><span>' + esc(c.name) + '<br><small class="muted">' + esc(c.state) + '</small></span>' + (cityId() == c.id ? icon('check') : '') + '</div>').join(''));
  box.querySelector('#useLoc').onclick = async () => {
    toast('Getting your location…');
    const l = await getLocation(true);
    if (!l) return toast('Location not available — turn on GPS');
    let best = null, bd = 1e9;
    m.cities.forEach(c => { if (!c.lat) return; const d = Math.hypot(c.lat - l.lat, c.lng - l.lng); if (d < bd) { bd = d; best = c; } });
    if (best) setCity(best);
    closeSheet(); route();
  };
  box.querySelectorAll('[data-city]').forEach(el => el.onclick = () => { setCity(m.cities.find(c => c.id == el.dataset.city)); closeSheet(); route(); });
}
function setCity(c) {
  S.city = { id: +c.id, name: c.name, state: c.state }; store.set('city', S.city);
  if (S.token) api('me/update', { body: { city_id: c.id } }).catch(() => {});
}

// ===================== SCREENS =====================

// ---------- Login ----------
function screenLogin() {
  let role = S.role || 'customer', mobile = '', step = 1, demo = '';
  const draw = () => {
    render('<div class="login"><div class="top"><div class="big">' + BOLT + '</div><h2>Electrical <b>Hub</b></h2><p>Every electrical shop near you — in one app</p></div>' +
      '<div class="box">' + (step === 1 ?
        '<label class="l">Mobile number</label><div style="display:flex;gap:8px"><span class="inp" style="width:auto;flex:none;color:var(--mute)">+91</span><input class="inp" id="mob" type="tel" inputmode="numeric" maxlength="10" placeholder="98xxxxxx10" value="' + esc(mobile) + '"></div>' +
        '<div class="roles"><button data-role="customer" class="' + (role === 'customer' ? 'on' : '') + '">I am a customer</button><button data-role="owner" class="' + (role === 'owner' ? 'on' : '') + '">I own a shop</button></div>' +
        '<button class="btn blk" id="send" style="margin-top:16px">Get OTP</button>'
        :
        '<label class="l">Enter the 4-digit OTP sent to +91 ' + esc(mobile) + '</label><div class="otp">' + [0, 1, 2, 3].map(i => '<input inputmode="numeric" maxlength="1" data-i="' + i + '">').join('') + '</div>' +
        (demo ? '<div class="demo">Demo mode (no SMS gateway yet) — your OTP is <b>' + demo + '</b></div>' : '') +
        '<button class="btn blk" id="verify" style="margin-top:16px">Continue</button><p style="text-align:center;font-size:12.5px;margin:12px 0 0"><a href="#" id="chg" style="color:var(--blue)">Change number</a> · <a href="#" id="resend" style="color:var(--blue)">Resend OTP</a></p>') +
      '</div><p style="text-align:center;font-size:12px;color:var(--mute);margin:16px"><a href="#/home" id="skip">Skip for now — browse shops</a></p></div>');
    if (step === 1) {
      const mob = document.getElementById('mob');
      mob.oninput = () => mobile = mob.value.replace(/\D/g, '');
      document.querySelectorAll('[data-role]').forEach(b => b.onclick = () => { role = b.dataset.role; draw(); });
      document.getElementById('send').onclick = send;
    } else {
      const ins = [...document.querySelectorAll('.otp input')];
      ins.forEach((inp, i) => {
        inp.oninput = () => { inp.value = inp.value.replace(/\D/g, '').slice(-1); if (inp.value && ins[i + 1]) ins[i + 1].focus(); if (ins.every(x => x.value)) verify(); };
        inp.onkeydown = e => { if (e.key === 'Backspace' && !inp.value && ins[i - 1]) ins[i - 1].focus(); };
      });
      ins[0].focus();
      if (demo) { /* helpful autofill for demo mode */ ins.forEach((x, i) => x.placeholder = demo[i]); }
      document.getElementById('verify').onclick = verify;
      document.getElementById('chg').onclick = e => { e.preventDefault(); step = 1; draw(); };
      document.getElementById('resend').onclick = e => { e.preventDefault(); send(); };
    }
  };
  async function send() {
    if (mobile.length !== 10) return toast('Enter your 10-digit mobile number');
    try { const r = await api('auth/send-otp', { body: { mobile } }); demo = r.demo_otp || ''; step = 2; draw(); toast(r.message); }
    catch (e) { toast(e.message); }
  }
  async function verify() {
    const code = [...document.querySelectorAll('.otp input')].map(x => x.value).join('');
    if (code.length !== 4) return toast('Enter the 4-digit OTP');
    try {
      const r = await api('auth/verify-otp', { body: { mobile, code, role } });
      S.token = r.token; S.user = r.user; S.role = role;
      store.set('token', r.token); store.set('user', r.user); store.set('role', role);
      if (!S.city) { const m = await meta(); const c = m.cities.find(x => x.id == r.user.city_id); if (c) setCity(c); }
      go(role === 'owner' ? '#/owner' : '#/home');
    } catch (e) { toast(e.message); }
  }
  draw();
}
function logout(silent) {
  if (!silent && S.token) api('auth/logout', { body: {} }).catch(() => {});
  S.token = ''; S.user = null; store.del('token'); store.del('user');
  go('#/login');
}
function needLogin(msg) {
  if (S.token) return false;
  toast(msg || 'Please login with your mobile number');
  go('#/login'); return true;
}

// ---------- Home ----------
async function screenHome() {
  const cname = S.city ? S.city.name + (S.city.state ? ', ' + S.city.state : '') : 'Choose city';
  render('<div class="scr"><div class="home-hd"><div class="row"><div class="brand"><span class="bolt">' + BOLT + '</span>Electrical <b>Hub</b></div><span style="flex:1"></span>' +
    '<button data-href="#/notifications" style="border:0;background:none;color:#fff;display:flex;cursor:pointer">' + icon('bell') + '</button></div>' +
    '<button class="city" id="city">' + icon('pin') + esc(cname) + ' ▾</button>' +
    '<form class="srch" id="sf">' + icon('search') + '<input id="q" placeholder="Search shops, products, brands..." enterkeyhint="search"></form></div>' +
    '<div class="wrap" id="body">' + loading() + '</div></div>' + bottomNav('Home'));
  document.getElementById('city').onclick = pickCity;
  document.getElementById('sf').onsubmit = e => { e.preventDefault(); const q = document.getElementById('q').value.trim(); if (q) go('#/shops?q=' + encodeURIComponent(q)); };
  if (!S.city) { const m = await meta().catch(() => null); if (m && m.cities.length) { const def = m.cities.find(c => c.id == (S.user && S.user.city_id)) || m.cities[0]; setCity(def); document.getElementById('city').innerHTML = icon('pin') + esc(def.name + ', ' + def.state) + ' ▾'; } }
  const loc = await getLocation();
  try {
    const d = await api('home', { params: { city_id: cityId(), lat: loc && loc.lat, lng: loc && loc.lng } });
    bindCards(d.featured); bindCards(d.nearby);
    const banners = d.banners.length ? d.banners : [{ title: 'All Electrical Products in One Place' }];
    let h = '<div class="banners">' + banners.map((b, i) => '<div class="bnr" ' + (b.image ? 'style="background-image:url(\'' + esc(b.image) + '\')"' : '') + (b.shop_id ? ' data-shop="' + b.shop_id + '"' : '') + '>' +
      (b.image ? '' : '<b>' + (i === 0 ? esc(b.title).replace(/in One Place/i, '<em>in One Place</em>') : esc(b.title)) + '</b>' + (b.subtitle ? '<small>' + esc(b.subtitle) + '</small>' : '') + '<span class="art">' + (i % 2 ? '💡' : '🔌') + '</span>') + '</div>').join('') + '</div>';
    h += '<div class="sec"><h3>Categories</h3></div><div class="cats">' + d.categories.map(c => '<div class="cat" data-href="#/shops?category_id=' + c.id + '&cname=' + encodeURIComponent(c.name) + '"><div class="em">' + (c.image ? '<img src="' + esc(c.image) + '">' : esc(c.icon || emojiFor(c.name))) + '</div>' + esc(c.name) + '</div>').join('') + '</div>';
    if (d.featured.length) h += '<div class="sec"><h3>Featured shops near you</h3><a href="#/shops">View all</a></div>' + d.featured.map(shopCard).join('');
    const rest = d.nearby.filter(s => !d.featured.find(f => f.id === s.id));
    if (rest.length) h += '<div class="sec"><h3>' + (loc ? 'Nearest to you' : 'More shops') + '</h3><a href="#/map">Map view</a></div>' + rest.map(shopCard).join('');
    if (!d.featured.length && !rest.length) h += '<div class="empty"><div class="big">🏪</div>No shops listed in this city yet.<br><a href="#" id="chg2" style="color:var(--blue)">Change city</a></div>';
    h += '<div class="own" data-href="#/owner">' + icon('store') + '<div><b>Own an electrical shop?</b><small>Get listed free & get customer calls</small></div></div>';
    document.getElementById('body').innerHTML = h;
    const c2 = document.getElementById('chg2'); if (c2) c2.onclick = e => { e.preventDefault(); pickCity(); };
  } catch (e) { document.getElementById('body').innerHTML = '<div class="empty">' + esc(e.message) + '<br><br><button class="btn sm" onclick="location.reload()">Retry</button></div>'; }
}

// ---------- Shops list ----------
async function screenShops(p) {
  const f = { q: p.q || '', category_id: p.category_id || '', brand_id: p.brand_id || '', product_id: p.product_id || '', sort: p.sort || 'nearest', open_now: p.open_now || '', min_rating: p.min_rating || '', shop_type: p.shop_type || '' };
  const title = p.pname || p.cname || (p.q ? '“' + p.q + '”' : 'Electrical shops');
  const m = await meta().catch(() => ({ categories: [] }));
  const chip = (lbl, key, val, on) => '<button class="chip ' + (on ? 'on' : '') + '" data-k="' + key + '" data-v="' + val + '">' + lbl + '</button>';
  render('<div class="scr">' + hd(title, { right: '<button class="r" data-href="#/map">' + icon('map') + '</button>' }) +
    '<div class="wrap" style="padding-bottom:0"><div class="chips">' +
    '<button class="chip" id="flt">' + icon('filter') + 'Filter</button>' +
    chip('Nearest', 'sort', f.sort === 'nearest' ? 'rating' : 'nearest', f.sort === 'nearest') +
    chip('Open now', 'open_now', f.open_now ? '' : '1', !!f.open_now) +
    chip('4 ★ +', 'min_rating', f.min_rating ? '' : '4', !!f.min_rating) +
    chip('Wholesale', 'shop_type', f.shop_type ? '' : 'Wholesale', !!f.shop_type) + '</div>' +
    '<div class="chips" style="margin-top:6px">' + chip('All', 'category_id', '', !f.category_id) + m.categories.map(c => chip(esc(c.name.replace(/ \(.*\)| &.*/, '')), 'category_id', c.id, f.category_id == c.id)).join('') + '</div>' +
    '<p class="muted" id="cnt" style="margin:8px 0 0"></p></div><div class="wrap" id="list">' + loading() + '</div></div>' + bottomNav('Shops'));
  document.querySelectorAll('.chip[data-k]').forEach(b => b.onclick = () => {
    const np = Object.assign({}, p, { [b.dataset.k]: b.dataset.v });
    if (b.dataset.k === 'category_id') { delete np.cname; const c = m.categories.find(x => x.id == b.dataset.v); if (c) np.cname = c.name; }
    go('#/shops?' + qs(np));
  });
  document.getElementById('flt').onclick = () => {
    const box = sheet('<h3>Filter shops</h3><label class="l">Search</label><input class="inp" id="fq" value="' + esc(f.q) + '" placeholder="Shop, product or brand">' +
      '<label class="l">Brand</label><select class="inp" id="fb"><option value="">Any brand</option>' + (m.brands || []).map(b => '<option value="' + b.id + '"' + (f.brand_id == b.id ? ' selected' : '') + '>' + esc(b.name) + '</option>').join('') + '</select>' +
      '<label class="l">Shop type</label><select class="inp" id="ft"><option value="">Any</option><option' + (f.shop_type === 'Retail' ? ' selected' : '') + '>Retail</option><option' + (f.shop_type === 'Wholesale' ? ' selected' : '') + '>Wholesale</option></select>' +
      '<label class="l">Sort by</label><select class="inp" id="fs"><option value="nearest">Nearest</option><option value="rating"' + (f.sort === 'rating' ? ' selected' : '') + '>Top rated</option></select>' +
      '<div style="display:flex;gap:10px;margin-top:16px"><button class="btn o" id="fr" style="flex:1">Clear</button><button class="btn" id="fa" style="flex:2">Apply</button></div>');
    box.querySelector('#fr').onclick = () => { closeSheet(); go('#/shops'); };
    box.querySelector('#fa').onclick = () => { closeSheet(); go('#/shops?' + qs(Object.assign({}, p, { q: box.querySelector('#fq').value.trim(), brand_id: box.querySelector('#fb').value, shop_type: box.querySelector('#ft').value, sort: box.querySelector('#fs').value }))); };
  };
  const loc = await getLocation();
  try {
    const d = await api('shops', { params: Object.assign({ city_id: cityId(), lat: loc && loc.lat, lng: loc && loc.lng }, f) });
    bindCards(d.shops);
    document.getElementById('cnt').textContent = d.shops.length + ' shop' + (d.shops.length === 1 ? '' : 's') + ' in ' + (S.city ? S.city.name : 'your city');
    document.getElementById('list').innerHTML = d.shops.length ? d.shops.map(shopCard).join('') : '<div class="empty"><div class="big">🔍</div>No shops match. Try removing a filter.</div>';
  } catch (e) { document.getElementById('list').innerHTML = '<div class="empty">' + esc(e.message) + '</div>'; }
}

// ---------- Map ----------
async function screenMap() {
  render('<div class="scr">' + hd('Map view', { right: '<button class="r" data-href="#/shops">' + icon('store') + '</button>' }) + '<div id="map" class="map"></div><div class="mapcard" id="mc"></div></div>' + bottomNav('Shops'));
  const loc = await getLocation();
  const m = await meta().catch(() => ({ cities: [] }));
  const c = m.cities.find(x => x.id == cityId());
  const center = loc ? [loc.lat, loc.lng] : c && c.lat ? [+c.lat, +c.lng] : [20.59, 78.96];
  if (!window.L) { document.getElementById('map').innerHTML = '<div class="empty">Map could not load</div>'; return; }
  const map = L.map('map', { zoomControl: false }).setView(center, loc || c ? 12 : 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
  if (loc) L.marker([loc.lat, loc.lng], { icon: L.divIcon({ className: '', html: '<div class="me-dot"></div>', iconSize: [16, 16] }) }).addTo(map);
  try {
    const d = await api('shops', { params: { city_id: cityId(), lat: loc && loc.lat, lng: loc && loc.lng, limit: 100 } });
    bindCards(d.shops);
    const pts = [];
    d.shops.filter(s => s.lat).forEach(s => {
      pts.push([s.lat, s.lng]);
      L.marker([s.lat, s.lng], { icon: L.divIcon({ className: '', html: '<div class="pin ' + (s.featured ? 'f' : '') + '"></div>', iconSize: [30, 30], iconAnchor: [15, 30] }) })
        .addTo(map).on('click', () => { document.getElementById('mc').innerHTML = shopCard(s); });
    });
    if (pts.length && !loc) map.fitBounds(pts, { padding: [40, 40], maxZoom: 14 });
    if (d.shops[0]) document.getElementById('mc').innerHTML = shopCard(d.shops[0]);
  } catch (e) { toast(e.message); }
}

// ---------- Shop page ----------
async function screenShop(id) {
  render('<div class="scr nonav">' + loading() + '</div>');
  let s;
  try { s = (await api('shop', { params: { id } })).shop; } catch (e) { render('<div class="scr">' + hd('Shop') + '<div class="empty">' + esc(e.message) + '</div></div>'); return; }
  CARD_CACHE[s.id] = s;
  const loc = S.loc;
  let dist = '';
  if (loc && s.lat) { const R = 6371, dLat = (s.lat - loc.lat) * Math.PI / 180, dLng = (s.lng - loc.lng) * Math.PI / 180; const a = Math.sin(dLat / 2) ** 2 + Math.cos(loc.lat * Math.PI / 180) * Math.cos(s.lat * Math.PI / 180) * Math.sin(dLng / 2) ** 2; dist = ' · ' + (R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))).toFixed(1) + ' km'; }
  const photos = s.photos.length ? s.photos : [{ url: '' }];
  const thumbs = s.photos.slice(0, 3).map((p, i) => '<div data-ph="' + i + '" style="background-image:url(\'' + esc(p.url) + '\')"></div>').join('') + (s.photos.length > 3 ? '<div class="more" data-ph="3">+' + (s.photos.length - 3) + '</div>' : '');
  render('<div class="scr nonav"><div class="gal"><div class="sl">' + photos.map(p => '<div style="background-image:url(\'' + esc(p.url) + '\')"></div>').join('') + '</div>' +
    '<div class="tb"><button data-act="back">' + icon('back') + '</button><button class="sv ' + (s.saved ? 'on' : '') + '" id="save">' + icon('heart') + '</button></div>' +
    (s.photos.length ? '<div class="live"><i></i>LIVE PHOTOS · updated ' + esc(s.photos_updated) + '</div>' : '') + '</div>' +
    '<div class="sp"><h2>' + esc(s.name) + (s.verified ? icon('vcheck') : '') + '</h2><div class="muted" style="margin:3px 0 8px">' + esc(s.address || s.area) + dist + '</div>' +
    '<div class="tags"><span class="t rt">' + (s.rating ? s.rating.toFixed(1) : 'New') + ' ★</span><span class="muted">(' + s.review_count + ' reviews)</span>' + (s.open ? '<span class="t op">Open</span><span class="muted">till ' + esc(s.close_time) + '</span>' : '<span class="t cl">Closed</span><span class="muted">opens ' + esc(s.open_time) + '</span>') + (s.featured ? '<span class="t ft">★ ' + esc(s.plan.toUpperCase()) + '</span>' : '') + '</div>' +
    '<div class="acts"><button id="a1"><span class="cb call">' + icon('phone') + '</span>Call</button><button id="a2"><span class="cb wa">' + icon('wa') + '</span>WhatsApp</button><button id="a3"><span class="cb dir">' + icon('nav') + '</span>Directions</button><button id="a4"><span class="cb sh">' + icon('share') + '</span>Share</button></div></div>' +
    '<div class="sp" style="margin-top:8px">' +
    (s.photos.length ? '<div class="h5" style="margin-top:0">Shop photos</div><div class="thumbs">' + thumbs + '</div>' : '') +
    (s.offers.length ? '<div class="h5">Offers</div>' + s.offers.map(o => '<div class="offer"><b>🏷️ ' + esc(o.title) + '</b><div class="muted">' + esc(o.description || '') + (o.valid_till ? ' · till ' + esc(o.valid_till) : '') + '</div></div>').join('') : '') +
    (s.brands.length ? '<div class="h5">Brands available</div><div class="chips wrapc">' + s.brands.map(b => '<span class="chip">' + esc(b.name) + '</span>').join('') + '</div>' : '') +
    (s.categories.length ? '<div class="h5">Categories</div><div class="chips wrapc">' + s.categories.map(c => '<span class="chip">' + esc(c.icon || '') + ' ' + esc(c.name) + '</span>').join('') + '</div>' : '') +
    (s.products.length ? '<div class="h5">Products</div>' + s.products.map(p => '<div class="prod"><div class="pi">' + emojiFor(p.name) + '</div><div><b>' + esc(p.name) + '</b><span class="pr">' + (p.price ? inr(p.price) : inr(p.min_price) + ' – ' + inr(p.max_price)) + '</span></div></div>').join('') : '') +
    '<div class="h5">Shop details</div><div class="muted" style="line-height:1.8">' + icon('clock', '') + ' ' + esc(s.open_time) + ' – ' + esc(s.close_time) + (s.weekly_off ? ' · ' + esc(s.weekly_off) + ' closed' : '') + '<br>Type: ' + esc(s.shop_type) + (s.is_service ? ' + electrician service' : '') + (s.gstin ? '<br>GSTIN: ' + esc(s.gstin) : '') + '</div>' +
    '<div class="h5" style="display:flex;justify-content:space-between">Reviews <a href="#" id="wr" style="color:var(--blue);font-size:12.5px">Write a review</a></div>' +
    (s.reviews.length ? s.reviews.map(r => '<div class="rev"><b>' + esc(r.customer_name) + '</b> <span class="stars">' + '★'.repeat(r.rating) + '</span><span style="color:#d7dce8">' + '★'.repeat(5 - r.rating) + '</span><div class="muted">' + esc(r.date) + '</div><div>' + esc(r.body || '') + '</div></div>').join('') : '<div class="muted">No reviews yet — be the first.</div>') +
    '<div class="cta" style="margin-top:16px" data-href="#/quote?shop=' + s.id + '"><b>Need many items?</b><span>Send your list to this and nearby shops — get quotes</span></div></div></div>' +
    '<div class="foot"><button class="btn" id="b1">' + icon('phone') + ' Call</button><button class="btn wa" id="b2">' + icon('wa') + ' WhatsApp</button></div>');
  const call = () => callShop(s), wa = () => waShop(s);
  ['a1', 'b1'].forEach(i => document.getElementById(i).onclick = call);
  ['a2', 'b2'].forEach(i => document.getElementById(i).onclick = wa);
  document.getElementById('a3').onclick = () => dirShop(s);
  document.getElementById('a4').onclick = () => shareShop(s);
  document.getElementById('save').onclick = async e => {
    if (needLogin('Login to save shops')) return;
    try { const r = await api('saved/toggle', { body: { shop_id: s.id } }); e.currentTarget.classList.toggle('on', r.saved); toast(r.saved ? 'Saved' : 'Removed from saved'); } catch (er) { toast(er.message); }
  };
  document.querySelectorAll('[data-ph]').forEach(t => t.onclick = () => { const sl = document.querySelector('.gal .sl'); sl.scrollTo({ left: sl.clientWidth * +t.dataset.ph, behavior: 'smooth' }); window.scrollTo({ top: 0, behavior: 'smooth' }); });
  document.getElementById('wr').onclick = e => { e.preventDefault(); if (!needLogin('Login to write a review')) reviewSheet(s); };
}
function reviewSheet(s) {
  let rating = 0;
  const box = sheet('<h3>Rate ' + esc(s.name) + '</h3><div class="rstars">' + [1, 2, 3, 4, 5].map(i => '<span data-r="' + i + '">★</span>').join('') + '</div><label class="l">Your review</label><textarea class="inp" id="rb" placeholder="Stock, price, service…"></textarea><button class="btn blk" id="rs" style="margin-top:14px">Submit review</button>');
  box.querySelectorAll('[data-r]').forEach(x => x.onclick = () => { rating = +x.dataset.r; box.querySelectorAll('[data-r]').forEach(y => y.classList.toggle('on', +y.dataset.r <= rating)); });
  box.querySelector('#rs').onclick = async () => {
    if (!rating) return toast('Tap the stars to rate');
    try { await api('reviews/create', { body: { shop_id: s.id, rating, body: box.querySelector('#rb').value } }); closeSheet(); toast('Thanks for your review!'); route(); } catch (e) { toast(e.message); }
  };
}

// ---------- Products ----------
async function screenProducts(p) {
  const m = await meta().catch(() => ({ categories: [] }));
  render('<div class="scr">' + hd('Products', { right: '<button class="r" id="ps">' + icon('search') + '</button>' }) +
    '<div class="wrap" style="padding-bottom:0"><div class="chips"><button class="chip ' + (!p.category_id ? 'on' : '') + '" data-c="">All</button>' + m.categories.map(c => '<button class="chip ' + (p.category_id == c.id ? 'on' : '') + '" data-c="' + c.id + '">' + esc(c.name.replace(/ \(.*\)| &.*/, '')) + '</button>').join('') + '</div>' +
    (p.q ? '<p class="muted">Results for “' + esc(p.q) + '” · <a href="#/products" style="color:var(--blue)">clear</a></p>' : '') + '</div><div class="wrap" id="pl">' + loading() + '</div></div>' + bottomNav('Products'));
  document.querySelectorAll('[data-c]').forEach(b => b.onclick = () => go('#/products?' + qs({ category_id: b.dataset.c })));
  document.getElementById('ps').onclick = () => { const box = sheet('<h3>Search products</h3><input class="inp" id="pq" placeholder="e.g. 1.5 sq mm wire, Havells MCB"><button class="btn blk" id="pgo" style="margin-top:12px">Search</button>'); const i = box.querySelector('#pq'); i.focus(); box.querySelector('#pgo').onclick = () => { closeSheet(); go('#/products?' + qs({ q: i.value.trim() })); }; };
  try {
    const d = await api('products', { params: { category_id: p.category_id, q: p.q, city_id: cityId() } });
    document.getElementById('pl').innerHTML = (d.products.length ? d.products.map(x => '<div class="card prod" data-href="#/shops?' + qs({ product_id: x.id, pname: x.name }) + '"><div class="pi">' + (x.image ? '<img src="' + esc(x.image) + '">' : emojiFor(x.category)) + '</div><div style="flex:1"><b>' + esc(x.name) + '</b><span class="pr">' + inr(x.min_price) + ' – ' + inr(x.max_price) + '</span><div class="muted">Available at ' + x.shop_count + ' shop' + (x.shop_count == 1 ? '' : 's') + '</div></div>' + icon('chev') + '</div>').join('') : '<div class="empty">No products found</div>') +
      '<div class="cta" data-href="#/quote"><b>Need many items?</b><span>Send your list — get quotes from nearby shops</span></div>';
  } catch (e) { document.getElementById('pl').innerHTML = '<div class="empty">' + esc(e.message) + '</div>'; }
}

// ---------- Offers ----------
async function screenOffers() {
  render('<div class="scr">' + hd('Offers', { noBack: true }) + '<div class="wrap" id="ol">' + loading() + '</div></div>' + bottomNav('Offers'));
  try {
    const d = await api('offers', { params: { city_id: cityId() } });
    document.getElementById('ol').innerHTML = d.offers.length ? d.offers.map(o => '<div class="card offer" data-shop="' + o.shop_id + '" style="margin-bottom:10px"><b>🏷️ ' + esc(o.title) + '</b><div>' + esc(o.description || '') + '</div><div class="muted" style="margin-top:4px">' + esc(o.shop_name) + ' · ' + esc(o.area || '') + (o.valid_till ? ' · till ' + esc(o.valid_till) : '') + '</div></div>').join('') : '<div class="empty"><div class="big">🏷️</div>No offers in your city right now.</div>';
  } catch (e) { document.getElementById('ol').innerHTML = '<div class="empty">' + esc(e.message) + '</div>'; }
}

// ---------- Quote ----------
async function screenQuote(p) {
  if (needLogin('Login to send your requirement')) return;
  let file = null, count = 5, verified = true;
  render('<div class="scr nonav">' + hd('Get quote from shops') + '<div class="wrap">' +
    '<label class="l" style="margin-top:0">What do you need?</label><textarea class="inp" id="items" placeholder="Wire 1.5 sq mm 5 coils, MCB 32A × 4, LED panel 12W × 20"></textarea>' +
    '<label class="l">Or upload your list</label><label class="inp" style="display:flex;align-items:center;justify-content:center;gap:8px;border-style:dashed;color:var(--mute);cursor:pointer" id="ul">' + icon('camera') + '<span id="fn">Photo / PDF of material list</span><input type="file" id="file" accept="image/*,application/pdf" hidden></label>' +
    '<label class="l">Delivery area</label><input class="inp" id="area" value="' + esc(S.city ? S.city.name : '') + '">' +
    '<label class="l">Send to</label><div class="chips wrapc"><button class="chip on" id="cnt">5 nearest shops</button><button class="chip on" id="vo">Only verified</button></div>' +
    (p.shop ? '<p class="muted">Includes the shop you were viewing.</p>' : '') +
    '<div id="recent"></div></div><div class="foot"><button class="btn" id="send">Send requirement</button></div></div>');
  document.getElementById('file').onchange = e => { file = e.target.files[0] || null; document.getElementById('fn').textContent = file ? '📎 ' + file.name : 'Photo / PDF of material list'; };
  document.getElementById('cnt').onclick = e => { count = count === 5 ? 10 : count === 10 ? 3 : 5; e.currentTarget.textContent = count + ' nearest shops'; };
  document.getElementById('vo').onclick = e => { verified = !verified; e.currentTarget.classList.toggle('on', verified); };
  getLocation();
  try {
    const qd = await api('quotes');
    if (qd.quotes.length) { const q = qd.quotes[0]; document.getElementById('recent').innerHTML = '<div class="card" style="margin-top:16px" data-href="#/quote/' + q.id + '"><b>Your last request · ' + esc(q.code) + '</b><div class="muted">' + esc(q.items.slice(0, 60)) + '</div><div style="margin-top:6px">' + q.replies + ' of ' + q.sent_to + ' shops replied ›</div></div>'; }
  } catch (e) {}
  document.getElementById('send').onclick = async () => {
    const items = document.getElementById('items').value.trim();
    if (!items && !file) return toast('Write what you need or upload your list');
    const fd = new FormData();
    fd.append('items', items); fd.append('area', document.getElementById('area').value); fd.append('send_count', count); fd.append('verified_only', verified ? '1' : '');
    fd.append('city_id', cityId()); if (S.loc) { fd.append('lat', S.loc.lat); fd.append('lng', S.loc.lng); }
    if (p.shop) fd.append('include_shop', p.shop);
    if (file) fd.append('file', file);
    const b = document.getElementById('send'); b.disabled = true; b.textContent = 'Sending…';
    try { const r = await api('quotes/create', { form: fd }); toast('Sent to ' + r.sent_to + ' shops. Replies will appear here.'); go('#/quote/' + r.quote_id); }
    catch (e) { toast(e.message); b.disabled = false; b.textContent = 'Send requirement'; }
  };
}
async function screenQuoteView(id) {
  render('<div class="scr nonav">' + hd('Quote request') + '<div class="wrap" id="qv">' + loading() + '</div></div>');
  try {
    const q = (await api('quote', { params: { id } })).quote;
    q.replies.forEach(r => CARD_CACHE['q' + r.shop_id] = { id: r.shop_id, name: r.name, mobile: r.mobile, whatsapp: r.whatsapp });
    document.getElementById('qv').innerHTML = '<div class="card quote-card"><b>' + esc(q.code) + '</b> <span class="muted">· ' + esc(q.area || '') + '</span><div style="white-space:pre-line;margin-top:6px">' + esc(q.items) + '</div>' + (q.attachment ? '<a class="muted" href="#" id="att">📎 View your uploaded list</a>' : '') + '</div>' +
      '<div class="h5">Replies</div><div class="card">' + q.replies.map(r => '<div class="reply"><div><b>' + esc(r.name) + '</b><div class="muted">' + esc(r.area || '') + (r.reply_note ? ' · ' + esc(r.reply_note) : '') + '</div></div><div style="text-align:right">' + (r.replied_at ? '<b>' + inr(r.reply_amount) + '</b><div class="muted">' + esc(r.delivery || '') + '</div><div class="ico-btns" style="justify-content:flex-end;margin-top:4px"><button class="cb call" data-qc="' + r.shop_id + '">' + icon('phone') + '</button><button class="cb wa" data-qw="' + r.shop_id + '">' + icon('wa') + '</button></div>' : '<span class="muted">awaiting</span>') + '</div></div>').join('') + '</div>' +
      '<p class="muted" style="text-align:center">Shops reply with their best price. Pull down / reopen to refresh.</p>';
    document.querySelectorAll('[data-qc]').forEach(b => b.onclick = () => callShop(CARD_CACHE['q' + b.dataset.qc]));
    document.querySelectorAll('[data-qw]').forEach(b => b.onclick = () => waShop(CARD_CACHE['q' + b.dataset.qw], 'Hi, about your quote for ' + q.code + ' on Electrical Hub: '));
    const att = document.getElementById('att'); if (att) att.onclick = e => { e.preventDefault(); openExt(q.attachment); };
  } catch (e) { document.getElementById('qv').innerHTML = '<div class="empty">' + esc(e.message) + '</div>'; }
}
async function screenQuotes() {
  if (needLogin()) return;
  render('<div class="scr">' + hd('My quote requests') + '<div class="wrap" id="ql">' + loading() + '</div></div>' + bottomNav('Account'));
  const d = await api('quotes').catch(e => ({ quotes: [] }));
  document.getElementById('ql').innerHTML = (d.quotes.length ? d.quotes.map(q => '<div class="card" style="margin-bottom:10px" data-href="#/quote/' + q.id + '"><div style="display:flex;justify-content:space-between"><b>' + esc(q.code) + '</b><span class="muted">' + esc(q.date) + '</span></div><div class="muted">' + esc(q.items.slice(0, 80)) + '</div><div style="margin-top:6px;color:var(--blue);font-weight:500">' + q.replies + ' of ' + q.sent_to + ' shops replied ›</div></div>').join('') : '<div class="empty"><div class="big">📝</div>No requests yet</div>') + '<button class="btn blk" data-href="#/quote">+ New requirement</button>';
}

// ---------- Account ----------
async function screenAccount() {
  if (!S.token) {
    render('<div class="scr">' + hd('Account', { noBack: true }) + '<div class="empty"><div class="big">👤</div>Login to save shops, send quote requests and write reviews.<br><br><button class="btn" data-href="#/login">Login with mobile</button></div>' +
      '<div class="list">' + li('pin', 'Change city', '', 'city') + li('store', 'List your shop (free)', '', '#/owner') + li('help', 'Help & support', '', 'help') + '</div></div>' + bottomNav('Account'));
    bindAccount(); return;
  }
  render('<div class="scr">' + hd('Account', { noBack: true }) + '<div id="ac">' + loading() + '</div></div>' + bottomNav('Account'));
  try {
    const d = await api('me');
    S.user = Object.assign(S.user || {}, { name: d.user.name, role: d.user.role }); store.set('user', S.user);
    const nm = d.user.name || 'Add your name';
    const ini = (d.user.name || 'U').split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase();
    document.getElementById('ac').innerHTML = '<div class="prof"><div class="av">' + esc(ini) + '</div><div style="flex:1"><b style="font-size:16px;color:var(--navy)">' + esc(nm) + '</b><div class="muted">+91 ' + esc(d.user.mobile) + (d.user.city ? ' · ' + esc(d.user.city.name) : '') + '</div></div><button class="btn sm o" id="ed">Edit</button></div>' +
      '<div class="list">' + li('heart', 'Saved shops', d.counts.saved, '#/saved') + li('file', 'My quote requests', d.counts.quotes, '#/quotes') + li('star', 'My reviews', d.counts.reviews, '#/my-reviews') +
      li('bell', 'Notifications', d.counts.notifications ? d.counts.notifications + ' new' : '', '#/notifications') + li('pin', 'Change city', '', 'city') +
      (d.has_shop ? li('store', 'My shop dashboard', '', '#/owner') : li('store', 'List your shop (free)', '', '#/owner')) + li('help', 'Help & support', '', 'help') + li('logout', 'Logout', '', 'logout') + '</div>' +
      (d.has_shop ? '' : '<div class="own" data-href="#/owner">' + icon('store') + '<div><b>Own an electrical shop?</b><small>Get listed free & get customer calls</small></div></div>') +
      '<p class="muted" style="text-align:center">Electrical Hub v1.0</p>';
    bindAccount();
    document.getElementById('ed').onclick = () => {
      const box = sheet('<h3>Your name</h3><input class="inp" id="nm" value="' + esc(d.user.name || '') + '"><button class="btn blk" id="sv" style="margin-top:12px">Save</button>');
      box.querySelector('#sv').onclick = async () => { await api('me/update', { body: { name: box.querySelector('#nm').value.trim() } }).catch(e => toast(e.message)); closeSheet(); route(); };
    };
  } catch (e) { document.getElementById('ac').innerHTML = '<div class="empty">' + esc(e.message) + '</div>'; }
}
function li(ic, label, right, target) { return '<div class="li" data-li="' + esc(target) + '">' + icon(ic) + '<span>' + esc(label) + '</span><em>' + esc(right === 0 ? '0' : right || '') + '</em>' + icon('chev', 'ch') + '</div>'; }
function bindAccount() {
  document.querySelectorAll('[data-li]').forEach(el => el.onclick = () => {
    const t = el.dataset.li;
    if (t === 'city') pickCity(); else if (t === 'logout') { if (confirm('Logout?')) logout(); } else if (t === 'help') helpSheet(); else go(t);
  });
}
async function helpSheet() {
  const m = await meta();
  const box = sheet('<h3>Help & support</h3><p class="muted">We usually reply within a few hours.</p><button class="btn blk wa" id="hw">' + icon('wa') + ' WhatsApp support</button><button class="btn blk o" id="hc" style="margin-top:10px">' + icon('phone') + ' Call ' + esc(m.support.phone) + '</button>' + (m.support.email ? '<p class="muted" style="text-align:center">' + esc(m.support.email) + '</p>' : ''));
  box.querySelector('#hw').onclick = () => openExt('https://wa.me/91' + m.support.whatsapp + '?text=' + encodeURIComponent('Hi Electrical Hub, I need help with '));
  box.querySelector('#hc').onclick = () => openExt('tel:+91' + m.support.phone);
}
async function screenSaved() {
  if (needLogin()) return;
  render('<div class="scr">' + hd('Saved shops') + '<div class="wrap" id="sl">' + loading() + '</div></div>' + bottomNav('Account'));
  const d = await api('saved').catch(() => ({ shops: [] }));
  bindCards(d.shops);
  document.getElementById('sl').innerHTML = d.shops.length ? d.shops.map(shopCard).join('') : '<div class="empty"><div class="big">♡</div>Tap the heart on a shop to save it here.</div>';
}
async function screenMyReviews() {
  if (needLogin()) return;
  render('<div class="scr">' + hd('My reviews') + '<div class="wrap" id="rl">' + loading() + '</div></div>' + bottomNav('Account'));
  const d = await api('my-reviews').catch(() => ({ reviews: [] }));
  document.getElementById('rl').innerHTML = d.reviews.length ? d.reviews.map(r => '<div class="card" style="margin-bottom:10px" data-shop="' + r.shop_id + '"><b>' + esc(r.shop_name) + '</b> <span class="stars">' + '★'.repeat(r.rating) + '</span><div>' + esc(r.body || '') + '</div>' + (r.status !== 'active' ? '<div class="muted">Under review</div>' : '') + '</div>').join('') : '<div class="empty">No reviews yet</div>';
}
async function screenNotifications() {
  if (needLogin()) return;
  render('<div class="scr">' + hd('Notifications') + '<div class="wrap" id="nl">' + loading() + '</div></div>' + bottomNav('Account'));
  const d = await api('notifications').catch(() => ({ notifications: [] }));
  document.getElementById('nl').innerHTML = d.notifications.length ? d.notifications.map(n => '<div class="card" style="margin-bottom:10px;' + (n.is_read ? '' : 'border-left:4px solid var(--yellow)') + '"><b>' + esc(n.title) + '</b><div>' + esc(n.body || '') + '</div><div class="muted">' + esc(n.date) + '</div></div>').join('') : '<div class="empty"><div class="big">🔔</div>No notifications</div>';
}

// ===================== SHOP OWNER =====================
let OWNER = null;
async function loadOwner() { OWNER = (await api('owner/shop')).shop; return OWNER; }

async function screenOwner() {
  if (needLogin('Login with your shop mobile number')) return;
  render('<div class="scr nonav">' + loading() + '</div>');
  try { await loadOwner(); } catch (e) { render('<div class="scr">' + hd('My shop') + '<div class="empty">' + esc(e.message) + '</div></div>'); return; }
  if (!OWNER) return go('#/owner/edit?step=2');
  if (OWNER.status === 'draft' || (OWNER.status === 'rejected')) { if (!OWNER.photos.length) return go('#/owner/photos'); }
  const d = await api('owner/dashboard');
  const s = d.shop, st = d.stats;
  const statusPill = s.status === 'verified' ? '<span class="pill" style="background:#E3F6EA;color:#0E7B3C">Verified</span> <span style="opacity:.8;font-size:12px">· listing live</span>'
    : s.status === 'pending' ? '<span class="pill" style="background:#FFF3D6;color:#a86200">Under verification</span> <span style="opacity:.8;font-size:12px">· usually within 24 hrs</span>'
    : s.status === 'rejected' ? '<span class="pill" style="background:#FDE8E8;color:#C22B31">Needs changes</span> <span style="opacity:.8;font-size:12px">· ' + esc(s.reject_reason || '') + '</span>'
    : s.status === 'blocked' ? '<span class="pill" style="background:#FDE8E8;color:#C22B31">Blocked</span> <span style="opacity:.8;font-size:12px">· contact support</span>'
    : '<span class="pill" style="background:#eee;color:#555">Incomplete</span>';
  const q = d.quotes.find(x => !x.replied_at);
  render('<div class="scr nonav"><div class="dash-hd"><div style="display:flex;align-items:center;justify-content:space-between"><div class="brand"><span class="bolt">' + BOLT + '</span>Electrical <b>Hub</b></div>' +
    (s.plan !== 'free' ? '<span class="pill" style="background:var(--yellow);color:var(--navy)">' + esc(s.plan.toUpperCase()) + '</span>' : '<button class="btn sm y" data-href="#/owner/plans">Upgrade</button>') + '</div>' +
    '<h2>' + esc(s.name) + '</h2>' + statusPill + (s.plan_expiry ? '<div style="font-size:11.5px;opacity:.75;margin-top:6px">Plan valid till ' + esc(s.plan_expiry) + '</div>' : '') + '</div>' +
    (s.status === 'rejected' ? '<div class="wrap"><div class="note">Admin asked for changes: <b>' + esc(s.reject_reason || '') + '</b>. Fix it and tap <b>Submit again</b>.<br><button class="btn sm" id="resub" style="margin-top:8px">Submit again</button></div></div>' : '') +
    '<div class="stats"><div class="stat"><small>Profile views</small><b>' + st.views.toLocaleString('en-IN') + '</b><em>' + (st.views_change >= 0 ? '▲ ' : '▼ ') + Math.abs(st.views_change) + '% this week</em></div>' +
    '<div class="stat"><small>Calls</small><b>' + st.calls + '</b><em>this month</em></div><div class="stat"><small>WhatsApp clicks</small><b>' + st.whatsapp + '</b><em>this month</em></div>' +
    '<div class="stat" data-href="#/owner/quotes"><small>Quote requests</small><b>' + st.quotes + '</b><em>' + st.new_quotes + ' new</em></div></div>' +
    (q ? '<div class="wrap"><div class="card"><b style="color:var(--navy)">New quote request</b><div class="muted">' + esc(q.items.replace(/\n/g, ', ').slice(0, 70)) + ' · ' + esc(q.area || '') + '</div>' +
      '<div style="display:flex;gap:8px;margin-top:10px"><button class="btn sm" style="flex:1" id="sp">Send price</button><button class="btn sm wa" style="flex:1" id="sw">WhatsApp</button></div></div></div>' : '<div style="height:14px"></div>') +
    '<div class="menu">' + li('camera', 'Update live photos', st.photos, '#/owner/photos') + li('tag', 'Post an offer', '', '#/owner/offers') + li('grid', 'Products & brands', '', '#/owner/edit?step=3') +
    li('clock', 'Shop timings', '', '#/owner/edit?step=4') + li('store', 'Shop details & location', '', '#/owner/edit?step=2') + li('star', 'Reviews (' + st.reviews + ')', '', '#/owner/reviews') + li('file', 'All quote requests', '', '#/owner/quotes') +
    li('crown', 'Plans — Featured / Premium', s.plan, '#/owner/plans') + li('search', 'Browse as customer', '', '#/home') + '</div></div>');
  bindAccount();
  if (q) { document.getElementById('sp').onclick = () => replySheet(q); document.getElementById('sw').onclick = () => openExt('https://wa.me/91' + q.customer_mobile + '?text=' + encodeURIComponent('Hi ' + (q.customer_name || '') + ', this is ' + s.name + ' from Electrical Hub regarding your requirement ' + q.code + ': ')); }
  const rs = document.getElementById('resub'); if (rs) rs.onclick = async () => { try { await api('owner/submit', { body: {} }); toast('Sent for verification'); route(); } catch (e) { toast(e.message); } };
}
function replySheet(q) {
  const box = sheet('<h3>Send price · ' + esc(q.code) + '</h3><div class="note" style="white-space:pre-line">' + esc(q.items) + '</div>' +
    '<label class="l">Total price (₹)</label><input class="inp" id="amt" type="number" inputmode="numeric">' +
    '<label class="l">Delivery</label><div class="chips wrapc" id="dl">' + ['Delivery today', 'Tomorrow', 'Pickup only', '2–3 days'].map((x, i) => '<button class="chip ' + (i === 0 ? 'on' : '') + '">' + x + '</button>').join('') + '</div>' +
    '<label class="l">Note (optional)</label><input class="inp" id="nt" placeholder="Brands, GST bill, etc."><button class="btn blk" id="go" style="margin-top:14px">Send price to customer</button>');
  let del = 'Delivery today';
  box.querySelectorAll('#dl .chip').forEach(c => c.onclick = () => { del = c.textContent; box.querySelectorAll('#dl .chip').forEach(x => x.classList.toggle('on', x === c)); });
  box.querySelector('#go').onclick = async () => {
    const amount = +box.querySelector('#amt').value; if (!amount) return toast('Enter the price');
    try { await api('owner/quote-reply', { body: { quote_id: q.id, amount, delivery: del, note: box.querySelector('#nt').value } }); closeSheet(); toast('Price sent!'); route(); } catch (e) { toast(e.message); }
  };
}
async function screenOwnerQuotes() {
  if (needLogin()) return;
  render('<div class="scr nonav">' + hd('Quote requests') + '<div class="wrap" id="oq">' + loading() + '</div></div>');
  const d = await api('owner/dashboard');
  document.getElementById('oq').innerHTML = d.quotes.length ? d.quotes.map((q, i) => '<div class="card" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between"><b>' + esc(q.code) + '</b><span class="muted">' + esc(q.date) + '</span></div><div style="white-space:pre-line;font-size:13px;margin:4px 0">' + esc(q.items) + '</div><div class="muted">' + esc(q.customer_name || '') + ' · ' + esc(q.area || '') + '</div>' +
    (q.replied_at ? '<div style="color:var(--green2);font-weight:600;margin-top:6px">You quoted ' + inr(q.reply_amount) + '</div>' : '<div style="display:flex;gap:8px;margin-top:8px"><button class="btn sm" style="flex:1" data-rq="' + i + '">Send price</button><button class="btn sm wa" style="flex:1" data-wq="' + i + '">WhatsApp</button></div>') + '</div>').join('') : '<div class="empty"><div class="big">📭</div>No quote requests yet. Featured & Premium shops get more requests.</div>';
  document.querySelectorAll('[data-rq]').forEach(b => b.onclick = () => replySheet(d.quotes[b.dataset.rq]));
  document.querySelectorAll('[data-wq]').forEach(b => b.onclick = () => { const q = d.quotes[b.dataset.wq]; openExt('https://wa.me/91' + q.customer_mobile + '?text=' + encodeURIComponent('Hi, this is ' + d.shop.name + ' from Electrical Hub regarding ' + q.code + ': ')); });
}

// ----- Listing wizard: step 2 details, 3 categories/brands, 4 location & timings -----
async function screenOwnerEdit(p) {
  if (needLogin('Login with your shop mobile number')) return;
  const step = +(p.step || 2);
  const m = await meta();
  if (OWNER === null) await loadOwner().catch(() => {});
  const s = OWNER || {};
  const isNew = !OWNER;
  const D = Object.assign({ name: '', owner_name: S.user && S.user.name || '', mobile: S.user ? S.user.mobile : '', whatsapp: '', gstin: '', shop_type: 'Retail', is_service: false, address: '', area: '', pincode: '', city_id: cityId(), lat: null, lng: null, open_time_24: '09:00', close_time_24: '21:00', weekly_off: '' }, s);
  D.cats = (s.categories || []).map(c => c.id); D.brs = (s.brands || []).map(b => b.id); D.prods = (s.products || []).map(x => x.id);
  if (store.get('draft')) Object.assign(D, store.get('draft'));
  const title = isNew ? 'List your shop' : 'Edit shop';
  const stepper = '<div class="steps">' + [1, 2, 3, 4].map(i => '<i class="' + (i <= step ? 'on' : '') + '"></i>').join('') + '</div><div class="muted" style="margin-bottom:6px">Step ' + step + ' of 4 · ' + ['', 'Mobile', 'Shop details', 'Categories & brands', 'Location & timings'][step] + '</div>';
  let body = '';
  if (step === 2) body = '<label class="l">Shop name</label><input class="inp" id="name" value="' + esc(D.name) + '" placeholder="KK Electrical">' +
    '<label class="l">Owner name</label><input class="inp" id="owner_name" value="' + esc(D.owner_name) + '">' +
    '<label class="l">Mobile / WhatsApp</label><input class="inp" id="mobile" type="tel" maxlength="10" value="' + esc(D.mobile) + '">' +
    '<label class="l">GSTIN (optional)</label><input class="inp" id="gstin" value="' + esc(D.gstin || '') + '" placeholder="33AAxxxxx1Z5" style="text-transform:uppercase">' +
    '<label class="l">Shop type</label><div class="chips wrapc" id="stype">' + ['Retail', 'Wholesale', 'Both'].map(t => '<button class="chip ' + (D.shop_type === t ? 'on' : '') + '" data-t="' + t + '">' + t + '</button>').join('') + '<button class="chip ' + (D.is_service ? 'on' : '') + '" id="svc">Service / electrician</button></div>';
  if (step === 3) body = '<label class="l">Categories</label><div class="chips wrapc" id="cats">' + m.categories.map(c => '<button class="chip ' + (D.cats.includes(+c.id) ? 'on' : '') + '" data-id="' + c.id + '">' + esc(c.name) + '</button>').join('') + '</div>' +
    '<label class="l">Brands you sell</label><div class="chips wrapc" id="brs">' + m.brands.map(b => '<button class="chip ' + (D.brs.includes(+b.id) ? 'on' : '') + '" data-id="' + b.id + '">' + esc(b.name) + '</button>').join('') + '</div>' +
    '<label class="l">Popular products you stock</label><div class="chips wrapc" id="prs">' + loading() + '</div>';
  if (step === 4) body = '<label class="l">Location</label><div class="minimap" id="mm"></div><button class="btn sm o" id="gps" style="margin-top:8px">' + icon('gps') + ' Use my current location</button> <span class="muted" id="ll">' + (D.lat ? 'Pin set ✓' : 'Drag the pin to your shop') + '</span>' +
    '<label class="l">City</label><select class="inp" id="city_id">' + m.cities.map(c => '<option value="' + c.id + '"' + (D.city_id == c.id ? ' selected' : '') + '>' + esc(c.name) + '</option>').join('') + '</select>' +
    '<label class="l">Area</label><input class="inp" id="area" value="' + esc(D.area || '') + '" placeholder="GN Street">' +
    '<label class="l">Full address</label><input class="inp" id="address" value="' + esc(D.address || '') + '" placeholder="No. 222, GN Street">' +
    '<label class="l">Pincode</label><input class="inp" id="pincode" inputmode="numeric" maxlength="6" value="' + esc(D.pincode || '') + '">' +
    '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px"><div><label class="l">Opens</label><input class="inp" type="time" id="open_time" value="' + esc(D.open_time_24) + '"></div><div><label class="l">Closes</label><input class="inp" type="time" id="close_time" value="' + esc(D.close_time_24) + '"></div><div><label class="l">Weekly off</label><select class="inp" id="weekly_off"><option value="">None</option>' + ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(d => '<option' + (D.weekly_off === d ? ' selected' : '') + '>' + d + '</option>').join('') + '</select></div></div>';
  render('<div class="scr nonav">' + hd(title) + '<div class="wrap">' + stepper + body + '</div><div class="foot">' + (step > 2 ? '<button class="btn o" id="prev" style="flex:1">Back</button>' : '') + '<button class="btn" id="next" style="flex:2">' + (step < 4 ? 'Next' : (isNew || !s.photos || !s.photos.length ? 'Next: Upload photos' : 'Save')) + '</button></div></div>');
  const saveDraft = () => store.set('draft', D);
  if (step === 2) {
    document.querySelectorAll('#stype [data-t]').forEach(b => b.onclick = () => { D.shop_type = b.dataset.t; document.querySelectorAll('#stype [data-t]').forEach(x => x.classList.toggle('on', x === b)); });
    document.getElementById('svc').onclick = e => { D.is_service = !D.is_service; e.currentTarget.classList.toggle('on', D.is_service); };
  }
  if (step === 3) {
    const tog = (sel, arr) => document.querySelectorAll(sel + ' [data-id]').forEach(b => b.onclick = () => { const id = +b.dataset.id; const i = arr.indexOf(id); if (i >= 0) arr.splice(i, 1); else arr.push(id); b.classList.toggle('on', i < 0); });
    tog('#cats', D.cats); tog('#brs', D.brs);
    api('products').then(d => { document.getElementById('prs').innerHTML = d.products.map(x => '<button class="chip ' + (D.prods.includes(+x.id) ? 'on' : '') + '" data-id="' + x.id + '">' + esc(x.name) + '</button>').join(''); tog('#prs', D.prods); });
  }
  if (step === 4 && window.L) {
    const c = m.cities.find(x => x.id == D.city_id) || {};
    const start = D.lat ? [D.lat, D.lng] : S.loc ? [S.loc.lat, S.loc.lng] : c.lat ? [+c.lat, +c.lng] : [20.59, 78.96];
    const map = L.map('mm', { zoomControl: false }).setView(start, D.lat || S.loc ? 16 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    const mk = L.marker(start, { draggable: true, icon: L.divIcon({ className: '', html: '<div class="pin"></div>', iconSize: [30, 30], iconAnchor: [15, 30] }) }).addTo(map);
    const setLL = ll => { D.lat = +ll.lat.toFixed(7); D.lng = +ll.lng.toFixed(7); document.getElementById('ll').textContent = 'Pin set ✓'; };
    mk.on('dragend', () => setLL(mk.getLatLng()));
    map.on('click', e => { mk.setLatLng(e.latlng); setLL(e.latlng); });
    document.getElementById('gps').onclick = async () => { toast('Getting location…'); const l = await getLocation(true); if (!l) return toast('Turn on GPS / location'); mk.setLatLng([l.lat, l.lng]); map.setView([l.lat, l.lng], 17); setLL({ lat: l.lat, lng: l.lng }); };
    if (!D.lat && S.loc) setLL({ lat: S.loc.lat, lng: S.loc.lng });
  }
  const collect = () => {
    if (step === 2) ['name', 'owner_name', 'mobile', 'gstin'].forEach(k => D[k] = document.getElementById(k).value.trim());
    if (step === 4) ['area', 'address', 'pincode', 'open_time', 'close_time', 'weekly_off', 'city_id'].forEach(k => { const v = document.getElementById(k).value.trim(); if (k === 'open_time' || k === 'close_time') D[k + '_24'] = v; else D[k] = v; });
    saveDraft();
  };
  const pb = document.getElementById('prev'); if (pb) pb.onclick = () => { collect(); go('#/owner/edit?step=' + (step - 1)); };
  document.getElementById('next').onclick = async () => {
    collect();
    if (step === 2) { if (!D.name) return toast('Enter shop name'); if (!/^\d{10}$/.test(D.mobile)) return toast('Enter 10-digit mobile'); }
    if (step === 3 && !D.cats.length) return toast('Pick at least one category');
    if (step < 4 && isNew) return go('#/owner/edit?step=' + (step + 1));
    if (step === 4 && !D.lat) return toast('Set your shop location on the map');
    if (step === 4 && !D.area) return toast('Enter your area');
    // save
    try {
      await api('owner/shop/save', { body: { name: D.name, owner_name: D.owner_name, mobile: D.mobile, whatsapp: D.mobile, gstin: D.gstin, shop_type: D.shop_type, is_service: D.is_service ? 1 : 0, city_id: D.city_id, area: D.area, address: D.address, pincode: D.pincode, lat: D.lat, lng: D.lng, open_time: D.open_time_24, close_time: D.close_time_24, weekly_off: D.weekly_off, categories: D.cats, brands: D.brs, products: D.prods } });
      store.del('draft'); await loadOwner();
      if (!OWNER.photos.length) go('#/owner/photos'); else { toast('Saved'); go('#/owner'); }
    } catch (e) { toast(e.message); }
  };
}

// ----- Live photos (camera only, GPS + time stamp) -----
const REQUIRED = ['Shop front', 'Inside view', 'Product shelves', 'Billing counter'];
async function screenOwnerPhotos() {
  if (needLogin()) return;
  render('<div class="scr nonav">' + loading() + '</div>');
  await loadOwner();
  if (!OWNER) return go('#/owner/edit?step=2');
  const ph = OWNER.photos;
  const have = new Set(ph.map(x => x.label));
  const next = REQUIRED.find(r => !have.has(r)) || 'More';
  render('<div class="scr nonav">' + hd('Live shop photos') + '<div class="wrap"><div class="note">📸 Photos must be clicked live from the shop (camera only, no gallery) — shows customers your real shop. GPS & time stamp added.</div>' +
    '<div class="pgrid">' + ph.map(x => '<div class="p" style="background-image:url(\'' + esc(x.url) + '\')"><span>' + esc(x.label || '') + ' · ' + esc(x.taken_at) + '</span><button data-del="' + x.id + '">×</button></div>').join('') +
    '<label class="p take">' + icon('camera') + 'Take photo<small class="muted" style="font-weight:400">' + esc(next) + '</small><input type="file" id="cam" accept="image/*" capture="environment" hidden></label></div>' +
    '<div class="h5">Required</div><div class="req">' + REQUIRED.map(r => '<div class="' + (have.has(r) ? 'done' : '') + '">' + icon('check') + r + '</div>').join('') + '</div></div>' +
    '<div class="foot"><button class="btn" id="sub">' + (OWNER.status === 'verified' ? 'Done' : 'Submit for verification') + '</button></div></div>');
  getLocation(true);
  document.getElementById('cam').onchange = async e => {
    const f = e.target.files[0]; if (!f) return;
    toast('Adding GPS & time stamp…', 6000);
    const loc = await getLocation(true);
    try {
      const blob = await stampPhoto(f, loc, next);
      const fd = new FormData();
      fd.append('photo', blob, 'live.jpg'); fd.append('label', next); fd.append('source', 'camera');
      if (loc) { fd.append('lat', loc.lat); fd.append('lng', loc.lng); }
      const r = await api('owner/photo', { form: fd });
      toast(r.distance_m != null ? 'Photo added · ' + r.distance_m + ' m from your pin' : 'Photo added');
      screenOwnerPhotos();
    } catch (er) { toast(er.message); }
  };
  document.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => { if (!confirm('Delete this photo?')) return; await api('owner/photo/delete', { body: { id: b.dataset.del } }).catch(e => toast(e.message)); screenOwnerPhotos(); });
  document.getElementById('sub').onclick = async () => {
    if (OWNER.status === 'verified') return go('#/owner');
    if (!ph.length) return toast('Take at least one live photo');
    try { await api('owner/submit', { body: {} }); toast('Submitted! We will verify within 24 hours.'); go('#/owner'); } catch (e) { toast(e.message); }
  };
}
function stampPhoto(file, loc, label) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const max = 1600, sc = Math.min(1, max / Math.max(img.width, img.height));
      const w = Math.round(img.width * sc), h = Math.round(img.height * sc);
      const c = document.createElement('canvas'); c.width = w; c.height = h;
      const x = c.getContext('2d'); x.drawImage(img, 0, 0, w, h);
      const fs = Math.max(14, Math.round(w / 45)), bar = fs * 2.6;
      x.fillStyle = 'rgba(11,37,89,.72)'; x.fillRect(0, h - bar, w, bar);
      x.fillStyle = '#FFC629'; x.font = '600 ' + fs + 'px sans-serif';
      const d = new Date(), ts = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
      x.fillText('⚡ Electrical Hub · ' + label, fs * .8, h - bar + fs * 1.15);
      x.fillStyle = '#fff'; x.font = fs * .85 + 'px sans-serif';
      x.fillText((loc ? '📍 ' + loc.lat.toFixed(5) + ', ' + loc.lng.toFixed(5) + '  ·  ' : '') + '🕒 ' + ts, fs * .8, h - bar + fs * 2.2);
      c.toBlob(b => b ? resolve(b) : reject(new Error('Could not process photo')), 'image/jpeg', .85);
      URL.revokeObjectURL(img.src);
    };
    img.onerror = () => reject(new Error('Could not read photo'));
    img.src = URL.createObjectURL(file);
  });
}

// ----- Offers (owner) -----
async function screenOwnerOffers() {
  if (needLogin()) return;
  render('<div class="scr nonav">' + hd('Offers') + '<div class="wrap" id="oo">' + loading() + '</div></div>');
  await loadOwner();
  const d = await api('owner/offers');
  const locked = OWNER.plan === 'free';
  document.getElementById('oo').innerHTML = (locked ? '<div class="note">Offers are shown on Featured & Premium plans. <a href="#/owner/plans" style="color:var(--blue);font-weight:600">Upgrade →</a></div>' :
    '<label class="l" style="margin-top:0">Offer title</label><input class="inp" id="ot" placeholder="Havells Week — 15% off MCBs"><label class="l">Details</label><textarea class="inp" id="od"></textarea><label class="l">Valid till</label><input class="inp" type="date" id="ov"><button class="btn blk" id="os" style="margin-top:12px">Post offer</button>') +
    '<div class="h5">Your offers</div>' + (d.offers.length ? d.offers.map(o => '<div class="offer"><div style="display:flex;justify-content:space-between"><b>' + esc(o.title) + '</b><a href="#" data-od="' + o.id + '" style="color:var(--red)">Delete</a></div><div class="muted">' + esc(o.description || '') + (o.valid_till ? ' · till ' + esc(o.valid_till) : '') + '</div></div>').join('') : '<div class="muted">No offers yet</div>');
  const os = document.getElementById('os');
  if (os) os.onclick = async () => { try { await api('owner/offer/save', { body: { title: document.getElementById('ot').value, description: document.getElementById('od').value, valid_till: document.getElementById('ov').value } }); toast('Offer posted'); screenOwnerOffers(); } catch (e) { toast(e.message); } };
  document.querySelectorAll('[data-od]').forEach(a => a.onclick = async e => { e.preventDefault(); await api('owner/offer/delete', { body: { id: a.dataset.od } }); screenOwnerOffers(); });
}
async function screenOwnerReviews() {
  if (needLogin()) return;
  render('<div class="scr nonav">' + hd('Reviews') + '<div class="wrap" id="or">' + loading() + '</div></div>');
  const d = await api('owner/reviews');
  document.getElementById('or').innerHTML = d.reviews.length ? '<div class="card">' + d.reviews.map(r => '<div class="rev"><b>' + esc(r.customer_name) + '</b> <span class="stars">' + '★'.repeat(r.rating) + '</span><div class="muted">' + esc(r.date) + '</div><div>' + esc(r.body || '') + '</div></div>').join('') + '</div>' : '<div class="empty">No reviews yet. Ask happy customers to rate you on Electrical Hub!</div>';
}

// ----- Plans -----
async function screenOwnerPlans() {
  if (needLogin()) return;
  const m = await meta(); await loadOwner();
  const cur = OWNER ? OWNER.plan : 'free';
  let sel = m.plans.find(p => p.code === 'featured') ? 'featured' : (m.plans[1] || m.plans[0]).code;
  const draw = () => {
    const sp = m.plans.find(p => p.code === sel);
    render('<div class="scr nonav">' + hd('Grow your shop') + '<div class="wrap">' + m.plans.map(p => '<div class="plan ' + (sel === p.code ? 'on' : '') + '" data-p="' + p.code + '"><div class="tp"><span>' + esc(p.name) + '</span><span>' + (p.price ? inr(p.price) + '*/mo' : '₹0') + '</span></div><div class="tg">' + esc(p.tagline || '') + (cur === p.code ? ' · <span class="cur">Current plan</span>' : '') + '</div>' + p.feature_list.map(f => '<div class="f">' + icon('check') + esc(f) + '</div>').join('') + '</div>').join('') +
      '<p class="muted">* Prices per month. Pay via any UPI app; plan activates after confirmation.</p></div>' +
      '<div class="foot">' + (sp && sp.price > 0 ? '<button class="btn y" id="up">Upgrade to ' + esc(sp.name) + ' · pay via UPI</button>' : '<button class="btn o" data-act="back">Stay on Free</button>') + '</div></div>');
    document.querySelectorAll('[data-p]').forEach(el => el.onclick = () => { sel = el.dataset.p; draw(); });
    const up = document.getElementById('up');
    if (up) up.onclick = async () => {
      if (!OWNER) return go('#/owner/edit?step=2');
      try {
        const r = await api('owner/upgrade', { body: { plan: sel } });
        openExt(r.upi_link);
        const box = sheet('<h3>Pay ' + inr(r.amount) + '</h3><p class="muted">' + esc(r.message) + '</p><p>UPI ID: <b>' + esc(m.support.upi) + '</b></p><button class="btn blk o" id="again">Open UPI app again</button><label class="l">UPI transaction ID / UTR</label><input class="inp" id="ref" placeholder="12-digit UTR"><button class="btn blk" id="done" style="margin-top:12px">I have paid</button>');
        box.querySelector('#again').onclick = () => openExt(r.upi_link);
        box.querySelector('#done').onclick = async () => { await api('owner/payment-ref', { body: { payment_id: r.payment_id, reference: box.querySelector('#ref').value } }).catch(() => {}); closeSheet(); toast('Thanks! We will activate your plan shortly.'); go('#/owner'); };
      } catch (e) { toast(e.message); }
    };
  };
  draw();
}

// ===================== ROUTER =====================
function parse() {
  const h = location.hash.replace(/^#/, '') || '/home';
  const [path, query] = h.split('?');
  const params = {}; (query || '').split('&').filter(Boolean).forEach(kv => { const [k, v] = kv.split('='); params[decodeURIComponent(k)] = decodeURIComponent((v || '').replace(/\+/g, ' ')); });
  return { parts: path.split('/').filter(Boolean), params };
}
function route() {
  closeSheet();
  const { parts, params } = parse();
  const [a, b] = parts;
  try {
    switch (a) {
      case 'login': return screenLogin();
      case 'home': return screenHome();
      case 'shops': return screenShops(params);
      case 'map': return screenMap();
      case 'shop': return screenShop(b);
      case 'products': return screenProducts(params);
      case 'offers': return screenOffers();
      case 'quote': return b ? screenQuoteView(b) : screenQuote(params);
      case 'quotes': return screenQuotes();
      case 'account': return screenAccount();
      case 'saved': return screenSaved();
      case 'my-reviews': return screenMyReviews();
      case 'notifications': return screenNotifications();
      case 'owner':
        if (!b) return screenOwner();
        if (b === 'edit') return screenOwnerEdit(params);
        if (b === 'photos') return screenOwnerPhotos();
        if (b === 'plans') return screenOwnerPlans();
        if (b === 'offers') return screenOwnerOffers();
        if (b === 'reviews') return screenOwnerReviews();
        if (b === 'quotes') return screenOwnerQuotes();
        return screenOwner();
      default: return screenHome();
    }
  } catch (e) { console.error(e); toast(e.message); }
}
window.addEventListener('hashchange', route);
window.addEventListener('unhandledrejection', e => { toast((e.reason && e.reason.message) || 'Something went wrong'); });
// Android back button support: native calls EH.back(); returns true if handled
window.EH = { back() { if (document.getElementById('sheet').classList.contains('show')) { closeSheet(); return true; } const h = location.hash; if (!h || h === '#/home' || h === '#/login' || h === '#/owner') return false; history.back(); return true; } };

if (!location.hash) location.hash = S.token ? (S.role === 'owner' ? '#/owner' : '#/home') : '#/login';
else route();
})();
