<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AmbulanceController;
use App\Http\Controllers\Api\AssistanceRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BedAssignmentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EncounterController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\HospitalController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ModuleBoardController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceOrderController;
use App\Http\Controllers\Api\StaffAssignmentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/switch-hospital', [AuthController::class, 'switchHospital']);
    Route::get('/auth/profile', [AccountController::class, 'show']);
    Route::put('/auth/profile', [AccountController::class, 'update']);
    Route::post('/auth/password', [AccountController::class, 'password']);
    Route::get('/auth/avatar', [AccountController::class, 'avatar']);
    Route::post('/auth/avatar', [AccountController::class, 'uploadAvatar']);
    Route::delete('/auth/avatar', [AccountController::class, 'destroyAvatar']);
    Route::get('/auth/sessions', [AccountController::class, 'sessions']);
    Route::post('/auth/sessions/revoke-others', [AccountController::class, 'revokeOtherSessions']);
    Route::delete('/auth/sessions/{session}', [AccountController::class, 'destroySession']);
    Route::get('/auth/activity', [AccountController::class, 'activity']);

    Route::get('/workspace', [WorkspaceController::class, 'show']);
    Route::get('/clinical-services', [PrescriptionController::class, 'services']);
    Route::get('/medications', [PrescriptionController::class, 'medications']);
    Route::patch('/medications/{medication}', [PrescriptionController::class, 'updateMedication']);

    Route::get('/dashboard', [DashboardController::class, 'command']);
    Route::get('/reports/meta', [ReportController::class, 'meta'])->middleware('permission:read,Report');
    Route::get('/reports/table', [ReportController::class, 'table'])->middleware('permission:read,Report');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('permission:read,Report');
    Route::get('/reports', [ReportController::class, 'show'])->middleware('permission:read,Report');

    Route::get('/modules/catalog', [ModuleBoardController::class, 'catalog']);
    Route::get('/modules/workspaces', [ModuleBoardController::class, 'workspaces'])->middleware('permission:read,Role');
    Route::get('/modules/{module}', [ModuleBoardController::class, 'show']);
    Route::patch('/modules/{module}/facilities/{facility}/status', [ModuleBoardController::class, 'updateFacilityStatus']);

    Route::get('/network/hospitals', [HospitalController::class, 'network']);
    Route::get('/hospitals', [HospitalController::class, 'index'])->middleware('permission:read,Hospital');
    Route::post('/hospitals', [HospitalController::class, 'store'])->middleware('permission:manage,Hospital');
    Route::get('/hospitals/{hospital}', [HospitalController::class, 'show'])->middleware('permission:read,Hospital');
    Route::put('/hospitals/{hospital}', [HospitalController::class, 'update'])->middleware('permission:manage,Hospital');
    Route::delete('/hospitals/{hospital}', [HospitalController::class, 'destroy'])->middleware('permission:manage,Hospital');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:read,Role');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:read,Role');
    Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:read,Role');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:create,Role');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:update,Role');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:delete,Role');

    Route::get('/users/directory', [UserController::class, 'directory']);
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:read,User');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:create,User');
    Route::post('/users/bulk-status', [UserController::class, 'bulkStatus'])->middleware('permission:update,User');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:read,User');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:update,User');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:delete,User');

    Route::get('/departments', [DepartmentController::class, 'index'])->middleware('permission:read,Department');
    Route::post('/departments', [DepartmentController::class, 'store'])->middleware('permission:manage,Department');
    Route::post('/departments/restore-defaults', [DepartmentController::class, 'restoreDefaults'])->middleware('permission:manage,Department');
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])->middleware('permission:read,Department');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:manage,Department');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:manage,Department');

    Route::get('/staff-assignments', [StaffAssignmentController::class, 'index']);
    Route::post('/staff-assignments', [StaffAssignmentController::class, 'store'])->middleware('permission:manage,User');
    Route::put('/staff-assignments/{staffAssignment}', [StaffAssignmentController::class, 'update'])->middleware('permission:manage,User');
    Route::delete('/staff-assignments/{staffAssignment}', [StaffAssignmentController::class, 'destroy'])->middleware('permission:manage,User');

    Route::get('/facility-types', [FacilityController::class, 'types']);
    Route::get('/facilities', [FacilityController::class, 'index']);
    Route::post('/facilities', [FacilityController::class, 'store']);
    Route::get('/facilities/{facility}', [FacilityController::class, 'show']);
    Route::put('/facilities/{facility}', [FacilityController::class, 'update'])->middleware('permission:update,Facility');
    Route::patch('/facilities/{facility}/status', [FacilityController::class, 'updateStatus'])->middleware('permission:update,Facility');
    Route::delete('/facilities/{facility}', [FacilityController::class, 'destroy'])->middleware('permission:manage,Facility');

    Route::get('/patients', [PatientController::class, 'index'])->middleware('permission:read,Patient');
    Route::post('/patients', [PatientController::class, 'store'])->middleware('permission:create,Patient');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->middleware('permission:read,Patient');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])->middleware('permission:update,Patient');
    Route::patch('/patients/{patient}/archive', [PatientController::class, 'archive'])->middleware('permission:update,Patient');
    Route::get('/patients/{patient}/export', [PatientController::class, 'export'])->middleware('permission:manage,Patient');
    Route::get('/patients/{patient}/documents', [DocumentController::class, 'index'])->middleware('permission:read,Patient');
    Route::post('/patients/{patient}/documents', [DocumentController::class, 'store'])->middleware('permission:update,Patient');
    Route::get('/documents/{clinicalDocument}/download', [DocumentController::class, 'download'])->middleware('permission:read,Patient');

    Route::get('/encounters', [EncounterController::class, 'index']);
    Route::post('/encounters', [EncounterController::class, 'store']);
    Route::get('/encounters/{encounter}', [EncounterController::class, 'show']);
    Route::patch('/encounters/{encounter}', [EncounterController::class, 'update']);
    Route::post('/encounters/{encounter}/vitals', [EncounterController::class, 'storeVitals']);
    Route::post('/encounters/{encounter}/notes', [EncounterController::class, 'storeNote']);
    Route::post('/encounters/{encounter}/diagnoses', [EncounterController::class, 'storeDiagnosis']);
    Route::post('/encounters/{encounter}/care-plans', [EncounterController::class, 'storeCarePlan']);
    Route::post('/encounters/{encounter}/admit', [EncounterController::class, 'admit']);
    Route::post('/encounters/{encounter}/discharge', [EncounterController::class, 'discharge']);
    Route::get('/encounters/{encounter}/invoice', [EncounterController::class, 'invoice']);

    Route::get('/bed-assignments', [BedAssignmentController::class, 'index'])->middleware('permission:read,Bed');
    Route::post('/bed-assignments', [BedAssignmentController::class, 'store'])->middleware('permission:create,Bed');
    Route::patch('/bed-assignments/{bedAssignment}/discharge', [BedAssignmentController::class, 'discharge'])->middleware('permission:update,Bed');
    Route::patch('/bed-assignments/{bedAssignment}/transfer', [BedAssignmentController::class, 'transfer'])->middleware('permission:update,Bed');

    Route::get('/service-orders', [ServiceOrderController::class, 'index']);
    Route::post('/service-orders', [ServiceOrderController::class, 'store']);
    Route::patch('/service-orders/{serviceOrder}', [ServiceOrderController::class, 'update']);

    Route::get('/prescriptions', [PrescriptionController::class, 'index']);
    Route::post('/prescriptions', [PrescriptionController::class, 'store']);
    Route::patch('/prescriptions/{prescription}/status', [PrescriptionController::class, 'updateStatus']);

    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:read,Invoice');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:create,Invoice');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:read,Invoice');
    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->middleware('permission:update,Invoice');
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->middleware('permission:update,Invoice');

    Route::get('/referrals/eligible-hospitals', [ReferralController::class, 'eligibleHospitals'])->middleware('permission:create,Referral');
    Route::get('/referrals', [ReferralController::class, 'index'])->middleware('permission:read,Referral');
    Route::post('/referrals', [ReferralController::class, 'store'])->middleware('permission:create,Referral');
    Route::get('/referrals/{referral}', [ReferralController::class, 'show'])->middleware('permission:read,Referral');
    Route::patch('/referrals/{referral}/status', [ReferralController::class, 'updateStatus']);

    Route::get('/assistance-requests', [AssistanceRequestController::class, 'index'])->middleware('permission:read,AssistanceRequest');
    Route::post('/assistance-requests', [AssistanceRequestController::class, 'store'])->middleware('permission:create,AssistanceRequest');
    Route::get('/assistance-requests/{assistanceRequest}', [AssistanceRequestController::class, 'show'])->middleware('permission:read,AssistanceRequest');
    Route::patch('/assistance-requests/{assistanceRequest}/status', [AssistanceRequestController::class, 'updateStatus']);

    Route::get('/ambulances', [AmbulanceController::class, 'index'])->middleware('permission:read,Ambulance');
    Route::post('/ambulances', [AmbulanceController::class, 'store'])->middleware('permission:create,Ambulance');
    Route::get('/ambulances/{ambulance}', [AmbulanceController::class, 'show'])->middleware('permission:read,Ambulance');
    Route::put('/ambulances/{ambulance}', [AmbulanceController::class, 'update'])->middleware('permission:update,Ambulance');
    Route::delete('/ambulances/{ambulance}', [AmbulanceController::class, 'destroy'])->middleware('permission:manage,Ambulance');
    Route::post('/ambulances/{ambulance}/dispatch', [AmbulanceController::class, 'dispatch'])->middleware('permission:dispatch,Ambulance');
    Route::get('/ambulance-trips', [AmbulanceController::class, 'trips'])->middleware('permission:read,Ambulance');
    Route::patch('/ambulance-trips/{trip}/status', [AmbulanceController::class, 'updateTripStatus'])->middleware('permission:dispatch,Ambulance');
});
