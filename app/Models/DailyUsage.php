<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class DailyUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'date',
        'conversions_count',
        'daily_limit',
        'is_expanded',
        'expanded_at',
        'expansion_expires_at',
    ];

    protected $casts = [
        'date' => 'date',
        'conversions_count' => 'integer',
        'daily_limit' => 'integer',
        'is_expanded' => 'boolean',
        'expanded_at' => 'datetime',
        'expansion_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope para filtrar por IP
     */
    public function scopeByIpAddress(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope para data específica
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('date', $date->toDateString());
    }

    /**
     * Scope para hoje
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->where('date', Carbon::today()->toDateString());
    }

    /**
     * Scope para expansões ativas
     */
    public function scopeExpanded(Builder $query): Builder
    {
        return $query->where('is_expanded', true)
                    ->where('expansion_expires_at', '>', Carbon::now());
    }

    /**
     * Obter uso para IP específico hoje
     */
    public static function getForIpToday(string $ipAddress): self
    {
        $today = Carbon::today();
        
        return static::firstOrCreate(
            [
                'ip_address' => $ipAddress,
                'date' => $today->toDateString(),
            ],
            [
                'conversions_count' => 0,
                'daily_limit' => config('pdfa.default_daily_limit', 10),
                'is_expanded' => false,
            ]
        );
    }

    /**
     * Verificar se pode converter
     */
    public static function canConvert(string $ipAddress, int $requestedCount = 1): bool
    {
        $usage = static::getForIpToday($ipAddress);
        return $usage->getRemainingConversions() >= $requestedCount;
    }

    /**
     * Obter conversões restantes
     */
    public function getRemainingConversions(): int
    {
        return max(0, $this->daily_limit - $this->conversions_count);
    }

    /**
     * Incrementar contador de conversões
     */
    public function incrementConversions(int $count = 1): void
    {
        $this->increment('conversions_count', $count);
    }

    /**
     * Aplicar expansão
     */
    public function applyExpansion(int $newLimit, int $durationDays = 30): void
    {
        $this->update([
            'daily_limit' => $newLimit,
            'is_expanded' => true,
            'expanded_at' => Carbon::now(),
            'expansion_expires_at' => Carbon::now()->addDays($durationDays),
        ]);
    }

    /**
     * Remover expansão
     */
    public function removeExpansion(): void
    {
        $this->update([
            'daily_limit' => config('pdfa.default_daily_limit', 10),
            'is_expanded' => false,
            'expanded_at' => null,
            'expansion_expires_at' => null,
        ]);
    }

    /**
     * Verificar se a expansão expirou
     */
    public function hasExpansionExpired(): bool
    {
        return $this->is_expanded && 
               $this->expansion_expires_at && 
               $this->expansion_expires_at->isPast();
    }

    /**
     * Limpar expansões expiradas
     */
    public static function cleanupExpiredExpansions(): int
    {
        $expired = static::where('is_expanded', true)
                        ->where('expansion_expires_at', '<', Carbon::now())
                        ->get();

        $count = 0;
        foreach ($expired as $usage) {
            $usage->removeExpansion();
            $count++;
        }

        return $count;
    }

    /**
     * Obter porcentagem de uso
     */
    public function getUsagePercentageAttribute(): float
    {
        if ($this->daily_limit === 0) {
            return 0;
        }

        return round(($this->conversions_count / $this->daily_limit) * 100, 2);
    }

    /**
     * Verificar se está no limite
     */
    public function isAtLimit(): bool
    {
        return $this->conversions_count >= $this->daily_limit;
    }

    /**
     * Verificar se está próximo do limite (80%)
     */
    public function isNearLimit(): bool
    {
        return $this->getUsagePercentageAttribute() >= 80;
    }

    /**
     * Resetar contador diário
     */
    public function resetDaily(): void
    {
        $this->update(['conversions_count' => 0]);
    }

    /**
     * Estatísticas do dia
     */
    public static function getTodayStats(): array
    {
        $today = Carbon::today()->toDateString();
        
        return [
            'total_users' => static::where('date', $today)->count(),
            'active_users' => static::where('date', $today)->where('conversions_count', '>', 0)->count(),
            'total_conversions' => static::where('date', $today)->sum('conversions_count'),
            'users_at_limit' => static::where('date', $today)->whereRaw('conversions_count >= daily_limit')->count(),
            'expanded_users' => static::where('date', $today)->where('is_expanded', true)->count(),
        ];
    }

    /**
     * Obter relatório semanal
     */
    public static function getWeeklyReport(): array
    {
        $weekAgo = Carbon::now()->subWeek();
        
        return static::where('date', '>=', $weekAgo->toDateString())
                    ->selectRaw('
                        date,
                        COUNT(*) as users_count,
                        SUM(conversions_count) as total_conversions,
                        SUM(CASE WHEN conversions_count >= daily_limit THEN 1 ELSE 0 END) as users_at_limit,
                        SUM(CASE WHEN is_expanded THEN 1 ELSE 0 END) as expanded_users
                    ')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->toArray();
    }

    /**
     * Relacionamento com conversões
     */
    public function conversions()
    {
        return $this->hasMany(PdfConversion::class, 'ip_address', 'ip_address')
                    ->whereDate('created_at', $this->date);
    }

    /**
     * Relacionamento com solicitações de expansão
     */
    public function expansionRequests()
    {
        return $this->hasMany(UserExpansionRequest::class, 'ip_address', 'ip_address');
    }
}
