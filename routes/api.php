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

Route::get('/students', [StudentController::class, 'index']);
Route::get('/students/{id}', [StudentController::class, 'show']);
Route::post('/students', [StudentController::class, 'store']);
Route::put('/students/{id}', [StudentController::class, 'update']);
Route::delete('/students/{id}', [StudentController::class, 'destroy']);

Route::middleware(['auth:sanctum', 'institution.access'])
    ->group(function () {
        Route::get('academic-programs', [AcademicProgramController::class, 'index']);
        Route::post('academic-programs', [AcademicProgramController::class, 'store']);
        Route::get('academic-programs/{id}', [AcademicProgramController::class, 'show']);
        Route::put('academic-programs/{id}', [AcademicProgramController::class, 'update']);
        Route::delete('academic-programs/{id}', [AcademicProgramController::class, 'destroy']);
    });

Route::middleware(['auth:sanctum', 'institution.access'])
    ->group(function () {
        Route::get('/academic-plans', [AcademicPlanController::class, 'index']);
        Route::post('/academic-plans', [AcademicPlanController::class, 'store']);
        Route::get('/academic-plans/{id}', [AcademicPlanController::class, 'show']);
        Route::put('/academic-plans/{id}', [AcademicPlanController::class, 'update']);
        Route::delete('/academic-plans/{id}', [AcademicPlanController::class, 'destroy']);
    });
Route::middleware(['auth:sanctum', 'institution.access'])
    ->group(function () {
        Route::get('/academic-levels', [AcademicLevelController::class, 'index']);
        Route::post('/academic-levels', [AcademicLevelController::class, 'store']);
        Route::get('/academic-levels/{id}', [AcademicLevelController::class, 'show']);
        Route::put('/academic-levels/{id}', [AcademicLevelController::class, 'update']);
        Route::delete('/academic-levels/{id}', [AcademicLevelController::class, 'destroy']);
    });
Route::get('/academic-curriculum-items', [AcademicCurriculumItemController::class, 'index'])->middleware(['auth:sanctum', 'institution.access']);
Route::post('/academic-curriculum-items', [AcademicCurriculumItemController::class, 'store'])->middleware(['auth:sanctum', 'institution.access']);
Route::get('/academic-curriculum-items/{id}', [AcademicCurriculumItemController::class, 'show'])->middleware(['auth:sanctum', 'institution.access']);
Route::put('/academic-curriculum-items/{id}', [AcademicCurriculumItemController::class, 'update'])->middleware(['auth:sanctum', 'institution.access']);
Route::delete('/academic-curriculum-items/{id}', [AcademicCurriculumItemController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access']);
Route::get('/academic-periods', [AcademicPeriodController::class, 'index'])->middleware(['auth:sanctum', 'institution.access']);
Route::post('/academic-periods', [AcademicPeriodController::class, 'store'])->middleware(['auth:sanctum', 'institution.access']);
Route::get('/academic-periods/{id}', [AcademicPeriodController::class, 'show'])->middleware(['auth:sanctum', 'institution.access']);
Route::put('/academic-periods/{id}', [AcademicPeriodController::class, 'update'])->middleware(['auth:sanctum', 'institution.access']);
Route::delete('/academic-periods/{id}', [AcademicPeriodController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access']);
Route::get('/academic-offerings', [AcademicOfferingController::class, 'index'])->middleware(['auth:sanctum', 'institution.access']);
Route::post('/academic-offerings', [AcademicOfferingController::class, 'store'])->middleware(['auth:sanctum', 'institution.access']);
Route::get('/academic-offerings/{id}', [AcademicOfferingController::class, 'show'])->middleware(['auth:sanctum', 'institution.access']);
Route::put('/academic-offerings/{id}', [AcademicOfferingController::class, 'update'])->middleware(['auth:sanctum', 'institution.access']);
Route::delete('/academic-offerings/{id}', [AcademicOfferingController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access']);
Route::get('/teaching-assignments', [TeachingAssignmentController::class, 'index']);
Route::post('/teaching-assignments', [TeachingAssignmentController::class, 'store']);
Route::get('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'show']);
Route::put('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'update']);
Route::delete('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'destroy']);
Route::get('/academic-records', [AcademicRecordController::class, 'index']);
Route::post('/academic-records', [AcademicRecordController::class, 'store']);
Route::get('/academic-records/{id}', [AcademicRecordController::class, 'show']);
Route::put('/academic-records/{id}', [AcademicRecordController::class, 'update']);
Route::delete('/academic-records/{id}', [AcademicRecordController::class, 'destroy']);
Route::get('/enrollments', [EnrollmentController::class, 'index']);
Route::post('/enrollments', [EnrollmentController::class, 'store']);
Route::get('/enrollments/{id}', [EnrollmentController::class, 'show']);
Route::put('/enrollments/{id}', [EnrollmentController::class, 'update']);
Route::delete('/enrollments/{id}', [EnrollmentController::class, 'destroy']);
Route::get('/enrollment-details', [EnrollmentDetailController::class, 'index']);
Route::post('/enrollment-details', [EnrollmentDetailController::class, 'store']);
Route::get('/enrollment-details/{id}', [EnrollmentDetailController::class, 'show']);
Route::put('/enrollment-details/{id}', [EnrollmentDetailController::class, 'update']);
Route::delete('/enrollment-details/{id}', [EnrollmentDetailController::class, 'destroy']);
Route::get('/academic-units', [AcademicUnitController::class, 'index'])->middleware(['auth:sanctum', 'institution.access']);
Route::post('/academic-units', [AcademicUnitController::class, 'store'])->middleware(['auth:sanctum', 'institution.access']);
Route::get('/academic-units/{id}', [AcademicUnitController::class, 'show'])->middleware(['auth:sanctum', 'institution.access']);
Route::put('/academic-units/{id}', [AcademicUnitController::class, 'update'])->middleware(['auth:sanctum', 'institution.access']);
Route::delete('/academic-units/{id}', [AcademicUnitController::class, 'destroy'])->middleware(['auth:sanctum', 'institution.access']);
Route::middleware(['auth:sanctum', 'institution.access'])
    ->group(function () {
        Route::get('/campuses', [CampusController::class, 'index']);
        Route::post('/campuses', [CampusController::class, 'store']);
        Route::get('/campuses/{id}', [CampusController::class, 'show']);
        Route::put('/campuses/{id}', [CampusController::class, 'update']);
        Route::delete('/campuses/{id}', [CampusController::class, 'destroy']);
    });
Route::get('/institutions', [InstitutionController::class, 'index']);
Route::post('/institutions', [InstitutionController::class, 'store']);
Route::get('/institutions/{id}', [InstitutionController::class, 'show']);
Route::put('/institutions/{id}', [InstitutionController::class, 'update']);
Route::delete('/institutions/{id}', [InstitutionController::class, 'destroy']);
Route::get('/people', [PersonController::class, 'index']);
Route::get('/people/{id}', [PersonController::class, 'show']);
Route::post('/people', [PersonController::class, 'store']);
Route::put('/people/{id}', [PersonController::class, 'update']);
Route::delete('/people/{id}', [PersonController::class, 'destroy']);
Route::get('/teachers', [TeacherController::class, 'index']);
Route::post('/teachers', [TeacherController::class, 'store']);
Route::get('/teachers/{id}', [TeacherController::class, 'show']);
Route::put('/teachers/{id}', [TeacherController::class, 'update']);
Route::delete('/teachers/{id}', [TeacherController::class, 'destroy']);