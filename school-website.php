<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
sendSecurityHeaders();
require __DIR__ . '/config/database.php';

function siteEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

$settings = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM school_settings')->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];
$schoolName = $settings['school_name'] ?? 'Northstar Academy';
$contactEmail = $settings['contact_email'] ?? '';
$teachers = $pdo->query("SELECT first_name,last_name,subject,photo_path FROM teachers WHERE status='Active' ORDER BY last_name,first_name LIMIT 8")->fetchAll();
$activeTeacherCount = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$news = $pdo->query("SELECT title,summary,published_on FROM announcements WHERE status='Published' ORDER BY published_on DESC,id DESC LIMIT 3")->fetchAll();
$achievements = $pdo->query("SELECT title,summary,achieved_on FROM school_achievements WHERE status='Published' ORDER BY achieved_on DESC,id DESC LIMIT 3")->fetchAll();
$activeStudentCount = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE enrollment_status='Active'")->fetchColumn();
$activeClassCount = (int) $pdo->query("SELECT COUNT(*) FROM classes WHERE status='Active'")->fetchColumn();
$photoForTeacher = static function (array $teacher): ?string {
    $path = (string) ($teacher['photo_path'] ?? '');
    if ($path === '' || !preg_match('#^images/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp)$#i', $path) || str_contains($path, '..')) return null;
    return is_file(__DIR__ . '/' . $path) ? $path : null;
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Discover <?= siteEscape($schoolName) ?>, meet our teachers, explore school news, and apply for admission online.">
  <title><?= siteEscape($schoolName) ?> | Learning, belonging, growing</title>
  <link rel="stylesheet" href="assets/css/school-website.css">
</head>
<body>
<header class="site-header"><a class="site-brand" href="school-website.php"><span class="brand-mark">N</span><span><?= siteEscape($schoolName) ?><small>LEARNING, BELONGING, GROWING</small></span></a><button class="menu-toggle" id="menuToggle" aria-expanded="false" aria-label="Open navigation">☰</button><nav class="site-nav" id="siteNav"><a href="#about">Our school</a><a href="#teachers">Teachers</a><a href="#achievements">Achievements</a><a href="#news">News</a><a href="parent-portal.php">Family portal</a><a class="nav-apply" href="admissions.php">Apply for admission</a></nav></header>
<main>
  <section class="hero"><div class="hero-copy"><span class="eyebrow">A SCHOOL COMMUNITY FOR EVERY LEARNER</span><h1>A bright start.<br><em>A future full of possibility.</em></h1><p>At <?= siteEscape($schoolName) ?>, children build knowledge, confidence, and friendships in a caring school community.</p><div class="hero-actions"><a class="button button-primary" href="admissions.php">Start an application <span>→</span></a><a class="button button-light" href="#about">Discover our school</a></div><div class="hero-facts"><span><b><?= $activeStudentCount ?></b> active students</span><span><b><?= $activeTeacherCount ?></b> active teachers</span><span><b><?= $activeClassCount ?></b> active classes</span></div></div><div class="hero-photo"><img src="images/schoolchildren-classroom.jpg" alt="Students learning together in a classroom"><span>Learning together, every day</span></div></section>
  <section class="welcome-section" id="about"><div class="section-label">WELCOME TO OUR SCHOOL</div><div class="welcome-grid"><h2>A place to feel known,<br>challenged, and inspired.</h2><div><p>We bring families and educators together around each child’s learning. Explore our school updates, meet the teaching team, and learn how to apply for a place.</p><a class="text-link" href="parent-portal.php">Visit the family portal →</a></div></div><div class="values-grid"><article><span>01</span><h3>Curiosity</h3><p>We encourage students to ask questions and take an active part in learning.</p></article><article><span>02</span><h3>Belonging</h3><p>We work to create a respectful environment where each student is welcomed.</p></article><article><span>03</span><h3>Growth</h3><p>We help learners build skills, confidence, and care for their community.</p></article></div></section>
  <section class="teachers-section" id="teachers"><div class="section-heading"><div><div class="section-label">MEET THE TEAM</div><h2>Teachers who help students grow.</h2></div><p>Our active teaching team and the subjects they support.</p></div>
    <?php if ($teachers): ?><div class="teacher-grid"><?php foreach ($teachers as $teacher): $teacherPhoto = $photoForTeacher($teacher); ?><article class="teacher-card"><?php if ($teacherPhoto): ?><img class="teacher-photo" src="<?= siteEscape($teacherPhoto) ?>" alt="Photo of <?= siteEscape($teacher['first_name'] . ' ' . $teacher['last_name']) ?>"><?php else: ?><div class="teacher-placeholder" aria-hidden="true"><?= siteEscape(strtoupper(substr($teacher['first_name'],0,1) . substr($teacher['last_name'],0,1))) ?></div><?php endif; ?><h3><?= siteEscape($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h3><p><?= siteEscape($teacher['subject'] ?: 'Teaching team') ?></p></article><?php endforeach; ?></div><?php else: ?><div class="empty-panel"><h3>Our teaching team</h3><p>Teacher profiles will appear here as the school adds active staff records. Add real teacher photos in the staff dashboard to display them.</p></div><?php endif; ?>
  </section>
  <section class="achievement-section" id="achievements"><div class="section-heading"><div><div class="section-label">MILESTONES</div><h2>School achievements</h2></div><p>Updates shared by the school. Each item is published by an administrator.</p></div><?php if ($achievements): ?><div class="achievement-grid"><?php foreach ($achievements as $item): ?><article class="achievement-card"><span class="achievement-icon">✦</span><?php if ($item['achieved_on']): ?><time><?= siteEscape(date('M Y', strtotime($item['achieved_on']))) ?></time><?php endif; ?><h3><?= siteEscape($item['title']) ?></h3><p><?= nl2br(siteEscape($item['summary'])) ?></p></article><?php endforeach; ?></div><?php else: ?><div class="empty-panel"><p>Verified achievements will appear here when the school publishes them in the staff dashboard.</p></div><?php endif; ?></section>
  <section class="admissions-band"><div><div class="section-label">ADMISSIONS</div><h2>Your child’s next chapter<br>can start here.</h2><p>Complete the application online and attach the documents requested by the school. The admissions team will review it and contact you about next steps and any entrance assessment.</p></div><a class="button button-primary" href="admissions.php">View application steps <span>→</span></a></section>
  <section class="news-section" id="news"><div class="section-heading"><div><div class="section-label">FROM OUR COMMUNITY</div><h2>Latest school news</h2></div><a class="text-link" href="parent-portal.php">More school updates →</a></div><?php if ($news): ?><div class="news-grid"><?php foreach ($news as $item): ?><article class="news-card"><time><?= siteEscape(date('M j, Y', strtotime($item['published_on']))) ?></time><h3><?= siteEscape($item['title']) ?></h3><p><?= nl2br(siteEscape($item['summary'])) ?></p></article><?php endforeach; ?></div><?php else: ?><div class="empty-panel"><p>School news will appear here when an administrator publishes an update.</p></div><?php endif; ?></section>
  <section class="contact-section"><div><div class="section-label">HERE FOR FAMILIES</div><h2>Want to learn more?</h2><p>Get in touch with the school or begin an application online.</p></div><div class="contact-actions"><a class="button button-light" href="<?= $contactEmail ? 'mailto:' . siteEscape($contactEmail) : 'parent-portal.php' ?>"><?= $contactEmail ? 'Email the school' : 'Open family portal' ?></a><a class="button button-primary" href="admissions.php">Apply now →</a></div></section>
</main>
<footer class="site-footer"><a class="site-brand" href="school-website.php"><span class="brand-mark">N</span><span><?= siteEscape($schoolName) ?><small>LEARNING, BELONGING, GROWING</small></span></a><p>Working together for every learner.</p><div><a href="parent-portal.php">Family portal</a> · <a href="login.php">Staff sign in</a></div></footer>
<script>const menuToggle=document.getElementById('menuToggle'),siteNav=document.getElementById('siteNav');menuToggle.addEventListener('click',()=>{const open=menuToggle.getAttribute('aria-expanded')!=='true';menuToggle.setAttribute('aria-expanded',String(open));siteNav.classList.toggle('open',open)});siteNav.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{menuToggle.setAttribute('aria-expanded','false');siteNav.classList.remove('open')}));</script>
</body></html>
