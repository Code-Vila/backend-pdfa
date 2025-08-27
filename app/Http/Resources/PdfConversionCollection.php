<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PdfConversionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'summary' => [
                'total_conversions' => $this->collection->count(),
                'completed_conversions' => $this->collection->where('status', 'completed')->count(),
                'failed_conversions' => $this->collection->where('status', 'failed')->count(),
                'pending_conversions' => $this->collection->where('status', 'pending')->count(),
                'total_original_size' => $this->collection->sum('original_size'),
                'total_converted_size' => $this->collection->where('status', 'completed')->sum('converted_size'),
                'average_processing_time' => $this->collection->where('status', 'completed')->avg('processing_time'),
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
                'conversion_statistics' => [
                    'success_rate' => $this->calculateSuccessRate(),
                    'average_file_size' => $this->calculateAverageFileSize(),
                    'most_common_status' => $this->getMostCommonStatus(),
                ],
                'links' => [
                    'conversion_docs' => url('/api/docs#conversions'),
                    'support' => 'mailto:support@example.com',
                ],
            ],
        ];
    }

    /**
     * Calculate success rate percentage.
     */
    private function calculateSuccessRate(): float
    {
        $total = $this->collection->count();
        if ($total === 0) return 0;
        
        $completed = $this->collection->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Calculate average file size.
     */
    private function calculateAverageFileSize(): int
    {
        return (int) $this->collection->avg('original_size');
    }

    /**
     * Get most common status.
     */
    private function getMostCommonStatus(): string
    {
        $statusCounts = $this->collection->groupBy('status')->map->count();
        return $statusCounts->keys()->first() ?? 'unknown';
    }
}
