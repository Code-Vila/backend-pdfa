<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class PdfConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'user_agent',
        'original_filename',
        'original_path',
        'converted_filename',
        'converted_path',
        'original_size',
        'converted_size',
        'status',
        'processing_time',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'original_size' => 'integer',
        'converted_size' => 'integer',
        'processing_time' => 'integer',
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
     * Scope para conversões completadas
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope para conversões de hoje
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    /**
     * Scope para conversões falhadas
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope para conversões em processamento
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    /**
     * Accessor para tamanho original formatado
     */
    public function getFormattedOriginalSizeAttribute(): string
    {
        return $this->formatBytes($this->original_size);
    }

    /**
     * Accessor para tamanho convertido formatado
     */
    public function getFormattedConvertedSizeAttribute(): string
    {
        return $this->formatBytes($this->converted_size);
    }

    /**
     * Accessor para tempo de processamento formatado
     */
    public function getFormattedProcessingTimeAttribute(): ?string
    {
        if (!$this->processing_time) {
            return null;
        }

        if ($this->processing_time < 1000) {
            return $this->processing_time . 'ms';
        } elseif ($this->processing_time < 60000) {
            return round($this->processing_time / 1000, 1) . 's';
        } else {
            return round($this->processing_time / 60000, 1) . 'min';
        }
    }

    /**
     * Verificar se a conversão está disponível para download
     */
    public function isDownloadable(): bool
    {
        return $this->status === 'completed' && !empty($this->converted_path);
    }

    /**
     * Verificar se a conversão falhou
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Verificar se está em processamento
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Obter economia de espaço (se houver)
     */
    public function getSpaceSavingsAttribute(): ?float
    {
        if (!$this->original_size || !$this->converted_size) {
            return null;
        }

        $savings = (($this->original_size - $this->converted_size) / $this->original_size) * 100;
        return round($savings, 2);
    }

    /**
     * Formatar bytes em formato legível
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Estatísticas globais do dia
     */
    public static function getTodayStats(): array
    {
        $today = Carbon::today();
        
        return [
            'total_conversions' => static::whereDate('created_at', $today)->count(),
            'successful_conversions' => static::whereDate('created_at', $today)->completed()->count(),
            'failed_conversions' => static::whereDate('created_at', $today)->failed()->count(),
            'processing_conversions' => static::whereDate('created_at', $today)->processing()->count(),
            'total_size_processed' => static::whereDate('created_at', $today)->sum('original_size'),
            'total_size_output' => static::whereDate('created_at', $today)->sum('converted_size'),
        ];
    }

    /**
     * Limpar conversões antigas
     */
    public static function cleanupOld(int $daysOld = 7): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);
        return static::where('created_at', '<', $cutoffDate)->delete();
    }
}
