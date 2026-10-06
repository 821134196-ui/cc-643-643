<?php

namespace Tests\Feature;

use App\Models\GatePass;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GatePassFlowTest extends TestCase
{
    use RefreshDatabase;

    private function png(string $name, int $r, int $g, int $b): File
    {
        $im = imagecreatetruecolor(80, 60);
        imagefilledrectangle($im, 0, 0, 80, 60, imagecolorallocate($im, $r, $g, $b));
        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        imagedestroy($im);

        $tmp = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($tmp, $data);

        return new File($name, fopen($tmp, 'r+'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * 关键拦截 1：复洗后仍不合格，禁止放行；历史不合格记录与照片全程保留。
     */
    public function test_release_blocked_while_latest_round_unqualified(): void
    {
        $clerk = User::factory()->create(['role' => User::ROLE_CLERK]);
        $pass = GatePass::create([
            'plate_number' => '京A11111',
            'recognition_status' => 'success',
            'recognized_text' => '京A11111',
            'recognition_confidence' => 99.0,
            'status' => GatePass::STATUS_PENDING,
            'created_by' => $clerk->id,
        ]);

        // 第 1 轮：不合格
        $this->actingAs($clerk)->postJson("/api/gate-passes/{$pass->id}/inspections", [
            'result' => Inspection::RESULT_UNQUALIFIED,
            'finding' => '后轮带泥',
            'wheel_photo' => $this->png('w1.png', 10, 20, 30),
            'body_photo' => $this->png('b1.png', 40, 50, 60),
        ])->assertCreated();

        $this->postJson("/api/gate-passes/{$pass->id}/release", ['type' => 'normal'])
            ->assertStatus(422)
            ->assertJson(['error_code' => 'LATEST_ROUND_NOT_QUALIFIED']);

        // 第 2 轮（复洗后复查）：仍然不合格
        $this->postJson("/api/gate-passes/{$pass->id}/inspections", [
            'result' => Inspection::RESULT_UNQUALIFIED,
            'finding' => '侧面泥污未洗净',
            'wheel_photo' => $this->png('w2.png', 70, 80, 90),
            'body_photo' => $this->png('b2.png', 100, 110, 120),
        ])->assertCreated()->assertJsonPath('data.round', 2);

        $this->postJson("/api/gate-passes/{$pass->id}/release", ['type' => 'normal'])
            ->assertStatus(422)
            ->assertJson(['error_code' => 'LATEST_ROUND_NOT_QUALIFIED']);

        // 闸机记录不得产生，出场单仍是检查中
        $this->assertDatabaseCount('gate_releases', 0);
        $this->assertSame(GatePass::STATUS_PENDING, $pass->fresh()->status);

        // 初次不合格照片与复洗记录都保留
        $this->assertDatabaseCount('inspections', 2);
        $this->assertDatabaseCount('inspection_photos', 4);
    }

    /**
     * 关键拦截 2：任何历史轮次/历史出场单的照片都不能冒充当次检查依据。
     */
    public function test_reusing_old_photos_is_rejected(): void
    {
        $clerk = User::factory()->create(['role' => User::ROLE_CLERK]);

        $pass1 = GatePass::create([
            'plate_number' => '京A22222', 'recognition_status' => 'success',
            'recognized_text' => '京A22222', 'status' => GatePass::STATUS_PENDING,
            'created_by' => $clerk->id,
        ]);

        $wheel1 = $this->png('wheel.png', 1, 2, 3);
        $body1 = $this->png('body.png', 4, 5, 6);

        $this->actingAs($clerk)->postJson("/api/gate-passes/{$pass1->id}/inspections", [
            'result' => Inspection::RESULT_QUALIFIED,
            'wheel_photo' => $wheel1,
            'body_photo' => $body1,
        ])->assertCreated();

        // 同一车轮照片在同一出场单新一轮中重复使用 → 拒绝
        $this->postJson("/api/gate-passes/{$pass1->id}/inspections", [
            'result' => Inspection::RESULT_QUALIFIED,
            'wheel_photo' => $this->png('wheel.png', 1, 2, 3), // 字节与第一次完全相同
            'body_photo' => $this->png('body2.png', 9, 9, 9),
        ])->assertStatus(422)->assertJson(['error_code' => 'PHOTO_ALREADY_USED']);

        // 同一车辆再次出场（新出场单），引用上一次出场照片 → 拒绝
        $pass2 = GatePass::create([
            'plate_number' => '京A22222', 'recognition_status' => 'success',
            'recognized_text' => '京A22222', 'status' => GatePass::STATUS_PENDING,
            'created_by' => $clerk->id,
        ]);

        $this->postJson("/api/gate-passes/{$pass2->id}/inspections", [
            'result' => Inspection::RESULT_QUALIFIED,
            'wheel_photo' => $this->png('wheel.png', 1, 2, 3),
            'body_photo' => $this->png('body-other.png', 7, 8, 9),
        ])->assertStatus(422)->assertJson(['error_code' => 'PHOTO_ALREADY_USED']);

        // 被拒的检查轮次不得落库
        $this->assertDatabaseCount('inspections', 1);
        $this->assertDatabaseCount('inspection_photos', 2);
    }

    /**
     * 关键拦截 3：普通录入人员不能人工放行；值班员也必须填写原因。
     */
    public function test_manual_release_requires_supervisor_and_reason(): void
    {
        $clerk = User::factory()->create(['role' => User::ROLE_CLERK]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $pass = GatePass::create([
            'plate_number' => '京A33333', 'recognition_status' => 'success',
            'recognized_text' => '京A33333', 'status' => GatePass::STATUS_PENDING,
            'created_by' => $clerk->id,
        ]);

        $this->actingAs($clerk)->postJson("/api/gate-passes/{$pass->id}/inspections", [
            'result' => Inspection::RESULT_UNQUALIFIED,
            'wheel_photo' => $this->png('w.png', 1, 1, 1),
            'body_photo' => $this->png('b.png', 2, 2, 2),
        ])->assertCreated();

        // 普通录入人员强行调人工放行 → 403，闸机无指令
        $this->postJson("/api/gate-passes/{$pass->id}/release", [
            'type' => 'manual', 'reason' => '司机着急',
        ])->assertStatus(403)->assertJson(['error_code' => 'FORBIDDEN']);

        // 值班员未填原因 → 422
        $this->actingAs($supervisor)->postJson("/api/gate-passes/{$pass->id}/release", [
            'type' => 'manual', 'reason' => '   ',
        ])->assertStatus(422)->assertJson(['error_code' => 'MANUAL_REASON_REQUIRED']);

        $this->assertDatabaseCount('gate_releases', 0);
        $this->assertSame(GatePass::STATUS_PENDING, $pass->fresh()->status);

        // 值班员 + 原因 → 人工放行成功并记录原因
        $this->postJson("/api/gate-passes/{$pass->id}/release", [
            'type' => 'manual', 'reason' => '应急物资车辆，经现场负责人同意',
        ])->assertStatus(200)->assertJsonPath('data.release_type', 'manual');

        $this->assertSame(GatePass::STATUS_RELEASED, $pass->fresh()->status);
    }

    public function test_failed_recognition_keeps_raw_result_and_allows_manual_registration(): void
    {
        $clerk = User::factory()->create(['role' => User::ROLE_CLERK]);

        $response = $this->actingAs($clerk)->post('/api/gate-passes', [
            'simulate_failure' => '1',
            'manual_plate' => '京A99999',
        ])->assertCreated();

        $id = $response->json('data.id');
        $this->assertNull($response->json('data.plate_number'));
        $this->assertSame('failed', $response->json('data.recognition.status'));
        $this->assertSame('E_PLATE_NOT_FOUND', $response->json('data.recognition.raw.return_code'));
        $this->assertSame('京A99999', $response->json('data.manual_plate_number'));

        // 原识别结果不被覆盖：失败状态与原始返回码仍在
        $pass = GatePass::find($id);
        $this->assertSame('failed', $pass->recognition_status);
        $this->assertSame('E_PLATE_NOT_FOUND', $pass->recognition_raw['return_code']);
    }
}
