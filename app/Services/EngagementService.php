<?php

namespace App\Services;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\Lab;
use InvalidArgumentException;

class EngagementService
{
    public function createEngagement(array $data): Engagement
    {
        $morphMap = [
            'lecture' => Course::class,
            'lab' => Lab::class,
            'business_session' => BusinessSession::class,
        ];

        $typeKey = $data['type'];

        if (! isset($morphMap[$typeKey])) {
            throw new InvalidArgumentException('Unsupported engagement type provided.');
        }

        $modelClass = $morphMap[$typeKey];
        $parentModel = $modelClass::find($data['engageable_id']);

        if (! $parentModel) {
            throw ValidationException::withMessages([
                'engageable_id' => [sprintf('The requested %s entity could not be retrieved from storage.', $typeKey)],
            ]);
        }

        $engagement = new Engagement([
            'type' => $typeKey,
            'staff_id' => $data['staff_id'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'scheduled_hours' => $data['scheduled_hours'],
        ]);

        $parentModel->engagements()->save($engagement);

        return $engagement;
    }
}
