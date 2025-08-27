<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserExpansionRequestResource extends JsonResource
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
            'status' => $this->status,
            'personal_info' => [
                'name' => $this->name,
                'email' => $this->email,
                'company' => $this->company,
            ],
            'request_details' => [
                'current_limit' => $this->when(
                    $this->relationLoaded('dailyUsage'),
                    optional($this->dailyUsage)->daily_limit ?? config('pdfa.default_daily_limit')
                ),
                'requested_limit' => $this->requested_limit,
                'justification' => $this->justification,
                'justification_length' => strlen($this->justification),
            ],
            'processing' => [
                'submitted_at' => $this->created_at->toISOString(),
                'processed_at' => $this->when($this->processed_at, $this->processed_at?->toISOString()),
                'processing_time' => $this->when(
                    $this->processed_at,
                    $this->created_at->diffForHumans($this->processed_at, true)
                ),
            ],
            'admin_response' => [
                'admin_notes' => $this->when(
                    in_array($this->status, ['approved', 'rejected']),
                    $this->admin_notes
                ),
                'response_date' => $this->when($this->processed_at, $this->processed_at?->format('d/m/Y H:i')),
            ],
            'technical_info' => $this->when($request->query('include_tech'), [
                'ip_address' => $this->ip_address,
                'user_agent' => $this->user_agent,
            ]),
            'timestamps' => [
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'status_codes' => [
                    'pending' => 'Aguardando análise',
                    'approved' => 'Aprovada',
                    'rejected' => 'Rejeitada',
                    'cancelled' => 'Cancelada pelo usuário'
                ],
                'limits' => [
                    'min_requested' => config('pdfa.default_daily_limit') + 1,
                    'max_requested' => 10000,
                    'default_limit' => config('pdfa.default_daily_limit'),
                ]
            ],
        ];
    }
}
