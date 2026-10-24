<?php
require __DIR__ . '/config/auth.php';
sendSecurityHeaders();
require __DIR__ . '/config/database.php';

function portalEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$settingsRows = $pdo->query('SELECT setting_key, setting_value FROM school_settings')->fetchAll();
$settings = [];
foreach ($settingsRows as $row) $settings[$row['setting_key']] = $row['setting_value'];
$schoolName = $settings['school_name'] ?? 'Northstar Academy';
$academicYear = $settings['academic_year'] ?? date('Y') . '-' . (date('Y') + 1);
$term = $settings['term'] ?? 'Current term';
$contactEmail = $settings['contact_email'] ?? '';
$activeTeachers = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$classCount = (int) $pdo->query("SELECT COUNT(*) FROM classes WHERE status='Active'")->fetchColumn();
$classes = $pdo->query("SELECT id, name, grade_level FROM classes WHERE status='Active' ORDER BY name")->fetchAll();
$selectedClass = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?: 0;
if ($selectedClass && !array_filter($classes, static fn($class) => (int) $class['id'] === $selectedClass)) $selectedClass = 0;

$newsQuery = $pdo->prepare("SELECT title, category, summary, body, published_on, event_date FROM announcements WHERE status='Published' ORDER BY published_on DESC, id DESC LIMIT 6");
$newsQuery->execute();
$news = $newsQuery->fetchAll();

$learningSql = "SELECT l.subject, l.topic, l.summary, l.published_on, c.name AS class_name, c.grade_level
    FROM learning_updates l JOIN classes c ON c.id=l.class_id
    WHERE l.status='Published' AND c.status='Active'";
$learningParameters = [];
if ($selectedClass) {
    $learningSql .= ' AND l.class_id=:class_id';
    $learningParameters['class_id'] = $selectedClass;
}
$learningSql .= ' ORDER BY l.published_on DESC, l.id DESC LIMIT 8';
$learningQuery = $pdo->prepare($learningSql);
$learningQuery->execute($learningParameters);
$learningUpdates = $learningQuery->fetchAll();

$today = date('Y-m-d');
$eventsQuery = $pdo->prepare("SELECT title, summary, event_date FROM announcements WHERE status='Published' AND category='Event' AND event_date >= :today ORDER BY event_date, id LIMIT 4");
$eventsQuery->execute(['today' => $today]);
$events = $eventsQuery->fetchAll();

$subjectsSql = "SELECT DISTINCT t.subject, t.day_of_week FROM timetable t JOIN classes c ON c.id=t.class_id WHERE c.status='Active'";
$subjectParameters = [];
if ($selectedClass) {
    $subjectsSql .= ' AND t.class_id=:class_id';
    $subjectParameters['class_id'] = $selectedClass;
}
$subjectsSql .= ' ORDER BY FIELD(t.day_of_week, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"), t.subject LIMIT 12';
$subjectsQuery = $pdo->prepare($subjectsSql);
$subjectsQuery->execute($subjectParameters);
$subjects = $subjectsQuery->fetchAll();

