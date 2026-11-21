/* ============================================================
   LETUP33 API BRIDGE
   Semua element ID dalam index.html KEKAL — hanya tukar data source.
============================================================ */
const BOOT = JSON.parse(document.getElementById('lp33-boot-config').textContent);

const API = {
  base: '/api',
  csrf: document.querySelector('meta[name=csrf-token]')?.content || '',

  async call(path, opts = {}) {
    const res = await fetch(this.base + path, {
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': this.csrf, ...(opts.headers || {}) },
      ...opts,
    });
    const j = await res.json().catch(() => ({}));
    if (res.status === 401) { document.body.classList.remove('ahw-after-login'); throw new Error('Sesi tamat'); }
    if (j.status === 'error') throw new Error(j.msg);
    return j;
  },

  login(m, p) { return this.call('/auth.php?action=login',
    { method:'POST', body:new URLSearchParams({mobile:m,password:p}) }); },
  logout()    { return this.call('/auth.php?action=logout', { method:'POST' }); },
  me()        { return this.call('/auth.php?action=me').catch(()=>null); },
  wallet()    { return this.call('/wallet.php'); },
  deposit(a)  { return this.call('/wallet.php', { method:'POST', body:new URLSearchParams({type:'deposit',amount:a}) }); },
  withdraw(a) { return this.call('/wallet.php', { method:'POST', body:new URLSearchParams({type:'withdraw',amount:a}) }); },
  settings()  { return this.call('/settings.php'); },
  liveTx(id)  { return fetch(`${this.base}/live-tx.php`, { method:'POST', body:new URLSearchParams({mId:id}) }).then(r=>r.json()); },
};

const $ = id => document.getElementById(id);
const fmtMoney = n => (typeof n === 'string' && n.startsWith('RM'))
  ? n
  : 'RM ' + Number(n).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});

/* ============================================================
   RENDER MAP — setiap elemen HTML ↔ field API
============================================================ */
function renderWallet(d) {
  $('letup33Amt').textContent     = d.cash;             // ← header pill
  $('letup33Balance').textContent = d.cash;             // ← modal balance
  $('letup33DepMin').textContent  = d.limits.minDeposit;
  $('letup33WdMin').textContent   = d.limits.minWithdraw;
  $('letup33WdMax').textContent   = d.limits.maxWithdraw;

  $('letup33RolloverAmount').textContent  = `${d.rollover.current} / ${d.rollover.total}`;
  $('letup33RolloverPercent').textContent = d.rollover.percent + '%';
  $('letup33RolloverFill').style.width    = d.rollover.percent + '%';
  $('lpKProgressLabel').textContent       = 'TURNOVER · ' + d.rollover.percent + '%';

  const dial = $('lpKDial');
  dial.style.background =
    `conic-gradient(rgb(25,99,128) ${d.rollover.percent}%, rgb(223,234,240) ${d.rollover.percent}%)`;
  dial.setAttribute('aria-valuenow', d.rollover.percent);

  $('letup33VipBadge').textContent  = d.vip.subtitle;
  $('letup33VipAmount').textContent = `${d.vip.current} / ${d.vip.next} · ${d.vip.level}`;
  $('homeVipBadge').textContent     = d.vip.subtitle;
  $('homeVipAmount').innerHTML      =
    `Deposit <b>${d.vip.current}</b> / ${d.vip.next} <span style="color:var(--mut)">menuju ${d.vip.level}</span>`;
}

function renderUser(u) {
  if (!u) return;
  $('letup33Username').textContent = u.name || u.username;
  $('letup33Account').textContent  = u.username;
  $('heroUsername').textContent    = u.name || u.username;
  $('heroAccount').textContent     = u.username;
}

function renderSettings(d) {
  $('walletBalance').textContent  = d.wallet;
  $('bankName').textContent       = d.bank.bank;
  $('bankAccName').textContent    = d.bank.accName;
  $('bankAccNumber').textContent  = d.bank.accNumber;
  $('lsBankMasked').textContent   = d.bank.masked;
}

async function syncWallet()   { try { renderWallet(await API.wallet()); }   catch(e){ console.warn(e); } }
async function syncSettings() { try { renderSettings(await API.settings()); } catch(e){ console.warn(e); } }

