<?php

namespace App\Services;

use App\Exceptions\GatePassRuleException;
use App\Models\GatePass;
use App\Models\GateRelease;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class GatePassService
{
    public function __construct(
        private readonly LprService $lpr,
        private readonly GateService $gate,
    ) {}

    /**
     * 登记一张新的出场单并调用本地车牌识别。
     * 识别结果（成功或失败）原样留存；识别失败时可同时/事后人工登记车牌。
     */
    public function createPass(
        User $operator,
        ?UploadedFile $lprImage,
        bool $simulateRecognitionFailure,
        ?string $manualPlate,
        ?string $driverName,
        ?string $remark,
    ): GatePass {
        $recognition = $this->lpr->recognize(
            $lprImage?->getPathname(),
            $lprImage?->getClientOriginalName(),
            $simulateRecognitionFailure,
        );

        $failed = $recognition['status'] === 'failed';

        if (! $failed && $manualPlate !== null && trim($manualPlate) !== '') {
            // 识别成功时不需要人工登记，避免混淆
            $manualPlate = null;
        }

        return GatePass::create([
            'plate_number' => $failed ? null : $recognition['plate'],
            'recognition_status' => $recognition['status'],
            'recognized_text' => $recognition['plate'],
            'recognition_confidence' => $recognition['confidence'],
            'recognition_raw' => $recognition['raw'],
            'manual_plate_number' => $failed ? $this->normalizePlate($manualPlate) : null,
            'manual_entered_by' => ($failed && $manualPlate) ? $operator->id : null,
            'driver_name' => $driverName,
            'remark' => $remark,
            'status' => GatePass::STATUS_PENDING,
            'created_by' => $operator->id,
        ]);
    }

    /**
     * 识别失败后补录车牌。原始识别结果始终保留，不做任何覆盖。
     */
    public function registerManualPlate(User $operator, GatePass $pass, string $plate): GatePass
    {
        if ($pass->recognition_status !== 'failed') {
            throw new GatePassRuleException('车牌识别成功，无需人工登记', 'MANUAL_PLATE_NOT_ALLOWED');
        }

        if ($pass->status === GatePass::STATUS_RELEASED) {
            throw new GatePassRuleException('出场单已放行，不能再修改车牌', 'PASS_ALREADY_RELEASED');
        }

        $pass->update([
            'manual_plate_number' => $this->normalizePlate($plate),
            'manual_entered_by' => $operator->id,
        ]);

        return $pass->fresh();
    }

    /**
     * 在原出场单上增加一轮检查（初次检查 / 复洗后复查）。
     *
     * 每一轮都必须随请求重新上传当次拍摄的车轮、车身照片：
     * - 系统不提供"引用历史照片"的入口；
     * - 服务端对文件 sha256 做全局唯一校验，任何与历史出场单或历史轮次字节相同的
     *   照片一律拒绝，防止用以前出场的照片顶替本次检查。
     */
    public function addInspection(
        User $inspector,
        GatePass $pass,
        string $result,
        ?string $finding,
        UploadedFile $wheelPhoto,
        UploadedFile $bodyPhoto,
    ): Inspection {
        if ($pass->status === GatePass::STATUS_RELEASED) {
            throw new GatePassRuleException('出场单已放行，不能再增加检查轮次', 'PASS_ALREADY_RELEASED');
        }

        if (! in_array($result, [Inspection::RESULT_QUALIFIED, Inspection::RESULT_UNQUALIFIED], true)) {
            throw new GatePassRuleException('检查结果取值非法', 'INVALID_RESULT');
        }

        $wheelHash = hash_file('sha256', $wheelPhoto->getPathname());
        $bodyHash = hash_file('sha256', $bodyPhoto->getPathname());

        if ($wheelHash === $bodyHash) {
            throw new GatePassRuleException('车轮照片与车身照片不能是同一张图片', 'PHOTO_DUPLICATE_IN_ROUND');
        }

        $used = InspectionPhoto::whereIn('file_hash', [$wheelHash, $bodyHash])
            ->with('inspection:id,gate_pass_id,round')
            ->get();

        if ($used->isNotEmpty()) {
            throw new GatePassRuleException(
                '检测到上传的照片已在历史出场检查中使用，必须使用本次当场拍摄的照片',
                'PHOTO_ALREADY_USED',
            );
        }

        $round = ((int) $pass->inspections()->max('round')) + 1;

        return DB::transaction(function () use ($inspector, $pass, $result, $finding, $wheelPhoto, $bodyPhoto, $wheelHash, $bodyHash, $round) {
            $inspection = $pass->inspections()->create([
                'round' => $round,
                'result' => $result,
                'finding' => $finding,
                'inspected_by' => $inspector->id,
                'inspected_at' => now(),
            ]);

            $this->storePhoto($inspection, InspectionPhoto::VIEW_WHEEL, $wheelPhoto, $wheelHash, $inspector);
            $this->storePhoto($inspection, InspectionPhoto::VIEW_BODY, $bodyPhoto, $bodyHash, $inspector);

            // 只有最新一轮的结论决定出场单状态；以前轮次即使合格也不算数
            $pass->update([
                'status' => $result === Inspection::RESULT_QUALIFIED
                    ? GatePass::STATUS_PASSED
                    : GatePass::STATUS_PENDING,
            ]);

            return $inspection->load('photos', 'inspector:id,name');
        });
    }

    private function storePhoto(Inspection $inspection, string $viewType, UploadedFile $file, string $hash, User $uploader): void
    {
        $directory = sprintf('inspection-photos/%d/round-%d', $inspection->gate_pass_id, $inspection->round);
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $name = sprintf('%s-%s.%s', $viewType, substr($hash, 0, 16), $extension);

        $path = $file->storeAs($directory, $name, 'public');

        // 唯一索引兜底：并发请求同时通过前置哈希查询时，INSERT 会在这里冲突
        try {
            InspectionPhoto::create([
                'inspection_id' => $inspection->id,
                'view_type' => $viewType,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_hash' => $hash,
                'uploaded_by' => $uploader->id,
            ]);
        } catch (QueryException $e) {
            throw new GatePassRuleException(
                '检测到上传的照片已在历史出场检查中使用，必须使用本次当场拍摄的照片',
                'PHOTO_ALREADY_USED',
            );
        }
    }

    /**
     * 下发放行指令。
     *
     * 正常放行：本次出场单最后一轮检查必须合格（复洗后仍不合格直接拒绝）。
     * 人工放行：仅值班员可操作，且必须填写原因；普通录入人员抛出 403。
     */
    public function release(User $operator, GatePass $pass, string $type, ?string $reason): GateRelease
    {
        if ($pass->status === GatePass::STATUS_RELEASED) {
            throw new GatePassRuleException('该出场单已发送过放行指令，请勿重复放行', 'PASS_ALREADY_RELEASED');
        }

        $latest = $pass->inspections()->reorder()->orderByDesc('round')->first();

        if ($type === GateRelease::TYPE_NORMAL) {
            if ($latest === null) {
                throw new GatePassRuleException('出场单尚无任何检查记录，不允许放行', 'NO_INSPECTION');
            }
            if ($latest->result !== Inspection::RESULT_QUALIFIED) {
                throw new GatePassRuleException(
                    "第 {$latest->round} 轮检查（最后一轮）结论为不合格，请退回洗轮区复洗后重新检查",
                    'LATEST_ROUND_NOT_QUALIFIED',
                );
            }
        } elseif ($type === GateRelease::TYPE_MANUAL) {
            // 权限第一优先：普通录入人员拿不到人工放行入口，强行调用同样拒绝
            if (! $operator->isSupervisor()) {
                throw new AccessDeniedHttpException('只有值班员可以执行人工放行');
            }
            if ($latest === null) {
                throw new GatePassRuleException('出场单尚无任何检查记录，不允许人工放行', 'NO_INSPECTION');
            }
            // 最后一轮已合格时必须走正常放行，不允许用人工放行绕开标准流程
            if ($latest->result === Inspection::RESULT_QUALIFIED) {
                throw new GatePassRuleException(
                    '最后一轮检查已合格，请使用正常放行',
                    'MANUAL_RELEASE_NOT_NEEDED',
                );
            }
            if (trim((string) $reason) === '') {
                throw new GatePassRuleException('人工放行必须填写原因', 'MANUAL_REASON_REQUIRED');
            }
        } else {
            throw new GatePassRuleException('放行类型非法', 'INVALID_RELEASE_TYPE');
        }

        return DB::transaction(function () use ($operator, $pass, $type, $reason, $latest) {
            $gate = $this->gate->open($pass);

            $release = GateRelease::create([
                'gate_pass_id' => $pass->id,
                'release_type' => $type,
                'released_by' => $operator->id,
                'reason' => $type === GateRelease::TYPE_MANUAL ? trim((string) $reason) : null,
                'gate_command_id' => $gate['command_id'],
                'gate_response_code' => $gate['code'],
                'gate_response_raw' => $gate['raw'],
                'released_at' => now(),
            ]);

            $pass->update(['status' => GatePass::STATUS_RELEASED]);

            return $release;
        });
    }

    private function normalizePlate(?string $plate): ?string
    {
        $plate = trim((string) $plate);

        return $plate === '' ? null : mb_strtoupper(str_replace([' ', '-'], '', $plate));
    }
}