function portalDate($value): string
{
    if (!$value) return '';
    return date('M j, Y', strtotime($value));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="School news, learning updates, events, and parent information from <?= portalEscape($schoolName) ?>.">
    <title>Family portal | <?= portalEscape($schoolName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/parent-portal.css">
</head>
<body>
<header class="portal-header">
    <a class="portal-brand" href="parent-portal.php"><span class="portal-mark">✳</span><span><?= portalEscape($schoolName) ?><small>FAMILY & SCHOOL</small></span></a>
    <button class="portal-menu" id="portalMenu" aria-expanded="false" aria-label="Open menu">☰</button>
    <nav class="portal-nav" id="portalNav" aria-label="Parent portal">
        <a href="school-website.php">School website</a><a href="admissions.php">Admissions</a><a href="#learning">Learning</a><a href="#news">School news</a><a href="#events">Events</a>
        <a class="staff-link" href="login.php">Staff sign in ↗</a>
    </nav>
</header>
<main>
    <section class="portal-hero">
        <div class="hero-copy"><span class="portal-eyebrow"><?= portalEscape($academicYear) ?> · <?= portalEscape($term) ?></span>
            <h1>Growing together,<br><em>every school day.</em></h1>
            <p>Stay connected with what students are learning, school announcements, and upcoming events at <?= portalEscape($schoolName) ?>.</p>
            <div class="hero-actions"><a class="portal-button" href="#news">Read school updates <span>→</span></a><a class="portal-text-link" href="#learning">Explore learning</a></div>
        </div>
        <div class="hero-image"><img src="images/schoolchildren-classroom.jpg" alt="Students reading together in class"><div class="image-caption">Learning together at <?= portalEscape($schoolName) ?></div></div>
    </section>

    <section class="community-strip" aria-label="School at a glance">
        <div><strong><?= number_format($activeTeachers) ?></strong><span>active teachers</span></div>
        <div><strong><?= number_format($classCount) ?></strong><span>class groups</span></div>
        <p>Our teachers and school teams work together to help every learner thrive.</p>
    </section>

    <section class="portal-section learning-section" id="learning">
        <div class="section-heading"><div><span class="portal-eyebrow">IN THE CLASSROOM</span><h2>What students are learning</h2><p>Choose a class to see its published learning notes and current subjects.</p></div>
            <form class="class-picker" method="get" action="parent-portal.php"><label for="class_id">View class</label><select id="class_id" name="class_id"><option value="">All classes</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= $selectedClass === (int) $class['id'] ? 'selected' : '' ?>><?= portalEscape($class['name']) ?><?= $class['grade_level'] ? ' · ' . portalEscape($class['grade_level']) : '' ?></option><?php endforeach; ?></select><button type="submit" aria-label="Show selected class">Go</button></form>
        </div>
        <div class="learning-grid">
            <div class="learning-notes">
                <?php if ($learningUpdates): foreach ($learningUpdates as $update): ?>
                    <article class="learning-card"><div class="learning-meta"><span><?= portalEscape($update['class_name']) ?><?= $update['grade_level'] ? ' · ' . portalEscape($update['grade_level']) : '' ?></span><time><?= portalEscape(portalDate($update['published_on'])) ?></time></div><p class="subject-tag"><?= portalEscape($update['subject']) ?></p><h3><?= portalEscape($update['topic']) ?></h3><p><?= nl2br(portalEscape($update['summary'])) ?></p></article>
                <?php endforeach; else: ?>
                    <article class="learning-card empty-card"><span class="empty-icon">✎</span><h3>No learning notes published yet</h3><p>When teachers share a class update, it will appear here. Please check back soon.</p></article>
                <?php endif; ?>
            </div>
            <aside class="subject-panel"><span class="portal-eyebrow">CLASS TIMETABLE</span><h3>Subjects in the schedule</h3>
                <?php if ($subjects): ?><ul><?php foreach ($subjects as $subject): ?><li><span><?= portalEscape($subject['subject']) ?></span><small><?= portalEscape($subject['day_of_week']) ?></small></li><?php endforeach; ?></ul><?php else: ?><p>Published timetable subjects will appear here once lesson records are added.</p><?php endif; ?>
                <a href="#news">See what’s happening at school ↓</a>
            </aside>
        </div>
        <p class="privacy-note">This public page shares school-wide information only. Individual student attendance, results, and fee records are not shown here.</p>
    </section>

    <section class="portal-section news-section" id="news">
        <div class="section-heading"><div><span class="portal-eyebrow">FROM OUR SCHOOL</span><h2>News & updates</h2><p>Announcements and stories shared by the school.</p></div><a class="portal-text-link" href="login.php">Staff sign in ↗</a></div>
        <?php if ($news): ?><div class="news-grid"><?php foreach ($news as $item): ?><article class="news-card"><div class="news-top"><span class="news-category <?= strtolower(portalEscape($item['category'])) ?>"><?= portalEscape($item['category']) ?></span><time><?= portalEscape(portalDate($item['published_on'])) ?></time></div><h3><?= portalEscape($item['title']) ?></h3><p class="news-summary"><?= nl2br(portalEscape($item['summary'])) ?></p><details><summary>Read more</summary><p><?= nl2br(portalEscape($item['body'])) ?></p></details></article><?php endforeach; ?></div><?php else: ?><article class="news-empty"><span>✦</span><h3>School updates will appear here</h3><p>There are no published announcements yet. Visit again for school news, reminders, and community stories.</p></article><?php endif; ?>
    </section>

    <section class="event-band" id="events"><div><span class="portal-eyebrow">SAVE THE DATE</span><h2>Upcoming school events</h2><p>School activities and events for our community.</p></div><div class="event-list">
        <?php if ($events): foreach ($events as $event): ?><article><time><?= portalEscape(portalDate($event['event_date'])) ?></time><div><h3><?= portalEscape($event['title']) ?></h3><p><?= portalEscape($event['summary']) ?></p></div></article><?php endforeach; else: ?><p class="event-empty">No upcoming events have been announced yet.</p><?php endif; ?>
    </div></section>

    <section class="contact-band"><div><span class="portal-eyebrow">HERE FOR FAMILIES</span><h2>Questions? We’re here to help.</h2><p>Contact the school office for general information and support.</p></div><?php if ($contactEmail): ?><a class="portal-button" href="mailto:<?= portalEscape($contactEmail) ?>">Email the school office ↗</a><?php else: ?><span class="contact-hint">School contact details will be added by the administrator.</span><?php endif; ?></section>
</main>
<footer class="portal-footer"><a class="portal-brand" href="parent-portal.php"><span class="portal-mark">✳</span><span><?= portalEscape($schoolName) ?><small>FAMILY & SCHOOL</small></span></a><p>Working together for every learner.</p><small>Classroom photo: US Department of Education · CC BY 2.0</small></footer>
<script>
const portalMenu=document.getElementById('portalMenu');
portalMenu.addEventListener('click',()=>{const open=portalMenu.getAttribute('aria-expanded')!=='true';portalMenu.setAttribute('aria-expanded',String(open));document.getElementById('portalNav').classList.toggle('open',open);});
document.querySelectorAll('#portalNav a').forEach(link=>link.addEventListener('click',()=>{portalMenu.setAttribute('aria-expanded','false');document.getElementById('portalNav').classList.remove('open');}));
</script>
</body>
</html>
