<?php

use App\Http\Controllers\Api\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AcademicProgramController;
use App\Http\Controllers\Api\AcademicPlanController;
use App\Http\Controllers\Api\AcademicLevelController;
use App\Http\Controllers\Api\AcademicCurriculumItemController;
use App\Http\Controllers\Api\AcademicPeriodController;
use App\Http\Controllers\Api\AcademicOfferingController;
use App\Http\Controllers\Api\TeachingAssignmentController;
use App\Http\Controllers\Api\AcademicRecordController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\EnrollmentDetailController;
use App\Http\Controllers\Api\AcademicUnitController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\TeacherController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'message' => 'API funcionando correctamente'
    ]);
});

Route::get('/students', [StudentController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::get('/students/{id}', [StudentController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/students', [StudentController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/students/{id}', [StudentController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::delete('/students/{id}', [StudentController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);

Route::middleware(['auth:sanctum', 'institution.access'])
    ->group(function () {
        Route::get('academic-programs', [AcademicProgramController::class, 'index'])->middleware('institution.role:institution_admin,registrar');
        Route::post('academic-programs', [AcademicProgramController::class, 'store'])->middleware('institution.role:institution_admin');
        Route::get('academic-programs/{id}', [AcademicProgramController::class, 'show'])->middleware('institution.role:institution_admin,registrar');
        Route::put('academic-programs/{id}', [AcademicProgramController::class, 'update'])->middleware('institution.role:institution_admin');
        Route::delete('academic-programs/{id}', [AcademicProgramController::class, 'destroy'])->middleware('institution.role:institution_admin');
    });

Route::middleware(['auth:sanctum', 'institution.access'])
    ->group(function () {
        Route::get('/academic-plans', [AcademicPlanController::class, 'index'])->middleware('institution.role:institution_admin,registrar');
        Route::post('/academic-plans', [AcademicPlanController::class, 'store'])->middleware('institution.role:institution_admin');
        Route::get('/academic-plans/{id}', [AcademicPlanController::class, 'show'])->middleware('institution.role:institution_admin,registrar');
        Route::put('/academic-plans/{id}', [AcademicPlanController::class, 'update'])->middleware('institution.role:institution_admin');
        Route::delete('/academic-plans/{id}', [AcademicPlanController::class, 'destroy'])->middleware('institution.role:institution_admin');
    });
Route::middleware(['auth:sanctum', 'institution.access'])
    ->group(function () {
        Route::get('/academic-levels', [AcademicLevelController::class, 'index'])->middleware('institution.role:institution_admin,registrar');
        Route::post('/academic-levels', [AcademicLevelController::class, 'store'])->middleware('institution.role:institution_admin');
        Route::get('/academic-levels/{id}', [AcademicLevelController::class, 'show'])->middleware('institution.role:institution_admin,registrar');
        Route::put('/academic-levels/{id}', [AcademicLevelController::class, 'update'])->middleware('institution.role:institution_admin');
        Route::delete('/academic-levels/{id}', [AcademicLevelController::class, 'destroy'])->middleware('institution.role:institution_admin');
    });
Route::get('/academic-curriculum-items', [AcademicCurriculumItemController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/academic-curriculum-items', [AcademicCurriculumItemController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/academic-curriculum-items/{id}', [AcademicCurriculumItemController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/academic-curriculum-items/{id}', [AcademicCurriculumItemController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::delete('/academic-curriculum-items/{id}', [AcademicCurriculumItemController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/academic-periods', [AcademicPeriodController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/academic-periods', [AcademicPeriodController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/academic-periods/{id}', [AcademicPeriodController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/academic-periods/{id}', [AcademicPeriodController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::delete('/academic-periods/{id}', [AcademicPeriodController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/academic-offerings', [AcademicOfferingController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/academic-offerings', [AcademicOfferingController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/academic-offerings/{id}', [AcademicOfferingController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/academic-offerings/{id}', [AcademicOfferingController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::delete('/academic-offerings/{id}', [AcademicOfferingController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/teaching-assignments', [TeachingAssignmentController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::post('/teaching-assignments', [TeachingAssignmentController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::put('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::delete('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/academic-records', [AcademicRecordController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/academic-records', [AcademicRecordController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::get('/academic-records/{id}', [AcademicRecordController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/academic-records/{id}', [AcademicRecordController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::delete('/academic-records/{id}', [AcademicRecordController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::get('/enrollments', [EnrollmentController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/enrollments', [EnrollmentController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::get('/enrollments/{id}', [EnrollmentController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/enrollments/{id}', [EnrollmentController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::delete('/enrollments/{id}', [EnrollmentController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::get('/enrollment-details', [EnrollmentDetailController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/enrollment-details', [EnrollmentDetailController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::get('/enrollment-details/{id}', [EnrollmentDetailController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/enrollment-details/{id}', [EnrollmentDetailController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::delete('/enrollment-details/{id}', [EnrollmentDetailController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::get('/academic-units', [AcademicUnitController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/academic-units', [AcademicUnitController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/academic-units/{id}', [AcademicUnitController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/academic-units/{id}', [AcademicUnitController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::delete('/academic-units/{id}', [AcademicUnitController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::middleware(['auth:sanctum', 'institution.access'])
    ->group(function () {
        Route::get('/campuses', [CampusController::class, 'index'])->middleware('institution.role:institution_admin,registrar');
        Route::post('/campuses', [CampusController::class, 'store'])->middleware('institution.role:institution_admin');
        Route::get('/campuses/{id}', [CampusController::class, 'show'])->middleware('institution.role:institution_admin,registrar');
        Route::put('/campuses/{id}', [CampusController::class, 'update'])->middleware('institution.role:institution_admin');
        Route::delete('/campuses/{id}', [CampusController::class, 'destroy'])->middleware('institution.role:institution_admin');
    });
Route::get('/institutions', [InstitutionController::class, 'index'])->middleware(['auth:sanctum', 'institution.access']);
Route::post('/institutions', [InstitutionController::class, 'store'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsurePlatformAdmin::class]);
Route::get('/institutions/{id}', [InstitutionController::class, 'show'])->middleware(['auth:sanctum', 'institution.access']);
Route::put('/institutions/{id}', [InstitutionController::class, 'update'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsurePlatformAdmin::class]);
Route::delete('/institutions/{id}', [InstitutionController::class, 'destroy'])->middleware(['auth:sanctum', \App\Http\Middleware\EnsurePlatformAdmin::class]);
Route::get('/people', [PersonController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::get('/people/{id}', [PersonController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/people', [PersonController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/people/{id}', [PersonController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::delete('/people/{id}', [PersonController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/teachers', [TeacherController::class, 'index'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::post('/teachers', [TeacherController::class, 'store'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::get('/teachers/{id}', [TeacherController::class, 'show'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin,registrar']);
Route::put('/teachers/{id}', [TeacherController::class, 'update'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);
Route::delete('/teachers/{id}', [TeacherController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access', 'institution.role:institution_admin']);