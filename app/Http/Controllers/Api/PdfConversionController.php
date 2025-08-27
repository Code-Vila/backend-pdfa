<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PdfConversionService;
use App\Http\Requests\PdfConversionRequest;
use App\Http\Resources\PdfConversionResource;
use App\Http\Resources\PdfConversionCollection;
use App\Http\Resources\DailyUsageResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class PdfConversionController extends Controller
{
    protected PdfConversionService $conversionService;

    public function __construct(PdfConversionService $conversionService)
    {
        $this->conversionService = $conversionService;
    }

    /**
     * Converte PDF para PDF/A
     */
    public function convert(PdfConversionRequest $request): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $files = $request->file('files');

            // Verificar se tem conversões suficientes disponíveis
            $usageCheck = $this->conversionService->checkUsageLimit($ipAddress, count($files));
            
            if (!$usageCheck['can_convert']) {
                return response()->json([
                    'success' => false,
                    'message' => $usageCheck['message'],
                    'data' => $usageCheck['usage_info']
                ], 429);
            }

            $conversions = [];
            $errors = [];

            foreach ($files as $index => $file) {
                try {
                    $conversion = $this->conversionService->convertToPdfA($file, $ipAddress, $userAgent);
                    $conversions[] = new PdfConversionResource($conversion);
                } catch (Exception $e) {
                    $errors[] = [
                        'file_index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Atualizar informações de uso
            $updatedUsage = $this->conversionService->getUsageInfo($ipAddress);

            return response()->json([
                'success' => count($conversions) > 0,
                'message' => count($conversions) > 0 ? 'Conversões realizadas com sucesso.' : 'Nenhuma conversão foi bem-sucedida.',
                'data' => [
                    'conversions' => $conversions,
                    'errors' => $errors,
                    'usage_info' => new DailyUsageResource((object) $updatedUsage)
                ]
            ], count($conversions) > 0 ? 200 : 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Download do arquivo convertido
     */
    public function download(Request $request, int $conversionId): JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            $ipAddress = $request->ip();
            $fileData = $this->conversionService->getConvertedFile($conversionId, $ipAddress);

            return response()->download(
                $fileData['path'],
                $fileData['filename'],
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => $fileData['size']
                ]
            );

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Arquivo não encontrado ou não autorizado.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Status da conversão
     */
    public function status(Request $request, int $conversionId): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $conversionData = $this->conversionService->getConversionStatus($conversionId, $ipAddress);

            return response()->json([
                'success' => true,
                'data' => $conversionData
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversão não encontrada.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Histórico de conversões do usuário
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 15), 50);

            $historyData = $this->conversionService->getConversionHistory($ipAddress, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => new PdfConversionCollection(collect($historyData['conversions'])),
                'pagination' => $historyData['pagination']
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter histórico.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Estatísticas do usuário
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $statsData = $this->conversionService->getUserStats($ipAddress);

            return response()->json([
                'success' => true,
                'data' => [
                    'daily_usage' => new DailyUsageResource((object) $statsData['daily_usage']),
                    'total_stats' => $statsData['total_stats']
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter estatísticas.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }
}
