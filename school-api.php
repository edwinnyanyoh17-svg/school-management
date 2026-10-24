<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
startSchoolSession();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['staff_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to manage school records.']);
    exit;
}
require __DIR__ . '/config/database.php';
$csrf = schoolCsrfToken();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) respond(['success' => false, 'message' => 'Method not allowed.'], 405);
if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) respond(['success' => false, 'message' => 'The request is too large.'], 413);

$modules = [
    'teachers' => [
        'table' => 'teachers', 'title' => 'Teachers',
        'fields' => [
            'teacher_number' => ['Teacher ID', 'text'], 'first_name' => ['First name', 'text', true],
            'last_name' => ['Last name', 'text', true], 'email' => ['Email', 'email'],
            'phone' => ['Phone', 'tel'], 'subject' => ['Subject', 'text'],
            'photo_path' => ['Teacher photo', 'photo'],
            'status' => ['Status', 'select', true, ['Active','On leave','Inactive']],
        ],
        'columns' => ['teacher_number','first_name','last_name','email','phone','subject','photo_path','status'],
        'order' => 't.last_name, t.first_name',
    ],
    'classes' => [
        'table' => 'classes', 'title' => 'Classes',
        'fields' => [
            'name' => ['Class name', 'text', true], 'grade_level' => ['Grade level', 'text'],
            'room' => ['Room', 'text'], 'teacher_id' => ['Class teacher', 'select', false, 'teachers'],
            'status' => ['Class status', 'select', true, ['Active','Inactive']],
        ],
        'columns' => ['name','grade_level','room','teacher_id','status'], 'order' => 't.name',
    ],
    'enrollments' => [
        'table' => 'enrollments', 'title' => 'Enrollments',
        'fields' => [
            'student_id' => ['Student', 'select', true, 'students'], 'class_id' => ['Class', 'select', true, 'classes'],
            'academic_year' => ['Academic year', 'text', true], 'enrolled_on' => ['Enrollment date', 'date', true],
            'status' => ['Status', 'select', true, ['Active','Pending','Completed','Withdrawn']],
        ],
        'columns' => ['student_id','class_id','academic_year','enrolled_on','status'], 'order' => 't.enrolled_on DESC, t.id DESC',
    ],
    'attendance' => [
        'table' => 'attendance', 'title' => 'Attendance',
        'fields' => [
            'student_id' => ['Student', 'select', true, 'students'], 'class_id' => ['Class', 'select', false, 'classes'],
            'attendance_date' => ['Date', 'date', true], 'status' => ['Status', 'select', true, ['Present','Absent','Late','Excused']],
            'notes' => ['Notes', 'text'],
        ],
        'columns' => ['student_id','class_id','attendance_date','status','notes'], 'order' => 't.attendance_date DESC, t.id DESC',
    ],
    'payments' => [
        'table' => 'payments', 'title' => 'Finance',
        'fields' => [
            'student_id' => ['Student', 'select', true, 'students'], 'amount' => ['Amount', 'number', true],
            'currency' => ['Currency', 'select', true, ['USD','NGN','GBP','EUR']], 'paid_on' => ['Date', 'date', true],
            'method' => ['Payment method', 'text'], 'status' => ['Status', 'select', true, ['Paid','Pending','Waived','Refunded']],
            'reference' => ['Receipt/reference', 'text'], 'notes' => ['Notes', 'text'],
        ],
        'columns' => ['student_id','amount','currency','paid_on','method','status','reference','notes'], 'order' => 't.paid_on DESC, t.id DESC',
    ],
    'timetable' => [
        'table' => 'timetable', 'title' => 'Timetable',
        'fields' => [
            'class_id' => ['Class', 'select', true, 'classes'], 'teacher_id' => ['Teacher', 'select', false, 'teachers'],
            'subject' => ['Subject', 'text', true], 'day_of_week' => ['Day', 'select', true, ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']],
            'start_time' => ['Start time', 'time', true], 'end_time' => ['End time', 'time', true], 'room' => ['Room', 'text'],
        ],
        'columns' => ['class_id','teacher_id','subject','day_of_week','start_time','end_time','room'], 'order' => 'FIELD(t.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"), t.start_time',
    ],
    'announcements' => [
        'table' => 'announcements', 'title' => 'School news and notices',
        'fields' => [
            'title' => ['Headline', 'text', true], 'category' => ['Type', 'select', true, ['News','Event','Notice']],
            'summary' => ['Short summary', 'textarea', true], 'body' => ['Full update', 'textarea', true],
            'published_on' => ['Publish date', 'date', true], 'event_date' => ['Event date (if this is an event)', 'date'],
            'status' => ['Visibility', 'select', true, ['Draft','Published']],
        ],
        'columns' => ['title','category','summary','body','published_on','event_date','status'], 'order' => 't.published_on DESC, t.id DESC',
    ],
    'learning_updates' => [
        'table' => 'learning_updates', 'title' => 'Learning updates',
        'fields' => [
            'class_id' => ['Class', 'select', true, 'classes'], 'subject' => ['Subject', 'text', true],
            'topic' => ['Topic', 'text', true], 'summary' => ['What students are learning', 'textarea', true],
            'published_on' => ['Publish date', 'date', true], 'status' => ['Visibility', 'select', true, ['Draft','Published']],
        ],
        'columns' => ['class_id','subject','topic','summary','published_on','status'], 'order' => 't.published_on DESC, t.id DESC',
    ],
    'achievements' => [
        'table' => 'school_achievements', 'title' => 'School achievements',
        'fields' => [
            'title' => ['Achievement', 'text', true], 'summary' => ['Details', 'textarea', true],
            'achieved_on' => ['Date (optional)', 'date'], 'status' => ['Visibility', 'select', true, ['Draft','Published']],
        ],
        'columns' => ['title','summary','achieved_on','status'], 'order' => 't.achieved_on DESC, t.id DESC',
    ],
];

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function validIsoDate(?string $value): bool
{
    if (!$value) return false;
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function optionRows(PDO $pdo, string $source): array
{
    if ($source === 'students') {
        return $pdo->query("SELECT id AS value, CONCAT(first_name, ' ', last_name) AS label FROM students ORDER BY last_name, first_name")->fetchAll();
    }
    if ($source === 'teachers') {
        return $pdo->query("SELECT id AS value, CONCAT(first_name, ' ', last_name) AS label FROM teachers ORDER BY last_name, first_name")->fetchAll();
    }
    if ($source === 'classes') {
        return $pdo->query('SELECT id AS value, name AS label FROM classes ORDER BY name')->fetchAll();
    }
    return [];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $module = $_GET['module'] ?? '';
        if (!is_string($module)) respond(['success' => false, 'message' => 'Unknown module.'], 404);
        if ($module === 'settings') {
            $settings = $pdo->query('SELECT setting_key, setting_value FROM school_settings')->fetchAll();
            $values = ['school_name' => 'Northstar Academy', 'admin_name' => 'School administrator', 'academic_year' => date('Y') . '-' . (date('Y') + 1), 'term' => 'Autumn term', 'currency' => 'USD', 'contact_email' => ''];
            foreach ($settings as $item) $values[$item['setting_key']] = $item['setting_value'];
            respond(['success' => true, 'csrf' => $csrf, 'settings' => $values]);
        }
        if (!isset($modules[$module])) respond(['success' => false, 'message' => 'Unknown module.'], 404);
        $config = $modules[$module];
        if ($module === 'payments') {
            $defaultCurrency = $pdo->query("SELECT setting_value FROM school_settings WHERE setting_key='currency'")->fetchColumn();
            if ($defaultCurrency && in_array($defaultCurrency, $config['fields']['currency'][3], true)) {
                $config['fields']['currency'][3] = array_values(array_unique(array_merge([$defaultCurrency], $config['fields']['currency'][3])));
            }
        }
        $query = 'SELECT t.*';
        if ($module === 'classes') $query .= ", CONCAT(te.first_name, ' ', te.last_name) AS teacher_name";
        if ($module === 'enrollments') $query .= ", CONCAT(st.first_name, ' ', st.last_name) AS student_name, cl.name AS class_name";
        if ($module === 'learning_updates') $query .= ', cl.name AS class_name';
        if ($module === 'attendance' || $module === 'payments') $query .= ", CONCAT(st.first_name, ' ', st.last_name) AS student_name";
        if ($module === 'attendance' || $module === 'timetable') $query .= ', cl.name AS class_name';
        if ($module === 'timetable') $query .= ", CONCAT(te.first_name, ' ', te.last_name) AS teacher_name";
        $query .= ' FROM `' . $config['table'] . '` t';
        if ($module === 'classes' || $module === 'timetable') $query .= ' LEFT JOIN teachers te ON te.id=t.teacher_id';
        if ($module === 'enrollments' || $module === 'attendance' || $module === 'payments') $query .= ' LEFT JOIN students st ON st.id=t.student_id';
        if ($module === 'enrollments' || $module === 'attendance' || $module === 'timetable' || $module === 'learning_updates') $query .= ' LEFT JOIN classes cl ON cl.id=t.class_id';
        $query .= ' ORDER BY ' . $config['order'] . ' LIMIT 500';
        $records = $pdo->query($query)->fetchAll();
        $options = [];
        foreach ($config['fields'] as $field) if (isset($field[3]) && is_string($field[3])) $options[$field[3]] = optionRows($pdo, $field[3]);
        respond(['success' => true, 'csrf' => $csrf, 'title' => $config['title'], 'fields' => $config['fields'], 'records' => $records, 'options' => $options]);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!is_array($input) || !verifySchoolCsrf($input['csrf'] ?? null)) respond(['success' => false, 'message' => 'Please refresh the page and try again.'], 403);
    $module = $input['module'] ?? '';
    if ($module === 'settings') {
        if (($input['action'] ?? '') !== 'save') respond(['success' => false, 'message' => 'Unsupported settings action.'], 400);
        if (!isset($input['values']) || !is_array($input['values'])) respond(['success' => false, 'message' => 'School settings are invalid.'], 422);
        if (!in_array($input['values']['term'] ?? '', ['Autumn term','Spring term','Summer term'], true)) respond(['success' => false, 'message' => 'Choose a valid school term.'], 422);
        if (!in_array($input['values']['currency'] ?? '', ['USD','NGN','GBP','EUR'], true)) respond(['success' => false, 'message' => 'Choose a supported currency.'], 422);
        $contactEmail = $input['values']['contact_email'] ?? '';
        if (!is_scalar($contactEmail)) respond(['success' => false, 'message' => 'School settings are invalid.'], 422);
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) respond(['success' => false, 'message' => 'Enter a valid school contact email.'], 422);
        $allowed = ['school_name','admin_name','academic_year','term','currency','contact_email'];
        foreach ($allowed as $key) {
            $rawValue = $input['values'][$key] ?? '';
            if (!is_scalar($rawValue)) respond(['success' => false, 'message' => 'School settings are invalid.'], 422);
            $value = trim((string)$rawValue);
            if ($key !== 'contact_email' && $value === '') respond(['success' => false, 'message' => 'Please fill in all required school settings.'], 422);
            if (strlen($value) > 255) respond(['success' => false, 'message' => 'A school setting is too long.'], 422);
            $statement = $pdo->prepare('INSERT INTO school_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $statement->execute(['key' => $key, 'value' => substr($value, 0, 255)]);
        }
        respond(['success' => true, 'message' => 'School settings saved.']);
    }
    if (!is_string($module) || !isset($modules[$module])) respond(['success' => false, 'message' => 'Unknown module.'], 404);
    $config = $modules[$module];
    $action = $input['action'] ?? '';
    if ($action === 'delete') {
        $recordId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$recordId) respond(['success' => false, 'message' => 'Select a valid record to delete.'], 422);
        $statement = $pdo->prepare('DELETE FROM `' . $config['table'] . '` WHERE id=:id');
        $statement->execute(['id' => $recordId]);
        respond(['success' => true, 'message' => 'Record deleted.']);
    }
    if (!in_array($action, ['create','update'], true)) respond(['success' => false, 'message' => 'Unsupported action.'], 400);
    if (!isset($input['values']) || !is_array($input['values'])) respond(['success' => false, 'message' => 'Record values are invalid.'], 422);
    $values = [];
    foreach ($config['fields'] as $name => $definition) {
        $rawValue = $input['values'][$name] ?? '';
        if (!is_scalar($rawValue)) respond(['success' => false, 'message' => 'Record values are invalid.'], 422);
        $value = trim((string)$rawValue);
        if (!empty($definition[2]) && $value === '') respond(['success' => false, 'message' => $definition[0] . ' is required.'], 422);
        if (isset($definition[3]) && is_array($definition[3]) && $value !== '' && !in_array($value, $definition[3], true)) respond(['success' => false, 'message' => 'Choose a valid ' . strtolower($definition[0]) . '.'], 422);
        $values[$name] = $value === '' ? null : $value;
    }
    $maxLengths = ['teacher_number'=>40,'first_name'=>80,'last_name'=>80,'email'=>190,'phone'=>40,'subject'=>100,'photo_path'=>255,'name'=>80,'grade_level'=>40,'room'=>40,'academic_year'=>20,'notes'=>255,'method'=>40,'reference'=>100,'title'=>180,'summary'=>1000,'body'=>10000,'topic'=>180];
    foreach ($values as $name => $value) {
        if ($value !== null && isset($maxLengths[$name]) && strlen($value) > $maxLengths[$name]) respond(['success' => false, 'message' => 'One or more fields are too long.'], 422);
        if ($name === 'email' && $value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) respond(['success' => false, 'message' => 'Enter a valid email address.'], 422);
        if ($name === 'photo_path' && $value !== null && (!preg_match('#^images/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp)$#i', $value) || str_contains($value, '..'))) respond(['success' => false, 'message' => 'Choose a valid local teacher photo.'], 422);
    }
    foreach (['enrolled_on','attendance_date','paid_on','published_on','achieved_on'] as $dateField) {
        if (array_key_exists($dateField, $values) && !validIsoDate($values[$dateField])) respond(['success' => false, 'message' => 'Enter a valid date.'], 422);
    }
    if (isset($values['event_date']) && $values['event_date'] !== null && !validIsoDate($values['event_date'])) respond(['success' => false, 'message' => 'Enter a valid event date.'], 422);
    foreach (['student_id','class_id','teacher_id'] as $idField) {
        if (array_key_exists($idField, $values) && $values[$idField] !== null && filter_var($values[$idField], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) respond(['success' => false, 'message' => 'Choose a valid related record.'], 422);
    }
    if ($module === 'payments' && (!is_numeric($values['amount']) || (float)$values['amount'] <= 0)) respond(['success' => false, 'message' => 'Enter a payment amount greater than zero.'], 422);
    if ($module === 'timetable' && $values['end_time'] <= $values['start_time']) respond(['success' => false, 'message' => 'The lesson end time must be after its start time.'], 422);
    if ($module === 'announcements' && $values['category'] === 'Event' && !$values['event_date']) respond(['success' => false, 'message' => 'Add an event date before publishing a school event.'], 422);
    if ($module === 'attendance' && $action === 'create') {
        $statement = $pdo->prepare('INSERT INTO attendance (student_id, class_id, attendance_date, status, notes) VALUES (:student_id, :class_id, :attendance_date, :status, :notes) ON DUPLICATE KEY UPDATE class_id=VALUES(class_id), status=VALUES(status), notes=VALUES(notes)');
        $statement->execute($values);
        respond(['success' => true, 'message' => 'Attendance saved.']);
    }
    if ($action === 'create') {
        $columns = array_keys($values);
        $sql = 'INSERT INTO `' . $config['table'] . '` (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')';
    } else {
        $id = (int)($input['id'] ?? 0);
        if ($id < 1) respond(['success' => false, 'message' => 'Select a record to update.'], 422);
        $sets = array_map(fn($column) => '`' . $column . '`=:' . $column, array_keys($values));
        $sql = 'UPDATE `' . $config['table'] . '` SET ' . implode(',', $sets) . ' WHERE id=:record_id';
        $values['record_id'] = $id;
    }
    $statement = $pdo->prepare($sql);
    $statement->execute($values);
    respond(['success' => true, 'message' => $action === 'create' ? 'Record added.' : 'Record updated.']);
} catch (PDOException $exception) {
    $message = $exception->getCode() === '23000' ? 'This record conflicts with an existing record or is still in use.' : 'The request could not be saved. Check required related records and try again.';
    respond(['success' => false, 'message' => $message], 422);
} catch (Throwable $exception) {
    respond(['success' => false, 'message' => 'The school database could not complete this request.'], 500);
}
