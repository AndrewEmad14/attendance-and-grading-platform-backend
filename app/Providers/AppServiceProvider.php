<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Submission::class, SubmissionPolicy::class);
    }
}
