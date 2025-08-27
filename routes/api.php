<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PdfConversionController;
use App\Http\Controllers\Api\ExpansionRequestController;
use App\Http\Controllers\Api\ValidationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rotas públicas da API (sem autenticação)
Route::prefix('v1')->group(function () {
    
    // === Rotas de Conversão PDF ===
    Route::prefix('pdf')->name('api.pdf.')->group(function () {
        // Converter PDF para PDF/A
        Route::post('/convert', [PdfConversionController::class, 'convert'])->name('convert');
        
        // Download do arquivo convertido
        Route::get('/download/{conversionId}', [PdfConversionController::class, 'download'])->name('download');
        
        // Status de uma conversão específica
        Route::get('/status/{conversionId}', [PdfConversionController::class, 'status'])->name('status');
        
        // Histórico de conversões do usuário (por IP)
        Route::get('/history', [PdfConversionController::class, 'history'])->name('history');
        
        // Estatísticas do usuário
        Route::get('/stats', [PdfConversionController::class, 'stats'])->name('stats');
    });

    // === Rotas de Solicitação de Expansão ===
    Route::prefix('expansion')->name('api.expansion.')->group(function () {
        // Criar nova solicitação de expansão
        Route::post('/request', [ExpansionRequestController::class, 'store'])->name('request');
        
        // Status da solicitação atual
        Route::get('/status', [ExpansionRequestController::class, 'status'])->name('status');
        
        // Histórico de solicitações
        Route::get('/history', [ExpansionRequestController::class, 'history'])->name('history');
        
        // Informações sobre como solicitar expansão
        Route::get('/info', [ExpansionRequestController::class, 'info'])->name('info');
        
        // Cancelar solicitação pendente
        Route::delete('/cancel', [ExpansionRequestController::class, 'cancel'])->name('cancel');
    });

    // === Rotas de Validação ===
    Route::prefix('validate')->name('api.validate.')->group(function () {
        // Validar se PDF pode ser convertido
        Route::post('/pdf', [ValidationController::class, 'validatePdf'])->name('pdf');
        
        // Verificar se PDF já é PDF/A
        Route::post('/pdf-a', [ValidationController::class, 'checkPdfA'])->name('pdf-a');
        
        // Estimar tempo de processamento
        Route::post('/estimate', [ValidationController::class, 'estimateProcessing'])->name('estimate');
        
        // Formatos suportados
        Route::get('/formats', [ValidationController::class, 'supportedFormats'])->name('formats');
    });

    // === Rota de Status da API ===
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API PDF/A está funcionando.',
            'data' => [
                'service' => 'PDF/A Conversion API',
                'version' => '1.0.0',
                'status' => 'online',
                'timestamp' => now()->toISOString(),
                'endpoints' => [
                    'pdf_conversion' => '/api/v1/pdf',
                    'expansion_requests' => '/api/v1/expansion',
                    'validation' => '/api/v1/validate'
                ]
            ]
        ]);
    })->name('api.health');

});

// === Middleware de Rate Limiting ===
Route::middleware('throttle:api')->group(function () {
    // Todas as rotas da API já estão dentro do grupo v1 acima
    // O throttle será aplicado automaticamente
});

// === Rota para documentação da API (opcional) ===
Route::get('/docs', function () {
    return response()->json([
        'success' => true,
        'message' => 'Documentação da API PDF/A',
        'data' => [
            'base_url' => url('/api/v1'),
            'rate_limiting' => [
                'conversions' => 'Baseado em limite diário por IP',
                'api_calls' => '60 requests por minuto por IP',
                'expansion_requests' => '1 solicitação pendente por IP'
            ],
            'endpoints' => [
                'conversion' => [
                    'POST /pdf/convert' => 'Converter PDF para PDF/A',
                    'GET /pdf/download/{id}' => 'Download do arquivo convertido',
                    'GET /pdf/status/{id}' => 'Status de uma conversão',
                    'GET /pdf/history' => 'Histórico de conversões',
                    'GET /pdf/stats' => 'Estatísticas do usuário'
                ],
                'expansion' => [
                    'POST /expansion/request' => 'Solicitar expansão de limite',
                    'GET /expansion/status' => 'Status da solicitação',
                    'GET /expansion/history' => 'Histórico de solicitações',
                    'GET /expansion/info' => 'Informações sobre expansão',
                    'DELETE /expansion/cancel' => 'Cancelar solicitação pendente'
                ],
                'validation' => [
                    'POST /validate/pdf' => 'Validar PDF para conversão',
                    'POST /validate/pdf-a' => 'Verificar conformidade PDF/A',
                    'POST /validate/estimate' => 'Estimar tempo de processamento',
                    'GET /validate/formats' => 'Formatos suportados'
                ]
            ],
            'authentication' => 'Não requerida (API aberta)',
            'content_types' => [
                'input' => 'multipart/form-data (para uploads)',
                'output' => 'application/json'
            ]
        ]
    ]);
})->name('api.docs');
