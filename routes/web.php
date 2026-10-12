<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\CampusController;
use App\Http\Controllers\AcademicProgramController;
use App\Http\Controllers\AcademicPlanController;
use App\Http\Controllers\AcademicLevelController;
use App\Http\Controllers\AcademicUnitController;
use App\Http\Controllers\AcademicCurriculumItemController;
use App\Http\Controllers\AcademicPeriodController;
use App\Http\Controllers\AcademicOfferingController;
use App\Http\Controllers\TeachingAssignmentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EnrollmentDetailController;
Route::get('/', function () {
    return view('welcome');
});

Route::resource('institutions', InstitutionController::class);
Route::resource('campuses', CampusController::class);
Route::resource('academic-programs', AcademicProgramController::class);
Route::resource('academic-plans', AcademicPlanController::class);
Route::resource('academic-levels', AcademicLevelController::class);
Route::resource('academic-units', AcademicUnitController::class);
Route::resource('academic-curriculum-items', AcademicCurriculumItemController::class);
Route::resource('academic-periods', AcademicPeriodController::class);
Route::resource('academic-offerings', AcademicOfferingController::class);
Route::resource('teaching-assignments', TeachingAssignmentController::class);
Route::resource('enrollments', EnrollmentController::class);
Route::resource('enrollment-details', EnrollmentDetailController::class);