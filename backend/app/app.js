/* Electrical Market Hub — customer + shop-owner app (runs in the Android WebView and in any browser) */
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
/** Show a validation problem right at the field: red border, message under it, scrolled into view. */
function fieldError(id, msg) {
  const el = document.getElementById(id);
  toast(msg, 3500);
  if (!el) return;
  el.classList.add('bad');
  const m = document.createElement('div'); m.className = 'ferr'; m.textContent = msg;
  el.insertAdjacentElement('afterend', m);
  el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  if (el.tagName === 'INPUT') setTimeout(() => el.focus(), 300);
  const clear = () => { el.classList.remove('bad'); m.remove(); };
  el.addEventListener('input', clear, { once: true }); el.addEventListener('click', clear, { once: true });
}
function clearFieldErrors() { document.querySelectorAll('.ferr').forEach(x => x.remove()); document.querySelectorAll('.bad').forEach(x => x.classList.remove('bad')); }
function toast(msg, ms) { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), ms || 2600); }
// ---------- navigation: our own screen stack (reliable Android back button) ----------
const TABS = ['#/home', '#/shops', '#/products', '#/offers', '#/account'];
const NAV = { stack: [], mode: null };
try { NAV.stack = JSON.parse(sessionStorage.getItem('eh_nav') || '[]'); } catch (e) {}
function saveNav() { try { sessionStorage.setItem('eh_nav', JSON.stringify(NAV.stack.slice(-40))); } catch (e) {} }
function curHash() { return location.hash || '#/home'; }
function track(h) {
  const st = NAV.stack, mode = NAV.mode; NAV.mode = null;
  if (mode === 'back') { /* stack already adjusted */ }
  else if (TABS.includes(h)) NAV.stack = h === '#/home' ? ['#/home'] : ['#/home', h];
  else if (mode === 'replace' && st.length) st[st.length - 1] = h;
  else if (st[st.length - 1] === h) { /* same screen */ }
  else if (st.length > 1 && st[st.length - 2] === h) st.pop();   // browser/system history back
  else st.push(h);
  saveNav();
}
// Change the URL without relying on the 'hashchange' event (unreliable in some Android WebViews),
// then render the screen directly.
function setHash(h, replace) {
  if (curHash() !== h) {
    try { if (replace) history.replaceState(null, '', h); else history.pushState(null, '', h); }
    catch (e) { if (replace) location.replace(h); else location.hash = h; return; }
  }
  track(curHash()); route();
}
/** go(h) opens a new screen; go(h, true) swaps the current screen (redirects, filter changes). */
function go(h, replace) { NAV.mode = replace ? 'replace' : null; setHash(h, replace); }
/** Return to a screen already in the stack (e.g. back to the owner dashboard after saving). */
function goBackTo(h) {
  const i = NAV.stack.lastIndexOf(h);
  if (i >= 0 && i < NAV.stack.length - 1) { NAV.stack.length = i + 1; NAV.mode = 'back'; saveNav(); setHash(h, true); }
  else go(h, true);
}
/** Back: closes a sheet, else previous screen, else home; returns false only when the app should close. */
function navBack() {
  const sh = document.getElementById('sheet');
  if (sh.classList.contains('show')) { closeSheet(); return true; }
  if (NAV.stack.length > 1) { NAV.stack.pop(); NAV.mode = 'back'; saveNav(); setHash(NAV.stack[NAV.stack.length - 1], true); return true; }
  const h = curHash();
  if (h === '#/home' && S.token && S.role === 'owner') { go('#/owner', true); NAV.stack = ['#/owner']; saveNav(); return true; }
  if (h === '#/home' || h === '#/login' || (h === '#/owner' && S.role === 'owner')) return false;
  go('#/home', true); return true;
}
function back() { if (!navBack()) go('#/home', true); }

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

// Tell the Android app who/where the user is, so it can check for new notifications in the background
function syncNative() {
  try { if (NATIVE && NATIVE.setUser) NATIVE.setUser(JSON.stringify({ token: S.token || '', city_id: cityId(), role: S.role || 'customer' })); } catch (e) {}
}

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
function waShop(s, text) { lead(s.id, 'whatsapp'); openExt('https://wa.me/91' + (s.whatsapp || s.mobile) + '?text=' + encodeURIComponent(text || ('Hi ' + s.name + ', I found your shop on Electrical Market Hub. '))); }
function dirShop(s) { lead(s.id, 'directions'); openExt(s.lat ? 'https://www.google.com/maps/dir/?api=1&destination=' + s.lat + ',' + s.lng : 'https://www.google.com/maps/search/' + encodeURIComponent(s.name + ' ' + (s.address || ''))); }
function shareText(text) {
  if (NATIVE && NATIVE.share) NATIVE.share(text);
  else if (navigator.share) navigator.share({ text }).catch(() => {});
  else { navigator.clipboard && navigator.clipboard.writeText(text); toast('Copied to clipboard'); }
}
function shareShop(s) { lead(s.id, 'share'); shareText(s.name + ' — ' + (s.address || s.area || '') + '\nCall: +91 ' + s.mobile + (s.lat ? '\nMap: https://maps.google.com/?q=' + s.lat + ',' + s.lng : '') + '\n\nFound on Electrical Market Hub app'); }

// ---------- shop card ----------
function tags(s) {
  return '<div class="tags"><span class="t rt">' + (s.rating ? s.rating.toFixed(1) : 'New') + ' ★</span>' +
    (s.open ? '<span class="t op">Open</span>' : '<span class="t cl">Closed</span>') +
    (s.verified ? '<span class="t vf">✓ Verified</span>' : '') + '</div>';
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
// Taps: every tappable element gets its own click handler (bound automatically whenever the screen
// changes). Some Android WebViews do not deliver taps to a page-level listener, so we don't rely on one.
const NAV_SEL = '[data-call],[data-wa],[data-shop],[data-act="back"],[data-href],a[href^="#/"]';
function onNavTap(e) {
  const el = e.currentTarget;
  if (el.dataset.call) { e.preventDefault(); e.stopPropagation(); callShop(CARD_CACHE[el.dataset.call]); return; }
  if (el.dataset.wa) { e.preventDefault(); e.stopPropagation(); waShop(CARD_CACHE[el.dataset.wa]); return; }
  if (el.dataset.act === 'back') { e.preventDefault(); e.stopPropagation(); back(); return; }
  if (el.tagName === 'A' && (el.getAttribute('href') || '').indexOf('#/') === 0) { e.preventDefault(); e.stopPropagation(); go(el.getAttribute('href')); return; }
  if (el.dataset.href) { e.preventDefault(); e.stopPropagation(); go(el.dataset.href); return; }
  if (el.dataset.shop) { e.preventDefault(); e.stopPropagation(); go('#/shop/' + el.dataset.shop); }
}
function bindNav(root) {
  (root || document).querySelectorAll(NAV_SEL).forEach(el => { if (el._nb) return; el._nb = 1; el.addEventListener('click', onNavTap); });
}
let BIND_PENDING = false;
new MutationObserver(() => { if (BIND_PENDING) return; BIND_PENDING = true; Promise.resolve().then(() => { BIND_PENDING = false; bindNav(document.body); }); })
  .observe(document.body, { childList: true, subtree: true });

// Lightweight remote log (errors + screen changes) so problems on real phones show up in the server logs
function rlog(type, msg) {
  try {
    const body = JSON.stringify({ type, msg: String(msg).slice(0, 300), hash: location.hash, app: NATIVE ? 'android' : 'web', v: '1.11' });
    if (navigator.sendBeacon) navigator.sendBeacon(API + '?r=log', new Blob([body], { type: 'text/plain' }));
    else fetch(API + '?r=log', { method: 'POST', body }).catch(() => {});
  } catch (e) {}
}

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
  S.city = { id: +c.id, name: c.name, state: c.state }; store.set('city', S.city); syncNative();
  if (S.token) api('me/update', { body: { city_id: c.id } }).catch(() => {});
}

