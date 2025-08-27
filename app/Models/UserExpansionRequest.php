<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class UserExpansionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'user_agent',
        'email',
        'name',
        'justification',
        'requested_limit',
        'status',
        'admin_notes',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'requested_limit' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Scope para filtrar por IP
     */
    public function scopeByIpAddress(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope para status pendente
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope para status aprovado
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope para status rejeitado
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope para status cancelado
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope para solicitações recentes (últimos 30 dias)
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays(30));
    }

    /**
     * Verificar se existe solicitação pendente para o IP
     */
    public static function hasPendingRequest(string $ipAddress): bool
    {
        return static::byIpAddress($ipAddress)->pending()->exists();
    }

    /**
     * Obter solicitação pendente para o IP
     */
    public static function getPendingRequest(string $ipAddress): ?self
    {
        return static::byIpAddress($ipAddress)->pending()->first();
    }

    /**
     * Verificar se está pendente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Verificar se foi aprovada
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Verificar se foi rejeitada
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Verificar se foi cancelada
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Aprovar solicitação
     */
    public function approve(string $adminNotes = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'admin_notes' => $adminNotes,
            'approved_at' => Carbon::now(),
            'rejected_at' => null,
        ]);
    }

    /**
     * Rejeitar solicitação
     */
    public function reject(string $adminNotes = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'admin_notes' => $adminNotes,
            'rejected_at' => Carbon::now(),
            'approved_at' => null,
        ]);
    }

    /**
     * Cancelar solicitação
     */
    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Obter tempo desde a solicitação
     */
    public function getTimeSinceRequestAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Obter tempo de processamento (se processada)
     */
    public function getProcessingTimeAttribute(): ?string
    {
        if ($this->isPending()) {
            return null;
        }

        $processedAt = $this->approved_at ?? $this->rejected_at;
        if (!$processedAt) {
            return null;
        }

        return $this->created_at->diffForHumans($processedAt, true);
    }

    /**
     * Obter status formatado
     */
    public function getFormattedStatusAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_APPROVED => 'Aprovada',
            self::STATUS_REJECTED => 'Rejeitada',
            self::STATUS_CANCELLED => 'Cancelada',
            default => 'Desconhecido'
        };
    }

    /**
     * Obter cor do status para UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'dark'
        };
    }

    /**
     * Estatísticas das solicitações
     */
    public static function getStats(): array
    {
        return [
            'total_requests' => static::count(),
            'pending_requests' => static::pending()->count(),
            'approved_requests' => static::approved()->count(),
            'rejected_requests' => static::rejected()->count(),
            'cancelled_requests' => static::cancelled()->count(),
            'recent_requests' => static::recent()->count(),
            'approval_rate' => static::getApprovalRate(),
        ];
    }

    /**
     * Obter taxa de aprovação
     */
    public static function getApprovalRate(): float
    {
        $processed = static::whereIn('status', [self::STATUS_APPROVED, self::STATUS_REJECTED])->count();
        
        if ($processed === 0) {
            return 0;
        }

        $approved = static::approved()->count();
        return round(($approved / $processed) * 100, 2);
    }

    /**
     * Relatório mensal
     */
    public static function getMonthlyReport(): array
    {
        $monthAgo = Carbon::now()->subMonth();
        
        return static::where('created_at', '>=', $monthAgo)
                    ->selectRaw('
                        DATE(created_at) as date,
                        COUNT(*) as total_requests,
                        SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected,
                        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending
                    ')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->toArray();
    }

    /**
     * Obter solicitações que precisam de atenção (pendentes há mais de 24h)
     */
    public static function getNeedingAttention(): \Illuminate\Database\Eloquent\Collection
    {
        return static::pending()
                    ->where('created_at', '<', Carbon::now()->subHours(24))
                    ->orderBy('created_at')
                    ->get();
    }

    /**
     * Relacionamento com uso diário
     */
    public function dailyUsage()
    {
        return $this->hasOne(DailyUsage::class, 'ip_address', 'ip_address')
                    ->where('date', Carbon::today()->toDateString());
    }

    /**
     * Relacionamento com conversões
     */
    public function conversions()
    {
        return $this->hasMany(PdfConversion::class, 'ip_address', 'ip_address');
    }

    /**
     * Limpar solicitações antigas (rejeitadas/canceladas há mais de 90 dias)
     */
    public static function cleanupOldRequests(int $daysOld = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        return static::whereIn('status', [self::STATUS_REJECTED, self::STATUS_CANCELLED])
                    ->where('updated_at', '<', $cutoffDate)
                    ->delete();
    }
}
