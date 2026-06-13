<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessDailyBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:process-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process ended engagements and log them into billing records';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('['.now()->toDateTimeString().'] === Process Daily Billing Started ===');

        $now = now();

        // 1. Fetch finished engagements that are NOT already in billing_records
        $unloggedEngagements = DB::table('engagements')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('billing_records')
                    ->whereColumn('billing_records.engagement_id', 'engagements.id');
            })
            ->select('id', 'staff_id', 'scheduled_hours', 'ends_at')
            ->get();

        if ($unloggedEngagements->isEmpty()) {
            $this->info('['.now()->toDateTimeString().'] No unprocessed finished engagements found. Exiting.');

            return;
        }

        $this->info('['.now()->toDateTimeString().'] Found '.$unloggedEngagements->count().' unprocessed finished engagement(s).');

        // 2. Fetch staff profiles for these engagements
        $staffIds = $unloggedEngagements->pluck('staff_id')->unique()->filter()->values()->toArray();
        $staffProfiles = DB::table('staff_profiles')
            ->whereIn('id', $staffIds)
            ->select('id', 'compensation_type', 'hourly_rate', 'fixed_salary')
            ->get()
            ->keyBy('id');

        $engagementsByStaff = $unloggedEngagements->groupBy('staff_id');
        $recordsToInsert = [];
        $nowTimestamp = now();

        foreach ($engagementsByStaff as $staffId => $engs) {
            $staff = $staffProfiles->get($staffId);
            if (! $staff) {
                $this->warn('['.now()->toDateTimeString().'] Staff profile not found for ID: '.$staffId);

                continue;
            }

            $hourlyRate = (float) $staff->hourly_rate;
            $fixedSalary = (float) $staff->fixed_salary;
            $isInternal = ($staff->compensation_type === 'internal');

            // Check if internal staff member has already received their fixed salary in billing_records this month
            $fixedSalaryApplied = false;
            if ($isInternal) {
                $fixedSalaryApplied = DB::table('billing_records')
                    ->where('staff_id', $staffId)
                    ->whereMonth('created_at', $nowTimestamp->month)
                    ->whereYear('created_at', $nowTimestamp->year)
                    ->exists();
            }

            foreach ($engs as $eng) {
                $scheduledHours = (float) $eng->scheduled_hours;
                $totalAmount = $scheduledHours * $hourlyRate;

                if ($isInternal && ! $fixedSalaryApplied) {
                    $totalAmount += $fixedSalary;
                    $fixedSalaryApplied = true;
                }

                $recordsToInsert[] = [
                    'engagement_id' => $eng->id,
                    'staff_id' => $staffId,
                    'delivered_hours' => (int) $scheduledHours,
                    'total_amount' => (int) round($totalAmount),
                    'forwarded_at' => null,
                    'created_at' => $nowTimestamp,
                    'updated_at' => $nowTimestamp,
                ];
            }
        }

        // 3. Bulk insert the records inside a transaction
        if (! empty($recordsToInsert)) {
            DB::transaction(function () use ($recordsToInsert) {
                DB::table('billing_records')->insert($recordsToInsert);
            });
            $this->info('['.now()->toDateTimeString().'] Successfully inserted '.count($recordsToInsert).' billing record(s).');
        }

        $this->info('['.now()->toDateTimeString().'] === Process Daily Billing Completed ===');
    }
}