// ===================== SCREENS =====================

// ---------- Login ----------
function screenLogin() {
  // step: 'mobile' → 'pin' (registered) or 'create' (new number)
  let role = S.role || 'customer', mobile = '', step = 'mobile', name = '';
  const pinBoxes = id => '<div class="otp" id="' + id + '">' + [0, 1, 2, 3].map(i => '<input type="password" inputmode="numeric" maxlength="1" data-i="' + i + '">').join('') + '</div>';
  const readPin = id => [...document.querySelectorAll('#' + id + ' input')].map(x => x.value).join('');
  const wirePin = (id, done) => {
    const ins = [...document.querySelectorAll('#' + id + ' input')];
    ins.forEach((inp, i) => {
      inp.oninput = () => { inp.value = inp.value.replace(/\D/g, '').slice(-1); if (inp.value && ins[i + 1]) ins[i + 1].focus(); if (ins.every(x => x.value) && done) done(); };
      inp.onkeydown = e => { if (e.key === 'Backspace' && !inp.value && ins[i - 1]) ins[i - 1].focus(); };
    });
    return ins;
  };
  const draw = () => {
    let box = '';
    if (step === 'mobile') box =
      '<label class="l">Mobile number</label><div style="display:flex;gap:8px"><span class="inp" style="width:auto;flex:none;color:var(--mute)">+91</span><input class="inp" id="mob" type="tel" inputmode="numeric" maxlength="10" placeholder="98xxxxxx10" value="' + esc(mobile) + '"></div>' +
      '<div class="roles"><button data-role="customer" class="' + (role === 'customer' ? 'on' : '') + '">I am a customer</button><button data-role="owner" class="' + (role === 'owner' ? 'on' : '') + '">I own a shop</button></div>' +
      '<button class="btn blk" id="cont" style="margin-top:16px">Continue</button>';
    else if (step === 'pin') box =
      '<div class="muted">+91 ' + esc(mobile) + ' · <a href="#" id="chg" style="color:var(--blue)">change</a></div>' +
      '<label class="l">Enter your 4-digit PIN</label>' + pinBoxes('p1') +
      '<button class="btn blk" id="login" style="margin-top:16px">Login</button>' +
      '<p style="text-align:center;font-size:12.5px;margin:12px 0 0"><a href="#" id="forgot" style="color:var(--blue)">Forgot PIN?</a></p>';
    else box =
      '<div class="muted">+91 ' + esc(mobile) + ' · <a href="#" id="chg" style="color:var(--blue)">change</a></div>' +
      '<label class="l">Your name</label><input class="inp" id="nm" value="' + esc(name) + '" placeholder="' + (role === 'owner' ? 'Owner name' : 'Your name') + '">' +
      '<label class="l">Create a 4-digit PIN</label>' + pinBoxes('p1') +
      '<label class="l">Enter the PIN again</label>' + pinBoxes('p2') +
      '<p class="muted" style="margin:8px 0 0">You will use this PIN to log in. Don\'t share it.</p>' +
      '<button class="btn blk" id="reg" style="margin-top:14px">Create account</button>';
    render('<div class="login"><div class="top"><div class="big">' + BOLT + '</div><h2>Electrical Market <b>Hub</b></h2><p>Every electrical shop near you — in one app</p></div>' +
      '<div class="box">' + box + '</div><p style="text-align:center;font-size:12px;color:var(--mute);margin:16px"><a href="#/home">Skip for now — browse shops</a></p></div>');
    if (step === 'mobile') {
      const mob = document.getElementById('mob');
      mob.oninput = () => mobile = mob.value.replace(/\D/g, '');
      document.querySelectorAll('[data-role]').forEach(b => b.onclick = () => { role = b.dataset.role; draw(); });
      document.getElementById('cont').onclick = check;
      mob.onkeydown = e => { if (e.key === 'Enter') check(); };
    } else {
      document.getElementById('chg').onclick = e => { e.preventDefault(); step = 'mobile'; draw(); };
      if (step === 'pin') {
        wirePin('p1', login)[0].focus();
        document.getElementById('login').onclick = login;
        document.getElementById('forgot').onclick = e => { e.preventDefault(); forgot(); };
      } else {
        const a = wirePin('p1', () => document.querySelector('#p2 input').focus()); wirePin('p2');
        document.getElementById('nm').oninput = e => name = e.target.value;
        a[0].focus();
        document.getElementById('reg').onclick = register;
      }
    }
  };
  async function check() {
    if (!/^[6-9]\d{9}$/.test(mobile)) return toast('Enter your 10-digit mobile number');
    try { const r = await api('auth/check', { body: { mobile } }); if (r.blocked) return toast('This account is blocked. Contact support.'); step = r.registered ? 'pin' : 'create'; draw(); }
    catch (e) { toast(e.message); }
  }
  async function login() {
    const pin = readPin('p1'); if (pin.length !== 4) return toast('Enter your 4-digit PIN');
    try { done(await api('auth/login', { body: { mobile, pin, role } })); }
    catch (e) { toast(e.message); document.querySelectorAll('#p1 input').forEach(x => x.value = ''); const f = document.querySelector('#p1 input'); if (f) f.focus(); }
  }
  async function register() {
    const p1 = readPin('p1'), p2 = readPin('p2');
    if (p1.length !== 4) return toast('Create a 4-digit PIN');
    if (p1 !== p2) return toast('Both PINs must match');
    try { done(await api('auth/register', { body: { mobile, pin: p1, role, name: name.trim() } })); }
    catch (e) { toast(e.message); }
  }
  async function forgot() {
    const m = await meta().catch(() => null);
    const sup = m && m.support && (m.support.whatsapp || m.support.phone);
    const box = sheet('<h3>Forgot PIN?</h3><p class="muted">Our support team will reset your PIN after checking your number.</p>' +
      (sup ? '<button class="btn blk wa" id="fw">' + icon('wa') + ' Ask support on WhatsApp</button>' : '<p>Please contact the Electrical Market Hub team.</p>'));
    const fw = box.querySelector('#fw');
    if (fw) fw.onclick = () => openExt('https://wa.me/91' + sup + '?text=' + encodeURIComponent('Hi, please reset my Electrical Market Hub PIN. My mobile number: ' + mobile));
  }
  async function done(r) {
    S.token = r.token; S.user = r.user; S.role = role;
    store.set('token', r.token); store.set('user', r.user); store.set('role', role); syncNative();
    if (!S.city) { const m = await meta(); const c = m.cities.find(x => x.id == r.user.city_id); if (c) setCity(c); }
    if (role === 'owner') { NAV.stack = ['#/home']; go('#/owner', true); } else go('#/home', true);
  }
  draw();
}
function logout(silent) {
  if (!silent && S.token) api('auth/logout', { body: {} }).catch(() => {});
  S.token = ''; S.user = null; store.del('token'); store.del('user');
  NAV.stack = []; syncNative(); go('#/login', true);
}
function needLogin(msg) {
  if (S.token) return false;
  toast(msg || 'Please login with your mobile number');
  go('#/login', true); return true;
}

