/**
 * Front du generateur — AUCUNE generation cote client (Lot 4).
 * Le serveur (POST JSON -> ValidationService -> RouterOSGenerator) est la
 * source de verite unique : le JS synchronise le formulaire, pre-valide en
 * miroir (sans remplacer le serveur), puis affiche script ou erreurs.
 */
const bridgePorts = ['ether1','ether2','ether3','ether4','ether5','ether6','ether7','ether8','sfp1'];
const PRESETS = {
  home: 'presets/home-yaounde.json',
  cyber: 'presets/cybercafe-douala.json',
  minefop: 'presets/salle-minefop.json'
};
const state = JSON.parse(JSON.stringify(window.APP_DEFAULTS));

const el = (s) => document.querySelector(s);
const bridgesBox = el('#bridges');
const wifiBox = el('#wifiList');
const netBox = el('#networks');
const output = el('#output');
const errorsBox = el('#errors');
const copyOk = el('#copyOk');

function uid(){ return Math.random().toString(36).slice(2,8); }

/** Ecart de nom .rsc : {identity}-{AAAA-MM-JJ}.rsc, caracteres surs uniquement. */
function rscName(){
  const id = String(state.identity || 'mikrotik-config').toLowerCase()
    .replace(/[^a-z0-9-_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '') || 'mikrotik-config';
  const d = new Date();
  const stamp = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
  return id + '-' + stamp + '.rsc';
}
/** Echappement HTML strict pour interpolation en attribut/valeur (& \" < >). */
const AMP = '\u0026';
function escHtml(v){
  return String(v ?? '')
    .replace(/&/g, AMP + 'amp;')
    .replace(/</g, AMP + 'lt;')
    .replace(/>/g, AMP + 'gt;')
    .replace(/"/g, AMP + 'quot;')
    .replace(/'/g, AMP + '#39;');
}

function showErrors(errors, focusFirst){
  const items = Object.entries(errors || {});
  if(!items.length){ hideErrors(); return; }
  errorsBox.innerHTML = '<strong>Le serveur a refuse la demande :</strong><ul>'
    + items.map(([k, v]) => '<li>' + (k === 'global' ? '' : '<code>' + escHtml(k) + '</code> : ') + escHtml(v) + '</li>').join('')
    + '</ul>';
  errorsBox.hidden = false;
  errorsBox.scrollIntoView({ block: 'nearest' });
  if (focusFirst) focusFieldForKey(items[0][0]);
}
function hideErrors(){ errorsBox.hidden = true; errorsBox.innerHTML = ''; }

/** Associe une cle d'erreur serveur au champ correspondant pour le focus. */
function focusFieldForKey(key){
  const map = { identity: '#f-identity', security_profile: '#f-profile', guest_subnet: '#f-guest-subnet', vlan_subnet: '#f-vlan-subnet' };
  if (map[key]) { const t = document.querySelector(map[key]); if (t) { t.focus(); t.setAttribute('aria-invalid', 'true'); return; } }
  const m = /^(\w+)\.(\d+)\.(.+)$/.exec(key || '');
  if (m) {
    const [, group, idx, field] = m;
    const typeMap = { bridges: 'bridge-name', wifi: 'wifi-iface', networks: 'net-ip' };
    const t = document.querySelector('[data-type="' + (typeMap[group] || '') + '"][data-i="' + idx + '"]');
    if (t) { t.focus(); t.setAttribute('aria-invalid', 'true'); return; }
  }
  const first = document.querySelector('#routerForm input[aria-invalid="true"], #routerForm input');
  if (first) first.focus();
}

/* Pre-validation miroir (indicative) : le serveur reste l'arbitre. */
function isIpv4(s){ return /^(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/.test(String(s || '').trim()); }
function ipToLong(s){ const p = String(s).split('.').map(Number); return ((p[0]*256+p[1])*256+p[2])*256+p[3]; }
function inSameSubnet(ip, base, cidr){
  if (!isIpv4(ip) || !isIpv4(base)) return false;
  const c = Math.max(0, Math.min(32, +cidr || 0));
  if (c === 0) return true;
  const mask = c === 32 ? 0xFFFFFFFF : (0xFFFFFFFF << (32 - c)) >>> 0;
  return ((ipToLong(ip) & mask) >>> 0) === ((ipToLong(base) & mask) >>> 0);
}
function validateClient(s){
  const errs = {};
  if (!String(s.identity || '').trim()) errs.identity = 'Nom du routeur requis.';
  (s.wifi || []).forEach((w, i) => {
    if (!String(w.iface || '').trim()) errs['wifi.' + i + '.iface'] = 'Interface Wi-Fi requise.';
    if (!String(w.ssid || '').trim()) errs['wifi.' + i + '.ssid'] = 'SSID requis.';
    if (String(w.password || '').length < 8) errs['wifi.' + i + '.password'] = 'Mot de passe trop court (8 caracteres min).';
  });
  (s.networks || []).forEach((n, i) => {
    if (!isIpv4(n.address)) errs['networks.' + i + '.address'] = 'Adresse IP invalide.';
    const cidr = /^\d{1,2}$/.test(String(n.mask).trim()) ? +n.mask : -1;
    if (!(cidr >= 0 && cidr <= 32) && !isIpv4(n.mask)) errs['networks.' + i + '.mask'] = 'Masque invalide (0-32 ou 255.255.255.0).';
    if (n.gateway && !isIpv4(n.gateway)) errs['networks.' + i + '.gateway'] = 'Passerelle invalide.';
    else if (n.gateway && isIpv4(n.address) && cidr >= 0 && !inSameSubnet(n.gateway, n.address, cidr)) errs['networks.' + i + '.gateway'] = 'Passerelle hors du sous-reseau.';
    if (n.dns && !isIpv4(n.dns)) errs['networks.' + i + '.dns'] = 'DNS invalide.';
    if (n.dhcp) {
      if (!isIpv4(n.range_start)) errs['networks.' + i + '.range_start'] = 'Debut de plage DHCP invalide.';
      if (!isIpv4(n.range_end)) errs['networks.' + i + '.range_end'] = 'Fin de plage DHCP invalide.';
      if (isIpv4(n.range_start) && isIpv4(n.range_end) && ipToLong(n.range_start) > ipToLong(n.range_end)) errs['networks.' + i + '.range'] = 'Plage DHCP inversee (debut > fin).';
    }
  });
  return errs;
}
function renderBridges(){
  bridgesBox.innerHTML = state.bridges.map(function(b,i){
    var ports = bridgePorts.map(function(p){
      var c = b.ports.includes(p) ? 'checked' : '';
      return '<label><input type="checkbox" data-type="bridge-port" data-i="' + i + '" value="' + p + '" ' + c + '>' + p + '</label>';
    }).join('');
    return '<div class="card"><div class="grid">'
      + '<label>Bridge name<input type="text" data-type="bridge-name" data-i="' + i + '" value="' + escHtml(b.name) + '"></label>'
      + '</div><div class="ports" role="group" aria-label="Ports">' + ports + '</div></div>';
  }).join('');
}
function renderWifi(){
  wifiBox.innerHTML = state.wifi.map(function(w,i){
    return '<div class="card"><div class="grid">'
      + '<label>Interface<input type="text" data-type="wifi-iface" data-i="' + i + '" value="' + escHtml(w.iface) + '"></label>'
      + '<label>SSID<input type="text" data-type="wifi-ssid" data-i="' + i + '" value="' + escHtml(w.ssid) + '" maxlength="32"></label>'
      + '<label>Securite<select data-type="wifi-sec" data-i="' + i + '"><option' + (w.security==='WPA2-Personal'?' selected':'') + '>WPA2-Personal</option><option' + (w.security==='WPA3-Personal'?' selected':'') + '>WPA3-Personal</option></select></label>'
      + '<label>Mot de passe<input type="password" data-type="wifi-pass" data-i="' + i + '" value="' + escHtml(w.password) + '" minlength="8" autocomplete="new-password"></label>'
      + '</div></div>';
  }).join('');
}
function renderNets(){
  netBox.innerHTML = state.networks.map(function(n,i){
    return '<div class="card"><div class="grid">'
      + '<label>Interface<input type="text" data-type="net-if" data-i="' + i + '" value="' + escHtml(n.interface) + '"></label>'
      + '<label>Adresse IP<input type="text" data-type="net-ip" data-i="' + i + '" value="' + escHtml(n.address) + '" inputmode="decimal"></label>'
      + '<label>Masque/CIDR<input type="text" data-type="net-mask" data-i="' + i + '" value="' + escHtml(n.mask) + '"></label>'
      + '<label>Passerelle<input type="text" data-type="net-gw" data-i="' + i + '" value="' + escHtml(n.gateway) + '" inputmode="decimal"></label>'
      + '<label>DNS<input type="text" data-type="net-dns" data-i="' + i + '" value="' + escHtml(n.dns) + '" inputmode="decimal"></label>'
      + '<label>DHCP debut<input type="text" data-type="net-rs" data-i="' + i + '" value="' + escHtml(n.range_start) + '" inputmode="decimal"></label>'
      + '<label>DHCP fin<input type="text" data-type="net-re" data-i="' + i + '" value="' + escHtml(n.range_end) + '" inputmode="decimal"></label>'
      + '<label><input type="checkbox" data-type="net-dhcp" data-i="' + i + '" ' + (n.dhcp?'checked':'') + '> Activer DHCP</label>'
      + '</div></div>';
  }).join('');
}
function render(){ renderBridges(); renderWifi(); renderNets(); }
function syncFromForm(){
  document.querySelectorAll('[data-type]').forEach(function(inp){
    var i = +inp.dataset.i; var type = inp.dataset.type;
    if(type==='bridge-name') state.bridges[i].name = inp.value;
    if(type==='bridge-port'){ state.bridges[i].ports = bridgePorts.filter(function(x){ var c = document.querySelector('[data-type="bridge-port"][data-i="' + i + '"][value="' + x + '"]'); return c && c.checked; }); }
    if(type==='wifi-iface') state.wifi[i].iface = inp.value;
    if(type==='wifi-ssid') state.wifi[i].ssid = inp.value;
    if(type==='wifi-sec') state.wifi[i].security = inp.value;
    if(type==='wifi-pass') state.wifi[i].password = inp.value;
    if(type==='net-if') state.networks[i].interface = inp.value;
    if(type==='net-ip') state.networks[i].address = inp.value;
    if(type==='net-mask') state.networks[i].mask = inp.value;
    if(type==='net-gw') state.networks[i].gateway = inp.value;
    if(type==='net-dns') state.networks[i].dns = inp.value;
    if(type==='net-rs') state.networks[i].range_start = inp.value;
    if(type==='net-re') state.networks[i].range_end = inp.value;
    if(type==='net-dhcp') state.networks[i].dhcp = inp.checked;
  });
  state.identity = document.querySelector('input[name="identity"]').value;
  state.security_profile = document.querySelector('select[name="security_profile"]').value;
  state.ntp = document.querySelector('input[name="ntp"]').checked;
  state.nat = document.querySelector('input[name="nat"]').checked;
  state.firewall = document.querySelector('input[name="firewall"]').checked;
  state.guest = document.querySelector('input[name="guest"]').checked;
  state.guest_subnet = document.querySelector('input[name="guest_subnet"]').value;
  state.vlan = document.querySelector('input[name="vlan"]').checked;
  state.vlan_subnet = document.querySelector('input[name="vlan_subnet"]').value;
}
async function requestScript(){
  syncFromForm(); hideErrors();
  document.querySelectorAll('[aria-invalid]').forEach(function(t){ t.removeAttribute('aria-invalid'); });
  var local = validateClient(state);
  if(Object.keys(local).length){ showErrors(local, true); return null; }
  var res;
  try{
    res = await fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(state) });
  }catch(err){ showErrors({ global: 'Impossible de joindre le serveur : ' + err.message }, false); return null; }
  var data = null;
  try{ data = await res.json(); }catch(e){ /* corps non JSON */ }
  if(!res.ok || !data || data.ok !== true){ showErrors(data && data.errors ? data.errors : { global: 'Erreur serveur (HTTP ' + res.status + ').' }, true); return null; }
  return data.script;
}
async function generateIntoOutput(){
  var script = await requestScript();
  if(script === null) return null;
  output.value = script;
  var ps = el('#printScript'); if (ps) { ps.textContent = script; var sh = el('#printSheet'); if (sh) sh.hidden = false; }
  return script;
}
function add(type){
  if(type==='bridge') state.bridges.push({name:'bridge-' + uid(),ports:[]});
  if(type==='wifi') state.wifi.push({iface:'wifi' + (state.wifi.length+1),ssid:'SSID-' + uid(),security:'WPA2-Personal',password:'change-me-123'});
  if(type==='network'){
    var k = 10 + state.networks.length;
    state.networks.push({interface:'bridge-lan',address:'192.168.' + k + '.1',mask:24,gateway:'192.168.' + k + '.1',dns:'192.168.' + k + '.1',dhcp:true,range_start:'192.168.' + k + '.10',range_end:'192.168.' + k + '.254'});
  }
  render();
}
function hydrateStaticFields(){
  document.querySelector('input[name="identity"]').value = state.identity;
  document.querySelector('select[name="security_profile"]').value = state.security_profile;
  ['ntp','nat','firewall','guest','vlan'].forEach(function(k){ var box = document.querySelector('input[name="' + k + '"]'); if(box) box.checked = !!state[k]; });
  var gs = document.querySelector('input[name="guest_subnet"]'); if (gs) gs.value = state.guest_subnet || '10.10.10.0/24';
  var vs = document.querySelector('input[name="vlan_subnet"]'); if (vs) vs.value = state.vlan_subnet || '10.100.100.0/24';
}
async function loadPreset(kind){
  var map = { home: 'home-yaounde.json', cyber: 'cybercafe-douala.json', minefop: 'salle-minefop.json' };
  var url = 'presets/' + (map[kind] || 'home-yaounde.json');
  try {
    var res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    var data = await res.json();
    Object.keys(data).forEach(function(k){ state[k] = data[k]; });
    render(); hydrateStaticFields(); hideErrors();
    generateIntoOutput();
  } catch (err) { showErrors({ global: 'Preset introuvable (' + url + ') : ' + err.message }, false); }
}
document.addEventListener('click', function(e){
  var b = e.target.closest('[data-add]'); if(b){ add(b.dataset.add); }
  var p = e.target.closest('[data-preset]'); if(p){ loadPreset(p.dataset.preset); }
});
document.addEventListener('input', function(){ syncFromForm(); });
el('#routerForm').addEventListener('submit', function(e){
  e.preventDefault();
  var backup = document.querySelector('input[name="backup_ok"]');
  if (backup && !backup.checked && (state.guest || state.vlan || state.firewall)) {
    showErrors({ global: 'Cochez « J\u2019ai sauvegarde le routeur » avant de generer un script avec pare-feu, invite ou VLAN.' }, false);
    backup.focus();
    return;
  }
  generateIntoOutput();
});
el('#copyBtn').addEventListener('click', async function(){
  var script = await generateIntoOutput();
  if(script === null) return;
  try{
    await navigator.clipboard.writeText(script);
    if (copyOk) { copyOk.hidden = false; copyOk.textContent = 'Copie !'; setTimeout(function(){ copyOk.hidden = true; }, 2000); }
  }catch(err){ showErrors({ global: 'Copie impossible : ' + err.message }, false); }
});
el('#downloadBtn').addEventListener('click', async function(){
  var script = await generateIntoOutput();
  if(script === null) return;
  var blob = new Blob([script], {type:'text/plain'});
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = rscName();
  a.click();
  URL.revokeObjectURL(a.href);
});
var printBtn = el('#printBtn');
if (printBtn) printBtn.addEventListener('click', async function(){ var s = await generateIntoOutput(); if (s !== null) window.print(); });
render();
hydrateStaticFields();
generateIntoOutput();
