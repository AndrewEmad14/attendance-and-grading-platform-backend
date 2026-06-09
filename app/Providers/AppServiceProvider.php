<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Submission;
use App\Policies\SubmissionPolicy;
use Illuminate\Support\Facades\Gate;


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
        Gate::policy(Submission::class, SubmissionPolicy::class);
    }
}