// ---------- Home ----------
async function screenHome() {
  const cname = S.city ? S.city.name + (S.city.state ? ', ' + S.city.state : '') : 'Choose city';
  render('<div class="scr"><div class="home-hd"><div class="row"><div class="brand"><span class="bolt">' + BOLT + '</span>Electrical Market <b>Hub</b></div><span style="flex:1"></span>' +
    '<button data-href="#/notifications" style="border:0;background:none;color:#fff;display:flex;cursor:pointer">' + icon('bell') + '</button></div>' +
    '<button class="city" id="city">' + icon('pin') + esc(cname) + ' ▾</button>' +
    '<form class="srch" id="sf">' + icon('search') + '<input id="q" placeholder="Search shops, products, brands..." enterkeyhint="search"></form></div>' +
    '<div class="wrap" id="body">' + loading() + '</div></div>' + bottomNav('Home'));
  document.getElementById('city').onclick = pickCity;
  if (S.token) api('me').then(m => { const c = m.counts.notifications; const bell = document.querySelector('.home-hd [data-href="#/notifications"]'); if (c && bell) { bell.style.position = 'relative'; bell.insertAdjacentHTML('beforeend', '<i style="position:absolute;top:-4px;right:-4px;background:var(--yellow);color:#000;font-style:normal;font-size:10px;font-weight:700;border-radius:9px;padding:0 5px">' + c + '</i>'); } }).catch(() => {});
  document.getElementById('sf').onsubmit = e => { e.preventDefault(); const q = document.getElementById('q').value.trim(); if (q) go('#/shops?q=' + encodeURIComponent(q)); };
  if (!S.city) { const m = await meta().catch(() => null); if (m && m.cities.length) { const def = m.cities.find(c => c.id == (S.user && S.user.city_id)) || m.cities[0]; setCity(def); document.getElementById('city').innerHTML = icon('pin') + esc(def.name + ', ' + def.state) + ' ▾'; } }
  const loc = await getLocation();
  try {
    const d = await api('home', { params: { city_id: cityId(), lat: loc && loc.lat, lng: loc && loc.lng } });
    bindCards(d.nearby);
    const banners = d.banners.length ? d.banners : [{ title: 'All Electrical Products in One Place' }];
    let h = '<div class="banners">' + banners.map((b, i) => b.type === 'video' ?
      '<div class="bnr vid"' + (b.shop_id ? ' data-shop="' + b.shop_id + '"' : '') + '><video src="' + esc(b.video) + '"' + (b.image ? ' poster="' + esc(b.image) + '"' : '') + ' muted playsinline preload="auto"></video></div>' :
      '<div class="bnr" ' + (b.image ? 'style="background-image:url(\'' + esc(b.image) + '\')"' : '') + (b.shop_id ? ' data-shop="' + b.shop_id + '"' : '') + '>' +
      (b.image ? '' : '<b>' + (i === 0 ? esc(b.title).replace(/in One Place/i, '<em>in One Place</em>') : esc(b.title)) + '</b>' + (b.subtitle ? '<small>' + esc(b.subtitle) + '</small>' : '') + '<span class="art">' + (i % 2 ? '💡' : '🔌') + '</span>') + '</div>').join('') + '</div>' +
      (banners.length > 1 ? '<div class="dots">' + banners.map((b, i) => '<i class="' + (i ? '' : 'on') + '"></i>').join('') + '</div>' : '');
    h += '<div class="sec"><h3>Categories</h3><a href="#/products">View all</a></div><div class="cats">' + d.categories.map(c => '<div class="cat" data-href="#/shops?category_id=' + c.id + '&cname=' + encodeURIComponent(c.name) + '"><div class="em">' + (c.image ? '<img src="' + esc(c.image) + '">' : esc(c.icon || emojiFor(c.name))) + '</div>' + esc(c.name) + '</div>').join('') + '</div>';
    const rest = d.nearby;
    if (rest.length) h += '<div class="sec"><h3>' + (loc ? 'Shops near you' : 'Shops in your city') + '</h3><a href="#/shops">View all</a></div>' + rest.map(shopCard).join('');
    if (!rest.length) h += '<div class="empty"><div class="big">🏪</div>No shops listed in this city yet.<br><a href="#" id="chg2" style="color:var(--blue)">Change city</a></div>';
    h += '<div class="own" data-href="#/owner">' + icon('store') + '<div><b>Own an electrical shop?</b><small>Get listed free & get customer calls</small></div></div>';
    document.getElementById('body').innerHTML = h;
    const c2 = document.getElementById('chg2'); if (c2) c2.onclick = e => { e.preventDefault(); pickCity(); };
    startSlider(); startCatScroll();
  } catch (e) { document.getElementById('body').innerHTML = '<div class="empty">' + esc(e.message) + '<br><br><button class="btn sm" onclick="location.reload()">Retry</button></div>'; }
}

