# 工地出场车辆洗轮与泥污复查系统

门岗核对出场车辆是否洗净:每张出场单可记录多轮检查(初检 + 复洗后复检),
**只有本次出场最后一轮检查合格**才允许发送闸机放行指令;初次不合格照片与复洗记录全部留痕。

## 一条命令启动

```bash
./start.sh
```

- 前端(React + Vite): http://localhost:5173
- 后端(Laravel + SQLite): http://127.0.0.1:8000
- 首次启动会自动安装依赖、迁移并写入演示账号

> 环境内无系统 PHP,已使用静态 PHP 8.3(`/workspace/.tools/php`)+ `composer.phar`。
> 若机器上已有 PHP 8.2+ 与 composer,把 `start.sh` 里的 `PHP`/`COMPOSER` 改成系统命令即可。

演示账号(登录页点击即可):

| 账号 | 角色 | 权限 |
|---|---|---|
| recorder@demo.local | 普通录入 | 开单、上传照片、登记检查、合格放行 |
| supervisor@demo.local | 值班员 | 以上全部 + 人工放行(需填原因) |

## 运行测试

```bash
cd backend && /workspace/.tools/php artisan test
```

## 关键业务规则(重点防护)

1. **复洗仍不合格不能出场** —— 放行只认本单 `round_no` 最大的一轮;
   不合格时返回 422 并提示返回洗轮区。复洗后在原单追加新一轮(round_no 递增),
   历史轮次与照片全部保留。见 `ReleaseController::release`、`RoundController::store`。
2. **不能用以前出场的照片代替** —— 照片先上传到本单,再绑定到某一轮;
   绑定校验:照片必须属于本出场单(拦截引用旧单照片),且未被任何轮次使用过
   (拦截复洗时重复使用初检照片)。每轮必须同时包含车轮与车身照片。
3. **无权限不能人工放行** —— 人工放行路由挂 `supervisor` 中间件,
   普通录入人员得到 403;值班员也必须填写原因,指令以 `type=manual` 连同
   原因、操作人一起写入 `gate_commands` 留痕。

车牌识别(`PlateRecognizer`)与闸机(`GateService`)均为本地模拟实现,
真实环境替换为对应硬件接口即可;照片存于 `backend/storage/app/public/photos/`。

## API 概览(均需 `Authorization: Bearer <token>`,登录除外)

| 方法 | 路径 | 说明 |
|---|---|---|
| POST | /api/login | 登录(演示:按邮箱选择账号) |
| POST | /api/plate/recognize | 车牌识别(模拟,`?fail=1` 强制失败) |
| GET/POST | /api/orders | 出场单列表 / 开单(人工登记须带 `plate_recognized` 原识别结果) |
| GET | /api/orders/{id} | 单详情:按顺序的轮次、照片、结论、闸机指令 |
| POST | /api/orders/{id}/photos | 上传车轮/车身照片(multipart) |
| POST | /api/orders/{id}/rounds | 追加一轮检查(绑定本单新照片) |
| POST | /api/orders/{id}/release | 正常放行(仅最后一轮合格可用) |
| POST | /api/orders/{id}/manual-release | 人工放行(仅值班员,原因必填) |

## 目录结构

```
backend/    Laravel 13 + SQLite(Eloquent 模型见 app/Models)
frontend/   React + Vite(页面:登录 / 出场单列表 / 单详情)
start.sh    一键启动前后端
```
