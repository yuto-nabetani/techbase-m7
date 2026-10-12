<?php
session_start();
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('アクセス権限がありません。管理者アカウントでログインしてください。');
}
try {
    require_once __DIR__ . '/config/db.php';
} catch (PDOException $e) {
    error_log('admin connection: ' . $e->getMessage());
    http_response_code(500);
    exit('DBに接続できませんでした。接続設定を確認してください。');
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('DB接続の設定を確認してください。');
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function input_text($source, $key, $default = '') {
    return isset($source[$key]) && is_string($source[$key]) ? $source[$key] : $default;
}
function admin_url($view, $extra = []) {
    return 'admin.php?' . http_build_query(array_merge(['view' => $view], $extra));
}
function text_length($value) {
    return preg_match_all('/./us', $value, $matches);
}
function checked_text($source, $key, $label, $max, $required = false) {
    $value = trim(input_text($source, $key));
    if (preg_match('//u', $value) !== 1 || strpos($value, "\0") !== false) {
        throw new InvalidArgumentException($label . 'の文字を確認してください。');
    }
    if ($required && $value === '') {
        throw new InvalidArgumentException($label . 'を入力してください。');
    }
    if (text_length($value) > $max) {
        throw new InvalidArgumentException($label . 'は' . $max . '文字以内で入力してください。');
    }
    return $value;
}
function checked_integer($value, $label, $min, $max) {
    if (!is_string($value) || !preg_match('/^-?\d+$/D', $value)) {
        throw new InvalidArgumentException($label . 'を整数で入力してください。');
    }
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($number === false) {
        throw new InvalidArgumentException($label . 'は' . $min . '〜' . $max . 'の範囲にしてください。');
    }
    return $number;
}
function scene_revision($scene, $choices, $secret) {
    // PDOの数値型の返し方に左右されない比較用データ。
    $normalize = static function ($row) {
        return array_map(static function ($v) { return $v === null ? null : (string)$v; }, $row);
    };
    return hash_hmac('sha256', json_encode([$normalize($scene), array_map($normalize, $choices)], JSON_UNESCAPED_UNICODE), $secret);
}
function image_catalog() {
    $items = [];
    $root = realpath(__DIR__ . '/images');
    if ($root === false) { return $items; }
    $prefix = str_replace('\\', '/', $root) . '/';
    try {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->isLink()) { continue; }
            $real = str_replace('\\', '/', (string)$file->getRealPath());
            if (strpos($real, $prefix) !== 0) { continue; }
            if (!in_array(strtolower(pathinfo($real, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) { continue; }
            // 不正な引用符などを含むパスを、ゲームの背景CSSへ渡さない。
            $relative = 'images/' . substr($real, strlen($prefix));
            if (preg_match('/[\x00-\x1f\x7f\x22\x27<>\\\\]/u', $relative)) { continue; }
            $info = @getimagesize($real);
            if ($info === false || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) { continue; }
            $items[$relative] = $relative;
        }
    } catch (UnexpectedValueException $e) {
        error_log('admin image scan: ' . $e->getMessage());
    }
    ksort($items);
    return $items;
}
function image_url($path) {
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}
function effective_image($path, $catalog) {
    // 現在のgame.phpと同じ素材差し替え規則。
    $map = [
        'miyu.png' => 'images/characters/miyu-v2.png',
        'miyu_normal_transparent (2).png' => 'images/characters/miyu-v2.png',
        'おばあちゃん.png' => 'images/characters/grandma-v2.png',
        'grandma_normal_transparent (3).png' => 'images/characters/grandma-v2.png',
        'おばあちゃんち.png' => 'images/background/home-v2.png',
        '学校.png' => 'images/background/school-v2.png',
        '雨の日.jpg' => 'images/background/rain-v2.png',
        '文化祭荷物倉庫.png' => 'images/background/storage-v2.png'
    ];
    $path = (string)$path;
    $replacement = $map[basename($path)] ?? null;
    if ($replacement !== null && isset($catalog[$replacement])) { return $replacement; }
    return isset($catalog[$path]) ? $path : '';
}
function checked_image($value, $old, $catalog, $label) {
    // 既存の未配置パスはそのまま保存可能。新規指定は実在する画像のみ。
    if ($value === (string)$old) { return $old; }
    if ($value === '') { return null; }
    if (strlen($value) > 255 || !isset($catalog[$value])) {
        throw new InvalidArgumentException($label . 'を一覧から選んでください。');
    }
    return $value;
}

$views = ['create' => '新規シナリオ', 'overview' => '概要', 'scenes' => 'シナリオ', 'choices' => '選択肢', 'users' => 'ユーザー', 'checks' => '確認事項'];
$types = ['normal' => '通常', 'choice' => '選択', 'judge' => '結末判定', 'ending_true' => 'TRUE END', 'ending_bad' => 'BAD END'];
$view = input_text($_GET, 'view', 'overview');
if (!isset($views[$view])) { $view = 'overview'; }
$selectedId = (int)input_text($_GET, 'id', '0');
$query = trim(input_text($_GET, 'q'));
$typeFilter = input_text($_GET, 'type');
if (!isset($types[$typeFilter])) { $typeFilter = ''; }
$errors = [];
$fatal = '';
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
if (!isset($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];
$sceneColumns = 'id, title, speaker, content_text, background_image, character_image, scene_type, display_type, next_scene_id';
$choiceColumns = 'id, scene_id, choice_text, love_change, next_scene_id, display_order';
$users = $scenes = $choices = $catalog = [];
$retainInput = false;

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // DBで権限を外されたユーザーは、古いセッションがあっても編集できない。
    $auth = $pdo->prepare('SELECT username, role FROM users WHERE id = ?');
    $auth->execute([$_SESSION['user_id']]);
    $admin = $auth->fetch(PDO::FETCH_ASSOC);
    if (!$admin || $admin['role'] !== 'admin') {
        http_response_code(403);
        exit('管理者権限がありません。ログインし直してください。');
    }
    $catalog = image_catalog();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = input_text($_POST, 'action');
        $view = $action === 'create_scene' ? 'create' : (in_array($action, ['save_choices', 'add_choice'], true) ? 'choices' : 'scenes');
        $selectedId = (int)input_text($_POST, 'scene_id', '0');
        $retainInput = true;
        try {
            if (!hash_equals($csrf, input_text($_POST, 'csrf'))) {
                http_response_code(403);
                throw new InvalidArgumentException('ページの有効期限を確認できません。ページを開き直してから保存してください。');
            }
            if (!in_array($action, ['save_scene', 'save_choices', 'create_scene', 'add_choice'], true)) {
                throw new InvalidArgumentException('保存操作を確認してください。');
            }
            $pdo->beginTransaction();

            if ($action === 'create_scene') {
                $title = checked_text($_POST, 'title', 'タイトル', 100, true);
                $speaker = checked_text($_POST, 'speaker', '話者', 50);
                $body = checked_text($_POST, 'content_text', '本文', 30000, true);
                if (strlen($body) > 65535) { throw new InvalidArgumentException('本文は65,535バイト以内にしてください。'); }
                $kind = input_text($_POST, 'scene_type');
                if (!isset($types[$kind])) { throw new InvalidArgumentException('種別を選んでください。'); }
                $background = checked_image(input_text($_POST, 'background_image'), null, $catalog, '背景');
                $character = checked_image(input_text($_POST, 'character_image'), null, $catalog, 'キャラクター');
                $exists = $pdo->prepare('SELECT id FROM scenes WHERE id = ?');
                $target = static function ($value) use ($exists) {
                    $id = checked_integer($value, '移動先', 1, 2147483647);
                    $exists->execute([$id]);
                    if (!$exists->fetchColumn()) { throw new InvalidArgumentException('存在する移動先を選んでください。'); }
                    return $id;
                };
                $next = $kind === 'normal' ? $target(input_text($_POST, 'next_scene_id')) : null;
                $newChoices = [];
                if ($kind === 'choice') {
                    for ($i = 0; $i < 3; $i++) {
                        $text = checked_text($_POST, 'new_text_' . $i, '選択肢', 255);
                        if ($text === '') { continue; }
                        $newChoices[] = [$text, checked_integer(input_text($_POST, 'new_love_' . $i), '好感度', -100, 100), $target(input_text($_POST, 'new_next_' . $i))];
                    }
                    if (count($newChoices) < 2) { throw new InvalidArgumentException('選択シーンには2つ以上の選択肢を入力してください。'); }
                }
                $stmt = $pdo->prepare('INSERT INTO scenes (title, speaker, content_text, background_image, character_image, scene_type, display_type, next_scene_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$title, $speaker === '' ? null : $speaker, $body, $background, $character, $kind, 'dialogue', $next]);
                $selectedId = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare('INSERT INTO choices (scene_id, choice_text, love_change, next_scene_id, display_order) VALUES (?, ?, ?, ?, ?)');
                foreach ($newChoices as $i => $choice) { $stmt->execute([$selectedId, $choice[0], $choice[1], $choice[2], $i + 1]); }
                $pdo->commit();
                $_SESSION['admin_flash'] = 'シナリオ #' . $selectedId . ' を作成しました。既存シーンの移動先をこのシーンに変更すると、ゲームにつながります。';
                header('Location: ' . admin_url('scenes', ['id' => $selectedId]), true, 303);
                exit;
            }

            $stmt = $pdo->prepare('SELECT ' . $sceneColumns . ' FROM scenes WHERE id = ? FOR UPDATE');
            $stmt->execute([$selectedId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) { throw new InvalidArgumentException('対象のシナリオが見つかりません。'); }
            $stmt = $pdo->prepare('SELECT ' . $choiceColumns . ' FROM choices WHERE scene_id = ? ORDER BY display_order, id FOR UPDATE');
            $stmt->execute([$selectedId]);
            $currentChoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!hash_equals(scene_revision($current, $currentChoices, $csrf), input_text($_POST, 'revision'))) {
                throw new InvalidArgumentException('別の画面で更新されています。入力内容を控えてから、このシナリオを開き直してください。');
            }
            $exists = $pdo->prepare('SELECT id FROM scenes WHERE id = ?');
            if ($action === 'add_choice') {
                if ($current['scene_type'] !== 'choice') { throw new InvalidArgumentException('選択シーンを選んでください。'); }
                $text = checked_text($_POST, 'new_choice_text', '選択肢の文章', 255, true);
                $love = checked_integer(input_text($_POST, 'new_choice_love'), '好感度', -100, 100);
                $next = checked_integer(input_text($_POST, 'new_choice_next'), '移動先', 1, 2147483647);
                $exists->execute([$next]);
                if (!$exists->fetchColumn() || $next === $selectedId) { throw new InvalidArgumentException('自分以外の移動先を選んでください。'); }
                $order = 1;
                foreach ($currentChoices as $choice) { $order = max($order, (int)$choice['display_order'] + 1); }
                if ($order > 999) { throw new InvalidArgumentException('既存の選択肢の表示順を小さくしてください。'); }
                $stmt = $pdo->prepare('INSERT INTO choices (scene_id, choice_text, love_change, next_scene_id, display_order) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$selectedId, $text, $love, $next, $order]);
            } elseif ($action === 'save_scene') {
                $title = checked_text($_POST, 'title', 'タイトル', 100, true);
                $speaker = checked_text($_POST, 'speaker', '話者', 50);
                $body = checked_text($_POST, 'content_text', '本文', 30000, true);
                if (strlen($body) > 65535) { throw new InvalidArgumentException('本文が長すぎます。UTF-8で65,535バイト以内にしてください。'); }
                $background = checked_image(input_text($_POST, 'background_image'), $current['background_image'], $catalog, '背景画像');
                $character = checked_image(input_text($_POST, 'character_image'), $current['character_image'], $catalog, 'キャラクター画像');
                $next = $current['next_scene_id'];
                if ($current['scene_type'] === 'normal') {
                    $next = checked_integer(input_text($_POST, 'next_scene_id'), '次のシーン', 1, 2147483647);
                    $exists->execute([$next]);
                    if (!$exists->fetchColumn() || $next === $selectedId) {
                        throw new InvalidArgumentException('次のシーンには、自分以外の存在するシーンを選んでください。');
                    }
                }
                $stmt = $pdo->prepare('UPDATE scenes SET title = ?, speaker = ?, content_text = ?, background_image = ?, character_image = ?, next_scene_id = ? WHERE id = ?');
                $stmt->execute([$title, $speaker === '' ? null : $speaker, $body, $background, $character, $next, $selectedId]);
            } else {
                if ($current['scene_type'] !== 'choice' || !$currentChoices) {
                    throw new InvalidArgumentException('このシーンには編集できる選択肢がありません。');
                }
                $posted = $_POST['choices'] ?? null;
                if (!is_array($posted) || count($posted) !== count($currentChoices)) {
                    throw new InvalidArgumentException('選択肢の件数が変わっています。ページを開き直してください。');
                }
                $orders = [];
                $stmt = $pdo->prepare('UPDATE choices SET choice_text = ?, love_change = ?, next_scene_id = ?, display_order = ? WHERE id = ? AND scene_id = ?');
                foreach ($currentChoices as $choice) {
                    $id = (int)$choice['id'];
                    $data = $posted[$id] ?? null;
                    if (!is_array($data)) { throw new InvalidArgumentException('選択肢の内容を確認してください。'); }
                    $text = checked_text($data, 'choice_text', '選択肢の文章', 255, true);
                    $love = checked_integer(input_text($data, 'love_change'), '好感度の増減', -100, 100);
                    $order = checked_integer(input_text($data, 'display_order'), '表示順', 1, 999);
                    if (isset($orders[$order])) { throw new InvalidArgumentException('表示順が重複しています。別々の番号を付けてください。'); }
                    $orders[$order] = true;
                    $next = checked_integer(input_text($data, 'next_scene_id'), '移動先', 1, 2147483647);
                    $exists->execute([$next]);
                    if (!$exists->fetchColumn() || $next === $selectedId) { throw new InvalidArgumentException('移動先は、自分以外の存在するシーンを選んでください。'); }
                    $stmt->execute([$text, $love, $next, $order, $id, $selectedId]);
                }
            }
            $pdo->commit();
            $_SESSION['admin_flash'] = $action === 'save_scene' ? 'シナリオを保存しました。ゲームに反映されます。' : '選択肢を保存しました。ゲームに反映されます。';
            header('Location: ' . admin_url($view, ['id' => $selectedId]), true, 303);
            exit;
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('admin save: ' . $e->getMessage());
            $errors[] = '保存できませんでした。入力内容を控え、DB接続を確認してからやり直してください。';
        }
    }
    $users = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $scenes = $pdo->query('SELECT ' . $sceneColumns . ' FROM scenes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $choices = $pdo->query('SELECT ' . $choiceColumns . ' FROM choices ORDER BY scene_id, display_order, id')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('admin load: ' . $e->getMessage());
    http_response_code(500);
    $fatal = '管理データを取得できませんでした。DB接続と users・scenes・choices テーブルを確認してください。';
}

$sceneById = $choicesByScene = [];
foreach ($scenes as $row) { $sceneById[(int)$row['id']] = $row; }
foreach ($choices as $row) { $choicesByScene[(int)$row['scene_id']][] = $row; }
$selected = $sceneById[$selectedId] ?? null;
$selectedChoices = $choicesByScene[$selectedId] ?? [];
$revision = $selected ? scene_revision($selected, $selectedChoices, $csrf) : '';
// エラー後に最新バージョンを自動採用しない。古い画面からの再保存も拒否する。
if ($retainInput) { $revision = input_text($_POST, 'revision'); }
$draft = $selected ?: [];
if ($retainInput && $view === 'scenes') {
    foreach (['title', 'speaker', 'content_text', 'background_image', 'character_image', 'next_scene_id'] as $key) {
        $draft[$key] = input_text($_POST, $key, (string)($draft[$key] ?? ''));
    }
}
$issues = [];
foreach ([1 => '開始', 11 => 'TRUE END', 12 => 'BAD END'] as $id => $label) {
    if (!isset($sceneById[$id])) { $issues[] = ['id' => 0, 'text' => $label . 'シーン #' . $id . ' がありません。']; }
}
foreach ([1 => 'normal', 11 => 'ending_true', 12 => 'ending_bad'] as $id => $expectedType) {
    if (isset($sceneById[$id]) && $sceneById[$id]['scene_type'] !== $expectedType) {
        $issues[] = ['id' => $id, 'text' => 'ゲーム側が想定しているシーン種別と異なります：' . $expectedType];
    }
}
foreach ($scenes as $row) {
    $id = (int)$row['id'];
    if (trim($row['content_text']) === '') { $issues[] = ['id' => $id, 'text' => '本文が空です。']; }
    if (!isset($types[$row['scene_type']])) { $issues[] = ['id' => $id, 'text' => 'ゲームが対応していないシーン種別です。']; }
    if ($row['scene_type'] === 'normal' && !isset($sceneById[(int)$row['next_scene_id']])) {
        $issues[] = ['id' => $id, 'text' => '通常シーンの移動先が未設定、または存在しません。'];
    }
    if ($row['next_scene_id'] !== null && (int)$row['next_scene_id'] === $id) {
        $issues[] = ['id' => $id, 'text' => '移動先が自分自身です。ループしないか確認してください。'];
    }
    if ($row['scene_type'] === 'choice' && empty($choicesByScene[$id])) { $issues[] = ['id' => $id, 'text' => '選択シーンに選択肢がありません。']; }
    if ($row['scene_type'] !== 'choice' && !empty($choicesByScene[$id])) { $issues[] = ['id' => $id, 'text' => 'このシーン種別では登録された選択肢は表示されません。']; }
    foreach (['background_image' => '背景', 'character_image' => 'キャラクター'] as $key => $label) {
        if (!empty($row[$key]) && effective_image($row[$key], $catalog) === '') { $issues[] = ['id' => $id, 'text' => $label . '画像が見つからないか、使用できない形式です：' . $row[$key]]; }
    }
}
foreach ($choices as $row) {
    if (!isset($sceneById[(int)$row['scene_id']])) {
        $issues[] = ['id' => 0, 'text' => '選択肢 #' . $row['id'] . ' の所属シーンがありません。'];
    }
    if (!isset($sceneById[(int)$row['next_scene_id']]) || (int)$row['next_scene_id'] === (int)$row['scene_id']) {
        $issues[] = ['id' => (int)$row['scene_id'], 'text' => '選択肢 #' . $row['id'] . ' の移動先を確認してください。'];
    }
}
foreach ($choicesByScene as $id => $rows) {
    $seenOrders = [];
    foreach ($rows as $row) {
        $order = (int)$row['display_order'];
        if (isset($seenOrders[$order])) {
            $issues[] = ['id' => $id, 'text' => '選択肢の表示順が重複しています。'];
            break;
        }
        $seenOrders[$order] = true;
    }
}
$filtered = array_values(array_filter($scenes, static function ($row) use ($query, $typeFilter, $view) {
    if ($view === 'choices' && $row['scene_type'] !== 'choice') { return false; }
    if ($typeFilter !== '' && $row['scene_type'] !== $typeFilter) { return false; }
    return $query === '' || stripos($row['id'] . ' ' . $row['title'] . ' ' . $row['speaker'] . ' ' . $row['content_text'], $query) !== false;
}));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($views[$view]) ?> | ゲーム管理</title>
    <link rel="stylesheet" href="css/admin.css?v=20261012-navigation">
</head>
<body>
<a class="skip-link" href="#main">本文へ移動</a>
<aside class="sidebar">
    <a class="brand" href="admin.php"><span class="brand-icon" aria-hidden="true">✿</span><span>ファーストキス<br><strong>おばあちゃん</strong></span></a>
    <p class="sidebar-label">ゲーム管理</p>
    <nav aria-label="管理メニュー">
        <?php $navNumber = 1; foreach ($views as $key => $label): ?>
            <a class="nav-item <?= $view === $key ? 'active' : '' ?>" href="<?= h(admin_url($key)) ?>" <?= $view === $key ? 'aria-current="page"' : '' ?>><span class="nav-number">0<?= $navNumber++ ?></span><?= h($label) ?><?php if ($key === 'checks' && $issues): ?><span class="nav-count"><?= count($issues) ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom"><span class="admin-avatar" aria-hidden="true">A</span><div><strong><?= h($admin['username'] ?? '') ?></strong><small>管理者としてログイン中</small></div></div>
</aside>
<main id="main" class="main">
    <header class="page-header"><div><p class="eyebrow">FIRST KISS / ADMIN</p><h1><?= h($views[$view]) ?></h1></div><div class="header-actions"><a class="button" href="game.php">ゲームへ ↗</a><a class="button secondary" href="index.php">トップページへ ↗</a></div></header>
    <?php if ($flash): ?><div class="notice success" role="status"><?= h($flash) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="notice error" role="alert"><strong>保存されていません</strong><?php foreach ($errors as $message): ?><p><?= h($message) ?></p><?php endforeach; ?></div><?php endif; ?>
    <?php if ($fatal): ?><div class="notice error" role="alert"><?= h($fatal) ?></div>
    <?php elseif ($view === 'overview'): ?>
        <p class="page-description">物語の文章、分岐、素材をここから確認・編集できます。</p>
        <div class="stats">
            <?php foreach ([['scenes', 'シナリオ', count($scenes), '物語を構成する場面'], ['choices', '選択肢', count($choices), '好感度とルートの分岐'], ['users', '登録ユーザー', count($users), 'アカウントの一覧'], ['checks', '確認事項', count($issues), '画像と移動先のチェック']] as $stat): ?>
            <a class="stat" href="<?= h(admin_url($stat[0])) ?>"><span><?= h($stat[1]) ?></span><strong><?= h($stat[2]) ?><small>件</small></strong><p><?= h($stat[3]) ?> <span aria-hidden="true">→</span></p></a>
            <?php endforeach; ?>
        </div>
        <div class="overview-grid">
            <section class="panel"><div class="panel-heading"><h2>まずは、ここから</h2><span class="badge">編集メニュー</span></div>
                <a class="task-link" href="<?= h(admin_url('scenes')) ?>"><span class="task-icon">文</span><span><strong>シナリオを整える</strong><small>タイトル・本文・話者・画像を編集</small></span><span>→</span></a>
                <a class="task-link" href="<?= h(admin_url('choices')) ?>"><span class="task-icon">枝</span><span><strong>選択肢を調整する</strong><small>選択文・好感度・移動先・表示順を編集</small></span><span>→</span></a>
                <a class="task-link" href="<?= h(admin_url('checks')) ?>"><span class="task-icon">確</span><span><strong>公開前の確認</strong><small>見つからない画像や移動先を確認</small></span><span>→</span></a>
            </section>
            <section class="panel guide"><p class="eyebrow">編集のヒント</p><h2>一場面ずつ、<br>読みやすく。</h2><p>本文の空行が「次へ」で進む区切りになります。プロローグは全文表示、選択肢は最後の文章のあとに表示されます。</p><p>保存した変更はゲームにすぐ反映されます。</p><div class="rule-note">現在の結末判定：好感度75以上でTRUE END。開始 #1 / TRUE END #11 / BAD END #12。</div></section>
        </div>

    <?php elseif ($view === 'create'): ?>
        <p class="page-description">場面を作成したあと、既存シーンの「次のシーン」または選択肢の「移動先」からつなげます。</p>
        <section class="panel editor">
        <form method="post" action="<?= h(admin_url('create')) ?>">
            <input type="hidden" name="action" value="create_scene">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <label for="new-kind">シーン種別</label>
            <select id="new-kind" name="scene_type"><?php foreach ($types as $key => $label): ?><option value="<?= h($key) ?>" <?= input_text($_POST, 'scene_type', 'normal') === $key ? 'selected' : '' ?>><?= h($label) ?>（<?= h($key) ?>）</option><?php endforeach; ?></select>
            <p class="hint">通常：文章のあと次の場面へ。選択：最後の文章のあと選択肢を表示。結末判定：好感度75以上で #11、それ以外は #12 へ。エンディング：文章のあと再プレイボタンを表示。</p>
            <label for="new-title">タイトル（必須）</label><input id="new-title" name="title" maxlength="100" required value="<?= h(input_text($_POST, 'title')) ?>">
            <label for="new-speaker">話者（空欄は地の文）</label><input id="new-speaker" name="speaker" maxlength="50" value="<?= h(input_text($_POST, 'speaker')) ?>">
            <label for="new-body">本文（必須）</label><textarea id="new-body" name="content_text" rows="10" maxlength="30000" required><?= h(input_text($_POST, 'content_text')) ?></textarea>
            <p class="hint">空行ごとに「次へ」で進みます。全文表示のプロローグは既存の #1 専用です。結末判定の本文はゲームでは表示されません。</p>
            <div class="field-grid">
            <?php foreach (['background_image' => '背景', 'character_image' => 'キャラクター'] as $field => $label): ?>
                <div><label for="new-<?= h($field) ?>"><?= h($label) ?></label><select id="new-<?= h($field) ?>" name="<?= h($field) ?>"><option value="">なし</option><?php foreach ($catalog as $path): ?><option value="<?= h($path) ?>" <?= input_text($_POST, $field) === $path ? 'selected' : '' ?>><?= h($path) ?></option><?php endforeach; ?></select></div>
            <?php endforeach; ?>
            </div>
            <label for="new-next">通常シーンの移動先</label>
            <select id="new-next" name="next_scene_id"><option value="">通常シーンの場合は選択してください</option><?php foreach ($scenes as $row): ?><option value="<?= h($row['id']) ?>" <?= input_text($_POST, 'next_scene_id') === (string)$row['id'] ? 'selected' : '' ?>>#<?= h($row['id']) ?> <?= h($row['title']) ?></option><?php endforeach; ?></select>
            <h2>選択シーンの分岐</h2><p class="hint">種別が「選択」のときだけ使用します。2〜3個入力してください。4個目以降は作成後の選択肢画面で追加できます。通常シーンなどではこの欄は無視されます。</p>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <fieldset class="choice-card"><legend>選択肢 <?= $i + 1 ?></legend>
                <label for="nt-<?= $i ?>">文章</label><input id="nt-<?= $i ?>" name="new_text_<?= $i ?>" maxlength="255" value="<?= h(input_text($_POST, 'new_text_' . $i)) ?>">
                <label for="nl-<?= $i ?>">好感度の増減</label><input id="nl-<?= $i ?>" type="number" name="new_love_<?= $i ?>" min="-100" max="100" value="<?= h(input_text($_POST, 'new_love_' . $i, '0')) ?>">
                <label for="nn-<?= $i ?>">移動先</label><select id="nn-<?= $i ?>" name="new_next_<?= $i ?>"><option value="">選択してください</option><?php foreach ($scenes as $row): ?><option value="<?= h($row['id']) ?>" <?= input_text($_POST, 'new_next_' . $i) === (string)$row['id'] ? 'selected' : '' ?>>#<?= h($row['id']) ?> <?= h($row['title']) ?></option><?php endforeach; ?></select>
                </fieldset>
            <?php endfor; ?>
            <p class="rule-note">分岐先の場面を先に作り、そのあと選択シーンを作ると接続しやすくなります。作成した場面は、既存ルートの移動先に指定するまで通常のプレイには登場しません。</p>
            <div class="form-footer"><button type="submit" class="button primary">シナリオを作成</button></div>
        </form></section>

    <?php elseif ($view === 'scenes' || $view === 'choices'): ?>
        <p class="page-description"><?= $view === 'scenes' ? '一覧から場面を選ぶと、本文と画像を編集できます。' : '選択シーンを選んで、分岐と好感度を調整します。' ?></p>
        <form class="filter-bar" method="get" action="admin.php">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <div class="filter-search"><label for="q">シナリオを検索</label><input id="q" name="q" value="<?= h($query) ?>" placeholder="タイトル・本文・IDで検索"></div>
            <?php if ($view === 'scenes'): ?><div><label for="type">種別</label><select id="type" name="type"><option value="">すべての種別</option><?php foreach ($types as $key => $label): ?><option value="<?= h($key) ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <button class="button secondary" type="submit">検索</button><a class="text-link" href="<?= h(admin_url($view)) ?>">クリア</a>
        </form>
        <div class="editor-grid">
            <section class="scene-list panel" aria-label="シナリオ一覧"><div class="panel-heading"><h2>シナリオ一覧</h2><span class="muted"><?= count($filtered) ?>件</span></div>
                <?php if (!$filtered): ?><p class="empty-small">条件に合うシナリオはありません。</p><?php endif; ?>
                <?php foreach ($filtered as $row): ?><a class="scene-row <?= (int)$row['id'] === $selectedId ? 'selected' : '' ?>" href="<?= h(admin_url($view, ['id' => $row['id'], 'q' => $query, 'type' => $typeFilter])) ?>" <?= (int)$row['id'] === $selectedId ? 'aria-current="true"' : '' ?>><span class="scene-id">#<?= h($row['id']) ?></span><span><strong><?= h($row['title']) ?></strong><small><?= h($types[$row['scene_type']] ?? $row['scene_type']) ?> · <?= count($choicesByScene[(int)$row['id']] ?? []) ?>選択肢</small></span><span aria-hidden="true">›</span></a><?php endforeach; ?>
            </section>
            <section class="panel editor">
            <?php if (!$selected): ?><div class="empty"><span aria-hidden="true">✿</span><h2>編集する場面を選んでください</h2><p>一覧を選ぶと、ここに編集画面が開きます。</p></div>
            <?php elseif ($view === 'scenes'): ?>
                <div class="panel-heading"><div><p class="eyebrow">SCENE #<?= h($selectedId) ?></p><h2><?= h($selected['title']) ?></h2></div><span class="badge"><?= h($types[$selected['scene_type']] ?? $selected['scene_type']) ?></span></div>
                <form method="post" action="<?= h(admin_url('scenes', ['id' => $selectedId])) ?>">
                    <input type="hidden" name="action" value="save_scene"><input type="hidden" name="scene_id" value="<?= h($selectedId) ?>"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>">
                    <label for="title">タイトル <span class="required">必須</span></label><input id="title" name="title" maxlength="100" value="<?= h($draft['title']) ?>" required>
                    <label for="speaker">話者名</label><input id="speaker" name="speaker" maxlength="50" value="<?= h($draft['speaker']) ?>" placeholder="空欄の場合は地の文"><p class="hint">名前欄の表示用です。本文に書いたセリフの名前はそのまま残ります。</p>
                    <label for="content_text">本文 <span class="required">必須</span></label><textarea id="content_text" name="content_text" rows="12" maxlength="30000" required aria-describedby="body-help"><?= h($draft['content_text']) ?></textarea><p class="hint" id="body-help">空行で文章送りを区切ります。プロローグは全文表示です。</p>
                    <div class="field-grid">
                    <?php foreach (['background_image' => '背景画像', 'character_image' => 'キャラクター画像'] as $field => $label): ?>
                        <div><label for="<?= h($field) ?>"><?= h($label) ?></label><select id="<?= h($field) ?>" name="<?= h($field) ?>"><option value="">なし</option>
                        <?php $currentPath = (string)($draft[$field] ?? ''); if ($currentPath !== '' && !isset($catalog[$currentPath])): ?><option value="<?= h($currentPath) ?>" selected>現在の設定：<?= h($currentPath) ?></option><?php endif; ?>
                        <?php foreach ($catalog as $path): ?><option value="<?= h($path) ?>" <?= $currentPath === $path ? 'selected' : '' ?>><?= h($path) ?></option><?php endforeach; ?></select>
                        <?php $preview = effective_image($currentPath, $catalog); if ($preview !== ''): ?><figure class="image-preview <?= $field === 'character_image' ? 'character-preview' : '' ?>"><img src="<?= h(image_url($preview)) ?>" alt="<?= h($label) ?>の現在のプレビュー" loading="lazy"><figcaption><?= h(basename($preview)) ?></figcaption></figure><?php else: ?><div class="image-empty"><?= $currentPath === '' ? '画像なし' : '画像を確認できません' ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div><p class="hint">画像は images フォルダ内から選べます。プレビューはページ表示時点の設定です。新しく選んだ画像は保存後に表示されます。TRUE ENDではゲーム側の立ち絵は非表示です。</p>
                    <?php if ($selected['scene_type'] === 'normal'): ?><label for="next_scene_id">次のシーン <span class="required">必須</span></label><select id="next_scene_id" name="next_scene_id" required><option value="">選んでください</option><?php foreach ($scenes as $row): if ((int)$row['id'] === $selectedId) { continue; } ?><option value="<?= h($row['id']) ?>" <?= (string)$draft['next_scene_id'] === (string)$row['id'] ? 'selected' : '' ?>>#<?= h($row['id']) ?> <?= h($row['title']) ?></option><?php endforeach; ?></select>
                    <?php elseif ($selected['scene_type'] === 'choice'): ?><p class="rule-note">移動先は<a href="<?= h(admin_url('choices', ['id' => $selectedId])) ?>">選択肢の編集</a>で設定できます。</p>
                    <?php else: ?><p class="rule-note">結末判定とエンディングの移動は、ゲーム側の処理で決まります。</p><?php endif; ?>
                    <div class="form-footer"><p class="hint">保存するとゲームへ反映されます。</p><a class="text-link" href="<?= h(admin_url('scenes', ['id' => $selectedId])) ?>">入力を戻す</a><button class="button primary" type="submit">変更を保存</button></div>
                </form>
                <details class="text-preview"><summary>保存済みの本文を読む</summary><div><?= nl2br(h($selected['content_text']), false) ?></div></details>
            <?php elseif ($selected['scene_type'] !== 'choice' || !$selectedChoices): ?><div class="empty"><h2>編集できる選択肢がありません</h2><p>選択肢のあるシーンを一覧から選んでください。</p></div>
            <?php else: ?>
                <div class="panel-heading"><div><p class="eyebrow">CHOICES / SCENE #<?= h($selectedId) ?></p><h2><?= h($selected['title']) ?></h2></div><span class="badge"><?= count($selectedChoices) ?>選択肢</span></div>
                <p class="hint">好感度は選択したときの増減値です。表示順は小さい番号から並びます。</p>
                <form method="post" action="<?= h(admin_url('choices', ['id' => $selectedId])) ?>">
                    <input type="hidden" name="action" value="save_choices"><input type="hidden" name="scene_id" value="<?= h($selectedId) ?>"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>">
                    <?php foreach ($selectedChoices as $choice): $cid = (int)$choice['id']; $data = $choice; if ($retainInput && isset($_POST['choices'][$cid]) && is_array($_POST['choices'][$cid])) { foreach (['choice_text', 'love_change', 'next_scene_id', 'display_order'] as $key) { $data[$key] = input_text($_POST['choices'][$cid], $key, (string)$data[$key]); } } ?>
                    <fieldset class="choice-card"><legend>選択肢 #<?= h($cid) ?></legend><label for="choice-<?= h($cid) ?>">選択肢の文章</label><input id="choice-<?= h($cid) ?>" name="choices[<?= h($cid) ?>][choice_text]" maxlength="255" value="<?= h($data['choice_text']) ?>" required>
                        <div class="choice-fields"><div><label for="love-<?= h($cid) ?>">好感度の増減</label><input id="love-<?= h($cid) ?>" type="number" name="choices[<?= h($cid) ?>][love_change]" min="-100" max="100" value="<?= h($data['love_change']) ?>" required></div><div><label for="order-<?= h($cid) ?>">表示順</label><input id="order-<?= h($cid) ?>" type="number" name="choices[<?= h($cid) ?>][display_order]" min="1" max="999" value="<?= h($data['display_order']) ?>" required></div><div><label for="next-<?= h($cid) ?>">移動先</label><select id="next-<?= h($cid) ?>" name="choices[<?= h($cid) ?>][next_scene_id]" required><option value="">選んでください</option><?php foreach ($scenes as $row): if ((int)$row['id'] === $selectedId) { continue; } ?><option value="<?= h($row['id']) ?>" <?= (string)$data['next_scene_id'] === (string)$row['id'] ? 'selected' : '' ?>>#<?= h($row['id']) ?> <?= h($row['title']) ?></option><?php endforeach; ?></select></div></div>
                    </fieldset><?php endforeach; ?>
                    <div class="form-footer"><p class="hint">このシーンの選択肢をまとめて保存します。</p><a class="text-link" href="<?= h(admin_url('choices', ['id' => $selectedId])) ?>">入力を戻す</a><button class="button primary" type="submit">選択肢を保存</button></div>
                </form>
            <?php endif; ?>

            <?php if ($view === 'choices' && $selected && $selected['scene_type'] === 'choice'): ?>
            <details class="text-preview"><summary>＋ 選択肢を追加する</summary>
            <form method="post" action="<?= h(admin_url('choices', ['id' => $selectedId])) ?>">
                <input type="hidden" name="action" value="add_choice"><input type="hidden" name="scene_id" value="<?= h($selectedId) ?>"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>">
                <p class="hint">既存の選択肢を編集中の場合は先に保存してください。追加は別の保存操作です。</p>
                <label for="add-text">選択肢の文章</label><input id="add-text" name="new_choice_text" maxlength="255" required value="<?= h(input_text($_POST, 'new_choice_text')) ?>">
                <label for="add-love">好感度の増減</label><input id="add-love" type="number" name="new_choice_love" min="-100" max="100" required value="<?= h(input_text($_POST, 'new_choice_love', '0')) ?>">
                <label for="add-next">移動先</label><select id="add-next" name="new_choice_next" required><option value="">選択してください</option><?php foreach ($scenes as $row): if ((int)$row['id'] === $selectedId) { continue; } ?><option value="<?= h($row['id']) ?>" <?= input_text($_POST, 'new_choice_next') === (string)$row['id'] ? 'selected' : '' ?>>#<?= h($row['id']) ?> <?= h($row['title']) ?></option><?php endforeach; ?></select>
                <div class="form-footer"><button class="button primary" type="submit">選択肢を追加</button></div>
            </form></details>
            <?php endif; ?>

            </section>
        </div>
    <?php elseif ($view === 'users'): ?>
        <p class="page-description">登録アカウントの一覧です。管理者権限の変更はphpMyAdminから行います。</p>
        <form class="filter-bar" method="get"><input type="hidden" name="view" value="users"><div class="filter-search"><label for="user-q">ユーザーを検索</label><input id="user-q" name="q" value="<?= h($query) ?>" placeholder="ユーザー名・ID"></div><button class="button secondary">検索</button><a class="text-link" href="<?= h(admin_url('users')) ?>">クリア</a></form>
        <?php $visibleUsers = array_values(array_filter($users, static function ($u) use ($query) { return $query === '' || stripos($u['id'] . ' ' . $u['username'], $query) !== false; })); $pageCount = max(1, (int)ceil(count($visibleUsers) / 25)); $page = max(1, min($pageCount, (int)input_text($_GET, 'page', '1'))); ?>
        <section class="panel"><div class="panel-heading"><h2>登録ユーザー</h2><span class="muted"><?= count($visibleUsers) ?>件</span></div><div class="table-scroll"><table><thead><tr><th scope="col">ID</th><th scope="col">ユーザー名</th><th scope="col">権限</th><th scope="col">登録日時</th></tr></thead><tbody><?php foreach (array_slice($visibleUsers, ($page - 1) * 25, 25) as $user): ?><tr><td class="muted">#<?= h($user['id']) ?></td><td><strong><?= h($user['username']) ?></strong></td><td><span class="badge <?= $user['role'] === 'admin' ? 'admin-badge' : '' ?>"><?= h($user['role'] === 'admin' ? '管理者' : ($user['role'] === 'user' ? '一般ユーザー' : $user['role'])) ?></span></td><td><?= h($user['created_at']) ?></td></tr><?php endforeach; ?><?php if (!$visibleUsers): ?><tr><td colspan="4">該当するユーザーはいません。</td></tr><?php endif; ?></tbody></table></div><nav class="pagination" aria-label="ユーザー一覧のページ"><span><?= $page ?> / <?= $pageCount ?>ページ</span><?php if ($page > 1): ?><a class="button secondary" href="<?= h(admin_url('users', ['q' => $query, 'page' => $page - 1])) ?>">前へ</a><?php endif; ?><?php if ($page < $pageCount): ?><a class="button secondary" href="<?= h(admin_url('users', ['q' => $query, 'page' => $page + 1])) ?>">次へ</a><?php endif; ?></nav></section>
    <?php elseif ($view === 'checks'): ?>
        <p class="page-description">画像ファイルと移動先を簡易確認しています。公開前には実際のゲームも一度通して確認してください。</p>
        <section class="panel"><div class="panel-heading"><h2>確認が必要な項目</h2><span class="badge"><?= count($issues) ?>件</span></div><?php if (!$issues): ?><div class="empty"><span aria-hidden="true">✓</span><h2>チェック対象の不備は見つかりませんでした</h2><p>文章の内容や全ルートの到達可否は、プレイして確認してください。</p></div><?php else: ?><ul class="issue-list"><?php foreach ($issues as $issue): ?><li><span class="issue-dot" aria-hidden="true">!</span><div><?php if ($issue['id']): ?><a href="<?= h(admin_url('scenes', ['id' => $issue['id']])) ?>">シーン #<?= h($issue['id']) ?></a><?php endif; ?><p><?= h($issue['text']) ?></p></div></li><?php endforeach; ?></ul><?php endif; ?></section>
    <?php endif; ?>
    <footer class="page-footer">ファーストキスおばあちゃん <span>管理者ページ</span></footer>
</main>
</body>
</html>