/* ============================================================
   UI object — ganti versi mock dengan versi fetch
============================================================ */
let mode = 'deposit';
const UI = {
  openWallet() { $('letup33WalletModal').classList.add('open'); syncWallet(); },
  openSettings() { this.close(); $('drawer').classList.add('open'); syncSettings(); },
  close() {
    $('letup33WalletModal').classList.remove('open');
    $('drawer').classList.remove('open');
    $('lpKConfirm').classList.remove('open');
  },
  mode(m) {
    mode = m;
    $('letup33TabDeposit').classList.toggle('active', m==='deposit');
    $('letup33TabWithdraw').classList.toggle('active', m==='withdraw');
    const btn = $('confirmBtn');
    btn.textContent = m==='deposit' ? 'Sahkan Deposit' : 'Sahkan Withdraw';
    btn.className   = 'act ' + (m==='deposit'?'dep':'wd');
    btn.style.width = '100%';
    $('amt').value = ''; $('errMsg').textContent = '';
  },
  async login() {
    const f = document.querySelector('#guestView form');
    try {
      const r = await API.login(f.mobile.value.trim(), f.password.value);
      API.csrf = r.csrf;
      document.body.classList.add('ahw-after-login');
      renderUser(r.user);
      await syncWallet();
      toast('✅ Log masuk berjaya!');
    } catch (e) { toast('❌ ' + e.message); }
  },
  async logout() {
    try { await API.logout(); } catch {}
    this.close();
    toast('👋 Anda telah log keluar');
  },
  async submit() {
    const v = parseFloat($('amt').value);
    $('errMsg').textContent = '';
    if (!v || v <= 0) { $('errMsg').textContent = 'Masukkan jumlah yang sah.'; return; }
    try {
      const r = mode==='deposit' ? await API.deposit(v) : await API.withdraw(v);
      await syncWallet();
      this.close();
      toast(`✅ ${mode==='deposit'?'Deposit':'Withdraw'} ${r.amount} berjaya!`);
    } catch (e) { $('errMsg').textContent = e.message; }
  },
  async sync() { await syncWallet(); await syncSettings(); toast('🔄 Data disync'); },
};

/* ============================================================
   Live TX — guna BOOT.liveTx.maxRows & sources (sama seperti asal)
============================================================ */
function startLiveTx() {
  const body  = $('tx-body');
  const count = $('txCount');
  const max   = BOOT.liveTx.maxRows;
  const seen  = new Set();

  const mask = m => (!m || m.length < 5) ? (m||'—') : m.slice(0,2)+'•••'+m.slice(-3);

  async function refresh() {
    const all = [];
    for (const src of BOOT.liveTx.sources) {
      try {
        const j = await API.liveTx(src.mId);
        if (j.status !== 'SUCCESS' || !j.data) continue;
        (j.data.DEPOSIT  || []).forEach(t => t.id && all.push({...t, game:src.game, txType:'deposit'}));
        (j.data.WITHDRAW || []).forEach(t => t.id && all.push({...t, game:src.game, txType:'withdrawal'}));
      } catch {}
    }
    all.sort((a,b)=>(b.created_at||0)-(a.created_at||0));
    for (const tx of all) {
      const key = `${tx.game}_${tx.txType}_${tx.id}_${tx.mobile}_${tx.cash}`;
      if (seen.has(key)) continue;
      seen.add(key);
      if (seen.size > 500) seen.delete(seen.values().next().value);
      insertRow(tx);
    }
  }
  function insertRow(tx) {
    if (body.querySelector('.tx-empty')) body.innerHTML = '';
    const dep = tx.txType === 'deposit';
    const amt = Number(String(tx.cash || '0').replace(/,/g,''));
    const row = document.createElement('div');
    row.className = 'tx-row';
    row.innerHTML =
      `<span>${dep?'DEPOSIT':'WITHDRAW'}</span>
       <span>${mask(tx.mobile)}</span>
       <span style="color:${dep?'var(--acc2)':'var(--acc)'}">
         ${dep?'+':'-'} ${fmtMoney(amt)}
       </span>
       <span>${tx.game || '—'}</span>`;
    body.prepend(row);
    while (body.children.length > max) body.lastChild.remove();
    count.textContent = body.children.length + ' transaksi';
  }
  refresh();
  setInterval(refresh, 5000);
}

/* Ticker dari PHP config (bukan DB mock) */
$('lp33Ticker').textContent = BOOT.platform.ticker;

/* Boot: tarik state sebenar, ganti nilai mock */
(async function boot() {
  const me = await API.me();
  if (me) { renderUser(me.user); await syncWallet(); }
  startLiveTx();
})();