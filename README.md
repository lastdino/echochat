# EchoChat Package

EchoChatは、LaravelアプリケーションにSlackのようなワークスペースおよびチャンネルベースのリアルタイム・チャット機能を追加するためのLaravelパッケージです。

## 特徴

- **ワークスペース**: ユーザーが所属できる分離された環境を構築。
- **チャンネル**: ワークスペース内のパブリック/プライベートな会話スペース。
- **リアルタイムメッセージング**: Laravel Reverb/Pusherを通じたリアルタイム通信。
- **リアクション**: 絵文字によるメッセージへのリアクション機能。
- **添付ファイル**: メッセージへのファイル添付機能（Spatie MediaLibrary連携）。
- **Livewire Volt連携**: モダンな単一ファイルコンポーネントによるUI提供。

## 依存関係

このパッケージは以下の主要なライブラリに依存しています：

- **Laravel Framework (v12.0+)**: コアフレームワーク。
- **Livewire & Volt**: インタラクティブなUIコンポーネント。
- **Spatie Laravel MediaLibrary**: 添付ファイルの管理。
- **Laravel Reverb/Pusher**: リアルタイムイベント放送用（ホスト側で設定が必要）。

## インストール方法

### 1. パッケージの追加

現在開発中のため、ローカルのリポジトリとして `composer.json` に追加するか、直接 `packages` ディレクトリに配置してオートロードを設定します。

`composer.json` の `autoload` セクションに以下を追加します：

```json
{
    "autoload": {
        "psr-4": {
            "EchoChat\\": "packages/lastdino/echochat/src/",
            "EchoChat\\Database\\Seeders\\": "packages/lastdino/echochat/database/seeders/"
        }
    }
}
```

設定後、以下のコマンドを実行します：
```bash
composer dump-autoload
```

### 2. モデルの設定

`User` モデルに `HasWorkspaces` インターフェースと `InteractsWithWorkspaces` トレイトを実装し、さらに `HasMedia` (MediaLibrary) を設定します。

```php
use EchoChat\Contracts\HasWorkspaces;
use EchoChat\Traits\InteractsWithWorkspaces;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia, HasWorkspaces
{
    use InteractsWithMedia, InteractsWithWorkspaces;
    // ...
}
```

### 3. マイグレーションの実行

パッケージのテーブルをデータベースに作成します：
```bash
php artisan migrate
```

### 4. 設定とアセットの公開（任意）

設定ファイル、ビュー、カスタムアイコンなどをカスタマイズしたい場合に公開します：
```bash
# 設定ファイル
php artisan vendor:publish --tag=echochat-config
# ビューファイル
php artisan vendor:publish --tag=echochat-views
# カスタムアイコン（必須：リアクション追加ボタン等に使用）
php artisan vendor:publish --tag=echochat-flux-icons
```

## 設定方法

### ブロードキャストの設定

パッケージ内の `routes/channels.php` は、`EchoChatServiceProvider` によって自動的に読み込まれます。
ただし、Laravel 11 以降では、アプリケーション側でブロードキャストを有効にするために以下の準備が必要です。

1. **ブロードキャストのインストール**（未実施の場合）：
   ```bash
   php artisan install:broadcasting
   ```

2. **環境変数の設定**:
   `.env` ファイルでブロードキャストドライバー（例: `reverb` や `pusher`）を適切に設定してください。

3. **認証ユーザーの型定義**:
   パッケージのチャンネル認証では `auth()->check()` を使用しています。ユーザーがログインしていることを確認してください。

### アセット（CSS）の読み込み

プロジェクトに Tailwind がすでにインストールされている場合は、`resources/css/app.css` ファイルに次の構成を追加するだけです。

```css
@import '../../vendor/lastdino/echochat/dist/echochat.css';
```

### ルーティング

パッケージはデフォルトで以下のルートを提供します（`config/echochat.php` の `path` 設定で変更可能）：
- `/echochat/workspaces`: ワークスペース一覧
- `/echochat/{workspace_slug}/{channel_id?}`: チャット画面

設定ファイル `config/echochat.php` で以下の設定が可能です：
- `table_prefix`: データベーステーブルの接頭辞（デフォルト: `echochat_`）
- `path`: EchoChat のルートパス（デフォルト: `echochat`）

## 開発とテスト

### テストの実行

```bash
php artisan test packages/lastdino/echochat
```

## ライセンス

MIT License.
