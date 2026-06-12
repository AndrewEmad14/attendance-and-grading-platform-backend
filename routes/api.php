<?php

use App\Enums\Role;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceLedgerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchAnalyticsController;
use App\Http\Controllers\Api\BusinessSessionController;
use App\Http\Controllers\Api\CohortController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EngagementController;
use App\Http\Controllers\Api\ExcuseRequestController;
use App\Http\Controllers\Api\GradingAnalyticsController;
use App\Http\Controllers\Api\LabGroupController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'API is running']);
});

Route::patch('/test-notes/{studentId}', [NoteController::class, 'append']);
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('tracks/{track}')->group(function () {
        Route::post('cohorts', [CohortController::class, 'store']);
        Route::get('cohorts', [CohortController::class, 'index']);
        Route::patch('cohorts/{cohort}', [CohortController::class, 'update']);
    });
    Route::get('cohorts', [CohortController::class, 'index']);

    Route::get('/cohorts/{cohortId}/courses', [CourseController::class, 'index']);
    Route::post('/cohorts/{cohortId}/courses', [CourseController::class, 'store']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::patch('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);

    Route::post('/deliverables/{deliverable}/submissions', [SubmissionController::class, 'store'])
        ->middleware('role:'.Role::STUDENT);
    Route::get('/deliverables/{deliverable}/submissions', [SubmissionController::class, 'index']);
    Route::get('/deliverables/{deliverable}/missing', [SubmissionController::class, 'missing']);
    Route::get('/students/{studentId}/submissions', [SubmissionController::class, 'studentSubmissions']);

    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    Route::delete('/submissions/{submission}', [SubmissionController::class, 'destroy']);

    Route::get('/submissions/{submission}/download', [SubmissionController::class, 'download']);
    Route::patch('/submissions/{submission}', [SubmissionController::class, 'grade']);
    Route::post('/submissions/{submission}/override', [SubmissionController::class, 'override']);

    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::get('/students/{studentId}/tags', [TagController::class, 'studentTags']);
    Route::post('/students/{studentId}/tags', [TagController::class, 'attach']);
    Route::delete('/students/{studentId}/tags/{tagId}', [TagController::class, 'detach']);

    Route::patch('/students/{studentId}/notes', [NoteController::class, 'append']);

    Route::get('/analytics/branch', BranchAnalyticsController::class);
    Route::get('/analytics/cohorts/{cohortId}', [GradingAnalyticsController::class, 'cohortGrades']);
    Route::get('/analytics/cohorts/{cohortId}/at-risk', [GradingAnalyticsController::class, 'atRiskStudents']);
    Route::get('/analytics/lab-groups/{labGroupId}', [GradingAnalyticsController::class, 'labGroupGrades']);

    Route::prefix('cohorts/{cohort}')->group(function () {
        Route::get('lab-groups', [LabGroupController::class, 'index']);
        Route::post('lab-groups', [LabGroupController::class, 'store']);
        Route::get('students', [LabGroupController::class, 'cohortStudents']);
    });

    Route::prefix('lab-groups/{labGroup}')->group(function () {
        Route::post('students', [LabGroupController::class, 'attachStudent']);
        Route::delete('students/{studentId}', [LabGroupController::class, 'detachStudent']);
        Route::delete('', [LabGroupController::class, 'destroy']);
    });

    Route::prefix('engagements')->group(function () {
        Route::get('', [EngagementController::class, 'index']);
        Route::post('', [EngagementController::class, 'store']);
        Route::get('{engagement}', [EngagementController::class, 'show']);
        Route::patch('{engagement}', [EngagementController::class, 'update']);
        Route::delete('{engagement}', [EngagementController::class, 'destroy']);
    });

    Route::prefix('business-sessions')->group(function () {
        Route::get('', [BusinessSessionController::class, 'index']);
        Route::post('', [BusinessSessionController::class, 'store']);
        Route::get('{businessSession}', [BusinessSessionController::class, 'show']);

        Route::post('{businessSession}/cohorts', [BusinessSessionController::class, 'enrollCohort']);
        Route::delete('{businessSession}/cohorts/{cohortId}', [BusinessSessionController::class, 'removeCohort']);
    });

    Route::prefix('attendance')->group(function () {
        Route::get('', [AttendanceController::class, 'index']);
        Route::get('/{attendance}', [AttendanceController::class, 'show']);
        Route::post('', [AttendanceController::class, 'store']);
        Route::patch('/{attendance}', [AttendanceController::class, 'update']);
    });

    Route::get('/students/{student}/attendance-ledger', [AttendanceLedgerController::class, 'show']);

    Route::prefix('excuse-requests')->group(function () {
        Route::get('', [ExcuseRequestController::class, 'index']);
        Route::get('/{excuseRequest}', [ExcuseRequestController::class, 'show']);
        Route::post('', [ExcuseRequestController::class, 'store']);
        Route::patch('/{excuseRequest}', [ExcuseRequestController::class, 'update']);
        Route::post('/{excuseRequest}/approve', [ExcuseRequestController::class, 'approve']);
        Route::post('/{excuseRequest}/reject', [ExcuseRequestController::class, 'reject']);
    });
});

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
});

Route::group(['prefix' => 'users', 'middleware' => ['auth:sanctum', 'role:'.Role::BRANCH_MANAGER.','.Role::TRACK_ADMIN]], function () {
    Route::get('students', [UserController::class, 'listStudents']);
    Route::get('instructors', [UserController::class, 'listInstructors']);
    Route::get('track-admins', [UserController::class, 'listTrackAdmins'])->middleware('role:'.Role::BRANCH_MANAGER);

    Route::post('', [UserController::class, 'store']);
    Route::get('{user}', [UserController::class, 'show']);
    Route::patch('{user}', [UserController::class, 'update']);
    Route::delete('{user}', [UserController::class, 'destroy']);
});

Route::group(['prefix' => 'cohorts/{cohort}/announcements', 'middleware' => ['auth:sanctum']], function () {
    Route::get('', [AnnouncementController::class, 'index']);
    Route::post('', [AnnouncementController::class, 'store'])
        ->middleware('role:'.Role::BRANCH_MANAGER.','.Role::TRACK_ADMIN.','.Role::INSTRUCTOR);
});

Route::group(['prefix' => 'announcements', 'middleware' => ['auth:sanctum']], function () {
    Route::patch('{announcement}', [AnnouncementController::class, 'update'])
        ->middleware('role:'.Role::BRANCH_MANAGER.','.Role::TRACK_ADMIN.','.Role::INSTRUCTOR);
    Route::delete('{announcement}', [AnnouncementController::class, 'destroy'])
        ->middleware('role:'.Role::BRANCH_MANAGER.','.Role::TRACK_ADMIN.','.Role::INSTRUCTOR);
    Route::post('broadcast', [AnnouncementController::class, 'broadcast'])
        ->middleware('role:'.Role::BRANCH_MANAGER);
});
