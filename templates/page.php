<?php
/**
 * The logbook page. Variables come from page() in public/index.php:
 * $book, $who, $log, $boot, $hold, $mount, $platform, $sealed, $ops, $error, $old.
 */
$sketches = $hold['groups']['sketches/']['files'] ?? [];
$storms = max(0, $boot['deployments'] - 1);
$onClever = Platform::onClever();
$oldWind = $old['wind'] ?? 'W';
$oldWeather = $old['weather'] ?? 'Fair';
$waterY = 82 - (int) round(min(1, $hold['share']) * 42);  // Plimsoll gauge: empty draft at y=82, load line at y=40
?>
<!doctype html>
<html lang="en">
<head>
<!-- Clever Brand Kit — <head> snippet. -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#13172e">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/cc-brand.css">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 101 116'%3E%3Cpolygon fill='%23AB2B4A' points='100.423 29.134 100.423 86.868 89.721 73.062 83.76 58.003 83.756 58.003 89.418 42.731'/%3E%3Cpolygon fill='%23E87A68' points='.429 29.134 .429 86.868 9.889 73.481 17.092 58.003 17.096 58.003 9.773 43.319'/%3E%3Cpolygon fill='%23D74D4E' points='83.756 58.003 52.241 60.003 17.092 58.003 31.76 29.134 50.426 .269 70.722 31.961'/%3E%3Cpolygon fill='%23BD3246' points='100.423 29.134 83.756 58.003 50.426 .269'/%3E%3Cpolygon fill='%23F19175' points='50.426 .269 17.092 58.003 .425 29.134'/%3E%3Cpolygon fill='%23CF3942' points='83.756 58.003 72.365 82.931 50.426 115.737 27.849 81.83 17.092 58.003'/%3E%3Cpolygon fill='%23E0625B' points='50.426 115.737 .425 86.868 17.092 58.003'/%3E%3Cpolygon fill='%239A244F' points='50.422 115.737 100.423 86.868 83.756 58.003'/%3E%3C/svg%3E">
<!-- The logbook's own universe -->
<link href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=IM+Fell+English+SC&family=La+Belle+Aurore&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/logbook.css">
<script src="/assets/logbook.js" defer></script>
<title>The Ship's Logbook</title>
<meta name="description" content="A ship's logbook kept as plain files on a Clever Cloud FS Bucket: entries, sketches and boot records survive every redeploy.">
</head>
<body>

