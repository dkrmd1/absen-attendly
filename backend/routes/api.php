<?php

use App\Http\Controllers\Api\ActivityReportController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeResourceController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PushDeviceController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::get('/push/config', [PushDeviceController::class, 'config'])->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/push/devices', [PushDeviceController::class, 'store'])->middleware('throttle:20,1');
    Route::delete('/push/devices', [PushDeviceController::class, 'destroy'])->middleware('throttle:20,1');
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/profile', [ProfileController::class, 'update'])->middleware('throttle:5,1');
    Route::get('/profile/npwp', [ProfileController::class, 'npwpDocument']);
    Route::get('/profile/photo', [ProfileController::class, 'photo']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance/settings', [AttendanceController::class, 'settings']);
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/{attendance}/selfies/{type}', [AttendanceController::class, 'selfie'])
        ->whereIn('type', ['check-in', 'check-out']);
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])
        ->middleware('throttle:10,1');
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])
        ->middleware('throttle:10,1');
    Route::get('/attendance-corrections', [AttendanceCorrectionController::class, 'index']);
    Route::post('/attendances/{attendance}/corrections', [AttendanceCorrectionController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::get('/leave-requests/review-queue', [LeaveRequestController::class, 'reviewQueue']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::patch('/leave-requests/{leaveRequest}/review', [LeaveRequestController::class, 'review'])
        ->middleware('throttle:10,1');
    Route::get('/leave-requests/{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment']);
    Route::get('/leave-balance', [EmployeeResourceController::class, 'leaveBalance']);
    Route::get('/activity-reports', [ActivityReportController::class, 'index']);
    Route::post('/activity-reports', [ActivityReportController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::get('/payroll', [EmployeeResourceController::class, 'payroll']);
    Route::get('/payroll/{payrollRecord}/payslip', [EmployeeResourceController::class, 'payslip']);
    Route::get('/tax-documents', [EmployeeResourceController::class, 'taxDocuments']);
    Route::get('/tax-documents/{taxDocument}/file', [EmployeeResourceController::class, 'taxDocumentFile']);
    Route::get('/announcements', [EmployeeResourceController::class, 'announcements']);
});
