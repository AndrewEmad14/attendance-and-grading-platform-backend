<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cohorts = App\Models\Cohort::pluck('id');
foreach ($cohorts as $cohortId) {
    $cohortIds = collect([$cohortId]);
    $query = App\Models\User::where('role', 'instructor');
    $query->whereHas('staffProfile', function ($q) use ($cohortIds) {
        $q->whereHas('engagements', function ($q) use ($cohortIds) {
            $q->where(function ($inner) use ($cohortIds) {
                foreach ($cohortIds as $cohortId) {
                    $inner->orWhere(fn ($q) => $q->forCohort($cohortId));
                }
            });
        });
    });
    echo "Cohort $cohortId - Filtered Count: " . $query->count() . "\n";
}