<svg class="defs" aria-hidden="true" focusable="false">
  <symbol id="wax" viewBox="0 0 100 100">
    <path fill="currentColor" d="M98.0 50.0C98.4 52.4 97.0 55.0 96.3 57.3C95.5 59.7 94.5 61.9 93.5 64.1C92.6 66.4 92.0 68.8 90.7 70.8C89.5 72.7 87.5 74.3 85.9 76.1C84.3 77.8 82.8 79.6 81.1 81.1C79.3 82.6 77.5 84.0 75.5 85.1C73.5 86.2 71.1 86.3 69.2 87.6C67.3 89.0 66.0 91.5 64.1 93.3C62.2 95.2 60.1 98.1 57.7 98.7C55.4 99.3 52.4 97.8 50.0 96.9C47.6 96.1 45.4 94.3 43.1 93.5C40.8 92.7 38.5 92.9 36.3 92.1C34.2 91.3 32.2 90.1 30.2 88.9C28.2 87.8 26.4 86.4 24.4 85.2C22.5 83.9 20.4 82.9 18.6 81.4C16.9 79.8 15.8 77.8 14.1 76.1C12.4 74.4 10.3 73.0 8.5 71.2C6.6 69.4 3.8 67.5 3.2 65.2C2.5 62.9 3.8 59.7 4.6 57.2C5.4 54.6 7.5 52.3 8.0 50.0C8.4 47.7 7.4 45.5 7.4 43.3C7.4 41.0 7.4 38.6 7.9 36.3C8.5 34.1 9.7 32.1 10.5 29.9C11.4 27.7 11.9 25.2 13.1 23.2C14.2 21.1 15.9 19.3 17.4 17.4C19.0 15.6 20.8 13.9 22.6 12.3C24.4 10.6 26.0 8.2 28.3 7.4C30.6 6.7 33.7 7.3 36.3 7.7C38.8 8.1 41.3 10.0 43.6 9.8C45.9 9.7 47.7 7.8 50.0 6.9C52.3 6.1 54.8 4.7 57.2 4.6C59.5 4.6 61.8 5.8 64.1 6.5C66.4 7.2 68.8 7.7 71.0 8.7C73.2 9.7 75.5 10.9 77.3 12.4C79.2 13.9 80.7 15.9 82.3 17.7C83.9 19.5 85.9 21.1 86.9 23.2C88.0 25.3 88.3 27.9 88.7 30.3C89.0 32.7 87.9 35.3 88.8 37.4C89.6 39.5 92.0 41.0 93.5 43.1C95.1 45.2 97.5 47.6 98.0 50.0Z"/>
    <circle cx="50" cy="51" r="33" fill="none" stroke="#000" stroke-opacity=".28" stroke-width="3"/>
    <circle cx="50" cy="50" r="33" fill="none" stroke="#fff" stroke-opacity=".22" stroke-width="1.4"/>
    <g transform="translate(50 51) scale(.4) translate(-50.4 -58)" fill="#000" fill-opacity=".26">
      <polygon points="100.423 29.134 100.423 86.868 83.756 58.003"/><polygon points=".429 29.134 .429 86.868 17.092 58.003"/><polygon points="83.756 58.003 17.092 58.003 50.426 .269"/><polygon points="83.756 58.003 50.426 115.737 17.092 58.003"/>
    </g>
    <g transform="translate(49.4 49.6) scale(.4) translate(-50.4 -58)" fill="#fff" fill-opacity=".16">
      <polygon points="100.423 29.134 83.756 58.003 50.426 .269"/><polygon points="50.426 .269 17.092 58.003 .425 29.134"/><polygon points="50.426 115.737 .425 86.868 17.092 58.003"/>
    </g>
    <ellipse cx="36" cy="30" rx="16" ry="9" fill="#fff" fill-opacity=".14" transform="rotate(-30 36 30)"/>
  </symbol>
  <symbol id="rose" viewBox="-100 -100 200 200">
    <circle r="62" fill="none" stroke="currentColor" stroke-width="1"/>
    <circle r="56" fill="none" stroke="currentColor" stroke-width=".5" stroke-dasharray="1 3"/>
    <g fill="currentColor">
      <path d="M0 -78 L7 -7 L0 0 Z" /><path d="M0 -78 L-7 -7 L0 0 Z" opacity=".35"/>
      <path d="M78 0 L7 7 L0 0 Z" /><path d="M78 0 L7 -7 L0 0 Z" opacity=".35"/>
      <path d="M0 78 L-7 7 L0 0 Z" /><path d="M0 78 L7 7 L0 0 Z" opacity=".35"/>
      <path d="M-78 0 L-7 -7 L0 0 Z" /><path d="M-78 0 L-7 7 L0 0 Z" opacity=".35"/>
      <g transform="rotate(45)"><path d="M0 -52 L5 -5 L0 0 Z" /><path d="M0 -52 L-5 -5 L0 0 Z" opacity=".35"/><path d="M52 0 L5 5 L0 0 Z" /><path d="M52 0 L5 -5 L0 0 Z" opacity=".35"/><path d="M0 52 L-5 5 L0 0 Z" /><path d="M0 52 L5 5 L0 0 Z" opacity=".35"/><path d="M-52 0 L-5 -5 L0 0 Z" /><path d="M-52 0 L-5 5 L0 0 Z" opacity=".35"/></g>
    </g>
    <circle r="3" fill="currentColor"/>
  </symbol>
</svg>

