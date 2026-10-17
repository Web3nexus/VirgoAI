<?php

namespace App\Http\Resources;

use App\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_super_admin' => $this->isSuperAdmin(),
            'balance_usd' => Money::toUsd((int) $this->balance_micros),
            'balance_tokens' => Money::toTokens((int) $this->balance_micros),
            'country_code' => $this->country_code,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