// Home banner slider: auto-advances every 3 s, loops, pauses while the user is touching it
let SLIDER = null;
function stopSlider() { if (SLIDER) { clearInterval(SLIDER.t); SLIDER = null; } document.querySelectorAll('.banners video').forEach(v => v.pause()); }
function startSlider() {
  stopSlider();
  const el = document.querySelector('.banners');
  if (!el) return;
  if (el.children.length < 2) { const v = el.querySelector('video'); if (v) { v.loop = true; const p = v.play(); if (p && p.catch) p.catch(() => {}); } return; }
  const dots = [...document.querySelectorAll('.dots i')];
  const n = el.children.length;
  const cur = () => Math.round(el.scrollLeft / (el.children[0].offsetWidth + 10));
  const mark = () => { const i = cur(); dots.forEach((d, k) => d.classList.toggle('on', k === i)); };
  const st = SLIDER = { t: 0, hold: false };
  el.addEventListener('scroll', mark, { passive: true });
  el.addEventListener('touchstart', () => { st.hold = true; }, { passive: true });
  el.addEventListener('touchend', () => { setTimeout(() => { st.hold = false; }, 1500); }, { passive: true });
  const vids = el.querySelectorAll('video');
  const playVisible = () => { const i = cur(); vids.forEach(v => { if (el.children[i] && el.children[i].contains(v)) { const p = v.play(); if (p && p.catch) p.catch(() => {}); } else v.pause(); }); };
  el.addEventListener('scroll', () => { clearTimeout(st.pv); st.pv = setTimeout(playVisible, 200); }, { passive: true });
  setTimeout(playVisible, 300);
  const advance = () => { const next = (cur() + 1) % n; el.scrollTo({ left: next * (el.children[0].offsetWidth + 10), behavior: 'smooth' }); };
  vids.forEach(v => v.addEventListener('ended', () => { if (!st.hold && SLIDER === st) advance(); }));
  st.t = setInterval(() => {
    if (!document.body.contains(el)) return stopSlider();
    if (st.hold || document.hidden) return;
    const slide = el.children[cur()];
    const v = slide && slide.querySelector('video');
    if (v && !v.paused && !v.ended) return;   // let the video finish, then 'ended' moves on
    advance();
  }, 3000);
}

