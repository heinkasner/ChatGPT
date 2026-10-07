<?php
declare(strict_types=1);
if (!in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/', '/index.php'], true)) {
    http_response_code(404);
    exit('Not found');
}
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$directory = dirname(__DIR__) . '/data';
$tasks = [];
$error = '';
$filter = in_array($_GET['filter'] ?? '', ['all', 'active', 'completed'], true) ? $_GET['filter'] : 'all';
try {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create task storage.');
    }
    $file = fopen($directory . '/tasks.json', 'c+');
    if ($file === false) { throw new RuntimeException('Cannot open task storage.'); }
    try {
        if (!flock($file, LOCK_EX)) { throw new RuntimeException('Cannot lock task storage.'); }
        $contents = stream_get_contents($file);
        $tasks = $contents === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($tasks)) { throw new RuntimeException('Invalid task storage.'); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
                http_response_code(403);
                $error = 'Your session expired. Please refresh and try again.';
            } else {
                $action = $_POST['action'] ?? '';
                if ($action === 'add') {
                    $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
                    if ($title === '' || strlen($title) > 500) {
                        $error = 'Enter a task between 1 and 500 bytes.';
                    } else {
                        array_unshift($tasks, ['id' => bin2hex(random_bytes(16)), 'title' => $title, 'completed' => false]);
                    }
                } elseif (in_array($action, ['toggle', 'delete'], true)) {
                    foreach ($tasks as $key => &$task) {
                        if ($task['id'] === ($_POST['id'] ?? null)) {
                            if ($action === 'toggle') { $task['completed'] = !$task['completed']; }
                            else { unset($tasks[$key]); }
                            break;
                        }
                    }
                    unset($task);
                } else { $error = 'Unknown action.'; }
                if ($error === '') {
                    $json = json_encode(array_values($tasks), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                    rewind($file);
                    if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
                        throw new RuntimeException('Cannot save tasks.');
                    }
                    header('Location: /?filter=' . $filter, true, 303);
                    exit;
                }
            }
        }
    } finally { flock($file, LOCK_UN); fclose($file); }
} catch (Throwable $exception) {
    http_response_code(500);
    $error = 'Task storage is unavailable. Check the data directory permissions and server logs.';
    error_log($exception->getMessage());
}
$active = count(array_filter($tasks, fn($task) => !$task['completed']));
$visible = array_filter($tasks, fn($task) => $filter === 'all' || ($filter === 'completed' ? $task['completed'] : !$task['completed']));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tasks — Your everyday task manager</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f6fa;color:#202638;font:16px system-ui,sans-serif}main{max-width:740px;margin:70px auto;padding:0 24px}.eyebrow{color:#6856cc;font-size:13px;font-weight:700;letter-spacing:2px}h1{font-size:42px;margin:12px 0}p{color:#747b8b}.panel{background:white;border:1px solid #e5e7ee;border-radius:18px;padding:24px;margin-top:30px;box-shadow:0 8px 35px #20263808}.add{display:flex;gap:10px}input[type=text]{flex:1;min-width:0;border:1px solid #dce0e9;border-radius:9px;padding:13px;font:inherit}button{cursor:pointer;border:0;border-radius:9px;padding:12px 16px;font:inherit;background:#6856cc;color:white}button:hover{filter:brightness(.92)}nav{display:flex;gap:8px;padding:24px 0;border-bottom:1px solid #eef0f5}nav a{text-decoration:none;color:#747b8b;padding:8px 12px;border-radius:8px}nav a.selected{background:#eeeafa;color:#6856cc}.task{display:flex;align-items:center;gap:14px;padding:18px 0;border-bottom:1px solid #eef0f5}.task:last-child{border:0}.title{flex:1;overflow-wrap:anywhere}.done .title{text-decoration:line-through;color:#9095a3}.toggle{padding:0;width:28px;height:28px;border:2px solid #dce0e9;background:white;color:#6856cc}.done .toggle{background:#eeeafa;border-color:#6856cc}.delete{background:transparent;color:#8b6470;font-size:14px;padding:8px}.empty{text-align:center;padding:36px 0}.error{color:#992f42;background:#fff0f2;padding:12px;border-radius:8px}footer{font-size:13px;text-align:center;color:#949aaa;margin:25px}button:focus-visible,a:focus-visible,input:focus-visible{outline:3px solid #a99be9;outline-offset:3px}@media(max-width:500px){main{margin:35px auto;padding:0 16px}.panel{padding:16px}h1{font-size:34px}}
</style>
</head>
<body><main>
<div class="eyebrow">A LITTLE MORE ORGANIZED</div>
<h1>Your everyday tasks.</h1><p>Make room for what matters, one task at a time.</p>
<section class="panel" aria-label="Task manager">
<?php if ($error !== ''): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
<form class="add" method="post">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="add">
<input type="text" name="title" aria-label="New task" placeholder="What needs to get done?" maxlength="500" required><button>Add task</button>
</form>
<nav aria-label="Filter tasks">
<?php foreach (['all' => 'All', 'active' => 'Active', 'completed' => 'Completed'] as $key => $label): ?>
<a href="/?filter=<?= $key ?>" class="<?= $filter === $key ? 'selected' : '' ?>" <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a>
<?php endforeach; ?>
</nav>
<?php if (!$visible): ?><div class="empty"><strong><?= $filter === 'completed' ? 'No completed tasks yet.' : 'A clear list, a fresh start.' ?></strong><p><?= $filter === 'completed' ? 'Finished tasks will appear here.' : 'Add a task above to get started.' ?></p></div><?php endif; ?>
<?php foreach ($visible as $task): ?>
<div class="task <?= $task['completed'] ? 'done' : '' ?>">
<form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= escape($task['id']) ?>"><button class="toggle" aria-label="<?= escape(($task['completed'] ? 'Mark active: ' : 'Complete: ') . $task['title']) ?>"><?= $task['completed'] ? '✓' : '' ?></button></form>
<span class="title"><?= escape($task['title']) ?></span>
<form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= escape($task['id']) ?>"><button class="delete" aria-label="<?= escape('Delete: ' . $task['title']) ?>">Delete</button></form>
</div>
<?php endforeach; ?>
</section><footer><?= $active ?> <?= $active === 1 ? 'task' : 'tasks' ?> remaining · Keep moving forward.</footer>
</main></body></html>
