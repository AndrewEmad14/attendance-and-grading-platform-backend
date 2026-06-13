<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TrackAdminStaffProfileResource;
// app/Http/Resources/TrackAdminResource.php
class TrackAdminResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'role'              => $this->role,
            'expires_at'        => $this->expires_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'staff_profile'     => new TrackAdminStaffProfileResource($this->whenLoaded('staffProfile')),
        ];
    }
}
