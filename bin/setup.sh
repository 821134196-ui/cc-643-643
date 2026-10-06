#!/usr/bin/env bash
# 首次环境准备：安装 PHP/Node 依赖、初始化 SQLite 数据库。
# 用法：bin/setup.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -x "$ROOT/.tools/php" ]]; then
  PHP="$ROOT/.tools/php"
  COMPOSER="$ROOT/.tools/composer"
else
  PHP="$(command -v php)"
  COMPOSER="$(command -v composer)"
fi
echo "PHP: $($PHP -v | head -1)"

command -v npm >/dev/null || { echo "未找到 npm，请先安装 Node.js 18+"; exit 1; }
command -v node >/dev/null || { echo "未找到 node"; exit 1; }

if [[ ! -d vendor ]]; then
  echo "安装 PHP 依赖…"
  "$COMPOSER" install --no-interaction --prefer-dist
fi

if [[ ! -d frontend/node_modules ]]; then
  echo "安装前端依赖…"
  ( cd frontend && npm install --no-audit --no-fund )
fi

[[ -f .env ]] || cp .env.example .env
# 默认使用 SQLite
sed -i.bak 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env 2>/dev/null || true
sed -i.bak 's/^DB_DATABASE=.*/# DB_DATABASE=/' .env 2>/dev/null || true
rm -f .env.bak

: > database/database.sqlite
if ! grep -q '^APP_KEY=base64:' .env; then
  "$PHP" artisan key:generate --force
fi
"$PHP" artisan migrate:fresh --force
"$PHP" artisan db:seed --force
[[ -e public/storage ]] || "$PHP" artisan storage:link

echo
echo "初始化完成。执行 bin/dev.sh 一键启动前后端。"
