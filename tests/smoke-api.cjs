// 后端关键规则冒烟测试：复洗仍不合格 / 引用旧照片 / 无权限人工放行
const fs = require('fs');
const API = 'http://127.0.0.1:8000/api';
const img = n => `/tmp/imgs/p${n}.png`;
const buf = n => fs.readFileSync(img(n));
const PNG = 'image/png';

let pass = 0, fail = 0;
function check(name, cond, extra = '') {
  if (cond) { pass++; console.log(`  ✓ ${name}`); }
  else { fail++; console.log(`  ✗ ${name} ${extra}`); }
}

async function login(email) {
  const r = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password: 'password' }),
  });
  return (await r.json()).token;
}

function auth(token) {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

async function createPass(token, imageNo) {
  const fd = new FormData();
  fd.append('lpr_image', new Blob([buf(imageNo)], { type: PNG }), `lpr${imageNo}.png`);
  fd.append('driver_name', '张师傅');
  const r = await fetch(`${API}/gate-passes`, { method: 'POST', headers: auth(token), body: fd });
  const j = await r.json();
  return { status: r.status, json: j, id: j.data?.id };
}

async function inspect(token, id, result, wheelNo, bodyNo, finding) {
  const fd = new FormData();
  fd.append('result', result);
  if (finding) fd.append('finding', finding);
  fd.append('wheel_photo', new Blob([buf(wheelNo)], { type: PNG }), `w${wheelNo}.png`);
  fd.append('body_photo', new Blob([buf(bodyNo)], { type: PNG }), `b${bodyNo}.png`);
  const r = await fetch(`${API}/gate-passes/${id}/inspections`, { method: 'POST', headers: auth(token), body: fd });
  return { status: r.status, json: await r.json() };
}

async function release(token, id, type, reason) {
  const r = await fetch(`${API}/gate-passes/${id}/release`, {
    method: 'POST',
    headers: { ...auth(token), 'Content-Type': 'application/json' },
    body: JSON.stringify({ type, reason }),
  });
  return { status: r.status, json: await r.json() };
}

(async () => {
  const clerk = await login('clerk@site.local');
  const supervisor = await login('supervisor@site.local');
  console.log('登录：', clerk ? 'clerk OK' : 'clerk FAIL', supervisor ? 'supervisor OK' : 'supervisor FAIL');

  console.log('\n== 场景 A：初次不合格 -> 复洗仍不合格 -> 放行必须被阻止 ==');
  const a = await createPass(clerk, 1);
  check('建单成功', a.status === 201, `status=${a.status}`);

  const a1 = await inspect(clerk, a.id, 'unqualified', 2, 3, '后轮带泥');
  check('第1轮不合格记录成功', a1.status === 201 && a1.json.data.round === 1);

  const ar1 = await release(clerk, a.id, 'normal');
  check('第1轮不合格时正常放行被拒(422)', ar1.status === 422 && ar1.json.error_code === 'LATEST_ROUND_NOT_QUALIFIED',
    `status=${ar1.status} code=${ar1.json.error_code}`);

  const a2 = await inspect(clerk, a.id, 'unqualified', 4, 5, '侧面仍有泥污');
  check('原单上新增第2轮复洗检查', a2.status === 201 && a2.json.data.round === 2);

  const ar2 = await release(clerk, a.id, 'normal');
  check('复洗仍不合格时正常放行被拒(422)', ar2.status === 422 && ar2.json.error_code === 'LATEST_ROUND_NOT_QUALIFIED',
    `status=${ar2.status} code=${ar2.json.error_code}`);

  const detail = await (await fetch(`${API}/gate-passes/${a.id}`, { headers: auth(clerk) })).json();
  check('初次不合格照片仍保留（2轮×2张）', detail.data.inspections.length === 2 &&
    detail.data.inspections.every(r => r.photos.length === 2));

  console.log('\n== 场景 B：禁止用旧照片充当本次检查 ==');
  const br1 = await inspect(clerk, a.id, 'qualified', 2, 3);
  check('同单内引用第1轮旧照片被拒(422)', br1.status === 422 && br1.json.error_code === 'PHOTO_ALREADY_USED',
    `status=${br1.status} code=${br1.json.error_code}`);

  const bpass = await createPass(clerk, 6);
  const br2 = await inspect(clerk, bpass.id, 'qualified', 2, 7);
  check('新出场单引用历史出场照片被拒(422)', br2.status === 422 && br2.json.error_code === 'PHOTO_ALREADY_USED',
    `status=${br2.status} code=${br2.json.error_code}`);

  console.log('\n== 场景 C：无权限人工放行被阻止 ==');
  const cr1 = await release(clerk, a.id, 'manual', '赶时间');
  check('普通录入人员人工放行被拒(403)', cr1.status === 403 && cr1.json.error_code === 'FORBIDDEN',
    `status=${cr1.status} code=${cr1.json.error_code}`);

  const cr2 = await release(supervisor, a.id, 'manual', '   ');
  check('值班员人工放行但未填原因被拒(422)', cr2.status === 422 && cr2.json.error_code === 'MANUAL_REASON_REQUIRED',
    `status=${cr2.status} code=${cr2.json.error_code}`);

  console.log('\n== 场景 D：当次新照片复洗合格 -> 允许放行 ==');
  const d1 = await inspect(clerk, a.id, 'qualified', 6, 8);
  check('第3轮当次新照片合格', d1.status === 201 && d1.json.data.round === 3);
  const d2 = await release(clerk, a.id, 'normal');
  check('最后一轮合格后放行成功(200)', d2.status === 200 && d2.json.data.release_type === 'normal',
    `status=${d2.status}`);
  const d3 = await release(clerk, a.id, 'normal');
  check('重复放行被拒(422)', d3.status === 422 && d3.json.error_code === 'PASS_ALREADY_RELEASED',
    `status=${d3.status}`);
  const d4 = await inspect(clerk, a.id, 'unqualified', 2, 3);
  check('已放行单不能再增加检查', d4.status === 422, `status=${d4.status}`);

  console.log('\n== 场景 E：车牌识别失败与人工登记 ==');
  const e1 = await (async () => {
    const fd = new FormData();
    fd.append('lpr_image', new Blob([buf(1)], { type: PNG }), 'FAIL-shot.png');
    const r = await fetch(`${API}/gate-passes`, { method: 'POST', headers: auth(clerk), body: fd });
    return { status: r.status, json: await r.json() };
  })();
  check('文件名带 FAIL 触发识别失败并保留原始返回', e1.status === 201 &&
    e1.json.data.recognition.status === 'failed' &&
    e1.json.data.recognition.raw.return_code === 'E_PLATE_NOT_FOUND' &&
    e1.json.data.plate_number === null,
    `status=${e1.status}`);
  const eid = e1.json.data.id;
  const e2r = await fetch(`${API}/gate-passes/${eid}/manual-plate`, {
    method: 'POST', headers: { ...auth(clerk), 'Content-Type': 'application/json' },
    body: JSON.stringify({ manual_plate: '京A12345' }),
  });
  const e2 = await e2r.json();
  check('人工登记车牌成功且原识别结果仍在', e2r.status === 200 &&
    e2.data.manual_plate_number === '京A12345' &&
    e2.data.recognition.status === 'failed' &&
    e2.data.effective_plate === '京A12345', `status=${e2r.status}`);

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
