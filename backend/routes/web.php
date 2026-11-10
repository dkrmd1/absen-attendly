<?php

use App\Http\Controllers\Admin\ActivityReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceCorrectionController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeResourceController;
use App\Http\Controllers\Admin\FirebaseMessagingSettingsController;
use App\Http\Controllers\Admin\LeaveBalanceController;
use App\Http\Controllers\Admin\LeaveRequestController;
use App\Http\Controllers\Admin\PayrollRecordController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\WorkGroupController;
use App\Http\Controllers\Admin\WorkLocationController;
use App\Http\Controllers\Admin\WorkShiftController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('admin.login.store');
});

Route::prefix('admin')->name('admin.')
    ->middleware(['admin-or-department-supervisor', 'audit.activity'])
    ->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/attendance-corrections', [AttendanceCorrectionController::class, 'index'])
        ->name('attendance-corrections.index');
    Route::patch('/attendance-corrections/{correction}', [AttendanceCorrectionController::class, 'update'])
        ->name('attendance-corrections.update');
    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::get('/leave-requests/export', [LeaveRequestController::class, 'export'])
        ->name('leave-requests.export');
    Route::get('/leave-requests/export.xlsx', [LeaveRequestController::class, 'exportExcel'])
        ->name('leave-requests.export.xlsx');
    Route::patch('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])
        ->name('leave-requests.update');
    Route::get('/leave-requests/{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment'])
        ->name('leave-requests.attachment');
});

Route::prefix('admin')->name('admin.')
    ->middleware(['admin-or-hr', 'audit.activity'])
    ->group(function () {
    Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('/attendances/export', [AttendanceController::class, 'export'])
        ->name('attendances.export');
    Route::get('/attendances/export.xlsx', [AttendanceController::class, 'exportExcel'])
        ->name('attendances.export.xlsx');
    Route::patch('/attendances/{attendance}/review', [AttendanceController::class, 'review'])
        ->name('attendances.review');
    Route::get('/attendances/{attendance}/selfie', [AttendanceController::class, 'selfie'])
        ->name('attendances.selfie');
    Route::patch('/attendances/{attendance}/selfie-review', [AttendanceController::class, 'reviewSelfie'])
        ->name('attendances.selfie-review');
    Route::get('/activity-reports', [ActivityReportController::class, 'index'])->name('activity-reports.index');
    Route::get('/activity-reports/export', [ActivityReportController::class, 'export'])
        ->name('activity-reports.export');
    Route::get('/activity-reports/export.xlsx', [ActivityReportController::class, 'exportExcel'])
        ->name('activity-reports.export.xlsx');
    Route::patch('/activity-reports/{activityReport}', [ActivityReportController::class, 'update'])
        ->name('activity-reports.update');
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index'])
        ->name('leave-balances.index');
    Route::put('/leave-balances/{employee}', [LeaveBalanceController::class, 'update'])
        ->name('leave-balances.update');
    Route::post('/company-holidays', [LeaveBalanceController::class, 'storeHoliday'])
        ->name('company-holidays.store');
    Route::put('/company-holidays/{companyHoliday}', [LeaveBalanceController::class, 'updateHoliday'])
        ->name('company-holidays.update');
    Route::delete('/company-holidays/{companyHoliday}', [LeaveBalanceController::class, 'destroyHoliday'])
        ->name('company-holidays.destroy');
    Route::patch('/company-holidays/{companyHoliday}/restore', [LeaveBalanceController::class, 'restoreHoliday'])
        ->name('company-holidays.restore');
    Route::get('/payroll', [PayrollRecordController::class, 'index'])->name('payroll.index');
    Route::post('/payroll', [PayrollRecordController::class, 'store'])->name('payroll.store');
    Route::put('/payroll/{payrollRecord}', [PayrollRecordController::class, 'update'])
        ->name('payroll.update');
    Route::patch('/payroll/{payrollRecord}/archive', [PayrollRecordController::class, 'archive'])
        ->name('payroll.archive');
    Route::patch('/payroll/{payrollRecord}/restore', [PayrollRecordController::class, 'restore'])
        ->name('payroll.restore');
    Route::get('/payroll/{payrollRecord}/payslip', [PayrollRecordController::class, 'payslip'])
        ->name('payroll.payslip');
    Route::get('/tax-documents', [EmployeeResourceController::class, 'taxDocuments'])->name('tax-documents.index');
    Route::post('/tax-documents', [EmployeeResourceController::class, 'storeTaxDocument'])->name('tax-documents.store');
    Route::put('/tax-documents/{taxDocument}', [EmployeeResourceController::class, 'updateTaxDocument'])
        ->name('tax-documents.update');
    Route::patch('/tax-documents/{taxDocument}/publish', [EmployeeResourceController::class, 'publishTaxDocument'])
        ->name('tax-documents.publish');
    Route::patch('/tax-documents/{taxDocument}/archive', [EmployeeResourceController::class, 'archiveTaxDocument'])
        ->name('tax-documents.archive');
    Route::patch('/tax-documents/{taxDocument}/restore', [EmployeeResourceController::class, 'restoreTaxDocument'])
        ->name('tax-documents.restore');
    Route::get('/tax-documents/{taxDocument}/file', [EmployeeResourceController::class, 'taxDocumentFile'])
        ->name('tax-documents.file');
    Route::get('/announcements', [EmployeeResourceController::class, 'announcements'])->name('announcements.index');
    Route::post('/announcements', [EmployeeResourceController::class, 'storeAnnouncement'])->name('announcements.store');
    Route::patch('/announcements/{announcement}', [EmployeeResourceController::class, 'updateAnnouncement'])
        ->name('announcements.update');
    Route::patch('/announcements/{announcement}/archive', [EmployeeResourceController::class, 'archiveAnnouncement'])
        ->name('announcements.archive');
    Route::patch('/announcements/{announcement}/restore', [EmployeeResourceController::class, 'restoreAnnouncement'])
        ->name('announcements.restore');
});

