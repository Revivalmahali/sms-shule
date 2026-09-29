<?php
/**
 * index.php — SMS-SHULE
 * Landing page (ukurasa wa umma) — iliyoboreshwa na PICHA:
 *   - Hero yenye picha ya nyuma + cutout ya mwanafunzi
 *   - Gallery ya "Maisha Shuleni"
 * Kama mtumiaji ameshalogin, anapelekwa dashibodi yake moja kwa moja.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/images.php';

if (is_logged_in()) {
    redirect(dashboard_for($_SESSION['user']['role']));
}

$hero    = img_url('hero');
$cutout  = img_url('cutout');
$gallery = sms_images()['gallery'] ?? [];
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS Shule — Mfumo wa Usimamizi wa Shule</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, sans-serif; }
  body { background:#f8fafc; color:#1e293b; line-height:1.6; }

  /* ---------- NAV ---------- */
  nav { display:flex; align-items:center; justify-content:space-between;
        padding:16px 6%; background:#fff; border-bottom:1px solid #e2e8f0;
        position:sticky; top:0; z-index:10; }
  .logo { font-size:22px; font-weight:800; color:#1e293b; }
  .logo span { color:#f59e0b; }
  nav .links a { margin-left:14px; text-decoration:none; font-weight:600; font-size:14px;
                 padding:9px 18px; border-radius:8px; }
  .btn-ghost { color:#1e3c72; border:1px solid #cbd5e1; }
  .btn-ghost:hover { background:#f1f5f9; }
  .btn-solid { background:#1e3c72; color:#fff !important; }
  .btn-solid:hover { background:#152c54; }

  /* ---------- HERO (picha ya nyuma) ---------- */
  .hero { position:relative; color:#fff; text-align:center;
          padding:120px 6% 150px; background-size:cover; background-position:center 30%;
          background-color:#26324b; }
  .hero::before { content:''; position:absolute; inset:0;
          background:linear-gradient(180deg, rgba(10,25,55,.78), rgba(10,25,55,.55) 55%, rgba(10,25,55,.88)); }
  .hero > * { position:relative; z-index:1; }
  .hero h1 { font-size:clamp(30px, 5vw, 48px); margin-bottom:14px; text-shadow:0 2px 6px rgba(0,0,0,.4); }
  .hero p { font-size:17px; opacity:.95; max-width:640px; margin:0 auto 30px;
            text-shadow:0 1px 3px rgba(0,0,0,.4); }
  .hero .cta a { display:inline-block; margin:6px 8px; padding:13px 30px; border-radius:10px;
                 font-weight:700; text-decoration:none; font-size:15px; }
  .cta-primary { background:#f59e0b; color:#3b2600; }
  .cta-primary:hover { background:#e8920a; }
  .cta-secondary { background:rgba(255,255,255,.14); color:#fff; border:1px solid rgba(255,255,255,.4); }
  .cta-secondary:hover { background:rgba(255,255,255,.24); }

  /* cutout ya mwanafunzi (picha transparent) — inaelea juu ya chini ya hero */
  .hero-cutout {
      position:absolute; bottom:-30px; right:8%; z-index:2;
      width:260px; max-width:38vw;
      filter:drop-shadow(0 14px 24px rgba(0,0,0,.35));
  }
  @media (max-width: 700px) { .hero-cutout { display:none; } }

  /* ---------- SECTIONS ---------- */
  section { padding:64px 6%; }
  .section-title { text-align:center; font-size:26px; margin-bottom:8px; }
  .section-sub { text-align:center; color:#64748b; margin-bottom:38px; }

  /* ---------- ROLES ---------- */
  .roles { display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr));
           gap:18px; max-width:1100px; margin:0 auto; }
  .role-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px;
               padding:26px 22px; box-shadow:0 1px 3px rgba(15,23,42,.06); }
  .role-card .ic { width:52px; height:52px; border-radius:12px; display:flex;
                   align-items:center; justify-content:center; font-size:25px; margin-bottom:14px; }
  .role-card h3 { font-size:17px; margin-bottom:6px; }
  .role-card ul { margin:10px 0 0 18px; font-size:13.5px; color:#475569; }
  .role-card ul li { margin-bottom:4px; }

  .r-admin   .ic { background:#e3f2fd; } .r-admin   h3 { color:#1565c0; }
  .r-teacher .ic { background:#e8f5e9; } .r-teacher h3 { color:#2e7d32; }
  .r-parent  .ic { background:#fff3e0; } .r-parent  h3 { color:#e65100; }
  .r-student .ic { background:#f3e5f5; } .r-student h3 { color:#6a1b9a; }

  /* ---------- GALLERY (Maisha Shuleni) ---------- */
  .gallery { background:#fff; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; }
  .gallery-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
                  gap:14px; max-width:1100px; margin:0 auto; }
  .gallery-grid figure {
      position:relative; border-radius:12px; overflow:hidden;
      height:190px; margin:0;
      box-shadow:0 1px 4px rgba(15,23,42,.12);
  }
  .gallery-grid img { width:100%; height:100%; object-fit:cover; display:block;
                      transition:transform .35s ease; }
  .gallery-grid figure:hover img { transform:scale(1.06); }
  .gallery-grid figcaption {
      position:absolute; left:0; right:0; bottom:0;
      padding:24px 12px 10px;
      background:linear-gradient(180deg, transparent, rgba(10,25,55,.85));
      color:#fff; font-size:13px; font-weight:600;
  }

  /* ---------- FEATURES ---------- */
  .feat-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
               gap:18px; max-width:1100px; margin:0 auto; }
  .feat { display:flex; gap:14px; align-items:flex-start; }
  .feat .fic { font-size:26px; }
  .feat h4 { font-size:15.5px; margin-bottom:3px; }
  .feat p { font-size:13.5px; color:#64748b; }

  /* ---------- FOOTER ---------- */
  footer { text-align:center; padding:26px; color:#94a3b8; font-size:13px; }
  footer strong { color:#475569; }
</style>
</head>
<body>

<nav>
  <div class="logo">SMS <span>Shule</span></div>
  <div class="links">
    <a class="btn-ghost" href="auth/register.php">Sajili (Mzazi)</a>
    <a class="btn-solid" href="auth/login.php">Ingia</a>
  </div>
</nav>

<header class="hero" style="background-image:url('<?= e($hero) ?>');">
  <h1>Karibu SMS Shule &#127979;</h1>
  <p>
    Mfumo wa usimamizi wa shule unaounganisha <strong>walimu, wazazi na wanafunzi</strong>.
    Matokeo, mahudhurio, comments za walimu na picha za shughuli — vyote kwenye dashibodi moja.
  </p>
  <div class="cta">
    <a class="cta-primary" href="auth/register.php">Sajili kama Mzazi</a>
    <a class="cta-secondary" href="auth/login.php">Tayari nina akaunti</a>
  </div>

  <img class="hero-cutout" src="<?= e($cutout) ?>" alt="Mwanafunzi mwenye vitabu">
</header>

<section id="roles">
  <h2 class="section-title">Watumiaji wa Mfumo</h2>
  <p class="section-sub">Kila mtumiaji ana dashibodi yake tofauti</p>

  <div class="roles">
    <div class="role-card r-admin">
      <div class="ic">&#128202;</div>
      <h3>Admin (Msimamizi)</h3>
      <ul>
        <li>Kusimamia walimu na madarasa</li>
        <li>Kusimamia masomo na wanafunzi</li>
        <li>Kutuma matangazo ya shule</li>
      </ul>
    </div>
    <div class="role-card r-teacher">
      <div class="ic">&#128218;</div>
      <h3>Mwalimu</h3>
      <ul>
        <li>Kuweka matokeo na mahudhurio</li>
        <li>Kutoa comments za wanafunzi</li>
        <li>Ku-upload picha za shughuli</li>
      </ul>
    </div>
    <div class="role-card r-parent">
      <div class="ic">&#128104;&#8205;&#128105;&#8205;&#128118;</div>
      <h3>Mzazi</h3>
      <ul>
        <li>Kusajili watoto wake wote</li>
        <li>Kufuatilia matokeo na mahudhurio</li>
        <li>Kusoma comments za walimu</li>
      </ul>
    </div>
    <div class="role-card r-student">
      <div class="ic">&#127891;</div>
      <h3>Mwanafunzi</h3>
      <ul>
        <li>Kuona matokeo yake</li>
        <li>Kuona mahudhurio yake</li>
        <li>Kuona picha na taarifa za shule</li>
      </ul>
    </div>
  </div>
</section>

<section class="gallery">
  <h2 class="section-title">Maisha Shuleni</h2>
  <p class="section-sub">Muda mwingine kila siku ni safari ya kuvutia</p>

  <div class="gallery-grid">
    <?php foreach ($gallery as $g): ?>
      <figure>
        <img src="<?= e(img_file_url($g['src'])) ?>" alt="<?= e($g['caption']) ?>" loading="lazy">
        <figcaption><?= e($g['caption']) ?></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
</section>

<section class="features">
  <h2 class="section-title">Vituopendwa vya Mfumo</h2>
  <p class="section-sub">Kila kitu unachohitaji kufuatilia elimu ya mtoto</p>

  <div class="feat-grid">
    <div class="feat">
      <div class="fic">&#128221;</div>
      <div><h4>Matokeo na Grades</h4><p>Alama zinawekwa na walimu, grades zinahesabiwa automatically (A–F).</p></div>
    </div>
    <div class="feat">
      <div class="fic">&#128197;</div>
      <div><h4>Mahudhurio ya Kila Siku</h4><p>Yupo, Hakupo, Amchelewa au Mgonjwa — mzazi anaona kila siku.</p></div>
    </div>
    <div class="feat">
      <div class="fic">&#128172;</div>
      <div><h4>Comments za Walimu</h4><p>Comments za somo, jumla, tabia na za mkuu wa shule.</p></div>
    </div>
    <div class="feat">
      <div class="fic">&#128248;</div>
      <div><h4>Picha za Shughuli</h4><p>Walimu wanashirikisha picha za darasa na matukio ya shule.</p></div>
    </div>
    <div class="feat">
      <div class="fic">&#128226;</div>
      <div><h4>Matangazo</h4><p>Taarifa za shule zinapelekwa kwa wazazi, walimu au wanafunzi.</p></div>
    </div>
    <div class="feat">
      <div class="fic">&#128274;</div>
      <div><h4>Usalama</h4><p>Nywira zimehifadhiwa kwa usalama, kila mtumiaji anaona taarifa zake tu.</p></div>
    </div>
  </div>
</section>

<footer>
  <strong>SMS Shule</strong> &copy; <?= date('Y') ?> — Mfumo wa Usimamizi wa Shule (Msingi na Sekondari)
</footer>

</body>
</html>
