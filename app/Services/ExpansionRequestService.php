<?php

namespace App\Services;

use App\Models\UserExpansionRequest;
use App\Models\DailyUsage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class ExpansionRequestService
{
    /**
     * Criar nova solicitação de expansão
     */
    public function createExpansionRequest(array $data): UserExpansionRequest
    {
        // Verificar se já existe uma solicitação pendente para este IP
        $existingPending = UserExpansionRequest::where('ip_address', $data['ip_address'])
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            throw new Exception('Já existe uma solicitação pendente para este IP.');
        }

        // Verificar se este IP já possui um limite expandido ativo
        $usage = DailyUsage::where('ip_address', $data['ip_address'])
            ->where('is_expanded', true)
            ->first();

        if ($usage) {
            throw new Exception('Este IP já possui um limite expandido ativo.');
        }

        // Criar a solicitação
        $request = UserExpansionRequest::create([
            'email' => $data['email'],
            'name' => $data['name'],
            'company' => $data['company'],
            'justification' => $data['justification'],
            'requested_limit' => $data['requested_limit'],
            'ip_address' => $data['ip_address'],
            'user_agent' => $data['user_agent'],
            'status' => 'pending'
        ]);

        // Enviar notificação para o administrador
        $this->sendNotificationToAdmin($request);

        return $request;
    }

    /**
     * Obter status da solicitação
     */
    public function getRequestStatus(string $ipAddress): array
    {
        $expansionRequest = UserExpansionRequest::byIpAddress($ipAddress)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$expansionRequest) {
            throw new Exception('Nenhuma solicitação encontrada para este IP.');
        }

        return [
            'has_request' => true,
            'request' => [
                'id' => $expansionRequest->id,
                'status' => $expansionRequest->status,
                'email' => $expansionRequest->email,
                'name' => $expansionRequest->name,
                'company' => $expansionRequest->company,
                'requested_limit' => $expansionRequest->requested_limit,
                'admin_notes' => $expansionRequest->admin_notes,
                'created_at' => $expansionRequest->created_at->toISOString(),
                'updated_at' => $expansionRequest->updated_at->toISOString(),
                'processed_at' => $expansionRequest->processed_at?->toISOString()
            ],
            'current_usage' => $this->getCurrentUsageInfo($ipAddress)
        ];
    }

    /**
     * Obter histórico de solicitações
     */
    public function getRequestHistory(string $ipAddress, int $page, int $perPage): array
    {
        $requests = UserExpansionRequest::byIpAddress($ipAddress)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'requests' => $requests->items(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total()
            ],
            'current_usage' => $this->getCurrentUsageInfo($ipAddress)
        ];
    }

    /**
     * Obter informações sobre expansão
     */
    public function getExpansionInfo(string $ipAddress): array
    {
        $usage = DailyUsage::getForIpToday($ipAddress);
        $pendingRequest = UserExpansionRequest::byIpAddress($ipAddress)->pending()->first();
        
        return [
            'current_usage' => [
                'daily_limit' => $usage->daily_limit,
                'conversions_used_today' => $usage->conversions_count,
                'remaining_conversions' => $usage->getRemainingConversions(),
                'is_expanded' => $usage->is_expanded
            ],
            'expansion_info' => [
                'can_request' => !$pendingRequest && !$usage->is_expanded,
                'has_pending_request' => (bool) $pendingRequest,
                'is_already_expanded' => $usage->is_expanded,
                'min_requested_limit' => config('pdfa.default_daily_limit') + 1,
                'max_requested_limit' => 10000,
                'min_justification_length' => 50,
                'processing_time' => '24 horas',
                'requirements' => [
                    'Email válido para receber a resposta',
                    'Nome completo',
                    'Empresa (opcional)',
                    'Justificativa detalhada (mínimo 50 caracteres)',
                    'Limite solicitado entre ' . (config('pdfa.default_daily_limit') + 1) . ' e 10.000'
                ]
            ]
        ];
    }

    /**
     * Cancelar solicitação pendente
     */
    public function cancelPendingRequest(string $ipAddress): array
    {
        $expansionRequest = UserExpansionRequest::byIpAddress($ipAddress)->pending()->first();

        if (!$expansionRequest) {
            throw new Exception('Nenhuma solicitação pendente encontrada para este IP.');
        }

        $expansionRequest->update([
            'status' => 'cancelled',
            'processed_at' => now(),
            'admin_notes' => 'Cancelada pelo usuário'
        ]);

        return [
            'request_id' => $expansionRequest->id,
            'status' => $expansionRequest->status
        ];
    }

    /**
     * Obter informações de uso atual
     */
    public function getCurrentUsageInfo(string $ipAddress): array
    {
        $usage = DailyUsage::getForIpToday($ipAddress);
        
        return [
            'daily_limit' => $usage->daily_limit,
            'conversions_used_today' => $usage->conversions_count,
            'remaining_conversions' => $usage->getRemainingConversions(),
            'is_expanded' => $usage->is_expanded
        ];
    }

    /**
     * Enviar notificação para administração
     */
    protected function sendNotificationToAdmin(UserExpansionRequest $request): void
    {
        $message = $this->buildAdminNotificationMessage($request);

        // Por enquanto usar log, depois implementar email real
        Log::info('Email de notificação enviado para administração', [
            'request_id' => $request->id,
            'email' => $request->email,
            'requested_limit' => $request->requested_limit,
            'message' => $message
        ]);
    }

    /**
     * Construir mensagem de notificação para admin
     */
    protected function buildAdminNotificationMessage(UserExpansionRequest $request): string
    {
        return "Nova solicitação de expansão de limite:\n\n" .
               "ID da Solicitação: {$request->id}\n" .
               "Nome: {$request->name}\n" .
               "Email: {$request->email}\n" .
               "Empresa: " . ($request->company ?? 'Não informado') . "\n" .
               "IP: {$request->ip_address}\n" .
               "Limite Solicitado: {$request->requested_limit} conversões/dia\n" .
               "Justificativa: {$request->justification}\n\n" .
               "Data da Solicitação: {$request->created_at->format('d/m/Y H:i:s')}\n";
    }

    /**
     * Aprovar solicitação (método para admin)
     */
    public function approveRequest(int $requestId, string $adminNotes = null): UserExpansionRequest
    {
        $request = UserExpansionRequest::findOrFail($requestId);
        
        $request->update([
            'status' => 'approved',
            'processed_at' => now(),
            'admin_notes' => $adminNotes
        ]);

        // Atualizar limite do usuário
        $usage = DailyUsage::getForIpToday($request->ip_address);
        $usage->update([
            'daily_limit' => $request->requested_limit,
            'is_expanded' => true
        ]);

        return $request;
    }

    /**
     * Rejeitar solicitação (método para admin)
     */
    public function rejectRequest(int $requestId, string $adminNotes = null): UserExpansionRequest
    {
        $request = UserExpansionRequest::findOrFail($requestId);
        
        $request->update([
            'status' => 'rejected',
            'processed_at' => now(),
            'admin_notes' => $adminNotes
        ]);

        return $request;
    }
}
