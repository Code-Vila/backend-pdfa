<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PdfConversionService;
use App\Http\Requests\PdfValidationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class ValidationController extends Controller
{
    protected PdfConversionService $conversionService;

    public function __construct(PdfConversionService $conversionService)
    {
        $this->conversionService = $conversionService;
    }

    /**
     * Validar se um PDF é compatível com PDF/A
     */
    public function validatePdf(PdfValidationRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $validation = $this->conversionService->validatePdfForConversion($file);

            return response()->json([
                'success' => true,
                'message' => 'Validação concluída.',
                'data' => [
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'formatted_size' => $this->formatBytes($file->getSize()),
                    'validation_result' => $validation
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao validar o arquivo.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Verificar se um PDF já é PDF/A
     */
    public function checkPdfA(PdfValidationRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $check = $this->conversionService->checkPdfACompliance($file);

            return response()->json([
                'success' => true,
                'message' => 'Verificação concluída.',
                'data' => [
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'formatted_size' => $this->formatBytes($file->getSize()),
                    'compliance_result' => $check
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar o arquivo.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Estimar tempo de processamento
     */
    public function estimateProcessing(PdfValidationRequest $request): JsonResponse
    {
        try {
            $files = $request->file('files');
            $estimates = [];
            $totalEstimatedTime = 0;

            foreach ($files as $index => $file) {
                $estimate = $this->conversionService->estimateProcessingTime($file);
                $estimates[] = [
                    'file_index' => $index,
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'formatted_size' => $this->formatBytes($file->getSize()),
                    'estimation' => $estimate
                ];
                $totalEstimatedTime += $estimate['estimated_time'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Estimativa concluída.',
                'data' => [
                    'file_estimates' => $estimates,
                    'summary' => [
                        'total_files' => count($files),
                        'total_estimated_time_ms' => $totalEstimatedTime,
                        'total_estimated_time_human' => $this->formatTime($totalEstimatedTime),
                        'processing_mode' => 'sequential',
                        'note' => 'Tempos são estimativas baseadas no tamanho e complexidade dos arquivos.'
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao estimar tempo de processamento.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Obter informações sobre os formatos suportados
     */
    public function supportedFormats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'input_formats' => [
                    'pdf' => [
                        'name' => 'PDF',
                        'mime_types' => ['application/pdf'],
                        'extensions' => ['.pdf'],
                        'max_size_mb' => config('pdfa.max_file_size', 10240) / 1024,
                        'supported_versions' => ['1.3', '1.4', '1.5', '1.6', '1.7', '2.0']
                    ]
                ],
                'output_formats' => [
                    'pdf_a_1b' => [
                        'name' => 'PDF/A-1b',
                        'description' => 'ISO 19005-1:2005 - Visual appearance preservation',
                        'use_cases' => ['Long-term archiving', 'Legal documents']
                    ],
                    'pdf_a_2b' => [
                        'name' => 'PDF/A-2b',
                        'description' => 'ISO 19005-2:2011 - Enhanced features',
                        'use_cases' => ['Modern documents', 'Multimedia content']
                    ],
                    'pdf_a_3b' => [
                        'name' => 'PDF/A-3b',
                        'description' => 'ISO 19005-3:2012 - Embedded files support',
                        'use_cases' => ['Documents with attachments', 'Complex workflows']
                    ]
                ],
                'processing_options' => [
                    'quality_levels' => ['low', 'medium', 'high'],
                    'compression_options' => ['lossless', 'optimized', 'maximum'],
                    'color_profiles' => ['sRGB', 'Adobe RGB', 'CMYK']
                ],
                'limitations' => [
                    'max_file_size' => config('pdfa.max_file_size', 10240) . ' KB',
                    'max_files_per_batch' => 5,
                    'supported_languages' => ['Portuguese', 'English', 'Spanish'],
                    'processing_timeout' => config('pdfa.processing_timeout', 300) . ' seconds'
                ]
            ]
        ]);
    }

    /**
     * Formatar bytes em formato legível
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Formatar tempo em formato legível
     */
    private function formatTime(int $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return $milliseconds . 'ms';
        }
        
        $seconds = $milliseconds / 1000;
        
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }
        
        $minutes = $seconds / 60;
        return round($minutes, 1) . 'min';
    }
}
