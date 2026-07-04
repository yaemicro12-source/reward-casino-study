<?php

require_once __DIR__ . '/../app/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';
$action = $_POST['action'] ?? null;
$user = current_user();

function setting_value(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM game_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string) $value : $default;
}

function balance_label(array $balance): string
{
    return 'ゲームポイント ' . number_format((int) $balance['game_points']) . ' / 交換ポイント ' . number_format((int) $balance['exchange_points']);
}

function render_layout(string $title, string $content, array $nav = []): void
{
    $user = current_user();
    $balance = $user ? get_balance((int) $user['id']) : ['game_points' => 0, 'exchange_points' => 0];
    $success = flash('success');
    $error = flash('error');
    ?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> | <?= h(config_value('app_name')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-shell">
<div class="app-grid">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">RC</div>
            <div>
                <h1><?= h(config_value('app_name')) ?></h1>
                <p>ネイビーとゴールドの学習ゲーミフィケーション</p>
            </div>
        </div>
        <?php if ($user): ?>
            <div class="balance-card">
                <div class="balance-label">残高</div>
                <div class="balance-value"><?= h(balance_label($balance)) ?></div>
            </div>
        <?php endif; ?>
        <nav class="nav-list">
            <?php foreach ($nav as $item): ?>
                <a class="nav-link" href="<?= h($item['href']) ?>"><?= h($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($user): ?>
            <form method="post" class="logout-form">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-ghost">ログアウト</button>
            </form>
        <?php endif; ?>
    </aside>
    <main class="main-panel">
        <?php if ($success): ?>
            <div class="notice notice-success"><?= h($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
    <?php
}

function render_card(string $title, string $body, string $foot = ''): string
{
    return '<section class="card"><h2>' . h($title) . '</h2><p>' . $body . '</p>' . ($foot !== '' ? '<div class="card-foot">' . $foot . '</div>' : '') . '</section>';
}

if ($action === 'logout') {
    session_destroy();
    session_start();
    flash('success', 'ログアウトしました。');
    redirect_to('login');
}

if ($action === 'login') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        $_SESSION['user_id'] = $account['id'];
        flash('success', 'ログインしました。');
        redirect_to(($account['role'] ?? 'learner') === 'admin' ? 'admin-dashboard' : 'dashboard');
    }

    flash('error', 'メールアドレスまたはパスワードが違います。');
    redirect_to('login');
}

if ($action === 'register') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = in_array(($_POST['role'] ?? 'learner'), ['learner', 'admin'], true) ? $_POST['role'] : 'learner';

    if ($name === '' || $email === '' || $password === '') {
        flash('error', 'すべて入力してください。');
        redirect_to('register');
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        flash('error', 'そのメールアドレスは既に使われています。');
        redirect_to('register');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())')
        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO point_balances (user_id, game_points, exchange_points, updated_at) VALUES (?, 0, 0, NOW())')
        ->execute([$userId]);
    $pdo->commit();

    $_SESSION['user_id'] = $userId;
    flash('success', '登録しました。');
    redirect_to($role === 'admin' ? 'admin-dashboard' : 'dashboard');
}

