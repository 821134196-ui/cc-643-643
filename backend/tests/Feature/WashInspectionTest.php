<?php

namespace Tests\Feature;

use App\Models\ExitOrder;
use App\Models\InspectionPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WashInspectionTest extends TestCase
{
    use RefreshDatabase;

    private User $recorder;
    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = User::create([
            'name' => '录入员', 'email' => 'rec@test.local',
            'role' => User::ROLE_RECORDER, 'api_token' => 'rec-token', 'password' => 'x',
        ]);
        $this->supervisor = User::create([
            'name' => '值班员', 'email' => 'sup@test.local',
            'role' => User::ROLE_SUPERVISOR, 'api_token' => 'sup-token', 'password' => 'x',
        ]);
    }

    private function as(User $user): self
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$user->api_token]);
    }

    private function createOrder(array $overrides = []): array
    {
        return $this->as($this->recorder)->postJson('/api/orders', [
            'plate_number' => '沪A12345',
            'plate_source' => 'recognized',
            ...$overrides,
        ])->assertCreated()->json();
    }

    private function uploadPhoto(int $orderId, string $type): array
    {
        return $this->as($this->recorder)->postJson("/api/orders/{$orderId}/photos", [
            'type' => $type,
            'photo' => UploadedFile::fake()->image("{$type}.png"),
        ])->assertCreated()->json();
    }

    private function addRound(int $orderId, string $result, array $photoIds)
    {
        return $this->as($this->recorder)->postJson("/api/orders/{$orderId}/rounds", [
            'result' => $result,
            'photo_ids' => $photoIds,
        ]);
    }

    private function freshPhotoIds(int $orderId): array
    {
        return [
            $this->uploadPhoto($orderId, 'wheel')['id'],
            $this->uploadPhoto($orderId, 'body')['id'],
        ];
    }

    public function test_rewash_still_failing_blocks_release_until_last_round_passes(): void
    {
        $order = $this->createOrder();
        $id = $order['id'];

        // 无任何检查时禁止放行
        $this->as($this->recorder)->postJson("/api/orders/{$id}/release")
            ->assertStatus(422);

        // 第 1 轮不合格 → 放行被拒
        $this->addRound($id, 'fail', $this->freshPhotoIds($id))->assertCreated();
        $this->as($this->recorder)->postJson("/api/orders/{$id}/release")
            ->assertStatus(422)
            ->assertJsonPath('message', '最后一轮检查不合格(第 1 轮),请返回洗轮区复洗后重新检查');

        // 复洗后第 2 轮仍不合格 → 放行仍被拒
        $this->addRound($id, 'fail', $this->freshPhotoIds($id))->assertCreated();
        $this->as($this->recorder)->postJson("/api/orders/{$id}/release")
            ->assertStatus(422)
            ->assertJsonPath('message', '最后一轮检查不合格(第 2 轮),请返回洗轮区复洗后重新检查');

        // 再次复洗,第 3 轮合格 → 放行成功并下发闸机指令
        $this->addRound($id, 'pass', $this->freshPhotoIds($id))->assertCreated();
        $this->as($this->recorder)->postJson("/api/orders/{$id}/release")
            ->assertOk()
            ->assertJsonPath('order.status', ExitOrder::STATUS_RELEASED)
            ->assertJsonPath('command.type', 'auto');

        // 已放行后不能重复放行、不能再上传照片或追加检查
        $this->as($this->recorder)->postJson("/api/orders/{$id}/release")->assertStatus(422);
        $this->as($this->recorder)->postJson("/api/orders/{$id}/photos", [
            'type' => 'wheel',
            'photo' => UploadedFile::fake()->image('wheel.png'),
        ])->assertStatus(422);
        $this->addRound($id, 'pass', [1, 2])->assertStatus(422);

        $this->assertDatabaseHas('gate_commands', ['exit_order_id' => $id, 'type' => 'auto']);
    }

    public function test_only_the_latest_round_counts_for_release(): void
    {
        $order = $this->createOrder();
        $id = $order['id'];

        // 第 1 轮合格,但第 2 轮(最新)不合格 → 必须按最新一轮判定,禁止放行
        $this->addRound($id, 'pass', $this->freshPhotoIds($id))->assertCreated();
        $this->addRound($id, 'fail', $this->freshPhotoIds($id))->assertCreated();

        $this->as($this->recorder)->postJson("/api/orders/{$id}/release")->assertStatus(422);
    }

    public function test_cannot_reference_photos_from_a_previous_exit_order(): void
    {
        // 同一辆车第一次出场的单
        $first = $this->createOrder();
        $oldPhotoIds = $this->freshPhotoIds($first['id']);

        // 同一辆车再次出场,生成新单
        $second = $this->createOrder();

        // 试图用上一次出场的旧照片登记本次检查 → 拒绝
        $this->addRound($second['id'], 'pass', $oldPhotoIds)
            ->assertStatus(422)
            ->assertJsonPath('message', '不能使用其他出场单的照片,必须现场重新拍照上传');

        // 旧照片即使已在旧单中用过,新单同样不能用
        $this->addRound($first['id'], 'pass', $oldPhotoIds)->assertCreated();
        $this->addRound($second['id'], 'pass', $oldPhotoIds)->assertStatus(422);
    }

    public function test_cannot_reuse_photos_across_rounds_within_same_order(): void
    {
        $order = $this->createOrder();
        $id = $order['id'];
        $photoIds = $this->freshPhotoIds($id);

        $this->addRound($id, 'fail', $photoIds)->assertCreated();

        // 复洗后追加第 2 轮时试图重复使用第 1 轮的照片 → 拒绝
        $this->addRound($id, 'pass', $photoIds)
            ->assertStatus(422)
            ->assertJsonPath('message', '照片已被此前的检查轮次使用,请重新拍照上传');
    }

    public function test_round_requires_both_wheel_and_body_photos(): void
    {
        $order = $this->createOrder();
        $id = $order['id'];

        $wheelOnly = [$this->uploadPhoto($id, 'wheel')['id'], $this->uploadPhoto($id, 'wheel')['id']];
        $this->addRound($id, 'pass', $wheelOnly)
            ->assertStatus(422)
            ->assertJsonPath('message', '每轮检查必须包含车轮和车身照片');
    }

    public function test_manual_release_requires_supervisor_role_and_reason(): void
    {
        $order = $this->createOrder();
        $id = $order['id'];
        $this->addRound($id, 'fail', $this->freshPhotoIds($id))->assertCreated();

        // 普通录入人员不能人工放行
        $this->as($this->recorder)->postJson("/api/orders/{$id}/manual-release", ['reason' => '领导特批'])
            ->assertStatus(403);

        // 值班员也必须填写原因
        $this->as($this->supervisor)->postJson("/api/orders/{$id}/manual-release", [])
            ->assertStatus(422);

        // 值班员 + 原因 → 放行成功,指令类型为 manual 且记录原因
        $this->as($this->supervisor)->postJson("/api/orders/{$id}/manual-release", ['reason' => '设备故障,现场确认已洗净'])
            ->assertOk()
            ->assertJsonPath('order.status', ExitOrder::STATUS_RELEASED_MANUAL)
            ->assertJsonPath('command.type', 'manual')
            ->assertJsonPath('command.reason', '设备故障,现场确认已洗净');

        $this->assertDatabaseHas('gate_commands', [
            'exit_order_id' => $id,
            'type' => 'manual',
            'issued_by' => $this->supervisor->id,
        ]);
    }

    public function test_manual_plate_entry_keeps_original_recognition_result(): void
    {
        // 识别失败(设备返回残缺结果)后人工登记,原识别结果必须保存
        $order = $this->createOrder([
            'plate_number' => '沪A12345',
            'plate_source' => 'manual',
            'plate_recognized' => '沪?1??45',
        ]);

        $this->assertDatabaseHas('exit_orders', [
            'id' => $order['id'],
            'plate_number' => '沪A12345',
            'plate_source' => 'manual',
            'plate_recognized' => '沪?1??45',
        ]);

        // 人工登记时缺省原识别结果字段 → 校验失败
        $this->as($this->recorder)->postJson('/api/orders', [
            'plate_number' => '沪A12345',
            'plate_source' => 'manual',
        ])->assertStatus(422);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
    }
}
