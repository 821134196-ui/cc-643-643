# 工地出场车辆洗轮与泥污复查系统（本地版）

门岗核对出场车辆是否洗净的业务系统。同一辆车每次出场都生成**独立出场单**，
每次检查都必须在当次出场单上留下**当轮拍摄的车轮、车身照片与结论**；
不合格退回洗轮区，复洗后在**原单上增加一轮检查**。只有**本次最后一轮检查合格**
才能向闸机发送放行指令。

- 后端：PHP 8.3 + Laravel 12（Eloquent / Sanctum token 认证）
- 前端：React 18 + Vite + React Router（纯手写，无 UI 库）
- 数据库：SQLite（`database/database.sqlite`）
- 车牌识别、闸机接口：本地模拟服务（`App\Services\LprService`、`App\Services\GateService`）
- 照片：保存在本地 `storage/app/public/inspection-photos/`，以文件 sha256 指纹防复用

## 一键启动

```bash
bin/setup.sh   # 首次：安装依赖、初始化 SQLite、写入演示账号
bin/dev.sh     # 启动后端 http://127.0.0.1:8000 与前端 http://127.0.0.1:5173
```

打开 http://127.0.0.1:5173 ，演示账号（密码均为 `password`）：

| 账号 | 角色 | 权限 |
| --- | --- | --- |
| `clerk@site.local` | 普通检查人员（李录入） | 建单、上传各轮检查照片；**不能人工放行** |
| `supervisor@site.local` | 值班员（王值班） | 全部录入权限 + **人工放行（必须填写原因）** |

> 无系统 PHP 时，`bin/setup.sh`/`bin/dev.sh` 会自动使用仓库 `.tools/php` 静态二进制
> （需自备，见文末“无 root 环境工具链”）。

## 业务流程与关键规则

1. **登记出场单**：上传相机抓拍照 → 本地模拟车牌识别。识别结果（成功/失败、置信度、
   原始返回 JSON、请求号）原样落库；识别失败时可人工登记车牌，**原始识别结果不被覆盖**。
   可勾选“模拟识别失败”联调。
2. **第 1 轮检查（初次）**：必须同时上传当次车轮、车身照片，选择合格/不合格。
3. **不合格 → 退回复洗**：车辆复洗后在**同一张出场单**上“增加一轮检查”（第 2、3…轮），
   初次不合格照片与所有复洗记录全部保留，门岗页面按轮次顺序展示。
4. **放行**：
   - 正常放行：**仅当最后一轮检查合格**时按钮可用；复洗仍不合格一律拒绝（HTTP 422）。
   - 人工放行：仅值班员账号可见入口，且必须填写原因；普通人员直接调 API 也会被拒绝（403）。
   - 放行调用模拟闸机接口，记录指令号与应答，出场单锁定，不能再补检查或重复放行。
5. **禁止旧照片顶替**：服务端对每张上传照片计算 sha256 并做**全局唯一约束**
   （`inspection_photos.file_hash` UNIQUE + 提交前查询双重校验）。
   无论是同一单的历史轮次，还是同一辆车以前的出场单，字节相同的照片一律拒绝提交。

## 主要 API（均在 `/api` 前缀下，除登录外需 `Authorization: Bearer <token>`）

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| POST | `/auth/login` | 登录获取 token |
| POST | `/gate-passes` | multipart：`lpr_image`、`simulate_failure`、`manual_plate` 等，建单并识别 |
| GET | `/gate-passes` / `/gate-passes/{id}` | 列表（带末轮摘要）/ 详情（含全部轮次、照片、放行记录） |
| POST | `/gate-passes/{id}/manual-plate` | 识别失败后人工登记车牌（原结果保留） |
| POST | `/gate-passes/{id}/inspections` | multipart：`result`、`wheel_photo`、`body_photo`、`finding`，新增一轮检查 |
| POST | `/gate-passes/{id}/release` | `{"type":"normal"}` 或 `{"type":"manual","reason":"…"}` |

业务错误统一返回 `422 {"message", "error_code"}`，权限错误返回 `403`。
错误码：`LATEST_ROUND_NOT_QUALIFIED`、`PHOTO_ALREADY_USED`、`PHOTO_DUPLICATE_IN_ROUND`、
`FORBIDDEN`、`MANUAL_REASON_REQUIRED`、`PASS_ALREADY_RELEASED`、`NO_INSPECTION` 等。

## 自动测试

```bash
.tools/php artisan test          # PHPUnit Feature 测试（4 个用例 / 37 断言）
node tests/smoke-api.cjs         # 端到端 HTTP 冒烟（需先 bin/dev.sh 起后端，16 项检查）
```

重点覆盖的三类拦截：

- **复洗仍不合格**：连续两轮不合格时正常放行返回 422，闸机无任何放行记录，照片全保留；
- **引用旧照片**：同单旧轮次、跨出场单复用照片均返回 422 `PHOTO_ALREADY_USED`，轮次不落库；
- **无权限人工放行**：普通人员返回 403，值班员不填原因返回 422，均不产生闸机指令。

另含：识别失败保留原始返回 + 人工登记、合格放行成功、重复放行拒绝等。

## 目录结构（业务部分）

```
app/
  Models/            GatePass / Inspection / InspectionPhoto / GateRelease / User
  Services/
    LprService.php   车牌识别本地模拟（按图片哈希派生稳定车牌；可模拟失败）
    GateService.php  闸机接口本地模拟（返回指令号与应答 JSON）
    GatePassService.php  全部业务强校验集中在此（轮次、照片指纹、放行权限）
  Http/Controllers/Api/  AuthController、GatePassController
  Http/Requests/     建单/检查/放行的表单校验（每轮强制必传两类照片）
  Http/Resources/    出场单按轮次序列化输出
  Exceptions/GatePassRuleException.php  业务规则异常 → 422 + 错误码
database/migrations/ gate_passes、inspections、inspection_photos、gate_releases 等
frontend/src/pages/  LoginPage / PassListPage / PassDetailPage
tests/Feature/GatePassFlowTest.php
tests/smoke-api.cjs
```

## 无 root 环境工具链

仓库 `.tools/`（已 gitignore）可放用户态工具，脚本会自动识别：

- `.tools/php`：静态 PHP 8.3 CLI（含 pdo_sqlite、gd、mbstring 等）
- `.tools/composer.phar` + `.tools/composer`（包装脚本）

若环境网络对静态 PHP 的 TLS 不兼容，`.tools/proxy/` 内附带了一个 Node 本地
MITM 代理脚本（仅供本机开发安装依赖使用）。
