<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PdfConversionResource extends JsonResource
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
            'original_filename' => $this->original_filename,
            'converted_filename' => $this->when($this->status === 'completed', $this->converted_filename),
            'file_info' => [
                'original_size' => $this->original_size,
                'converted_size' => $this->when($this->status === 'completed', $this->converted_size),
                'formatted_original_size' => $this->formatted_original_size,
                'formatted_converted_size' => $this->when($this->status === 'completed', $this->formatted_converted_size),
            ],
            'processing' => [
                'processing_time' => $this->when($this->processing_time, $this->processing_time . 'ms'),
                'started_at' => $this->created_at->toISOString(),
                'completed_at' => $this->when($this->status === 'completed', $this->updated_at->toISOString()),
            ],
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            'download_url' => $this->when(
                $this->status === 'completed',
                route('api.pdf.download', $this->id)
            ),
            'ip_address' => $this->when($request->query('include_ip'), $this->ip_address),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
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
                'conversion_status_codes' => [
                    'pending' => 'Aguardando processamento',
                    'processing' => 'Em processamento',
                    'completed' => 'Concluído com sucesso',
                    'failed' => 'Falha no processamento'
                ]
            ],
        ];
    }
}