// Home categories: 2-row strip that keeps sliding sideways (pauses while touched, loops back to start)
let CATS = null;
function startCatScroll() {
  if (CATS) clearInterval(CATS);
  const el = document.querySelector('.cats');
  if (!el || el.scrollWidth <= el.clientWidth + 10) return;
  let hold = false;
  el.addEventListener('touchstart', () => { hold = true; }, { passive: true });
  el.addEventListener('touchend', () => { setTimeout(() => { hold = false; }, 2500); }, { passive: true });
  CATS = setInterval(() => {
    if (!document.body.contains(el)) { clearInterval(CATS); CATS = null; return; }
    if (hold || document.hidden) return;
    const col = (el.querySelector('.cat') || {}).offsetWidth || 90;
    const atEnd = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
    el.scrollTo({ left: atEnd ? 0 : el.scrollLeft + col + 12, behavior: 'smooth' });
  }, 2500);
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
    go('#/shops?' + qs(np), true);
  });
  document.getElementById('flt').onclick = () => {
    const box = sheet('<h3>Filter shops</h3><label class="l">Search</label><input class="inp" id="fq" value="' + esc(f.q) + '" placeholder="Shop, product or brand">' +
      '<label class="l">Brand</label><select class="inp" id="fb"><option value="">Any brand</option>' + (m.brands || []).map(b => '<option value="' + b.id + '"' + (f.brand_id == b.id ? ' selected' : '') + '>' + esc(b.name) + '</option>').join('') + '</select>' +
      '<label class="l">Shop type</label><select class="inp" id="ft"><option value="">Any</option><option' + (f.shop_type === 'Retail' ? ' selected' : '') + '>Retail</option><option' + (f.shop_type === 'Wholesale' ? ' selected' : '') + '>Wholesale</option><option' + (f.shop_type === 'Manufacturer' ? ' selected' : '') + '>Manufacturer</option></select>' +
      '<label class="l">Sort by</label><select class="inp" id="fs"><option value="nearest">Nearest</option><option value="rating"' + (f.sort === 'rating' ? ' selected' : '') + '>Top rated</option></select>' +
      '<div style="display:flex;gap:10px;margin-top:16px"><button class="btn o" id="fr" style="flex:1">Clear</button><button class="btn" id="fa" style="flex:2">Apply</button></div>');
    box.querySelector('#fr').onclick = () => { closeSheet(); go('#/shops', true); };
    box.querySelector('#fa').onclick = () => { closeSheet(); go('#/shops?' + qs(Object.assign({}, p, { q: box.querySelector('#fq').value.trim(), brand_id: box.querySelector('#fb').value, shop_type: box.querySelector('#ft').value, sort: box.querySelector('#fs').value })), true); };
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
      L.marker([s.lat, s.lng], { icon: L.divIcon({ className: '', html: '<div class="pin"></div>', iconSize: [30, 30], iconAnchor: [15, 30] }) })
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
    '<div class="tags"><span class="t rt">' + (s.rating ? s.rating.toFixed(1) : 'New') + ' ★</span><span class="muted">(' + s.review_count + ' reviews)</span>' + (s.open ? '<span class="t op">Open</span><span class="muted">till ' + esc(s.close_time) + '</span>' : '<span class="t cl">Closed</span><span class="muted">opens ' + esc(s.open_time) + '</span>') + '' + '</div>' +
    '<div class="acts"><button id="a1"><span class="cb call">' + icon('phone') + '</span>Call</button><button id="a2"><span class="cb wa">' + icon('wa') + '</span>WhatsApp</button><button id="a3"><span class="cb dir">' + icon('nav') + '</span>Directions</button><button id="a4"><span class="cb sh">' + icon('share') + '</span>Share</button></div></div>' +
    '<div class="sp" style="margin-top:8px">' +
    (s.photos.length ? '<div class="h5" style="margin-top:0">Shop photos</div><div class="thumbs">' + thumbs + '</div>' : '') +
    (s.offers.length ? '<div class="h5">Offers</div>' + s.offers.map(o => '<div class="offer"><b>🏷️ ' + esc(o.title) + '</b><div class="muted">' + esc(o.description || '') + (o.valid_till ? ' · till ' + esc(o.valid_till) : '') + '</div></div>').join('') : '') +
    (s.brands.length ? '<div class="h5">Brands available</div><div class="chips wrapc">' + s.brands.map(b => '<span class="chip">' + esc(b.name) + '</span>').join('') + '</div>' : '') +
    (s.categories.length ? '<div class="h5">Categories</div><div class="chips wrapc">' + s.categories.map(c => '<span class="chip">' + esc(c.icon || '') + ' ' + esc(c.name) + '</span>').join('') + '</div>' : '') +
    (s.products.length ? '<div class="h5">Products</div>' + s.products.map(p => '<div class="prod"><div class="pi">' + emojiFor(p.name) + '</div><div><b>' + esc(p.name) + '</b><span class="pr">' + (p.price ? inr(p.price) : inr(p.min_price) + ' – ' + inr(p.max_price)) + '</span></div></div>').join('') : '') +
    '<div class="h5">Shop details</div><div class="muted" style="line-height:1.8">' + icon('clock', '') + ' ' + esc(s.open_time) + ' – ' + esc(s.close_time) + (s.weekly_off ? ' · ' + esc(s.weekly_off) + ' closed' : '') + '<br>Type: ' + esc(s.shop_type) + (s.is_service ? ' + electrician service' : '') + (s.gstin ? '<br>GSTIN: ' + esc(s.gstin) : '') + '</div>' +
    '<div class="h5" style="display:flex;justify-content:space-between">Reviews <a href="#" id="wr" style="color:var(--blue);font-size:12.5px">Write a review</a></div>' +
    (s.reviews.length ? s.reviews.map(r => '<div class="rev"><b>' + esc(r.customer_name) + '</b> <span class="stars">' + '★'.repeat(r.rating) + '</span><span style="color:#E0DAD4">' + '★'.repeat(5 - r.rating) + '</span><div class="muted">' + esc(r.date) + '</div><div>' + esc(r.body || '') + '</div></div>').join('') : '<div class="muted">No reviews yet — be the first.</div>') +
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
  document.querySelectorAll('[data-c]').forEach(b => b.onclick = () => go('#/products?' + qs({ category_id: b.dataset.c }), true));
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
    try { const r = await api('quotes/create', { form: fd }); toast('Sent to ' + r.sent_to + ' shops. Replies will appear here.'); go('#/quote/' + r.quote_id, true); }
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
    document.querySelectorAll('[data-qw]').forEach(b => b.onclick = () => waShop(CARD_CACHE['q' + b.dataset.qw], 'Hi, about your quote for ' + q.code + ' on Electrical Market Hub: '));
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
      '<div class="list">' + li('pin', 'Change city', '', 'city') + li('store', 'List your shop (free)', '', '#/owner') + li('help', 'Help & support', '', 'help') + li('file', 'Privacy policy', '', 'privacy') + '</div></div>' + bottomNav('Account'));
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
      li('bell', 'Notifications', d.counts.notifications ? d.counts.notifications + ' new' : '', '#/notifications') + li('pin', 'Change city', '', 'city') + li('user', 'Change PIN', '', 'chpin') +
      (d.has_shop ? li('store', 'My shop dashboard', '', '#/owner') : li('store', 'List your shop (free)', '', '#/owner')) + li('help', 'Help & support', '', 'help') + li('file', 'Privacy policy', '', 'privacy') + li('logout', 'Logout', '', 'logout') +
      li('x', 'Delete my account', '', 'delacc') + '</div>' +
      (d.has_shop ? '' : '<div class="own" data-href="#/owner">' + icon('store') + '<div><b>Own an electrical shop?</b><small>Get listed free & get customer calls</small></div></div>') +
      '<p class="muted" style="text-align:center">Electrical Market Hub v1.11</p>';
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
    if (t === 'city') pickCity(); else if (t === 'chpin') changePin(); else if (t === 'delacc') deleteAccount(); else if (t === 'privacy') openExt(SITE + 'privacy.php'); else if (t === 'logout') { if (confirm('Logout?')) logout(); } else if (t === 'help') helpSheet(); else go(t);
  });
}
const SITE = API.replace(/api\/?$/, '');
function deleteAccount() {
  const box = sheet('<h3 style="color:var(--red)">Delete my account</h3><p class="muted">This permanently deletes your profile, saved shops, reviews and quote requests' + (S.role === 'owner' ? ', <b>and your shop listing with its photos and offers</b>' : '') + '. This cannot be undone.</p>' +
    '<label class="l">Enter your PIN to confirm</label><input class="inp" id="dp" type="password" inputmode="numeric" maxlength="4">' +
    '<button class="btn blk" id="dgo" style="margin-top:14px;background:var(--red)">Delete permanently</button><button class="btn blk o" id="dno" style="margin-top:8px">Cancel</button>');
  box.querySelector('#dno').onclick = closeSheet;
  box.querySelector('#dgo').onclick = async () => {
    const pin = box.querySelector('#dp').value;
    if (!/^\d{4}$/.test(pin)) return toast('Enter your 4-digit PIN');
    try { await api('me/delete', { body: { pin } }); closeSheet(); toast('Your account has been deleted'); logout(true); } catch (e) { toast(e.message); }
  };
}
function changePin() {
  const box = sheet('<h3>Change PIN</h3><label class="l">Current PIN</label><input class="inp" id="op" type="password" inputmode="numeric" maxlength="4">' +
    '<label class="l">New 4-digit PIN</label><input class="inp" id="np" type="password" inputmode="numeric" maxlength="4">' +
    '<label class="l">New PIN again</label><input class="inp" id="np2" type="password" inputmode="numeric" maxlength="4">' +
    '<button class="btn blk" id="sp" style="margin-top:14px">Save new PIN</button>');
  box.querySelector('#sp').onclick = async () => {
    const op = box.querySelector('#op').value, np = box.querySelector('#np').value, np2 = box.querySelector('#np2').value;
    if (!/^\d{4}$/.test(np)) return toast('New PIN must be 4 digits');
    if (np !== np2) return toast('New PINs do not match');
    try { await api('me/pin', { body: { old_pin: op, pin: np } }); closeSheet(); toast('PIN changed'); } catch (e) { toast(e.message); }
  };
}
async function helpSheet() {
  const m = await meta();
  const box = sheet('<h3>Help & support</h3><p class="muted">We usually reply within a few hours.</p><button class="btn blk wa" id="hw">' + icon('wa') + ' WhatsApp support</button><button class="btn blk o" id="hc" style="margin-top:10px">' + icon('phone') + ' Call ' + esc(m.support.phone) + '</button>' + (m.support.email ? '<p class="muted" style="text-align:center">' + esc(m.support.email) + '</p>' : ''));
  box.querySelector('#hw').onclick = () => openExt('https://wa.me/91' + m.support.whatsapp + '?text=' + encodeURIComponent('Hi Electrical Market Hub, I need help with '));
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
  document.getElementById('nl').innerHTML = d.notifications.length ? d.notifications.map(n => '<div class="card" style="margin-bottom:10px;' + (n.is_read ? '' : 'border-left:4px solid var(--yellow)') + '"' + (n.shop_id ? ' data-shop="' + n.shop_id + '"' : '') + '>' +
    (n.image ? '<img src="' + esc(n.image) + '" style="width:100%;border-radius:10px;margin-bottom:8px">' : '') + '<b>' + esc(n.title) + '</b><div>' + esc(n.body || '') + '</div><div class="muted">' + esc(n.date) + (n.shop_id ? ' · Tap to open shop ›' : '') + '</div></div>').join('') : '<div class="empty"><div class="big">🔔</div>No notifications</div>';
  syncNative();
}

