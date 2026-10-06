#!/usr/bin/env bash
# 本地一键启动：Laravel API(8000) + React/Vite 前端(5173)
# 用法：bin/dev.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# 选择 PHP：优先项目自带静态 PHP（无系统 PHP 的环境），其次系统 php
if [[ -x "$ROOT/.tools/php" ]]; then
  PHP="$ROOT/.tools/php"
else
  PHP="$(command -v php)"
fi
echo "使用 PHP：$($PHP -r 'echo PHP_VERSION;')"

# 依赖自检
if [[ ! -d vendor ]]; then
  echo "缺少 vendor，先执行 bin/setup.sh 安装后端依赖" >&2
  exit 1
fi
if [[ ! -d frontend/node_modules ]]; then
  echo "缺少 frontend/node_modules，先执行 bin/setup.sh 安装前端依赖" >&2
  exit 1
fi

# 数据库与存储链接准备（幂等）
[[ -f database/database.sqlite ]] || : > database/database.sqlite
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --force
[[ -e public/storage ]] || "$PHP" artisan storage:link

cleanup() {
  echo
  echo "正在停止前后端…"
  [[ -n "${BACK_PID:-}" ]] && kill "$BACK_PID" 2>/dev/null || true
  [[ -n "${FRONT_PID:-}" ]] && kill "$FRONT_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo "启动后端 API：http://127.0.0.1:8000"
"$PHP" artisan serve --host=127.0.0.1 --port=8000 &
BACK_PID=$!

echo "启动前端页面：http://127.0.0.1:5173"
( cd frontend && npm run dev ) &
FRONT_PID=$!

echo
echo "================================================================"
echo " 门岗终端： http://127.0.0.1:5173"
echo " 演示账号： clerk@site.local      （普通检查人员）"
echo "           supervisor@site.local （值班员，可人工放行）"
echo " 密码均为： password"
echo " 按 Ctrl+C 停止全部服务"
echo "================================================================"

wait -n "$BACK_PID" "$FRONT_PID"
