<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userData = null;
        if ($this->relationLoaded('user') && $this->user) {
            $userData = [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'account_type' => is_object($this->user->account_type)
                    ? $this->user->account_type->value
                    : $this->user->account_type,
                'avatar' => $this->user->avatar,
            ];
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $userData,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'payload' => $this->payload,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
