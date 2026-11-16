<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'group_type' => $this->group_type,
            'currency' => $this->currency,
            'status' => $this->status,
            'formation_date' => $this->formation_date?->toDateString(),
            // Set by whichever query built this resource (see ChamaController) — the
            // authenticated user's own role names in this specific chama, since the same user
            // can hold different roles in different chamas (spatie team-scoped roles). Label
            // text only: a client should branch on my_permissions, not these names, since a
            // chama can rename or reassign roles (ChamaPermissions is the fixed vocabulary).
            'my_roles' => $this->when(isset($this->my_roles), fn () => $this->my_roles),
            'my_permissions' => $this->when(isset($this->my_permissions), fn () => $this->my_permissions),
        ];
    }
}
