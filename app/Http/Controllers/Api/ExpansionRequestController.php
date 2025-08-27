<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExpansionRequestService;
use App\Http\Requests\ExpansionRequest;
use App\Http\Resources\UserExpansionRequestResource;
use App\Http\Resources\DailyUsageResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class ExpansionRequestController extends Controller
{
    protected ExpansionRequestService $expansionService;

    public function __construct(ExpansionRequestService $expansionService)
    {
        $this->expansionService = $expansionService;
    }

    /**
     * Criar solicitação de expansão de limite
     */
    public function store(ExpansionRequest $request): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            $requestData = [
                'email' => $request->email,
                'name' => $request->name,
                'company' => $request->company,
                'justification' => $request->justification,
                'requested_limit' => $request->requested_limit,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ];

            $expansionRequest = $this->expansionService->createExpansionRequest($requestData);

            return response()->json([
                'success' => true,
                'message' => 'Solicitação enviada com sucesso! Você receberá uma resposta por e-mail em até 24 horas.',
                'data' => new UserExpansionRequestResource($expansionRequest)
            ], 201);

        } catch (Exception $e) {
            if ($e->getMessage() === 'Já existe uma solicitação pendente para este IP.') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'PENDING_REQUEST_EXISTS'
                ], 409);
            }

            if ($e->getMessage() === 'Este IP já possui um limite expandido ativo.') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'ALREADY_EXPANDED'
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Status da solicitação
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $statusData = $this->expansionService->getRequestStatus($ipAddress);

            return response()->json([
                'success' => true,
                'data' => $statusData
            ]);

        } catch (Exception $e) {
            if ($e->getMessage() === 'Nenhuma solicitação encontrada para este IP.') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => [
                        'has_request' => false,
                        'current_usage' => $this->expansionService->getCurrentUsageInfo($ipAddress)
                    ]
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter status da solicitação.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Histórico de solicitações
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 10), 50);

            $historyData = $this->expansionService->getRequestHistory($ipAddress, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'requests' => UserExpansionRequestResource::collection($historyData['requests']),
                    'pagination' => $historyData['pagination'],
                    'current_usage' => new DailyUsageResource((object) $historyData['current_usage'])
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter histórico de solicitações.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Informações sobre como solicitar expansão
     */
    public function info(Request $request): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $infoData = $this->expansionService->getExpansionInfo($ipAddress);

            return response()->json([
                'success' => true,
                'data' => [
                    'current_usage' => new DailyUsageResource((object) $infoData['current_usage']),
                    'expansion_info' => $infoData['expansion_info']
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter informações.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Cancelar solicitação pendente
     */
    public function cancel(Request $request): JsonResponse
    {
        try {
            $ipAddress = $request->ip();
            $result = $this->expansionService->cancelPendingRequest($ipAddress);

            return response()->json([
                'success' => true,
                'message' => 'Solicitação cancelada com sucesso.',
                'data' => $result
            ]);

        } catch (Exception $e) {
            if ($e->getMessage() === 'Nenhuma solicitação pendente encontrada para este IP.') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao cancelar solicitação.',
                'error' => config('app.debug') ? $e->getMessage() : 'Tente novamente mais tarde.'
            ], 500);
        }
    }
}
