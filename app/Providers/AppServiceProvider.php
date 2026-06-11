<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Models\Submission;
use App\Policies\SubmissionPolicy;
use Illuminate\Support\Facades\Gate;
use App\Models\Tag;
use App\Policies\TagPolicy;


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
        ResetPassword::createUrlUsing(function ($user, string $token) {
        return env('FRONTEND_URL') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });
    }
}
