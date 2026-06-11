<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'student' => [
                'id' => $this->resource['student']['id'],
                'name' => $this->resource['student']['name'],
                'current_balance' => $this->resource['current_balance'],
            ],
            'entries' => collect($this->resource['entries'])->map(fn ($entry) => [
                'engagement_id' => $entry['engagement_id'],
                'engagement_type' => $entry['engagement_type'],
                'name' => $entry['name'],
                'date' => $entry['date'],
                'arrived_at' => $entry['arrived_at'],
                'left_at' => $entry['left_at'],
                'absence_status' => $entry['absence_status'],
                'excuse_status' => $entry['excuse_status'],
                'deduction' => $entry['deduction'],
            ]),
        ];
    }
}