Route::prefix('admin')->name('admin.')->middleware(['admin', 'audit.activity'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('/backups/{filename}', [BackupController::class, 'download'])
        ->where('filename', 'attendly-backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.enc')
        ->name('backups.download');
    Route::get('/settings/firebase-messaging', [FirebaseMessagingSettingsController::class, 'index'])
        ->name('firebase-messaging.index');
    Route::put('/settings/firebase-messaging', [FirebaseMessagingSettingsController::class, 'update'])
        ->name('firebase-messaging.update');
    Route::post('/settings/firebase-messaging/test', [FirebaseMessagingSettingsController::class, 'sendTest'])
        ->name('firebase-messaging.test');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('/employees/{employee}/archive', [EmployeeController::class, 'archive'])
        ->name('employees.archive');
    Route::patch('/employees/{employee}/restore', [EmployeeController::class, 'restore'])
        ->name('employees.restore');
    Route::patch('/employees/{employee}/role', [EmployeeController::class, 'updateRole'])
        ->name('employees.role.update');
    Route::patch('/employees/{employee}/group', [EmployeeController::class, 'updateGroup'])
        ->name('employees.group.update');
    Route::patch('/employees/{employee}/location', [EmployeeController::class, 'updateLocation'])
        ->name('employees.location.update');
    Route::patch('/employees/{employee}/department', [EmployeeController::class, 'updateDepartment'])
        ->name('employees.department.update');
    Route::get('/locations', [WorkLocationController::class, 'index'])->name('locations.index');
    Route::post('/locations', [WorkLocationController::class, 'store'])->name('locations.store');
    Route::put('/locations/{workLocation}', [WorkLocationController::class, 'update'])
        ->name('locations.update');
    Route::get('/shifts', [WorkShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts', [WorkShiftController::class, 'store'])->name('shifts.store');
    Route::put('/shifts/{workShift}', [WorkShiftController::class, 'update'])
        ->name('shifts.update');
    Route::patch('/employees/{employee}/shift', [EmployeeController::class, 'updateShift'])
        ->name('employees.shift.update');
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])
        ->name('departments.update');
    Route::get('/work-groups', [WorkGroupController::class, 'index'])->name('work-groups.index');
    Route::post('/work-groups', [WorkGroupController::class, 'store'])->name('work-groups.store');
    Route::put('/work-groups/{workGroup}', [WorkGroupController::class, 'update'])
        ->name('work-groups.update');
    Route::redirect('/settings/office', '/admin/locations');
});
