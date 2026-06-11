<?php

namespace App\Http\Controllers\Api;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Mail\EmailChangedNotification;
use App\Mail\AccountDeactivatedNotification;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\ListStudentsRequest;
use App\Http\Requests\ListInstructorsRequest;
use App\Http\Requests\ListTrackAdminsRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    use AuthorizesRequests;
    const PAGE_SIZE = 10;

    public function listStudents(listStudentsRequest $request)
    {
        $cohortIds = auth()->user()->role === Role::TRACK_ADMIN
        ? auth()->user()->staffProfile->managedCohorts()->pluck('cohort_id')
        : null;

        $query = User::where('role', Role::STUDENT)
            ->with([
                'studentProfile:id,user_id,cohort_id,lab_group_id,attendance_balance',
                'studentProfile.cohort:id,number,track_id,is_active',
                'studentProfile.cohort.track:id,name',
                'studentProfile.labGroup:id,name',
                'studentProfile.tags:id,tag',
            ]);

        // search by name
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        // filter cohort or track
        if ($request->filled('cohort') || $request->filled('track_id') || $request->filled('is_active')) {
            $query->whereHas('studentProfile.cohort', function ($q) use ($request) {
                if ($request->filled('cohort')) {
                    $q->where('number', $request->cohort);
                }
                if ($request->filled('track_id')) {
                    $q->where('track_id', $request->track_id);
                }
                // filter by whether the student's cohort is currently active
                if ($request->filled('is_active')) {
                    $q->where('is_active', $request->boolean('is_active'));
                }
            });
        }

        // filter by tag
        if ($request->filled('tag_id')) {
            $query->whereHas('studentProfile.tags', fn($q) => $q->where('tags.id', $request->tag_id));
        }

        // combine all student_profile conditions into one whereHas
        $hasProfileConditions = $request->filled('lab_group_id')
            || $request->filled('attendance_min')
            || $request->filled('attendance_max')
            || ($cohortIds && $cohortIds->isNotEmpty());

        if ($hasProfileConditions) {
            $query->whereHas('studentProfile', function ($q) use ($request, $cohortIds) {
                // filter by lab group
                if ($request->filled('lab_group_id')) {
                    $q->where('lab_group_id', $request->lab_group_id);
                }
                // filter by minimum attendance
                if ($request->filled('attendance_min')) {
                    $q->where('attendance_balance', '>=', $request->attendance_min);
                }
                //filter by maximum attendance
                if ($request->filled('attendance_max')) {
                    $q->where('attendance_balance', '<=', $request->attendance_max);
                }
                // if the requester is a track admin
                if ($cohortIds) {
                    $q->whereIn('cohort_id', $cohortIds);
                }
            });
        }

        // sorting logic
        // blank value = ascending, '-' prefix = descending
        // e.g. ?sort=name (asc), ?sort=-name (desc)
        if ($request->filled('sort')) {
            $sortParam = $request->sort;

            // Determine direction and clean up the key name
            $direction = str_starts_with($sortParam, '-') ? 'desc' : 'asc';
            $sortKey   = ltrim($sortParam, '-');

            switch ($sortKey) {
                case 'name':
                    // Direct sorting on the primary users table
                    $query->orderBy('name', $direction);
                    break;

                case 'cohort':
                    //  Pass a subquery directly into standard orderBy()
                    $query->orderBy(
                        \App\Models\StudentProfile::select('cohort_id')
                            ->whereColumn('student_profiles.user_id', 'users.id'),
                        $direction
                    );
                    break;

                case 'attendance':
                    // Correct Native Way: Pass a subquery directly into standard orderBy()
                    $query->orderBy(
                        \App\Models\StudentProfile::select('attendance_balance')
                            ->whereColumn('student_profiles.user_id', 'users.id'),
                        $direction
                    );
                    break;
            }
        }
        return response()->json([
            'message' => 'fetched students successfully',
            'status'  => 200,
            'data'    => $query->paginate(self::PAGE_SIZE),
        ]);
    }

    public function listInstructors(ListInstructorsRequest $request)
    {
        // resolve cohort scope for track admin once, before the query
        $cohortIds = auth()->user()->role === Role::TRACK_ADMIN
            ? auth()->user()->staffProfile->managedCohorts()->pluck('cohort_id')
            : null;

        $query = User::where('role', Role::INSTRUCTOR)
            ->with([
                'staffProfile:id,user_id,compensation_type,hourly_rate,fixed_salary',
            ]);

        // search by name on users table — no join needed
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // combine all staff_profile conditions into one EXISTS to avoid multiple subqueries
        $hasProfileConditions = $request->filled('compensation_type')
            || $request->filled('hourly_rate_min')
            || $request->filled('hourly_rate_max')
            || $request->filled('fixed_salary_min')
            || $request->filled('fixed_salary_max')
            || ($cohortIds && $cohortIds->isNotEmpty());

        if ($hasProfileConditions) {
            $query->whereHas('staffProfile', function ($q) use ($request, $cohortIds) {

                // filter by compensation type (hourly / fixed)
                if ($request->filled('compensation_type')) {
                    $q->where('compensation_type', $request->compensation_type);
                }

                // hourly rate range filter (slider)
                if ($request->filled('hourly_rate_min')) {
                    $q->where('hourly_rate', '>=', $request->hourly_rate_min);
                }
                if ($request->filled('hourly_rate_max')) {
                    $q->where('hourly_rate', '<=', $request->hourly_rate_max);
                }

                // fixed salary range filter (slider)
                if ($request->filled('fixed_salary_min')) {
                    $q->where('fixed_salary', '>=', $request->fixed_salary_min);
                }
                if ($request->filled('fixed_salary_max')) {
                    $q->where('fixed_salary', '<=', $request->fixed_salary_max);
                }
                // scope track admin to only see instructors in their cohorts
                if (($cohortIds && $cohortIds->isNotEmpty())) {
                    $q->whereHas('engagements', function ($q) use ($cohortIds) {
                        $q->where(function ($inner) use ($cohortIds) {
                            foreach ($cohortIds as $cohortId) {
                                $inner->orWhere(fn($q) => $q->forCohort($cohortId));
                            }
                        });
                    });
                }
            });
        }
        if ($request->filled('sort')) {
            $sortParam  = $request->sort;
            $direction  = str_starts_with($sortParam, '-') ? 'desc' : 'asc';
            $sortKey    = ltrim($sortParam, '-');

            switch ($sortKey) {
                // sort directly on users table — no subquery needed
                case 'name':
                    $query->orderBy('name', $direction);
                    break;

                // sort by hourly_rate from staff_profiles — NULL treated as 0 via COALESCE
                // so fixed-salary instructors (hourly_rate = null) sort as if they earn 0
                case 'hourly_rate':
                    $query->orderBy(
                        \App\Models\StaffProfile::selectRaw('COALESCE(hourly_rate, 0)')
                            ->whereColumn('staff_profiles.user_id', 'users.id'),
                        $direction
                    );
                    break;

                // sort by fixed_salary from staff_profiles — NULL treated as 0 via COALESCE
                // so hourly instructors (fixed_salary = null) sort as if they earn 0
                case 'fixed_salary':
                    $query->orderBy(
                        \App\Models\StaffProfile::selectRaw('COALESCE(fixed_salary, 0)')
                            ->whereColumn('staff_profiles.user_id', 'users.id'),
                        $direction
                    );
                    break;
            }
        }            
        return response()->json([
            'message' => 'fetched instructors successfully',
            'status'  => 200,
            'data'    => $query->paginate(self::PAGE_SIZE),
        ]);
    }

    public function listTrackAdmins(ListTrackAdminsRequest $request)
    {
        $query = User::where('role', Role::TRACK_ADMIN)
            ->with([
                'staffProfile:id,user_id',
                'staffProfile.managedCohorts:id,number,track_id,is_active',
                'staffProfile.managedCohorts.track:id,name',
            ]);

        // search by name on users table — no join needed
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // combine cohort, track, and is_active filters into one EXISTS on cohorts
        if ($request->filled('cohort') || $request->filled('track_id') || $request->filled('is_active')) {
            $query->whereHas('staffProfile.managedCohorts', function ($q) use ($request) {

                // filter by cohort number
                if ($request->filled('cohort')) {
                    $q->where('number', $request->cohort);
                }

                // track_id sits directly on cohorts, no need to traverse to tracks table
                if ($request->filled('track_id')) {
                    $q->where('track_id', $request->track_id);
                }

                // filter by whether the cohort is currently active
                if ($request->filled('is_active')) {
                    $q->where('is_active', $request->boolean('is_active'));
                }
            });
        }
        // sort by name — only sortable field for track admins
        if ($request->filled('sort')) {
            $direction = str_starts_with($request->sort, '-') ? 'desc' : 'asc';
            $sortKey   = ltrim($request->sort, '-');

            if ($sortKey === 'name') {
                $query->orderBy('name', $direction);
            }
        }

        return response()->json([
            'message' => 'fetched track admins successfully',
            'status'  => 200,
            'data'    => $query->paginate(self::PAGE_SIZE),
        ]);
    }
    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->load(match($user->role) {
            Role::STUDENT => [
                'studentProfile.cohort.track',
                'studentProfile.labGroup',
                'studentProfile.tags',
            ],
            default => [
                'staffProfile.managedCohorts.track',
            ],
        });

        return response()->json([
            'message' => 'fetched user successfully',
            'status'  => 200,
            'data'    => $user,
        ]);
    }
    public function store(StoreUserRequest $request)
    {
        $this->authorize('store', [User::class, $request->role]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name'       => $request->name,
                'email'      => $request->email,
                'password_hash' => Str::random(16),
                'role'       => $request->role,
                'expires_at' => $request->expires_at,
            ]);

            if ($user->role === Role::STUDENT) {
                $user->studentProfile()->create([
                    'cohort_id'          => $request->cohort_id,
                    'lab_group_id'       => $request->lab_group_id,
                    'attendance_balance' => 250,
                    'notes'              => '',
                ]);
            } else {
                $user->staffProfile()->create([
                    'compensation_type' => $request->compensation_type,
                    'hourly_rate'       => $request->hourly_rate,
                    'fixed_salary'      => $request->fixed_salary,
                ]);
            }

            Password::sendResetLink(['email' => $user->email]);

            return $user;
        });
        Password::sendResetLink(['email' => $user->email]);
        return response()->json([
            'message' => 'user created successfully',
            'status'  => 201,
            'data'    => $user,
        ], 201);
    }
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $emailChanged = $request->filled('email') && $request->email !== $user->email;
        $oldEmail     = $user->email;

        DB::transaction(function () use ($request, $user) {
            $user->update($request->only(['name', 'email', 'expires_at']));

            if ($user->staffProfile && $request->hasAny(['compensation_type', 'hourly_rate', 'fixed_salary'])) {
                $user->staffProfile->update(
                    $request->only(['compensation_type', 'hourly_rate', 'fixed_salary'])
                );
            }
        });

        // notify old email that the address was changed — safety net for the user
        if ($emailChanged) {
            Mail::to($oldEmail)->send(new EmailChangedNotification($user, $oldEmail));
        }

        return response()->json([
            'message' => 'user updated successfully',
            'status'  => 200,
            'data'    => $user->fresh()->load(
                match($user->role) {
                    'student' => ['studentProfile'],
                    default   => ['staffProfile'],
                }
            ),
        ]);
    }
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->update(['expires_at' => now()]);
        $user->tokens()->delete();

        // notify user their account was deactivated
        Mail::to($user->email)->send(new AccountDeactivatedNotification($user));

        return response()->json([
            'message' => 'user deactivated successfully',
            'status'  => 200,
        ]);
    }
}