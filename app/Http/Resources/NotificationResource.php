<?php


namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'title' => $this['title'] ?? null,
            'message' => $this['message'] ?? null,
        ];
    }
}
