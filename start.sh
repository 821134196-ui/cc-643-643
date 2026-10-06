#!/usr/bin/env bash
# 一条命令启动前后端:./start.sh
# 后端 http://127.0.0.1:8000 (Laravel + SQLite),前端 http://127.0.0.1:5173 (Vite)
set -e
cd "$(dirname "$0")"

PHP=/workspace/.tools/php
COMPOSER=/workspace/.tools/composer

if [ ! -x "$PHP" ]; then
  echo "未找到本地 PHP ($PHP),请先按 README 安装静态 PHP" >&2
  exit 1
fi

cd backend
[ -d vendor ] || "$PHP" "$COMPOSER" install --no-interaction
[ -f .env ] || cp .env.example .env
[ -f database/database.sqlite ] || touch database/database.sqlite
"$PHP" artisan key:generate --force --quiet
"$PHP" artisan migrate:fresh --seed --force --quiet
[ -L public/storage ] || "$PHP" artisan storage:link --quiet
"$PHP" artisan serve --host=127.0.0.1 --port=8000 &
BACKEND_PID=$!
cd ..

cd frontend
[ -d node_modules ] || npm install
npm run dev &
FRONTEND_PID=$!
cd ..

trap 'kill $BACKEND_PID $FRONTEND_PID 2>/dev/null' EXIT
echo "后端: http://127.0.0.1:8000  前端: http://127.0.0.1:5173 (Ctrl+C 停止)"
wait
