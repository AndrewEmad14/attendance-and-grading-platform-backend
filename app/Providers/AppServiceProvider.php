<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Submission;
use App\Models\Tag;
use App\Policies\CoursePolicy;
use App\Policies\SubmissionPolicy;
use App\Policies\TagPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Models\AttendanceRecord;
use App\Models\Cohort;
use App\Models\ExcuseRequest;
use App\Policies\AttendancePolicy;
use App\Policies\CohortPolicy;
use App\Policies\ExcuseRequestPolicy;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
                        'message' => 'Too many login attempts. Please try again in 60 seconds.',
                    ], 429);
                });
        });

        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Submission::class, SubmissionPolicy::class);
        Gate::policy(AttendanceRecord::class, AttendancePolicy::class);
        Gate::policy(ExcuseRequest::class, ExcuseRequestPolicy::class);
        Gate::policy(Cohort::class, CohortPolicy::class);
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return env('FRONTEND_URL') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });
        Gate::policy(Course::class, CoursePolicy::class);
    }
}