// ===================== SHOP OWNER =====================
let OWNER = null;
async function loadOwner() { OWNER = (await api('owner/shop')).shop; return OWNER; }

async function screenOwner() {
  if (needLogin('Login with your shop mobile number')) return;
  render('<div class="scr nonav">' + loading() + '</div>');
  try { await loadOwner(); } catch (e) { render('<div class="scr">' + hd('My shop') + '<div class="empty">' + esc(e.message) + '</div></div>'); return; }
  if (!OWNER) return go('#/owner/edit?step=2', true);
  if (OWNER.status === 'draft' || (OWNER.status === 'rejected')) { if (!OWNER.photos.length) return go('#/owner/photos', true); }
  const d = await api('owner/dashboard');
  const s = d.shop, st = d.stats;
  const statusPill = s.status === 'verified' ? '<span class="pill" style="background:#E3F6EA;color:#0E7B3C">Verified</span> <span style="opacity:.8;font-size:12px">· listing live</span>'
    : s.status === 'pending' ? '<span class="pill" style="background:#FFF3D6;color:#a86200">Under verification</span> <span style="opacity:.8;font-size:12px">· usually within 24 hrs</span>'
    : s.status === 'rejected' ? '<span class="pill" style="background:#FDE8E8;color:#C22B31">Needs changes</span> <span style="opacity:.8;font-size:12px">· ' + esc(s.reject_reason || '') + '</span>'
    : s.status === 'blocked' ? '<span class="pill" style="background:#FDE8E8;color:#C22B31">Blocked</span> <span style="opacity:.8;font-size:12px">· contact support</span>'
    : '<span class="pill" style="background:#eee;color:#555">Incomplete</span>';
  const q = d.quotes.find(x => !x.replied_at);
  render('<div class="scr nonav"><div class="dash-hd"><div style="display:flex;align-items:center;justify-content:space-between"><div class="brand"><span class="bolt">' + BOLT + '</span>Electrical Market <b>Hub</b></div>' +
    '</div>' +
    '<h2>' + esc(s.name) + '</h2>' + statusPill + '</div>' +
    (s.status === 'rejected' ? '<div class="wrap"><div class="note">Admin asked for changes: <b>' + esc(s.reject_reason || '') + '</b>. Fix it and tap <b>Submit again</b>.<br><button class="btn sm" id="resub" style="margin-top:8px">Submit again</button></div></div>' : '') +
    '<div class="stats"><div class="stat"><small>Profile views</small><b>' + st.views.toLocaleString('en-IN') + '</b><em>' + (st.views_change >= 0 ? '▲ ' : '▼ ') + Math.abs(st.views_change) + '% this week</em></div>' +
    '<div class="stat"><small>Calls</small><b>' + st.calls + '</b><em>this month</em></div><div class="stat"><small>WhatsApp clicks</small><b>' + st.whatsapp + '</b><em>this month</em></div>' +
    '<div class="stat" data-href="#/owner/quotes"><small>Quote requests</small><b>' + st.quotes + '</b><em>' + st.new_quotes + ' new</em></div></div>' +
    (q ? '<div class="wrap"><div class="card"><b style="color:var(--navy)">New quote request</b><div class="muted">' + esc(q.items.replace(/\n/g, ', ').slice(0, 70)) + ' · ' + esc(q.area || '') + '</div>' +
      '<div style="display:flex;gap:8px;margin-top:10px"><button class="btn sm" style="flex:1" id="sp">Send price</button><button class="btn sm wa" style="flex:1" id="sw">WhatsApp</button></div></div></div>' : '<div style="height:14px"></div>') +
    '<div class="menu">' + li('camera', 'Update live photos', st.photos, '#/owner/photos') + li('tag', 'Post an offer', '', '#/owner/offers') + li('grid', 'Products & brands', '', '#/owner/edit?step=3') +
    li('clock', 'Shop timings', '', '#/owner/edit?step=4') + li('store', 'Shop details & location', '', '#/owner/edit?step=2') + li('star', 'Reviews (' + st.reviews + ')', '', '#/owner/reviews') + li('file', 'All quote requests', '', '#/owner/quotes') +
    li('search', 'Browse as customer', '', '#/home') + '</div></div>');
  bindAccount();
  if (q) { document.getElementById('sp').onclick = () => replySheet(q); document.getElementById('sw').onclick = () => openExt('https://wa.me/91' + q.customer_mobile + '?text=' + encodeURIComponent('Hi ' + (q.customer_name || '') + ', this is ' + s.name + ' from Electrical Market Hub regarding your requirement ' + q.code + ': ')); }
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
  document.querySelectorAll('[data-wq]').forEach(b => b.onclick = () => { const q = d.quotes[b.dataset.wq]; openExt('https://wa.me/91' + q.customer_mobile + '?text=' + encodeURIComponent('Hi, this is ' + d.shop.name + ' from Electrical Market Hub regarding ' + q.code + ': ')); });
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
  if (step === 2) body = '<label class="l">Shop name</label><input class="inp" id="name" value="' + esc(D.name) + '" placeholder="e.g. KK Electrical">' +
    '<label class="l">Owner name</label><input class="inp" id="owner_name" value="' + esc(D.owner_name) + '">' +
    '<label class="l">Mobile / WhatsApp</label><input class="inp" id="mobile" type="tel" maxlength="10" value="' + esc(D.mobile) + '">' +
    '<label class="l">GSTIN (optional)</label><input class="inp" id="gstin" value="' + esc(D.gstin || '') + '" placeholder="33AAxxxxx1Z5" style="text-transform:uppercase">' +
    '<label class="l">Shop type</label><div class="chips wrapc" id="stype">' + ['Retail', 'Wholesale', 'Manufacturer', 'Both'].map(t => '<button class="chip ' + (D.shop_type === t ? 'on' : '') + '" data-t="' + t + '">' + t + '</button>').join('') + '<button class="chip ' + (D.is_service ? 'on' : '') + '" id="svc">Service / electrician</button></div>';
  if (step === 3) body = '<label class="l">Categories</label><div class="chips wrapc" id="cats">' + m.categories.map(c => '<button class="chip ' + (D.cats.includes(+c.id) ? 'on' : '') + '" data-id="' + c.id + '">' + esc(c.name) + '</button>').join('') + '</div>' +
    '<label class="l">Brands you sell</label><div class="chips wrapc" id="brs">' + m.brands.map(b => '<button class="chip ' + (D.brs.includes(+b.id) ? 'on' : '') + '" data-id="' + b.id + '">' + esc(b.name) + '</button>').join('') + '</div>' +
    '<label class="l">Popular products you stock</label><div class="chips wrapc" id="prs">' + loading() + '</div>';
  if (step === 4) body = '<label class="l">Location</label><div class="minimap" id="mm"></div><button class="btn sm o" id="gps" style="margin-top:8px">' + icon('gps') + ' Use my current location</button> <span class="muted" id="ll">' + (D.lat ? 'Pin set ✓' : 'Drag the pin to your shop') + '</span>' +
    '<label class="l">City</label><select class="inp" id="city_id">' + m.cities.map(c => '<option value="' + c.id + '"' + (D.city_id == c.id ? ' selected' : '') + '>' + esc(c.name) + '</option>').join('') + '</select>' +
    '<label class="l">Area / locality <span class="muted" style="font-weight:400">(optional)</span></label><input class="inp" id="area" value="' + esc(D.area || '') + '" placeholder="e.g. GN Street, T. Nagar">' +
    '<label class="l">Full address</label><input class="inp" id="address" value="' + esc(D.address || '') + '" placeholder="e.g. No. 222, GN Street">' +
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
    let cityTouched = !!(OWNER && OWNER.city_id);
    document.getElementById('city_id').addEventListener('change', () => { cityTouched = true; });
    const setLL = ll => {
      D.lat = +ll.lat.toFixed(7); D.lng = +ll.lng.toFixed(7); document.getElementById('ll').textContent = 'Pin set ✓';
      if (!cityTouched) {   // pick the nearest city to the pin
        let best = null, bd = 1e9;
        m.cities.forEach(c => { if (!c.lat) return; const d = Math.hypot(c.lat - D.lat, (c.lng - D.lng) * Math.cos(D.lat * Math.PI / 180)); if (d < bd) { bd = d; best = c; } });
        if (best && bd < 0.6) { document.getElementById('city_id').value = best.id; D.city_id = best.id; }
      }
    };
    mk.on('dragend', () => setLL(mk.getLatLng()));
    map.on('click', e => { mk.setLatLng(e.latlng); setLL(e.latlng); });
    document.getElementById('gps').onclick = async () => { toast('Getting location…'); const l = await getLocation(true); if (!l) return toast('Turn on GPS / location'); mk.setLatLng([l.lat, l.lng]); map.setView([l.lat, l.lng], 17); setLL({ lat: l.lat, lng: l.lng }); };
    if (!D.lat && S.loc) { setLL({ lat: S.loc.lat, lng: S.loc.lng }); mk.setLatLng([D.lat, D.lng]); map.setView([D.lat, D.lng], 16); }
    if (!D.lat) {   // new shop: put the pin at the phone's location straight away
      document.getElementById('ll').textContent = 'Finding your location…';
      getLocation(true).then(l => {
        if (!l || D.lat || !document.getElementById('mm')) { if (!D.lat && document.getElementById('ll')) document.getElementById('ll').textContent = 'Tap the map or "Use my current location"'; return; }
        mk.setLatLng([l.lat, l.lng]); map.setView([l.lat, l.lng], 16); setLL({ lat: l.lat, lng: l.lng });
      });
    }
  }
  const collect = () => {
    if (step === 2) ['name', 'owner_name', 'mobile', 'gstin'].forEach(k => D[k] = document.getElementById(k).value.trim());
    if (step === 4) ['area', 'address', 'pincode', 'open_time', 'close_time', 'weekly_off', 'city_id'].forEach(k => { const v = document.getElementById(k).value.trim(); if (k === 'open_time' || k === 'close_time') D[k + '_24'] = v; else D[k] = v; });
    saveDraft();
  };
  const pb = document.getElementById('prev'); if (pb) pb.onclick = () => { collect(); if (!navBack()) go('#/owner/edit?step=' + (step - 1), true); };
  document.getElementById('next').onclick = async () => {
    collect();
    clearFieldErrors();
    if (step === 2) { if (!D.name) return fieldError('name', 'Enter your shop name'); if (!/^\d{10}$/.test(D.mobile)) return fieldError('mobile', 'Enter a 10-digit mobile number'); }
    if (step === 3 && !D.cats.length) return fieldError('cats', 'Pick at least one category');
    if (step < 4 && isNew) return go('#/owner/edit?step=' + (step + 1));
    if (step === 4 && !D.lat) return fieldError('mm', 'Set your shop location: tap "Use my current location" or tap the map');
    if (step === 4 && !D.area) { const c = m.cities.find(x => x.id == D.city_id); D.area = c ? c.name : ''; }
    // save
    try {
      await api('owner/shop/save', { body: { name: D.name, owner_name: D.owner_name, mobile: D.mobile, whatsapp: D.mobile, gstin: D.gstin, shop_type: D.shop_type, is_service: D.is_service ? 1 : 0, city_id: D.city_id, area: D.area, address: D.address, pincode: D.pincode, lat: D.lat, lng: D.lng, open_time: D.open_time_24, close_time: D.close_time_24, weekly_off: D.weekly_off, categories: D.cats, brands: D.brs, products: D.prods } });
      store.del('draft'); await loadOwner();
      if (!OWNER.photos.length) go('#/owner/photos', true); else { toast('Saved'); goBackTo('#/owner'); }
    } catch (e) { toast(e.message); }
  };
}

