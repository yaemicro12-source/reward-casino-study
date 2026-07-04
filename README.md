# Reward Casino Study

勉強・家事・禁煙などの行動を、`ゲームポイント` と `交換ポイント` で管理するシンプルな Web アプリです。  
ネイビーとゴールドを基調にした、カジノ風・ゲーム風の見た目を前提にしています。

## できること

- ログイン / 新規登録
- 管理者 / 学習者のロール分岐
- ゲームポイント / 交換ポイントの残高確認
- ポイント履歴確認
- 管理者によるポイント調整
- ご褒美一覧 / 交換申請 / 承認
- 勉強タイマー
- 学習ログ登録 / 承認
- スロット
- キャラガチャ / ポイントガチャの仮画面
- 管理者向け設定画面

## 技術

- PHP
- MySQL
- HTML
- CSS
- JavaScript

## 初期セットアップ

### 1. データベース作成

MySQL で新しいデータベースを作成し、`database/schema.sql` を流し込みます。

```sql
source database/schema.sql;
```

### 2. 接続設定

`app/config.php` の DB 情報を環境に合わせて変更します。

### 3. Web サーバーの公開フォルダ

公開ディレクトリは `public/` を指定してください。

静的ファイルは `public/assets/`、アップロード先は `public/storage/uploads/` です。

### 4. 初回起動

初回アクセス時に、最低限のデータが自動投入されます。

- 管理者
  - email: `admin@example.com`
  - password: `password123`
- 学習者
  - email: `learner@example.com`
  - password: `password123`

## 画面一覧

### 共通

- ログイン画面
- 新規登録画面
- ダッシュボード
- ポイント履歴画面

### 学習者側

- 勉強タイマー画面
- 学習ログ登録画面
- 学習ログ一覧画面
- スロット画面
- ご褒美一覧画面
- ご褒美交換申請画面
- ガチャメニュー画面
- キャラガチャ仮画面
- ポイントガチャ仮画面

### 管理者側

- 管理者ダッシュボード
- ポイント調整画面
- ユーザー一覧画面
- 学習ログ承認画面
- ご褒美管理画面
- 交換申請承認画面
- ゲーム設定画面

## DB テーブル

- `users`
- `point_balances`
- `point_histories`
- `study_logs`
- `rewards`
- `reward_requests`
- `game_settings`
- `slot_results`
- `gacha_settings`
- `characters`
- `user_characters`

## 補足

- ゲームポイントと交換ポイントは分離しています。
- 直接の変換機能は初期実装では入れていません。
- 後からゲーム、ガチャ、交換ポイント施策を追加しやすい構成にしています。
