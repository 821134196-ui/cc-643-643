<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddInspectionRequest;
use App\Http\Requests\ReleaseRequest;
use App\Http\Requests\StoreGatePassRequest;
use App\Http\Resources\GatePassResource;
use App\Http\Resources\InspectionResource;
use App\Models\GatePass;
use App\Services\GatePassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatePassController extends Controller
{
    public function __construct(private readonly GatePassService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = GatePass::query()
            ->with([
                'creator:id,name',
                'latestInspection',
                'release',
            ])
            ->withCount('inspections')
            ->latest('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return GatePassResource::collection($query->paginate(20))->response();
    }

    public function store(StoreGatePassRequest $request): JsonResponse
    {
        $pass = $this->service->createPass(
            operator: $request->user(),
            lprImage: $request->file('lpr_image'),
            simulateRecognitionFailure: $request->boolean('simulate_failure'),
            manualPlate: $request->input('manual_plate'),
            driverName: $request->input('driver_name'),
            remark: $request->input('remark'),
        );

        $pass->load('creator:id,name', 'inspections.photos', 'inspections.inspector:id,name');

        return (new GatePassResource($pass))
            ->response()
            ->setStatusCode(201);
    }

    public function show(GatePass $gatePass): JsonResponse
    {
        $gatePass->load([
            'creator:id,name',
            'manualOperator:id,name',
            'inspections.photos',
            'inspections.inspector:id,name',
            'release.operator:id,name',
        ]);

        return (new GatePassResource($gatePass))->response();
    }

    public function registerManualPlate(Request $request, GatePass $gatePass): JsonResponse
    {
        $data = $request->validate([
            'manual_plate' => ['required', 'string', 'max:20'],
        ]);

        $pass = $this->service->registerManualPlate(
            $request->user(),
            $gatePass,
            $data['manual_plate'],
        );

        $pass->load('manualOperator:id,name');

        return (new GatePassResource($pass))->response();
    }

    public function addInspection(AddInspectionRequest $request, GatePass $gatePass): JsonResponse
    {
        $inspection = $this->service->addInspection(
            inspector: $request->user(),
            pass: $gatePass,
            result: $request->string('result')->toString(),
            finding: $request->input('finding'),
            wheelPhoto: $request->file('wheel_photo'),
            bodyPhoto: $request->file('body_photo'),
        );

        return (new InspectionResource($inspection))
            ->response()
            ->setStatusCode(201);
    }

    public function release(ReleaseRequest $request, GatePass $gatePass): JsonResponse
    {
        $release = $this->service->release(
            operator: $request->user(),
            pass: $gatePass,
            type: $request->string('type')->toString(),
            reason: $request->input('reason'),
        );

        $release->load('operator:id,name');

        return response()->json([
            'data' => [
                'release_type' => $release->release_type,
                'reason' => $release->reason,
                'released_at' => $release->released_at,
                'gate_command_id' => $release->gate_command_id,
                'gate_response_code' => $release->gate_response_code,
                'gate_response_raw' => $release->gate_response_raw,
                'operator' => [
                    'id' => $release->operator->id,
                    'name' => $release->operator->name,
                ],
            ],
        ]);
    }
}