// ----- Shop photos: live camera (GPS + time stamp) or from gallery -----
const REQUIRED = ['Shop front', 'Inside view', 'Product shelves', 'Billing counter'];
async function screenOwnerPhotos() {
  if (needLogin()) return;
  render('<div class="scr nonav">' + loading() + '</div>');
  await loadOwner();
  if (!OWNER) return go('#/owner/edit?step=2', true);
  const ph = OWNER.photos;
  const have = new Set(ph.map(x => x.label));
  const next = REQUIRED.find(r => !have.has(r)) || 'More';
  render('<div class="scr nonav">' + hd('Shop photos') + '<div class="wrap"><div class="note">📸 Take a live photo at the shop (GPS & time stamp added — customers trust these more) or pick photos from your gallery. Up to 10 photos.</div>' +
    '<div class="pgrid">' + ph.map(x => '<div class="p" style="background-image:url(\'' + esc(x.url) + '\')"><span>' + esc(x.label || '') + ' · ' + esc(x.taken_at) + '</span><button data-del="' + x.id + '">×</button></div>').join('') +
    (ph.length < 10 ? '<label class="p take">' + icon('camera') + 'Take photo<small class="muted" style="font-weight:400">' + esc(next) + ' · ' + ph.length + '/10</small><input type="file" id="cam" accept="image/*" capture="environment" hidden></label>' +
      '<label class="p take">' + icon('upload') + 'From gallery<small class="muted" style="font-weight:400">choose photos</small><input type="file" id="gal" accept="image/*" multiple hidden></label>' : '') + '</div>' +
    (ph.length >= 10 ? '<p class="muted">10/10 photos — delete an old photo to add a new one.</p>' : '') +
    '<div class="h5">Required</div><div class="req">' + REQUIRED.map(r => '<div class="' + (have.has(r) ? 'done' : '') + '">' + icon('check') + r + '</div>').join('') + '</div></div>' +
    '<div class="foot"><button class="btn" id="sub">' + (OWNER.status === 'verified' ? 'Done' : 'Submit for verification') + '</button></div></div>');
  getLocation(true);
  if (document.getElementById('cam')) document.getElementById('cam').onchange = async e => {
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
  if (document.getElementById('gal')) document.getElementById('gal').onchange = async e => {
    const files = [...e.target.files].slice(0, 10 - ph.length); if (!files.length) return;
    let ok = 0, labels = REQUIRED.filter(r => !have.has(r));
    for (const f of files) {
      toast('Uploading ' + (ok + 1) + ' of ' + files.length + '…', 8000);
      try {
        const label = labels.shift() || 'More';
        const blob = await stampPhoto(f, null, label, true);
        const fd = new FormData();
        fd.append('photo', blob, 'gallery.jpg'); fd.append('label', label); fd.append('source', 'gallery');
        await api('owner/photo', { form: fd }); ok++;
      } catch (er) { toast(er.message); break; }
    }
    if (ok) toast(ok + ' photo' + (ok > 1 ? 's' : '') + ' added');
    screenOwnerPhotos();
  };
  document.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => { if (!confirm('Delete this photo?')) return; await api('owner/photo/delete', { body: { id: b.dataset.del } }).catch(e => toast(e.message)); screenOwnerPhotos(); });
  document.getElementById('sub').onclick = async () => {
    if (OWNER.status === 'verified') return goBackTo('#/owner');
    if (!ph.length) return toast('Add at least one photo of your shop');
    try { await api('owner/submit', { body: {} }); toast('Submitted! We will verify within 24 hours.'); goBackTo('#/owner'); } catch (e) { toast(e.message); }
  };
}
function stampPhoto(file, loc, label, plain) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const max = 1600, sc = Math.min(1, max / Math.max(img.width, img.height));
      const w = Math.round(img.width * sc), h = Math.round(img.height * sc);
      const c = document.createElement('canvas'); c.width = w; c.height = h;
      const x = c.getContext('2d'); x.drawImage(img, 0, 0, w, h);
      if (plain) { c.toBlob(b => b ? resolve(b) : reject(new Error('Could not process photo')), 'image/jpeg', .85); URL.revokeObjectURL(img.src); return; }
      const fs = Math.max(14, Math.round(w / 45)), bar = fs * 2.6;
      x.fillStyle = 'rgba(18,18,18,.72)'; x.fillRect(0, h - bar, w, bar);
      x.fillStyle = '#FF7A00'; x.font = '600 ' + fs + 'px sans-serif';
      const d = new Date(), ts = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
      x.fillText('⚡ Electrical Market Hub · ' + label, fs * .8, h - bar + fs * 1.15);
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
  const locked = false;
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
  document.getElementById('or').innerHTML = d.reviews.length ? '<div class="card">' + d.reviews.map(r => '<div class="rev"><b>' + esc(r.customer_name) + '</b> <span class="stars">' + '★'.repeat(r.rating) + '</span><div class="muted">' + esc(r.date) + '</div><div>' + esc(r.body || '') + '</div></div>').join('') + '</div>' : '<div class="empty">No reviews yet. Ask happy customers to rate you on Electrical Market Hub!</div>';
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
      if (!OWNER) return go('#/owner/edit?step=2', true);
      try {
        const r = await api('owner/upgrade', { body: { plan: sel } });
        openExt(r.upi_link);
        const box = sheet('<h3>Pay ' + inr(r.amount) + '</h3><p class="muted">' + esc(r.message) + '</p><p>UPI ID: <b>' + esc(m.support.upi) + '</b></p><button class="btn blk o" id="again">Open UPI app again</button><label class="l">UPI transaction ID / UTR</label><input class="inp" id="ref" placeholder="12-digit UTR"><button class="btn blk" id="done" style="margin-top:12px">I have paid</button>');
        box.querySelector('#again').onclick = () => openExt(r.upi_link);
        box.querySelector('#done').onclick = async () => { await api('owner/payment-ref', { body: { payment_id: r.payment_id, reference: box.querySelector('#ref').value } }).catch(() => {}); closeSheet(); goBackTo('#/owner'); };
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
  if (CATS) { clearInterval(CATS); CATS = null; }
  LAST_ROUTED = curHash();
  rlog('route', curHash());
  stopSlider();
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
        if (b === 'plans') return screenOwner();
        if (b === 'offers') return screenOwnerOffers();
        if (b === 'reviews') return screenOwnerReviews();
        if (b === 'quotes') return screenOwnerQuotes();
        return screenOwner();
      default: return screenHome();
    }
  } catch (e) { console.error(e); toast(e.message); }
}
// Fallbacks: manual hash edits / browser back still work
let LAST_ROUTED = null;
window.addEventListener('hashchange', () => { if (curHash() !== LAST_ROUTED) { track(curHash()); route(); } });
window.addEventListener('popstate', () => { if (curHash() !== LAST_ROUTED) { track(curHash()); route(); } });
window.addEventListener('error', e => { try { rlog('error', (e.message || 'script error') + ' @' + (e.lineno || '')); toast('Error: ' + (e.message || 'script error')); } catch (x) {} });
window.addEventListener('unhandledrejection', e => { const m = (e.reason && e.reason.message) || 'Something went wrong'; rlog('reject', m); toast(m); });
// Android back button support: native calls EH.back(); returns true if handled
window.EH = { back: navBack, open: h => go(h) };

syncNative();
if (!location.hash) { NAV.stack = []; go(S.token ? (S.role === 'owner' ? '#/owner' : '#/home') : '#/login', true); }
else { if (!NAV.stack.length || NAV.stack[NAV.stack.length - 1] !== curHash()) track(curHash()); route(); }
})();
