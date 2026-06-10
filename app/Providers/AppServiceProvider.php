<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

use App\Models\Submission;
use App\Policies\SubmissionPolicy;
use Illuminate\Support\Facades\Gate;
use App\Models\Tag;
use App\Policies\TagPolicy;

use App\Models\AttendanceRecord;
use App\Models\Cohort;
use App\Models\ExcuseRequest;
use App\Models\StudentProfile;
use App\Policies\AttendancePolicy;
use App\Policies\CohortPolicy;
use App\Policies\ExcuseRequestPolicy;
use App\Policies\StudentProfilePolicy;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower($request->input('email'));

            return Limit::perMinute(5)
                ->by($request->ip() . '|' . $email)
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in 60 seconds.'
                    ], 429);
                });
        });

        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Submission::class, SubmissionPolicy::class);
        Gate::policy(AttendanceRecord::class, AttendancePolicy::class);
        Gate::policy(ExcuseRequest::class, ExcuseRequestPolicy::class);
        Gate::policy(StudentProfile::class, StudentProfilePolicy::class);
        Gate::policy(Cohort::class, CohortPolicy::class);
    }
}
