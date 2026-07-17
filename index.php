<?php
/**
 * index.php - Vue utilisateur - Réseau Mobile Aéroport DKR
 */
require_once __DIR__ . '/includes/xml_parser.php';

$xml = loadNetwork();
$equipements = $xml ? getEquipements($xml) : [];
$liens = $xml ? getLiens($xml) : [];
$zones = $xml ? getZonesCouverture($xml) : [];
$operateurs = $xml ? getOperateurs($xml) : [];
$stats = $xml ? getStats($xml) : [];
$couches = $xml ? getCouches($xml) : [];

$zoneInfo = $xml ? [
  'nom' => (string)$xml['zone'],
  'code' => (string)$xml['code_IATA'],
  'ville' => (string)$xml['ville'],
  'pays' => (string)$xml['pays'],
  'coords' => (string)$xml['coordonnees'],
  'surface' => (string)$xml['surface_km2'],
  'desc' => (string)$xml->description,
  'maj' => (string)$xml['date_mise_a_jour'],
] : [];

// Préparer données JS pour le canvas
$jsNodes = [];
$jsLinks = [];
foreach ($equipements as $eq) {
  $jsNodes[] = [
    'id' => $eq['id'],
    'type' => $eq['type'],
    'nom' => $eq['nom'],
    'icon' => getTypeIcon($eq['type']),
    'statut' => $eq['statut'],
  ];
}
foreach ($liens as $i => $l) {
  $jsLinks[] = [
    'id' => $i,
    'from' => $l['de'],
    'to' => $l['vers'],
    'type' => $l['type'],
    'debit' => (float)($l['debit_Gbps'] ?? 0),
    'statut' => $l['statut'],
  ];
}

// Signal strength helper
function signalPercent(string $dbm): int {
  $v = (int)$dbm;
  // -65 => excellent (100%), -95 => poor (0%)
  return max(0, min(100, (int)(($v + 95) / 30 * 100)));
}
function signalColor(string $dbm): string {
  $p = signalPercent($dbm);
  if ($p > 70) return '#00ff88';
  if ($p > 40) return '#ffd600';
  return '#ff6b2b';
}

