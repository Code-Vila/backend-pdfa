<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyUsageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'daily_limit' => $this->daily_limit,
            'conversions_used_today' => $this->conversions_count,
            'remaining_conversions' => $this->getRemainingConversions(),
            'usage_percentage' => round(($this->conversions_count / $this->daily_limit) * 100, 2),
            'is_expanded' => $this->is_expanded,
            'is_near_limit' => $this->getRemainingConversions() <= 5,
            'is_at_limit' => $this->getRemainingConversions() <= 0,
            'expansion_info' => [
                'can_request_expansion' => !$this->is_expanded && $this->getRemainingConversions() <= 10,
                'current_plan' => $this->is_expanded ? 'Expandido' : 'Padrão',
                'upgrade_benefits' => $this->when(!$this->is_expanded, [
                    'Limite personalizado até 10.000 conversões/dia',
                    'Processamento prioritário',
                    'Suporte dedicado por email'
                ]),
            ],
            'statistics' => [
                'efficiency_score' => $this->conversions_count > 0 ? 'Ativo' : 'Inativo',
                'daily_reset_time' => '00:00 UTC',
                'time_until_reset' => now()->diffForHumans(now()->addDay()->startOfDay()),
            ],
            'technical_info' => $this->when($request->query('include_tech'), [
                'ip_address' => $this->ip_address,
                'last_conversion' => $this->updated_at->toISOString(),
                'first_use_today' => $this->created_at->isToday() ? $this->created_at->toISOString() : null,
            ]),
            'timestamps' => [
                'updated_at' => $this->updated_at->toISOString(),
                'created_at' => $this->created_at->toISOString(),
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
                'limits' => [
                    'default_daily_limit' => config('pdfa.default_daily_limit'),
                    'max_expansion_limit' => 10000,
                    'warning_threshold' => 5, // Avisar quando restam 5 conversões
                ],
                'rate_limiting' => [
                    'period' => 'daily',
                    'reset_time' => '00:00 UTC',
                    'timezone' => 'UTC',
                ],
            ],
        ];
    }
}