if ($action === 'point-adjust') {
    require_login();
    if (!is_admin()) {
        redirect_to('dashboard');
    }

    $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
    $pointType = in_array($_POST['point_type'] ?? '', ['game', 'exchange'], true) ? $_POST['point_type'] : 'game';
    $amount = (int) ($_POST['amount'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));

    $stmt = db()->prepare('SELECT * FROM point_balances WHERE user_id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
    $balance = $stmt->fetch();
    if (!$balance) {
        flash('error', '対象ユーザーの残高が見つかりません。');
        redirect_to('admin-point-adjust');
    }

    $column = point_column($pointType);
    $before = (int) $balance[$column];
    $after = max(0, $before + $amount);

    db()->beginTransaction();
    db()->prepare("UPDATE point_balances SET {$column} = ?, updated_at = NOW() WHERE user_id = ?")
        ->execute([$after, $targetUserId]);
    add_point_history([
        'user_id' => $targetUserId,
        'point_type' => $pointType,
        'amount' => $amount,
        'before_amount' => $before,
        'after_amount' => $after,
        'reason' => 'manual_adjustment',
        'comment' => $comment,
        'actor_user_id' => (int) current_user()['id'],
    ]);
    db()->commit();

    flash('success', 'ポイントを調整しました。');
    redirect_to('admin-point-adjust');
}

if ($action === 'create-reward') {
    require_login();
    if (!is_admin()) {
        redirect_to('dashboard');
    }

    db()->prepare('INSERT INTO rewards (name, description, exchange_points_cost, daily_limit, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')
        ->execute([
            trim((string)($_POST['name'] ?? '')),
            trim((string)($_POST['description'] ?? '')),
            (int) ($_POST['exchange_points_cost'] ?? 0),
            (int) ($_POST['daily_limit'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
        ]);
    flash('success', 'ご褒美を登録しました。');
    redirect_to('admin-rewards');
}

if ($action === 'request-reward') {
    require_login();
    $rewardId = (int) ($_POST['reward_id'] ?? 0);
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $memo = trim((string) ($_POST['memo'] ?? ''));

    $stmt = db()->prepare('SELECT * FROM rewards WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$rewardId]);
    $reward = $stmt->fetch();

    if (!$reward) {
        flash('error', 'ご褒美が見つかりません。');
        redirect_to('rewards');
    }

    $cost = (int) $reward['exchange_points_cost'] * $quantity;
    $balance = get_balance((int) $user['id']);
    if ((int) $balance['exchange_points'] < $cost) {
        flash('error', '交換ポイントが足りません。');
        redirect_to('reward-request');
    }

    db()->prepare('INSERT INTO reward_requests (user_id, reward_id, quantity, requested_exchange_points, status, memo, created_at, updated_at) VALUES (?, ?, ?, ?, "pending", ?, NOW(), NOW())')
        ->execute([(int) $user['id'], $rewardId, $quantity, $cost, $memo]);
    flash('success', '交換申請を送信しました。');
    redirect_to('reward-request');
}

if ($action === 'submit-study-log') {
    require_login();
    $title = trim((string) ($_POST['title'] ?? ''));
    $studyMinutes = (int) ($_POST['study_minutes'] ?? 0);
    $questionCount = (int) ($_POST['question_count'] ?? 0);
    $correctCount = (int) ($_POST['correct_count'] ?? 0);
    $memo = trim((string) ($_POST['memo'] ?? ''));
    $rate = $questionCount > 0 ? round(($correctCount / $questionCount) * 100, 2) : 0.0;
    $screenshot = null;

    if (!empty($_FILES['screenshot']['name']) && is_uploaded_file($_FILES['screenshot']['tmp_name'])) {
        $dir = config_value('upload_dir');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
        $filename = 'study_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
        $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        move_uploaded_file($_FILES['screenshot']['tmp_name'], $target);
        $screenshot = 'storage/uploads/' . $filename;
    }

    db()->prepare('INSERT INTO study_logs (user_id, title, study_minutes, question_count, correct_count, correct_rate, screenshot_path, memo, status, rewarded_game_points, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", 0, NOW(), NOW())')
        ->execute([(int) $user['id'], $title, $studyMinutes, $questionCount, $correctCount, $rate, $screenshot, $memo]);
    flash('success', '学習ログを登録しました。');
    redirect_to('study-log-create');
}

if ($action === 'spin-slot') {
    require_login();
    $bet = max(1, (int) ($_POST['bet_points'] ?? 0));
    $balance = get_balance((int) $user['id']);
    if ($bet > (int) $balance['game_points']) {
        flash('error', 'ゲームポイントが足りません。');
        redirect_to('slot');
    }

    $winRate = (int) setting_value('slot_win_multiplier', '2');
    $lossMultiplier = (int) setting_value('slot_loss_multiplier', '-1');
    $before = (int) $balance['game_points'];
    $won = random_int(1, 100) <= 35;
    $delta = $won ? $bet * max(1, $winRate) : $bet * min(-1, $lossMultiplier);
    $after = max(0, $before + $delta);

    db()->beginTransaction();
    db()->prepare('UPDATE point_balances SET game_points = ?, updated_at = NOW() WHERE user_id = ?')
        ->execute([$after, (int) $user['id']]);
    add_point_history([
        'user_id' => (int) $user['id'],
        'point_type' => 'game',
        'amount' => $delta,
        'before_amount' => $before,
        'after_amount' => $after,
        'reason' => 'slot',
        'comment' => $won ? 'スロット当たり' : 'スロット外れ',
        'actor_user_id' => (int) $user['id'],
    ]);
    db()->prepare('INSERT INTO slot_results (user_id, bet_points, result_type, delta_points, before_points, after_points, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([(int) $user['id'], $bet, $won ? 'win' : 'lose', $delta, $before, $after, $won ? '当たり' : '外れ']);
    db()->commit();

    flash('success', $won ? '当たりです。ゲームポイントが増えました。' : '外れです。ゲームポイントが減りました。');
    redirect_to('slot');
}

if ($action === 'update-study-review') {
    require_login();
    if (!is_admin()) {
        redirect_to('dashboard');
    }

    $logId = (int) ($_POST['log_id'] ?? 0);
    $status = in_array($_POST['status'] ?? 'pending', ['approved', 'rejected'], true) ? $_POST['status'] : 'pending';
    $award = max(0, (int) ($_POST['award_game_points'] ?? 0));
    $comment = trim((string) ($_POST['comment'] ?? ''));

    $stmt = db()->prepare('SELECT * FROM study_logs WHERE id = ? LIMIT 1');
    $stmt->execute([$logId]);
    $log = $stmt->fetch();
    if (!$log) {
        flash('error', '学習ログが見つかりません。');
        redirect_to('admin-study-reviews');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE study_logs SET status = ?, rewarded_game_points = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?')
        ->execute([$status, $award, (int) $user['id'], $logId]);
    if ($status === 'approved' && $award > 0) {
        $balance = get_balance((int) $log['user_id']);
        $before = (int) $balance['game_points'];
        $after = $before + $award;
        $pdo->prepare('UPDATE point_balances SET game_points = ?, updated_at = NOW() WHERE user_id = ?')
            ->execute([$after, (int) $log['user_id']]);
        add_point_history([
            'user_id' => (int) $log['user_id'],
            'point_type' => 'game',
            'amount' => $award,
            'before_amount' => $before,
            'after_amount' => $after,
            'reason' => 'study_review',
            'comment' => $comment ?: '学習ログ承認',
            'actor_user_id' => (int) $user['id'],
        ]);
    }
    $pdo->commit();

    flash('success', '学習ログを更新しました。');
    redirect_to('admin-study-reviews');
}

if ($action === 'update-reward-request') {
    require_login();
    if (!is_admin()) {
        redirect_to('dashboard');
    }
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $status = in_array($_POST['status'] ?? 'pending', ['approved', 'rejected'], true) ? $_POST['status'] : 'pending';
    $comment = trim((string) ($_POST['comment'] ?? ''));

    $stmt = db()->prepare('SELECT rr.*, r.exchange_points_cost FROM reward_requests rr INNER JOIN rewards r ON r.id = rr.reward_id WHERE rr.id = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) {
        flash('error', '交換申請が見つかりません。');
        redirect_to('admin-reward-requests');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE reward_requests SET status = ?, reviewer_id = ?, reviewer_comment = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$status, (int) $user['id'], $comment, $requestId]);
    if ($status === 'approved') {
        $balance = get_balance((int) $request['user_id']);
        $before = (int) $balance['exchange_points'];
        $after = max(0, $before - (int) $request['requested_exchange_points']);
        $pdo->prepare('UPDATE point_balances SET exchange_points = ?, updated_at = NOW() WHERE user_id = ?')
            ->execute([$after, (int) $request['user_id']]);
        add_point_history([
            'user_id' => (int) $request['user_id'],
            'point_type' => 'exchange',
            'amount' => -((int) $request['requested_exchange_points']),
            'before_amount' => $before,
            'after_amount' => $after,
            'reason' => 'reward_exchange',
            'comment' => $comment ?: 'ご褒美交換',
            'actor_user_id' => (int) $user['id'],
        ]);
    }
    $pdo->commit();

    flash('success', '交換申請を更新しました。');
    redirect_to('admin-reward-requests');
}

if ($action === 'save-game-settings') {
    require_login();
    if (!is_admin()) {
        redirect_to('dashboard');
    }
    foreach (($_POST['settings'] ?? []) as $key => $value) {
        $stmt = db()->prepare('INSERT INTO game_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
        $stmt->execute([$key, (string) $value]);
    }
    flash('success', 'ゲーム設定を更新しました。');
    redirect_to('admin-game-settings');
}

if ($page === 'login') {
    $content = '
        <section class="hero">
            <h2>ログイン</h2>
            <form method="post" class="form-panel">
                <input type="hidden" name="action" value="login">
                <label>メールアドレス<input type="email" name="email" required></label>
                <label>パスワード<input type="password" name="password" required></label>
                <button class="btn btn-primary" type="submit">ログイン</button>
                <p class="muted">初期管理者: admin@example.com / password123</p>
            </form>
        </section>
    ';
    render_layout('ログイン', $content, [
        ['label' => '新規登録', 'href' => url('register')],
    ]);
    exit;
}

if ($page === 'register') {
    $content = '
        <section class="hero">
            <h2>新規登録</h2>
            <form method="post" class="form-panel">
                <input type="hidden" name="action" value="register">
                <label>名前<input type="text" name="name" required></label>
                <label>メールアドレス<input type="email" name="email" required></label>
                <label>パスワード<input type="password" name="password" required></label>
                <label>ロール
                    <select name="role">
                        <option value="learner">学習者</option>
                        <option value="admin">管理者</option>
                    </select>
                </label>
                <button class="btn btn-primary" type="submit">登録する</button>
            </form>
        </section>
    ';
    render_layout('新規登録', $content, [
        ['label' => 'ログイン', 'href' => url('login')],
    ]);
    exit;
}

if (!current_user()) {
    redirect_to('login');
}

$navCommon = [
    ['label' => 'ダッシュボード', 'href' => url('dashboard')],
    ['label' => 'ポイント履歴', 'href' => url('point-history')],
];

$learnerNav = [
    ['label' => '勉強タイマー', 'href' => url('study-timer')],
    ['label' => '学習ログ登録', 'href' => url('study-log-create')],
    ['label' => '学習ログ一覧', 'href' => url('study-log-list')],
    ['label' => 'スロット', 'href' => url('slot')],
    ['label' => 'ご褒美一覧', 'href' => url('rewards')],
    ['label' => '交換申請', 'href' => url('reward-request')],
    ['label' => 'ガチャメニュー', 'href' => url('gacha-menu')],
];

$adminNav = [
    ['label' => '管理者ダッシュボード', 'href' => url('admin-dashboard')],
    ['label' => 'ポイント調整', 'href' => url('admin-point-adjust')],
    ['label' => 'ユーザー一覧', 'href' => url('admin-users')],
    ['label' => '学習ログ承認', 'href' => url('admin-study-reviews')],
    ['label' => 'ご褒美管理', 'href' => url('admin-rewards')],
    ['label' => '交換申請承認', 'href' => url('admin-reward-requests')],
    ['label' => 'ゲーム設定', 'href' => url('admin-game-settings')],
];

$nav = array_merge($navCommon, is_admin() ? $adminNav : $learnerNav);

switch ($page) {
    case 'dashboard':
        $content = '';
        $content .= '<section class="hero"><h2>ダッシュボード</h2><p>役割に応じた操作をここから始めます。</p></section>';
        $content .= '<div class="grid">';
        $content .= render_card('ゲームポイント', 'ゲームのスロットで使うポイントです。');
        $content .= render_card('交換ポイント', 'ご褒美と交換するポイントです。');
        $content .= render_card('学習ログ', '勉強時間や正答率を登録して承認できます。');
        $content .= render_card('ご褒美', '交換申請と承認を管理します。');
        $content .= '</div>';
        render_layout('ダッシュボード', $content, $nav);
        break;

    case 'point-history':
        $stmt = db()->prepare('SELECT ph.*, u.name AS user_name FROM point_histories ph INNER JOIN users u ON u.id = ph.user_id WHERE ph.user_id = ? OR ? = 1 ORDER BY ph.id DESC LIMIT 100');
        $stmt->execute([(int) $user['id'], is_admin() ? 1 : 0]);
        $rows = $stmt->fetchAll();
        $items = '';
        foreach ($rows as $row) {
            $items .= '<li><span>' . h($row['created_at']) . '</span><strong>' . h($row['user_name']) . '</strong><span>' . h(money_label($row['point_type'])) . '</span><span>' . h((string) $row['amount']) . '</span><span>' . h($row['comment'] ?? '') . '</span></li>';
        }
        $content = '<section class="hero"><h2>ポイント履歴</h2><p>付与・減算の流れを一覧で確認します。</p></section><ul class="history-list">' . $items . '</ul>';
        render_layout('ポイント履歴', $content, $nav);
        break;

    case 'study-timer':
        $content = '<section class="hero"><h2>勉強タイマー</h2><p>学習時間を測って、終了後にログ登録へつなげます。</p></section>
        <section class="card timer-card">
            <div class="timer-display" id="timer-display">25:00</div>
            <div class="timer-actions">
                <button type="button" class="btn btn-secondary" data-timer="5">5分</button>
                <button type="button" class="btn btn-secondary" data-timer="25">25分</button>
                <button type="button" class="btn btn-secondary" data-timer="50">50分</button>
            </div>
            <div class="timer-actions">
                <button type="button" class="btn btn-primary" id="timer-start">スタート</button>
                <button type="button" class="btn btn-ghost" id="timer-reset">リセット</button>
            </div>
        </section>';
        render_layout('勉強タイマー', $content, $nav);
        break;

    case 'study-log-create':
        $content = '<section class="hero"><h2>学習ログ登録</h2><p>外部サイトで解いた結果を入力します。</p></section>
        <form method="post" enctype="multipart/form-data" class="form-panel">
            <input type="hidden" name="action" value="submit-study-log">
            <label>勉強タイトル<input type="text" name="title" required></label>
            <label>勉強時間（分）<input type="number" name="study_minutes" min="0" required></label>
            <label>問題数<input type="number" name="question_count" min="0" required></label>
            <label>正答数<input type="number" name="correct_count" min="0" required></label>
            <label>スクリーンショット<input type="file" name="screenshot" accept="image/*"></label>
            <label>メモ<textarea name="memo" rows="4"></textarea></label>
            <button class="btn btn-primary" type="submit">登録する</button>
        </form>';
        render_layout('学習ログ登録', $content, $nav);
        break;

    case 'study-log-list':
        $stmt = db()->prepare('SELECT * FROM study_logs WHERE user_id = ? ORDER BY id DESC LIMIT 100');
        $stmt->execute([(int) $user['id']]);
        $rows = $stmt->fetchAll();
        $cards = '';
        foreach ($rows as $row) {
            $cards .= '<article class="card"><h3>' . h($row['title']) . '</h3><p>勉強時間: ' . h((string) $row['study_minutes']) . ' 分</p><p>正答率: ' . h((string) $row['correct_rate']) . '%</p><p>状態: ' . h($row['status']) . '</p></article>';
        }
        $content = '<section class="hero"><h2>学習ログ一覧</h2><p>登録した内容と承認状態を確認します。</p></section><div class="grid">' . $cards . '</div>';
        render_layout('学習ログ一覧', $content, $nav);
        break;

    case 'slot':
        $stmt = db()->prepare('SELECT * FROM slot_results WHERE user_id = ? ORDER BY id DESC LIMIT 20');
        $stmt->execute([(int) $user['id']]);
        $rows = $stmt->fetchAll();
        $recent = '';
        foreach ($rows as $row) {
            $recent .= '<li>' . h($row['created_at']) . ' / ' . h($row['result_type']) . ' / ' . h((string) $row['delta_points']) . '</li>';
        }
        $content = '<section class="hero"><h2>スロット</h2><p>ゲームポイントをベットして結果を確認します。</p></section>
        <section class="card">
            <form method="post" class="form-panel inline-form">
                <input type="hidden" name="action" value="spin-slot">
                <label>ベット額<input type="number" name="bet_points" min="1" value="' . h(setting_value('slot_min_bet', '10')) . '"></label>
                <button class="btn btn-primary" type="submit">スピン</button>
            </form>
            <ul class="history-list">' . $recent . '</ul>
        </section>';
        render_layout('スロット', $content, $nav);
        break;

    case 'rewards':
        $rows = db()->query('SELECT * FROM rewards ORDER BY id DESC')->fetchAll();
        $cards = '';
        foreach ($rows as $row) {
            $cards .= '<article class="card"><h3>' . h($row['name']) . '</h3><p>' . h($row['description'] ?? '') . '</p><p>必要交換ポイント: ' . h((string) $row['exchange_points_cost']) . '</p><p>1日上限: ' . h((string) $row['daily_limit']) . '</p></article>';
        }
        $content = '<section class="hero"><h2>ご褒美一覧</h2><p>交換対象の一覧です。</p></section><div class="grid">' . $cards . '</div>';
        render_layout('ご褒美一覧', $content, $nav);
        break;

    case 'reward-request':
        $rewards = db()->query('SELECT * FROM rewards WHERE is_active = 1 ORDER BY id DESC')->fetchAll();
        $options = '';
        foreach ($rewards as $reward) {
            $options .= '<option value="' . h($reward['id']) . '">' . h($reward['name']) . '（' . h((string) $reward['exchange_points_cost']) . '）</option>';
        }
        $requests = db()->prepare('SELECT rr.*, r.name AS reward_name FROM reward_requests rr INNER JOIN rewards r ON r.id = rr.reward_id WHERE rr.user_id = ? ORDER BY rr.id DESC LIMIT 20');
        $requests->execute([(int) $user['id']]);
        $requestRows = $requests->fetchAll();
        $list = '';
        foreach ($requestRows as $row) {
            $list .= '<li>' . h($row['reward_name']) . ' / ' . h($row['status']) . ' / ' . h((string) $row['requested_exchange_points']) . '</li>';
        }
        $content = '<section class="hero"><h2>ご褒美交換申請</h2><p>管理者承認制で交換します。</p></section>
        <form method="post" class="form-panel">
            <input type="hidden" name="action" value="request-reward">
            <label>ご褒美<select name="reward_id">' . $options . '</select></label>
            <label>数量<input type="number" name="quantity" min="1" value="1"></label>
            <label>メモ<textarea name="memo" rows="3"></textarea></label>
            <button class="btn btn-primary" type="submit">申請する</button>
        </form>
        <ul class="history-list">' . $list . '</ul>';
        render_layout('ご褒美交換申請', $content, $nav);
        break;

    case 'gacha-menu':
        $content = '<section class="hero"><h2>ガチャメニュー</h2><p>今後の拡張を見据えた仮画面です。</p></section>
        <div class="grid">
            <a class="card card-link" href="' . h(url('gacha-characters')) . '"><h3>キャラガチャ仮画面</h3><p>キャラコレクションの入口です。</p></a>
            <a class="card card-link" href="' . h(url('gacha-points')) . '"><h3>ポイントガチャ仮画面</h3><p>交換ポイント増減の演出を想定しています。</p></a>
        </div>';
        render_layout('ガチャメニュー', $content, $nav);
        break;

    case 'gacha-characters':
        $content = '<section class="hero"><h2>キャラガチャ仮画面</h2><p>将来のキャラクター実装を前提にしたプレースホルダーです。</p></section><section class="card"><p>ここに演出、レア度、獲得キャラ一覧を追加できます。</p></section>';
        render_layout('キャラガチャ仮画面', $content, $nav);
        break;

    case 'gacha-points':
        $content = '<section class="hero"><h2>ポイントガチャ仮画面</h2><p>交換ポイントを消費するガチャの仮配置です。</p></section><section class="card"><p>大当たり・小当たり・ハズレのテーブルを後から追加しやすい構成です。</p></section>';
        render_layout('ポイントガチャ仮画面', $content, $nav);
        break;

    case 'admin-dashboard':
        if (!is_admin()) {
            redirect_to('dashboard');
        }
        $content = '<section class="hero"><h2>管理者ダッシュボード</h2><p>全体管理の入口です。</p></section><div class="grid">'
            . render_card('ユーザー管理', '学習者と管理者を一覧できます。')
            . render_card('ポイント調整', 'ゲームポイント / 交換ポイントを調整できます。')
            . render_card('承認待ち', '学習ログと交換申請を確認します。')
            . '</div>';
        render_layout('管理者ダッシュボード', $content, $nav);
        break;

    case 'admin-point-adjust':
        if (!is_admin()) {
            redirect_to('dashboard');
        }
        $users = db()->query('SELECT id, name, role FROM users ORDER BY id ASC')->fetchAll();
        $options = '';
        foreach ($users as $u) {
            $options .= '<option value="' . h($u['id']) . '">' . h($u['name']) . '（' . h($u['role']) . '）</option>';
        }
        $content = '<section class="hero"><h2>ポイント調整</h2><p>対象ユーザーとポイント種類を選んで増減します。</p></section>
        <form method="post" class="form-panel">
            <input type="hidden" name="action" value="point-adjust">
            <label>対象ユーザー<select name="target_user_id">' . $options . '</select></label>
            <label>ポイント種類
                <select name="point_type">
                    <option value="game">ゲームポイント</option>
                    <option value="exchange">交換ポイント</option>
                </select>
            </label>
            <label>増減値<input type="number" name="amount" value="10"></label>
            <label>コメント<textarea name="comment" rows="3"></textarea></label>
            <button class="btn btn-primary" type="submit">保存する</button>
        </form>';
        render_layout('ポイント調整', $content, $nav);
        break;

    case 'admin-users':
        if (!is_admin()) {
            redirect_to('dashboard');
        }
        $rows = db()->query('SELECT u.*, pb.game_points, pb.exchange_points FROM users u LEFT JOIN point_balances pb ON pb.user_id = u.id ORDER BY u.id DESC')->fetchAll();
        $items = '';
        foreach ($rows as $row) {
            $items .= '<tr><td>' . h($row['name']) . '</td><td>' . h($row['email']) . '</td><td>' . h($row['role']) . '</td><td>' . h((string) ($row['game_points'] ?? 0)) . '</td><td>' . h((string) ($row['exchange_points'] ?? 0)) . '</td></tr>';
        }
        $content = '<section class="hero"><h2>ユーザー一覧</h2><p>登録済みユーザーを確認します。</p></section>
        <table class="table"><thead><tr><th>名前</th><th>メール</th><th>ロール</th><th>ゲームポイント</th><th>交換ポイント</th></tr></thead><tbody>' . $items . '</tbody></table>';
        render_layout('ユーザー一覧', $content, $nav);
        break;

    case 'admin-study-reviews':
        if (!is_admin()) {
            redirect_to('dashboard');
        }
        $rows = db()->query('SELECT sl.*, u.name AS user_name FROM study_logs sl INNER JOIN users u ON u.id = sl.user_id ORDER BY sl.id DESC')->fetchAll();
        $cards = '';
        foreach ($rows as $row) {
            $cards .= '<article class="card"><h3>' . h($row['title']) . '</h3><p>ユーザー: ' . h($row['user_name']) . '</p><p>状態: ' . h($row['status']) . '</p><form method="post" class="form-panel compact-form"><input type="hidden" name="action" value="update-study-review"><input type="hidden" name="log_id" value="' . h((string) $row['id']) . '"><label>付与ゲームポイント<input type="number" name="award_game_points" value="' . h((string) $row['rewarded_game_points']) . '"></label><label>コメント<textarea name="comment" rows="2"></textarea></label><div class="button-row"><button class="btn btn-secondary" name="status" value="approved" type="submit">承認</button><button class="btn btn-ghost" name="status" value="rejected" type="submit">却下</button></div></form></article>';
        }
        $content = '<section class="hero"><h2>学習ログ承認</h2><p>学習ログを確認して承認します。</p></section><div class="grid">' . $cards . '</div>';
        render_layout('学習ログ承認', $content, $nav);
        break;

    case 'admin-rewards':
        if (!is_admin()) {
            redirect_to('dashboard');
        }
        $rows = db()->query('SELECT * FROM rewards ORDER BY id DESC')->fetchAll();
        $cards = '';
        foreach ($rows as $row) {
            $cards .= '<article class="card"><h3>' . h($row['name']) . '</h3><p>' . h($row['description'] ?? '') . '</p><p>必要交換ポイント: ' . h((string) $row['exchange_points_cost']) . '</p><p>1日上限: ' . h((string) $row['daily_limit']) . '</p></article>';
        }
        $content = '<section class="hero"><h2>ご褒美管理</h2><p>追加・編集・削除の入口です。初期版は登録中心です。</p></section>
        <form method="post" class="form-panel">
            <input type="hidden" name="action" value="create-reward">
            <label>商品名<input type="text" name="name" required></label>
            <label>説明<textarea name="description" rows="3"></textarea></label>
            <label>必要交換ポイント<input type="number" name="exchange_points_cost" value="30"></label>
            <label>1日の交換上限<input type="number" name="daily_limit" value="1"></label>
            <label class="checkbox"><input type="checkbox" name="is_active" checked>有効</label>
            <button class="btn btn-primary" type="submit">登録する</button>
        </form>
        <div class="grid">' . $cards . '</div>';
        render_layout('ご褒美管理', $content, $nav);
        break;

    case 'admin-reward-requests':
        if (!is_admin()) {
            redirect_to('dashboard');
        }
        $rows = db()->query('SELECT rr.*, u.name AS user_name, r.name AS reward_name FROM reward_requests rr INNER JOIN users u ON u.id = rr.user_id INNER JOIN rewards r ON r.id = rr.reward_id ORDER BY rr.id DESC')->fetchAll();
        $cards = '';
        foreach ($rows as $row) {
            $cards .= '<article class="card"><h3>' . h($row['reward_name']) . '</h3><p>申請者: ' . h($row['user_name']) . '</p><p>状態: ' . h($row['status']) . '</p><form method="post" class="form-panel compact-form"><input type="hidden" name="action" value="update-reward-request"><input type="hidden" name="request_id" value="' . h((string) $row['id']) . '"><label>コメント<input type="text" name="comment"></label><div class="button-row"><button class="btn btn-secondary" name="status" value="approved" type="submit">承認</button><button class="btn btn-ghost" name="status" value="rejected" type="submit">却下</button></div></form></article>';
        }
        $content = '<section class="hero"><h2>交換申請承認</h2><p>交換申請を承認または却下します。</p></section><div class="grid">' . $cards . '</div>';
        render_layout('交換申請承認', $content, $nav);
        break;

    case 'admin-game-settings':
        if (!is_admin()) {
            redirect_to('dashboard');
        }
        $settings = db()->query('SELECT * FROM game_settings ORDER BY setting_key ASC')->fetchAll();
        $fields = '';
        foreach ($settings as $setting) {
            $fields .= '<label>' . h($setting['setting_key']) . '<input type="text" name="settings[' . h($setting['setting_key']) . ']" value="' . h($setting['setting_value']) . '"></label>';
        }
        $content = '<section class="hero"><h2>ゲーム設定</h2><p>スロットや学習報酬の設定を保守します。</p></section>
        <form method="post" class="form-panel">
            <input type="hidden" name="action" value="save-game-settings">
            ' . $fields . '
            <button class="btn btn-primary" type="submit">保存する</button>
        </form>';
        render_layout('ゲーム設定', $content, $nav);
        break;

    default:
        redirect_to('dashboard');
}