function techBadge(string $tech): string {
  $t = strtolower($tech);
  if (str_contains($t, '5g')) return '<span class="tech-badge tech-5g">5G</span>';
  if (str_contains($t, '4g+') || str_contains($t, 'lte-a')) return '<span class="tech-badge tech-4gplus">4G+</span>';
  if (str_contains($t, '4g') || str_contains($t, 'lte')) return '<span class="tech-badge tech-4g">4G</span>';
  if (str_contains($t, '3g')) return '<span class="tech-badge tech-3g">3G</span>';
  if (str_contains($t, '2g')) return '<span class="tech-badge tech-2g">2G</span>';
  return '<span class="tech-badge tech-4g">' . htmlspecialchars($tech) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réseau Mobile - Aéroport DKR | Yoff, Dakar</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- HEADER -->
<header class="site-header">
  <div class="header-inner">
    <a href="index.php" class="site-logo">
      <div class="logo-icon">✈️</div>
      <div class="logo-text">
        <span class="main">NET·DKR</span>
        <span class="sub">Mobile Network Monitor</span>
      </div>
    </a>
    <nav class="main-nav">
      <a href="index.php" class="active">Dashboard</a>
      <a href="index.php#equipements">Équipements</a>
      <a href="index.php#topologie">Topologie</a>
      <a href="index.php#zones">Couverture</a>
      <a href="admin/index.php" class="admin-link">⚙ Admin</a>
    </nav>
    <div class="header-status">
      <div class="status-dot"></div>
      Réseau actif · <?= date('H:i') ?> UTC
    </div>
  </div>
</header>

<!-- MAIN -->
<main class="page-content">

  <!-- ZONE BANNER -->
  <section class="zone-banner">
    <div>
      <div class="zone-title">✈ <?= htmlspecialchars($zoneInfo['nom'] ?? '') ?></div>
      <div class="zone-subtitle">
        IATA: <?= htmlspecialchars($zoneInfo['code'] ?? '') ?> &nbsp;|&nbsp;
        <?= htmlspecialchars($zoneInfo['ville'] ?? '') ?>, <?= htmlspecialchars($zoneInfo['pays'] ?? '') ?> &nbsp;|&nbsp;
        <?= htmlspecialchars($zoneInfo['coords'] ?? '') ?> &nbsp;|&nbsp;
        Màj: <?= htmlspecialchars($zoneInfo['maj'] ?? '') ?>
      </div>
    </div>
    <div class="zone-meta">
      <div class="meta-chip">
        <span class="val"><?= htmlspecialchars($zoneInfo['surface'] ?? '') ?> km²</span>
        <span class="lbl">Surface</span>
      </div>
      <div class="meta-chip">
        <span class="val"><?= count($equipements) ?></span>
        <span class="lbl">Équipements</span>
      </div>
      <div class="meta-chip">
        <span class="val"><?= count($liens) ?></span>
        <span class="lbl">Liens</span>
      </div>
      <div class="meta-chip">
        <span class="val"><?= count($operateurs) ?></span>
        <span class="lbl">Opérateurs</span>
      </div>
    </div>
  </section>

  <!-- OPERATEURS -->
  <div style="margin-bottom:28px;">
    <div class="section-title"><span class="accent">01</span> Opérateurs présents</div>
    <div class="op-chips">
      <?php foreach ($operateurs as $op): ?>
        <div class="op-chip" style="color:<?= htmlspecialchars($op['couleur']) ?>;border-color:<?= htmlspecialchars($op['couleur']) ?>22;background:<?= htmlspecialchars($op['couleur']) ?>11;">
          <?= htmlspecialchars($op['nom']) ?> &nbsp;·&nbsp; MCC/MNC <?= htmlspecialchars($op['code_MCC']) ?>/<?= htmlspecialchars($op['code_MNC']) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- STATS KPI -->
  <div class="section-title"><span class="accent">02</span> Indicateurs clés de performance</div>
  <div class="stats-grid" style="margin-bottom:36px;">
    <?php
    $kpis = [
      ['icon'=>'📥','key'=>'Trafic journalier moyen DL','lbl'=>'Trafic DL / jour'],
      ['icon'=>'📤','key'=>'Trafic journalier moyen UL','lbl'=>'Trafic UL / jour'],
      ['icon'=>'👥','key'=>'Utilisateurs simultanés peak','lbl'=>'Users simultanés (pic)'],
      ['icon'=>'⚡','key'=>'Disponibilité réseau','lbl'=>'Disponibilité'],
      ['icon'=>'🕐','key'=>'Latence moyenne 4G','lbl'=>'Latence 4G moy.'],
      ['icon'=>'🚀','key'=>'Latence moyenne 5G','lbl'=>'Latence 5G moy.'],
      ['icon'=>'📞','key'=>'Appels voix simultanés max','lbl'=>'Appels voix max'],
      ['icon'=>'🔌','key'=>'Nombre équipements actifs','lbl'=>'Équipements actifs'],
      ['icon'=>'🔗','key'=>'Longueur fibre déployée','lbl'=>'Fibre déployée'],
      ['icon'=>'📶','key'=>'Nombre antennes indoor','lbl'=>'Antennes indoor'],
    ];
    foreach ($kpis as $k):
    ?>
    <div class="stat-card">
      <div class="icon"><?= $k['icon'] ?></div>
      <div class="value"><?= htmlspecialchars($stats[$k['key']] ?? 'N/A') ?></div>
      <div class="label"><?= $k['lbl'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- TABS SECTION -->
  <div id="equipements"></div>
  <div class="tabs">
    <button class="tab-btn" data-tab="equipements">📦 Équipements (<?= count($equipements) ?>)</button>
    <button class="tab-btn" data-tab="topologie">🗺 Topologie</button>
    <button class="tab-btn" data-tab="liens">🔗 Liens (<?= count($liens) ?>)</button>
    <button class="tab-btn" data-tab="zones">📶 Couverture (<?= count($zones) ?>)</button>
    <button class="tab-btn" data-tab="description">📄 Description</button>
  </div>

  <!-- TAB: EQUIPEMENTS -->
  <div id="tab-equipements" class="tab-pane">
    <!-- Filtres -->
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:20px;">
      <input id="equip-search" type="text" placeholder="🔍  Rechercher un équipement..."
        style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:9px 16px;color:var(--text-primary);font-family:var(--font-mono);font-size:13px;width:280px;outline:none;">
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <button class="tab-btn filter-btn active" data-filter="all" style="padding:7px 14px;font-size:12px;">Tous</button>
        <?php
        $types = array_unique(array_column($equipements, 'type'));
        foreach ($types as $t):
        ?>
        <button class="tab-btn filter-btn" data-filter="<?= htmlspecialchars($t) ?>" style="padding:7px 14px;font-size:12px;"><?= getTypeIcon($t) ?> <?= htmlspecialchars($t) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php foreach ($couches as $couche): ?>
    <div class="section-title" style="margin-bottom:14px;"><span class="accent">▶</span> <?= htmlspecialchars($couche['nom']) ?> <span style="color:var(--text-dim);font-size:11px;">(<?= $couche['count'] ?>)</span></div>
    <div class="equip-grid" style="margin-bottom:32px;">
      <?php foreach ($equipements as $eq):
        if ($eq['couche'] !== $couche['nom']) continue;
      ?>
      <div class="equip-card" data-equip-id="<?= htmlspecialchars($eq['id']) ?>"
           data-equip-type="<?= htmlspecialchars($eq['type']) ?>"
           onclick="openModal('<?= htmlspecialchars($eq['id']) ?>')">
        <!-- Hidden data for modal -->
        <script type="application/json" class="equip-specs-data"><?= json_encode($eq['specs']) ?></script>
        <script type="application/json" class="equip-cover-data"><?= json_encode($eq['couverture']) ?></script>

        <div class="equip-header">
          <div class="equip-icon"><?= getTypeIcon($eq['type']) ?></div>
          <div class="equip-title">
            <div class="equip-name"><?= htmlspecialchars($eq['nom']) ?></div>
            <div class="equip-type"><?= htmlspecialchars($eq['type']) ?> · <?= htmlspecialchars($eq['fabricant'] ?? '') ?> <?= htmlspecialchars($eq['modele'] ?? '') ?></div>
          </div>
          <?= getStatutBadge($eq['statut'] ?? 'actif') ?>
        </div>

        <div class="equip-desc"><?= htmlspecialchars(substr($eq['description'], 0, 140)) ?><?= strlen($eq['description']) > 140 ? '...' : '' ?></div>

        <div class="equip-meta">
          <?php if (!empty($eq['adresse_ip']) && $eq['adresse_ip'] !== 'N/A'): ?>
          <span class="meta-tag ip">IP: <?= htmlspecialchars($eq['adresse_ip']) ?></span>
          <?php endif; ?>
          <?php if (!empty($eq['localisation'])): ?>
          <span class="meta-tag loc">📍 <?= htmlspecialchars(substr($eq['localisation'], 0, 50)) ?></span>
          <?php endif; ?>
          <?php if (!empty($eq['fabricant'])): ?>
          <span class="meta-tag"><?= htmlspecialchars($eq['fabricant']) ?></span>
          <?php endif; ?>
        </div>

        <?php if (!empty($eq['specs'])): ?>
        <table class="specs-table" style="margin-top:12px;">
          <?php foreach (array_slice($eq['specs'], 0, 3) as $sk => $sv): ?>
          <tr><td><?= htmlspecialchars($sk) ?></td><td><?= htmlspecialchars($sv) ?></td></tr>
          <?php endforeach; ?>
          <?php if (count($eq['specs']) > 3): ?>
          <tr><td colspan="2" style="color:var(--text-dim);font-size:11px;">+ <?= count($eq['specs']) - 3 ?> autres specs · Cliquer pour détails</td></tr>
          <?php endif; ?>
        </table>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- TAB: TOPOLOGIE -->
  <div id="tab-topologie" class="tab-pane" style="display:none;">
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px;font-family:var(--font-mono);">
      🖱 Glisser pour déplacer · Molette pour zoomer · Cliquer sur un nœud pour détails · Les points animés représentent le flux de données en temps réel.
    </p>
    <div class="canvas-container" id="topologie">
      <canvas id="network-canvas"></canvas>
    </div>
    <div class="canvas-legend">
      <?php
      $legendTypes = ['EPC'=>'Cœur EPC','BBU'=>'Unité BBU','RRU'=>'Antenne RRU','Small_Cell'=>'Small Cell','Routeur_Agregation'=>'Routeur','Gateway_IoT'=>'IoT Gateway'];
      foreach ($legendTypes as $t => $lbl): ?>
        <div class="legend-item">
          <div class="legend-dot" style="color:<?= TYPE_COLORS[$t] ?? '#888' ?>;border-color:<?= TYPE_COLORS[$t] ?? '#888' ?>"></div>
          <?= $lbl ?>
        </div>
      <?php endforeach; ?>
      <div style="margin-left:auto;display:flex;gap:14px;">
        <?php
        $legendLinks = ['Fibre_100GE'=>'Fibre 100GE','Fibre_10GE'=>'Fibre 10GE','Fibre_CPRI'=>'CPRI','Microonde_23GHz'=>'Microonde (secours)'];
        foreach ($legendLinks as $t => $lbl): ?>
          <div class="legend-item">
            <div class="legend-line" style="background:<?= LINK_COLORS[$t] ?? '#888' ?>"></div>
            <?= $lbl ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- TAB: LIENS -->
  <div id="tab-liens" class="tab-pane" style="display:none;">
    <div style="overflow-x:auto;">
    <table class="liens-table">
      <thead>
        <tr>
          <th>ID</th><th>Type</th><th>Depuis</th><th>Vers</th>
          <th>Débit</th><th>Distance</th><th>Statut</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($liens as $l): ?>
        <tr>
          <td style="color:var(--accent-blue);"><?= htmlspecialchars($l['id']) ?></td>
          <td>
            <span class="type-badge" style="background:<?= getLienTypeColor($l['type']) ?>22;color:<?= getLienTypeColor($l['type']) ?>;border:1px solid <?= getLienTypeColor($l['type']) ?>44;">
              <?= htmlspecialchars($l['type']) ?>
            </span>
          </td>
          <td style="color:var(--text-primary);"><?= htmlspecialchars($l['de']) ?></td>
          <td style="color:var(--text-primary);"><?= htmlspecialchars($l['vers']) ?></td>
          <td style="color:var(--accent-yellow);"><?= htmlspecialchars($l['debit_Gbps'] ?? $l['debit_Mbps'] ?? '?') ?> Gbps</td>
          <td><?= htmlspecialchars($l['distance_m'] ?? $l['distance_km'] ?? '?') ?><?= isset($l['distance_km']) ? ' km' : ' m' ?></td>
          <td><?= getStatutBadge($l['statut'] ?? 'actif') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- TAB: ZONES DE COUVERTURE -->
  <div id="tab-zones" class="tab-pane" style="display:none;" id="zones">
    <div style="overflow-x:auto;">
    <table class="zones-table">
      <thead>
        <tr>
          <th>Zone</th><th>Surface</th><th>Technologie max</th>
          <th>Signal</th><th>Opérateurs</th><th>Équipements</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($zones as $z): ?>
        <tr>
          <td style="color:var(--text-primary);font-weight:600;"><?= htmlspecialchars($z['nom']) ?></td>
          <td style="font-family:var(--font-mono);font-size:12px;"><?= htmlspecialchars($z['surface_m2']) ?> m²</td>
          <td><?= techBadge($z['technologie_max'] ?? '') ?></td>
          <td>
            <?php $p = signalPercent($z['signal_dbm'] ?? '-80'); $c = signalColor($z['signal_dbm'] ?? '-80'); ?>
            <div class="signal-bar">
              <div class="signal-bg"><div class="signal-fill" style="width:<?= $p ?>%;background:<?= $c ?>;"></div></div>
              <span class="signal-val" style="color:<?= $c ?>;"><?= htmlspecialchars($z['signal_dbm']) ?> dBm</span>
            </div>
          </td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
              <?php foreach (explode(',', $z['operateurs'] ?? '') as $op):
                $opData = array_filter($operateurs, fn($o) => $o['id'] === trim($op));
                $opData = reset($opData);
              ?>
              <?php if ($opData): ?>
              <span style="font-size:11px;padding:2px 8px;border-radius:3px;border:1px solid <?= $opData['couleur'] ?>44;color:<?= $opData['couleur'] ?>;background:<?= $opData['couleur'] ?>11;">
                <?= htmlspecialchars($opData['nom']) ?>
              </span>
              <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </td>
          <td style="font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars($z['equipements_couvrants'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- TAB: DESCRIPTION -->
  <div id="tab-description" class="tab-pane" style="display:none;">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:32px;max-width:900px;">
      <div style="font-size:22px;font-weight:700;margin-bottom:16px;color:var(--accent-blue);">
        📋 Description du réseau mobile — <?= htmlspecialchars($zoneInfo['nom'] ?? '') ?>
      </div>
      <p style="font-size:15px;line-height:1.8;color:var(--text-secondary);margin-bottom:24px;">
        <?= nl2br(htmlspecialchars(trim($zoneInfo['desc'] ?? ''))) ?>
      </p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;">
        <?php
        $infos = [
          'Code IATA' => $zoneInfo['code'] ?? '',
          'Ville' => $zoneInfo['ville'] ?? '',
          'Pays' => $zoneInfo['pays'] ?? '',
          'Coordonnées GPS' => $zoneInfo['coords'] ?? '',
          'Surface totale' => ($zoneInfo['surface'] ?? '') . ' km²',
          'Dernière mise à jour' => $zoneInfo['maj'] ?? '',
        ];
        foreach ($infos as $k => $v):
        ?>
        <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:8px;padding:16px;">
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:var(--text-dim);margin-bottom:6px;"><?= $k ?></div>
          <div style="font-family:var(--font-mono);font-size:14px;color:var(--text-primary);"><?= htmlspecialchars($v) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:24px;">
        <div class="section-title"><span class="accent">Technologies</span> déployées</div>
        <?php foreach ($xml->technologies->technologie ?? [] as $tech): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--grid-line);">
          <div><?= techBadge((string)$tech['nom']) ?> <?= htmlspecialchars((string)$tech['nom']) ?></div>
          <div style="font-family:var(--font-mono);font-size:12px;color:var(--text-secondary);">
            <?= htmlspecialchars((string)$tech['frequence_MHz']) ?> MHz &nbsp;·&nbsp;
            <?= htmlspecialchars((string)$tech['debit_max_Mbps']) ?> Mbps max
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</main>

<!-- MODAL EQUIPEMENT -->
<div id="equip-modal" class="modal-overlay">
  <div class="modal-box" style="position:relative;">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div id="modal-title" class="modal-title"></div>
    <div id="modal-subtitle" class="modal-subtitle"></div>
    <div id="modal-status" style="margin-bottom:16px;"></div>

    <div style="font-size:14px;color:var(--text-secondary);line-height:1.7;margin-bottom:20px;" id="modal-desc"></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
      <div style="background:var(--bg-deep);border:1px solid var(--border);border-radius:8px;padding:14px;">
        <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;">Adresse IP</div>
        <div id="modal-ip" style="font-family:var(--font-mono);color:var(--accent-yellow);"></div>
      </div>
      <div style="background:var(--bg-deep);border:1px solid var(--border);border-radius:8px;padding:14px;">
        <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;">Localisation</div>
        <div id="modal-loc" style="font-size:13px;color:var(--accent-green);"></div>
      </div>
    </div>

    <div style="font-size:12px;text-transform:uppercase;letter-spacing:2px;color:var(--text-dim);margin-bottom:10px;">Spécifications techniques</div>
    <table class="specs-table" id="modal-specs" style="margin-bottom:20px;"></table>

    <div style="font-size:12px;text-transform:uppercase;letter-spacing:2px;color:var(--text-dim);margin-bottom:10px;">Zones de couverture</div>
    <div id="modal-coverage" class="cov-tags"></div>
  </div>
</div>

<!-- Inject network data for canvas -->
<?php
$typeColors = ['EPC'=>'#00c8ff','MSC'=>'#00c8ff','HSS'=>'#00c8ff','PCRF'=>'#00c8ff','BBU'=>'#aa44ff','RRU'=>'#ff6b2b','RRU_Indoor'=>'#ff9944','Small_Cell'=>'#ffaa22','Routeur_Agregation'=>'#00ff88','Switch_Aggregation'=>'#00cc66','ODF'=>'#00aa44','Lien_Microonde'=>'#ff44aa','Gateway_IoT'=>'#44aaff','Répéteur'=>'#ffdd00'];
define('TYPE_COLORS', $typeColors);
define('LINK_COLORS', ['Fibre_CPRI'=>'#00d4ff','Fibre_eCPRI'=>'#0088ff','Fibre_10GE'=>'#ffaa00','Fibre_100GE'=>'#ff6600','Fibre_MPLS_100G'=>'#ff2200','Microonde_23GHz'=>'#cc44ff','Coax_RF'=>'#44ff88','Fibre_1GE'=>'#ffdd00']);
?>
<script>
window.NETWORK_DATA = {
  nodes: <?= json_encode($jsNodes, JSON_UNESCAPED_UNICODE) ?>,
  links: <?= json_encode($jsLinks, JSON_UNESCAPED_UNICODE) ?>
};
const TYPE_COLORS = <?= json_encode($typeColors) ?>;
const LINK_COLORS = {
  'Fibre_CPRI':'#00d4ff','Fibre_eCPRI':'#0088ff',
  'Fibre_10GE':'#ffaa00','Fibre_100GE':'#ff6600',
  'Fibre_MPLS_100G':'#ff2200','Microonde_23GHz':'#cc44ff',
  'Coax_RF':'#44ff88','Fibre_1GE':'#ffdd00'
};
</script>
<script src="js/app.js"></script>
</body>
</html>

