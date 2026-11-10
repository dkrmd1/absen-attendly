<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->activityReports()
                ->orderByDesc('activity_date')
                ->paginate(30),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'activity_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'hours' => ['nullable', 'numeric', 'gt:0', 'lte:24'],
        ]);

        $report = $request->user()->activityReports()->create($attributes)->refresh();

        return response()->json(['data' => $report], 201);
    }
}