<header class="cc-topbar">
  <div class="cc-container cc-topbar__inner">
    <a href="https://www.clever.cloud" target="_blank" rel="noopener" aria-label="Clever Cloud"><?php readfile(__DIR__ . '/../public/cc-logo.svg'); ?></a>
    <span class="cc-topbar__sep"></span>
    <div class="cc-topbar__demo"><small>Demo</small>PHP + FS Bucket</div>
    <span class="cc-topbar__spacer"></span>
    <nav class="cc-topbar__nav" aria-label="Status">
      <span class="cc-pill cc-pill--mono cc-hide-sm">PHP <?= h(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?> + FS Bucket</span>
      <?php if ($onClever): ?>
        <span class="cc-pill cc-pill--live"><span class="cc-dot"></span>Live on Clever Cloud</span>
      <?php else: ?>
        <span class="cc-pill cc-pill--muted"><span class="cc-dot cc-dot--muted"></span>Running locally</span>
      <?php endif; ?>
      <a class="cc-pill cc-pill--gradient" href="https://academy.clever.cloud/" target="_blank" rel="noopener">Get certified ↗</a>
    </nav>
  </div>
</header>

<main class="sea">
  <svg class="portolan" viewBox="0 0 1440 2000" preserveAspectRatio="xMidYMin slice" aria-hidden="true" focusable="false">
    <g class="portolan__lines">
      <?php foreach ([[230, 330], [1290, 1240]] as [$cx, $cy]): ?>
        <?php for ($i = 0; $i < 32; $i++): $a = $i * M_PI / 16; ?>
          <line x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= round($cx + cos($a) * 2600) ?>" y2="<?= round($cy + sin($a) * 2600) ?>"<?= $i % 4 === 0 ? ' class="major"' : '' ?>/>
        <?php endfor; ?>
      <?php endforeach; ?>
    </g>
    <use href="#rose" x="20" y="120" width="420" height="420" class="portolan__rose"/>
    <use href="#rose" x="1160" y="1110" width="260" height="260" class="portolan__rose"/>
  </svg>

  <div class="cc-container voyage">
    <p class="chart-note">Visitors write in this log and paste in sketches. Every instance signs the watch roll when it boots. It is all plain files on a Clever Cloud FS Bucket, so a redeploy is only weather.</p>

    <?php if ($book->isBucket && !$mount): ?>
      <p class="alarm" role="alert">No FS Bucket is mounted on <?= h($book->label) ?>/: this log would sink with the next deployment.</p>
    <?php elseif (!$book->isBucket && $onClever): ?>
      <p class="alarm" role="alert">CC_FS_BUCKET is not set: this log is written on the instance's own disk and will sink with the next deployment.</p>
    <?php endif; ?>

    <article class="book" aria-label="The ship's logbook">
      <section class="page page--verso">
        <header class="masthead">
          <p class="masthead__kicker">A journal of the proceedings</p>
          <h1>The Ship&rsquo;s Logbook</h1>
          <p class="masthead__sub">kept aboard the good ship Clever, every page a file on an FS&nbsp;Bucket</p>
          <p class="masthead__state">
            <svg class="wax-mark <?= wax($who['instance']) ?>" aria-hidden="true"><use href="#wax"/></svg>
            <span><?= $who['instance'] === 'local' ? 'This machine' : 'Instance ' . h($who['instance']) . ' (' . h($who['name']) . ')' ?> holds the pen for this page.
            The log holds <?= $log['total'] ?> <?= $log['total'] === 1 ? 'entry' : 'entries' ?> and <?= count($sketches) ?> <?= count($sketches) === 1 ? 'sketch' : 'sketches' ?>, and has weathered <?= $storms ?> <?= $storms === 1 ? 'storm' : 'storms' ?>.</span>
          </p>
        </header>

        <form class="entry-form" method="post" action="/entries" enctype="multipart/form-data">
          <h2>Make an entry</h2>
          <label class="field-label" for="remarks">Remarks</label>
          <textarea class="ink-lines" id="remarks" name="remarks" rows="4" maxlength="<?= Logbook::MAX_REMARKS ?>" required placeholder="Sighted land at noon…"><?= h($old['remarks'] ?? '') ?></textarea>
          <p class="field-hint"><span id="remarks-count"><?= Logbook::MAX_REMARKS ?></span> characters left</p>

          <div class="form-grid">
            <fieldset class="windrose">
              <legend class="field-label">Wind from</legend>
              <div class="windrose__dial">
                <svg class="windrose__rose" aria-hidden="true"><use href="#rose"/></svg>
                <?php foreach (Logbook::WINDS as $wind): ?>
                  <input class="windrose__input visually-hidden" type="radio" name="wind" id="wind-<?= $wind ?>" value="<?= $wind ?>"<?= $oldWind === $wind ? ' checked' : '' ?>>
                  <label class="windrose__point windrose__point--<?= strtolower($wind) ?>" for="wind-<?= $wind ?>"><?= $wind ?></label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <div class="form-col">
              <label class="field-label" for="weather">Weather</label>
              <select class="ink-select" id="weather" name="weather">
                <?php foreach (Logbook::WEATHER as $weather): ?>
                  <option<?= $oldWeather === $weather ? ' selected' : '' ?>><?= $weather ?></option>
                <?php endforeach; ?>
              </select>

              <label class="field-label" for="hand">Your name</label>
              <input class="ink-input" id="hand" name="hand" maxlength="<?= Logbook::MAX_HAND ?>" placeholder="A passenger" value="<?= h($old['hand'] ?? '') ?>" autocomplete="off">

              <span class="field-label" id="sketch-label">Sketch</span>
              <label class="sketch-drop" for="sketch">
                <input id="sketch" name="sketch" type="file" accept="image/png,image/jpeg,image/gif,image/webp" aria-describedby="sketch-hint">
                <span class="sketch-drop__name" id="sketch-name">Paste in a drawing or a photo</span>
                <span class="sketch-drop__hint" id="sketch-hint">PNG, JPEG, GIF or WebP, 2 MB at most</span>
              </label>
            </div>
          </div>

          <p class="form-error" id="form-error" role="alert"<?= $error ? '' : ' hidden' ?>><?= h($error) ?></p>

          <button class="seal-button" type="submit">
            <svg class="seal-button__wax <?= wax($who['instance']) ?>" aria-hidden="true"><use href="#wax"/></svg>
            <span class="seal-button__text">Seal the entry<small>written to <?= h($book->label) ?>/entries/</small></span>
          </button>
        </form>

        <section class="watch-roll" aria-labelledby="watch-roll-title">
          <h2 id="watch-roll-title">The watch roll</h2>
          <p class="file-note"><?= h($book->label) ?>/boot.log, <?= $boot['total'] ?> <?= $boot['total'] === 1 ? 'line' : 'lines' ?>. Each instance signs it when it boots, and a storm is a new deployment.</p>
          <?php if (!$boot['lines']): ?>
            <p class="empty-note">No watch has signed yet. The boot hook writes here on the next start.</p>
          <?php else: ?>
            <ol class="roll">
              <?php $previous = null; foreach ($boot['lines'] as $line): ?>
                <?php if ($previous !== null && $line['deployment'] !== $previous): ?>
                  <li class="roll__storm"><svg viewBox="0 0 120 10" aria-hidden="true"><path d="M0 5 q 7.5 -6 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0"/></svg><span>storm weathered</span></li>
                <?php endif; $previous = $line['deployment']; ?>
                <li class="roll__line<?= $line['deployment'] === $who['deployment'] ? ' is-current' : '' ?>">
                  <svg class="wax-mark <?= wax($line['instance']) ?>" aria-hidden="true"><use href="#wax"/></svg>
                  <time datetime="<?= h($line['at']) ?>"><?= h(logDate($line['at'])) ?></time>
                  <span class="roll__who">Instance <?= h($line['instance']) ?>, <?= h($line['name']) ?></span>
                  <span class="roll__deploy"><?= h(shortId($line['deployment'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>
      </section>

      <section class="page page--recto" aria-labelledby="log-title">
        <h2 class="visually-hidden" id="log-title">Entries</h2>
        <table class="log">
          <thead>
            <tr><th scope="col">Hour</th><th scope="col">Winds</th><th scope="col">Remarks, signed</th></tr>
          </thead>
          <tbody>
            <?php foreach ($log['entries'] as $entry): ?>
              <tr class="entry<?= $entry['id'] === $sealed ? ' is-fresh' : '' ?>" id="entry-<?= h($entry['id']) ?>">
                <td class="entry__hour"><time datetime="<?= h($entry['written_at']) ?>"><?= h(logDate($entry['written_at'], true)) ?></time></td>
                <td class="entry__winds"><?= h($entry['wind'] ?: '—') ?><small><?= h(strtolower($entry['weather'])) ?></small></td>
                <td class="entry__remarks">
                  <p class="entry__text"><?= nl2br(h($entry['remarks'])) ?></p>
                  <?php if (!empty($entry['sketch'])): ?>
                    <figure class="tipped-in">
                      <img src="/sketches/<?= h($entry['sketch']['file']) ?>" alt="Sketch attached by <?= h($entry['hand']) ?>" width="<?= (int) $entry['sketch']['width'] ?>" height="<?= (int) $entry['sketch']['height'] ?>" loading="lazy">
                      <figcaption><?= h($book->label) ?>/sketches/<?= h($entry['sketch']['file']) ?>, <?= Logbook::bytes($entry['sketch']['bytes']) ?></figcaption>
                    </figure>
                  <?php endif; ?>
                  <p class="entry__sign">
                    <span class="entry__name"><?= h($entry['hand']) ?></span>
                    <span class="entry__via"><svg class="wax-mark <?= wax($entry['instance']) ?>" aria-hidden="true"><use href="#wax"/></svg>sealed by instance <?= h($entry['instance']) ?><?= empty($entry['seed']) ? '' : ', first-boot seed' ?></span>
                  </p>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (!$log['entries']): ?>
          <p class="empty-note">The page is blank. Make the first entry on the left.</p>
        <?php elseif ($log['total'] > count($log['entries'])): ?>
          <p class="page-foot"><?= $log['total'] - count($log['entries']) ?> older <?= $log['total'] - count($log['entries']) === 1 ? 'entry stays' : 'entries stay' ?> in the hold, in <?= h($book->label) ?>/entries/.</p>
        <?php endif; ?>
        <span class="folio" aria-hidden="true">fol. <?= max(1, (int) ceil($log['total'] / 8)) ?></span>
      </section>
    </article>

    <div class="deck">
      <section class="slip manifest" aria-labelledby="manifest-title">
        <h2 id="manifest-title">Hold inventory</h2>
        <p class="slip__lead">Every file on the bucket, as the app sees it.</p>
        <div class="manifest__body">
          <figure class="plimsoll">
            <svg viewBox="0 0 240 120" role="img" aria-label="<?= h(Logbook::bytes($hold['bytes'])) ?> stowed of the 100 MB free tier">
              <defs><clipPath id="hull"><path d="M14 18 H226 L218 70 Q212 98 186 100 H50 Q22 98 18 70 Z"/></clipPath></defs>
              <rect class="plimsoll__sea" x="0" y="<?= $waterY ?>" width="240" height="<?= 120 - $waterY ?>"/>
              <path class="plimsoll__hull" d="M14 18 H226 L218 70 Q212 98 186 100 H50 Q22 98 18 70 Z"/>
              <g clip-path="url(#hull)">
                <path class="plimsoll__planks" d="M0 36 H240 M0 54 H240 M0 72 H240 M0 90 H240"/>
                <rect class="plimsoll__water" x="0" y="<?= $waterY ?>" width="240" height="<?= 120 - $waterY ?>"/>
              </g>
              <path class="plimsoll__wave" d="M0 <?= $waterY ?> q 7.5 -3.5 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0 t 15 0"/>
              <path class="plimsoll__deck" d="M10 18 H230"/>
              <circle class="plimsoll__mark" cx="120" cy="40" r="11"/>
              <path class="plimsoll__mark" d="M100 40 H140"/>
            </svg>
            <figcaption><strong><?= h(Logbook::bytes($hold['bytes'])) ?></strong> stowed of the 100&nbsp;MB free tier (<?= number_format($hold['share'] * 100, 2) ?>%). When the sea reaches the ring of the load line, the free tier is full.</figcaption>
          </figure>
          <div class="manifest__files">
            <?php if (!$hold['groups']): ?>
              <p class="empty-note">The hold is empty.</p>
            <?php endif; ?>
            <?php foreach ($hold['groups'] as $folder => $group): ?>
              <details<?= $folder === '' ? ' open' : '' ?>>
                <summary><span class="manifest__folder"><?= h($book->label . '/' . $folder) ?></span><span><?= count($group['files']) ?> <?= count($group['files']) === 1 ? 'file' : 'files' ?>, <?= h(Logbook::bytes($group['bytes'])) ?></span></summary>
                <ul>
                  <?php foreach (array_slice($group['files'], 0, 6) as $file): ?>
                    <li><span class="manifest__name"><?= h($file['name']) ?></span><span><?= h(Logbook::bytes($file['bytes'])) ?></span><time datetime="<?= gmdate('c', $file['mtime']) ?>"><?= h(logDate(gmdate('Y-m-d\TH:i:s\Z', $file['mtime']))) ?></time></li>
                  <?php endforeach; ?>
                  <?php if (count($group['files']) > 6): ?>
                    <li class="manifest__more">and <?= count($group['files']) - 6 ?> more</li>
                  <?php endif; ?>
                </ul>
              </details>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="below-deck" aria-labelledby="below-title">
        <h2 id="below-title">Below deck</h2>
        <p class="below-deck__lead">What the FS Bucket did to answer this page: plain PHP file functions, no SDK, no API.</p>
        <p class="below-deck__mount">
          <?php if ($mount): ?>
            <span class="op__call">mount</span> <span class="op__path"><?= h($mount['type']) ?> <?= h($mount['source']) ?></span> on <span class="op__path">$APP_HOME/<?= h($book->label) ?></span>
          <?php else: ?>
            <span class="op__call">mount</span> <span class="op__detail"><?= h($book->label) ?>/ is a local folder here. On Clever Cloud, CC_FS_BUCKET mounts the bucket on $APP_HOME/<?= h($book->label) ?>.</span>
          <?php endif; ?>
        </p>
        <div class="seal-ops" id="seal-ops" hidden>
          <h3 id="seal-ops-title">Your entry was written like this</h3>
          <ol class="ops" id="seal-ops-list"></ol>
        </div>
        <h3>Reading this page</h3>
        <ol class="ops">
          <?php foreach ($ops as $op): ?>
            <li><span class="op__call"><?= h($op['call']) ?></span><span class="op__path"><?= h($op['path']) ?></span><span class="op__detail"><?= h($op['detail']) ?></span><span class="op__ms"><?= number_format($op['ms'], 2) ?> ms</span></li>
          <?php endforeach; ?>
        </ol>
        <p class="below-deck__total"><?= count($ops) ?> calls, <?= number_format(array_sum(array_column($ops, 'ms')), 1) ?> ms on the bucket.</p>
      </section>
    </div>

    <div class="deck deck--crew">
      <section class="slip hail" aria-labelledby="hail-title">
        <h2 id="hail-title">Hail the watches</h2>
        <p class="slip__lead">Eight requests in a row. Whichever instance answers reports what it reads in the shared hold.</p>
        <button class="ink-button" type="button" id="hail-button">Hail the watches</button>
        <ol class="hail__replies" id="hail-replies" aria-live="polite"></ol>
        <p class="hail__summary" id="hail-summary"></p>
      </section>

      <section class="cc-platform" aria-labelledby="platform-title">
        <div class="cc-platform__head">
          <h3 id="platform-title">Running on Clever Cloud</h3>
          <span class="cc-pill cc-pill--ok"><span class="cc-dot"></span><?= $onClever ? 'Instance ' . h($who['instance']) : 'Local' ?></span>
        </div>
        <dl class="cc-kv">
          <?php foreach ($platform as $row): ?>
            <div><dt><?= h($row['label']) ?></dt><dd<?= $row['value'] === '' ? ' class="is-empty"' : '' ?>><?= $row['value'] === '' ? 'not set (local)' : h($row['value']) ?></dd></div>
          <?php endforeach; ?>
        </dl>
        <div class="cc-platform__foot">Injected by the platform at each deployment: <a href="https://www.clever.cloud/developers/doc/develop/common-configuration/environment-variables/reference/" target="_blank" rel="noopener">reference</a>.</div>
      </section>
    </div>
  </div>
</main>

<footer class="cc-footer">
  <div class="cc-container">
    <div class="cc-footer__inner">
      <div class="cc-footer__brand">
        <?php readfile(__DIR__ . '/../public/cc-logo.svg'); ?>
        <p>European PaaS, fully managed. Push your code, the platform runs it.</p>
        <div class="cc-trust"><span>ISO 27001</span><span>HDS</span><span>HOSTED IN FRANCE</span></div>
      </div>
      <div>
        <h4>Documentation</h4>
        <ul>
          <li><a href="https://www.clever.cloud/developers/doc/deploy/storage/fs-bucket/" target="_blank" rel="noopener">FS Bucket add-on</a></li>
          <li><a href="https://www.clever.cloud/developers/doc/deploy/applications/php/" target="_blank" rel="noopener">PHP runtime</a></li>
          <li><a href="https://www.clever.cloud" target="_blank" rel="noopener">clever.cloud</a></li>
        </ul>
      </div>
      <a class="foot-cert" href="https://academy.clever.cloud/" target="_blank" rel="noopener">
        <?php readfile(__DIR__ . '/../public/cc-badge.svg'); ?>
        <span><strong>Clever Cloud Academy</strong>Get certified on academy.clever.cloud ↗</span>
      </a>
    </div>
    <div class="cc-footer__bottom"><span>A Clever Cloud presales demo</span><span>© 2026 Clever Cloud</span></div>
  </div>
</footer>

</body>
</html>
