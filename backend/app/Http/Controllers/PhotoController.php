<?php

namespace App\Http\Controllers;

use App\Models\ExitOrder;
use App\Models\InspectionPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    // 上传照片到指定出场单(尚未绑定轮次)
    public function store(Request $request, ExitOrder $order): JsonResponse
    {
        if (! $order->isOpen()) {
            return response()->json(['message' => '该出场单已放行,不能再上传照片'], 422);
        }

        $data = $request->validate([
            'type' => 'required|in:wheel,body',
            'photo' => 'required|image|max:10240',
        ]);

        $file = $request->file('photo');
        $path = $file->store("photos/{$order->id}", 'public');

        $photo = $order->photos()->create([
            'type' => $data['type'],
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($photo, 201);
    }
}
