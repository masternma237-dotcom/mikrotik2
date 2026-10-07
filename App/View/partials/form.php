<?php
/**
 * Formulaire — partiel inclus par layout.php.
 * Variable attendue : $identityDefault (string).
 * Pourquoi : chaque label porte un for/id (accessibilité), les sous-réseaux
 * guest/VLAN sont paramétrables (plus de 10.10.10.0/24 en dur).
 */
?>
  <form id="routerForm" class="panel" novalidate>
    <section aria-labelledby="t-ident">
      <h2 id="t-ident">1. Identité et Administration</h2>
      <label for="f-identity">Nom du routeur
        <input id="f-identity" name="identity" type="text" required maxlength="64"
          value="<?= htmlspecialchars((string)($identityDefault ?? 'MikroTik-Router'), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <label for="f-profile">Profil de sécurité
        <select id="f-profile" name="security_profile">
          <option>Home</option>
          <option>Office</option>
          <option>Strict</option>
        </select>
      </label>
    </section>

    <section aria-labelledby="t-l2">
      <div class="section-head">
        <h2 id="t-l2">2. Interfaces couche 2</h2>
        <button class="btn" type="button" data-add="bridge">+ Bridge</button>
      </div>
      <div id="bridges"></div>
    </section>

    <section aria-labelledby="t-wifi">
      <div class="section-head">
        <h2 id="t-wifi">3. Wi-Fi / Wave2</h2>
        <button class="btn" type="button" data-add="wifi">+ Wi-Fi</button>
      </div>
      <div id="wifiList"></div>
    </section>

    <section aria-labelledby="t-ip">
      <div class="section-head">
        <h2 id="t-ip">4. IP et services réseau</h2>
        <button class="btn" type="button" data-add="network">+ Réseau</button>
      </div>
      <div id="networks"></div>
    </section>

    <section class="toggles" aria-labelledby="t-opt">
      <h2 id="t-opt" class="sr-only">5. Options</h2>
      <label for="f-ntp"><input id="f-ntp" type="checkbox" name="ntp"> NTP Client</label>
      <label for="f-nat"><input id="f-nat" type="checkbox" name="nat"> NAT Masquerade</label>
      <label for="f-firewall"><input id="f-firewall" type="checkbox" name="firewall"> Pare-feu</label>
      <label for="f-guest"><input id="f-guest" type="checkbox" name="guest"> Réseau invité</label>
      <label for="f-guest-subnet">Sous-réseau invité
        <input id="f-guest-subnet" name="guest_subnet" type="text" inputmode="latin"
          value="10.10.10.0/24" placeholder="10.10.10.0/24" pattern="^[0-9.]+/[0-9]{1,2}$">
      </label>
      <label for="f-vlan"><input id="f-vlan" type="checkbox" name="vlan"> VLAN 100 gestion</label>
      <label for="f-vlan-subnet">Sous-réseau VLAN
        <input id="f-vlan-subnet" name="vlan_subnet" type="text" inputmode="latin"
          value="10.100.100.0/24" placeholder="10.100.100.0/24" pattern="^[0-9.]+/[0-9]{1,2}$">
      </label>
      <label class="save-check" for="f-backup">
        <input id="f-backup" type="checkbox" name="backup_ok">
        J'ai sauvegardé le routeur (<code>/export file=backup</code>) avant d'importer ce script.
      </label>
    </section>

    <section>
      <div class="actions">
        <button class="btn primary" type="submit">Générer</button>
        <button class="btn" type="button" id="copyBtn">Copier</button>
        <button class="btn" type="button" id="downloadBtn">Télécharger .rsc</button>
        <span id="copyOk" class="copy-ok" role="status" hidden>Copié !</span>
      </div>
    </section>
  </form>
