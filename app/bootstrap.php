<?php

$config = require __DIR__ . '/../config/db.php';

date_default_timezone_set($config['timezone']);
session_start();

spl_autoload_register(static function ($class) {
    $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function config_value(string $key, mixed $default = null): mixed
{
    global $config;
    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        config_value('db.host'),
        config_value('db.port'),
        config_value('db.name'),
        config_value('db.charset')
    );

    $pdo = new PDO($dsn, config_value('db.user'), config_value('db.pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $page = 'dashboard', array $params = []): string
{
    $query = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($query);
}

function redirect_to(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message === null) {
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }

    $_SESSION['flash'][$key] = $message;
    return null;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cache = [];
    $id = (int) $_SESSION['user_id'];

    if (array_key_exists($id, $cache)) {
        return $cache[$id];
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $cache[$id] = $stmt->fetch() ?: null;

    return $cache[$id];
}

function require_login(): void
{
    if (!current_user()) {
        redirect_to('login');
    }
}

function is_admin(?array $user = null): bool
{
    $user ??= current_user();
    return (bool) $user && ($user['role'] ?? '') === 'admin';
}

function money_label(string $type): string
{
    return $type === 'exchange' ? '交換ポイント' : 'ゲームポイント';
}

function point_column(string $type): string
{
    return $type === 'exchange' ? 'exchange_points' : 'game_points';
}

function get_balance(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM point_balances WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $balance = $stmt->fetch();

    if ($balance) {
        return $balance;
    }

    db()->prepare('INSERT INTO point_balances (user_id, game_points, exchange_points, updated_at) VALUES (?, 0, 0, NOW())')
        ->execute([$userId]);

    return [
        'user_id' => $userId,
        'game_points' => 0,
        'exchange_points' => 0,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function add_point_history(array $data): void
{
    $stmt = db()->prepare(
        'INSERT INTO point_histories
            (user_id, point_type, amount, before_amount, after_amount, reason, comment, actor_user_id, created_at)
         VALUES
            (:user_id, :point_type, :amount, :before_amount, :after_amount, :reason, :comment, :actor_user_id, NOW())'
    );
    $stmt->execute([
        ':user_id' => $data['user_id'],
        ':point_type' => $data['point_type'],
        ':amount' => $data['amount'],
        ':before_amount' => $data['before_amount'],
        ':after_amount' => $data['after_amount'],
        ':reason' => $data['reason'],
        ':comment' => $data['comment'] ?? '',
        ':actor_user_id' => $data['actor_user_id'] ?? null,
    ]);
}

function ensure_seed_data(): void
{
    $pdo = db();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $adminHash = password_hash('password123', PASSWORD_DEFAULT);
    $learnerHash = password_hash('password123', PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())')
        ->execute(['管理者', 'admin@example.com', $adminHash, 'admin']);
    $adminId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())')
        ->execute(['学習者', 'learner@example.com', $learnerHash, 'learner']);
    $learnerId = (int) $pdo->lastInsertId();

    foreach ([$adminId, $learnerId] as $userId) {
        $pdo->prepare('INSERT INTO point_balances (user_id, game_points, exchange_points, updated_at) VALUES (?, 500, 100, NOW())')
            ->execute([$userId]);
    }

    $defaults = [
        ['slot_min_bet', '10'],
        ['slot_win_multiplier', '2'],
        ['slot_loss_multiplier', '-1'],
        ['study_base_reward', '20'],
        ['study_correct_bonus', '2'],
        ['reward_daily_reset_hour', '0'],
    ];

    foreach ($defaults as [$key, $value]) {
        $pdo->prepare('INSERT INTO game_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())')
            ->execute([$key, $value]);
    }

    $pdo->prepare('INSERT INTO rewards (name, description, exchange_points_cost, daily_limit, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())')
        ->execute(['タバコ', '現実のご褒美交換サンプル', 30, 3]);
    $pdo->prepare('INSERT INTO rewards (name, description, exchange_points_cost, daily_limit, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())')
        ->execute(['ゲーム時間', '休憩用のご褒美', 50, 1]);
    $pdo->prepare('INSERT INTO rewards (name, description, exchange_points_cost, daily_limit, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())')
        ->execute(['お小遣い', '現金交換のサンプル', 100, 1]);

    $pdo->prepare('INSERT INTO gacha_settings (name, gacha_type, cost_exchange_points, config_json, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())')
        ->execute(['キャラガチャ', 'character', 20, json_encode(['rarities' => ['N', 'R', 'SR'], 'note' => '仮設定'], JSON_UNESCAPED_UNICODE)]);
    $pdo->prepare('INSERT INTO gacha_settings (name, gacha_type, cost_exchange_points, config_json, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())')
        ->execute(['ポイントガチャ', 'point', 20, json_encode(['payouts' => ['small', 'middle', 'big']], JSON_UNESCAPED_UNICODE)]);

    $pdo->prepare('INSERT INTO characters (name, image_path, rarity, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())')
        ->execute(['ゴールドキャット', '/assets/img/character-placeholder.png', 'R']);
    $characterId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO user_characters (user_id, character_id, obtained_at, is_displayed) VALUES (?, ?, NOW(), 1)')
        ->execute([$learnerId, $characterId]);

    $pdo->commit();
}

ensure_seed_data();
