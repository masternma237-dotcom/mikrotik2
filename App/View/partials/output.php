<?php
/**
 * Sortie script + pied de page — partiel inclus par layout.php.
 * Variable attendue : $jsUrl (string).
 * Pourquoi : #errors porte aria-live (annonce lecteur d'écran),
 * la sortie est imprimable (fiche TP via .print-only).
 */
?>
  <section class="panel" aria-labelledby="t-out">
    <div class="section-head">
      <h2 id="t-out">Sortie</h2>
      <button class="btn" type="button" id="printBtn">Imprimer la fiche TP</button>
    </div>
    <div id="errors" class="errors" role="alert" aria-live="assertive" hidden></div>
    <label class="sr-only" for="output">Script RouterOS généré</label>
    <textarea id="output" spellcheck="false" placeholder="Le script généré par le serveur apparaîtra ici."></textarea>
    <div class="print-only" id="printSheet" hidden>
      <h2>Fiche TP — Générateur MikroTik</h2>
      <p>Script à importer après sauvegarde (<code>/export file=backup</code>).</p>
      <pre id="printScript"></pre>
    </div>
  </section>
</div>

<script>window.APP_DEFAULTS = <?= $json ?>;</script>
<script src="<?= htmlspecialchars((string)($jsUrl ?? 'assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
