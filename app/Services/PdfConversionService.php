<?php

namespace App\Services;

use App\Models\PdfConversion;
use App\Models\DailyUsage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class PdfConversionService
{
    protected string $storagePath;
    protected int $maxFileSize;

    public function __construct()
    {
        $this->storagePath = config('pdfa.storage_path', 'pdfa');
        $this->maxFileSize = config('pdfa.max_file_size', 10240); // KB
    }

    /**
     * Converte PDF para PDF/A
     */
    public function convertToPdfA(UploadedFile $file, string $ipAddress, string $userAgent = null): PdfConversion
    {
        // Validações
        $this->validateFile($file);
        $this->validateRateLimit($ipAddress);

        $startTime = microtime(true);

        // Criar registro de conversão
        $conversion = PdfConversion::create([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'original_filename' => $file->getClientOriginalName(),
            'original_path' => '',
            'converted_filename' => '',
            'converted_path' => '',
            'original_size' => $file->getSize(),
            'converted_size' => 0,
            'status' => 'processing',
        ]);

        try {
            // Salvar arquivo original
            $originalPath = $this->storeOriginalFile($file, $conversion->id);
            $conversion->update(['original_path' => $originalPath]);

            // Executar conversão
            $convertedPath = $this->executeConversion($originalPath, $conversion->id);
            
            // Obter tamanho do arquivo convertido
            $convertedSize = Storage::size($convertedPath);

            // Atualizar registro
            $conversion->update([
                'converted_filename' => basename($convertedPath),
                'converted_path' => $convertedPath,
                'converted_size' => $convertedSize,
                'status' => 'completed',
                'processing_time' => round((microtime(true) - $startTime) * 1000), // ms
                'metadata' => $this->extractPdfMetadata($originalPath),
            ]);

            // Atualizar contador de uso diário
            $this->updateDailyUsage($ipAddress);

            return $conversion;

        } catch (Exception $e) {
            $conversion->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_time' => round((microtime(true) - $startTime) * 1000),
            ]);

            throw $e;
        }
    }

    /**
     * Valida o arquivo uploaded
     */
    protected function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new Exception('Arquivo inválido ou corrompido.');
        }

        if ($file->getSize() > ($this->maxFileSize * 1024)) {
            throw new Exception("Arquivo muito grande. Tamanho máximo: {$this->maxFileSize}KB");
        }

        if ($file->getClientOriginalExtension() !== 'pdf') {
            throw new Exception('Apenas arquivos PDF são aceitos.');
        }

        // Verificar se é realmente um PDF
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, ['application/pdf', 'application/x-pdf'])) {
            throw new Exception('Arquivo não é um PDF válido.');
        }
    }

    /**
     * Valida rate limiting
     */
    protected function validateRateLimit(string $ipAddress): void
    {
        if (!DailyUsage::canConvert($ipAddress)) {
            $usage = DailyUsage::getForIpToday($ipAddress);
            throw new Exception(
                "Limite diário de conversões atingido ({$usage->daily_limit}). " .
                "Tente novamente amanhã ou solicite expansão do limite."
            );
        }
    }

    /**
     * Armazena arquivo original
     */
    protected function storeOriginalFile(UploadedFile $file, int $conversionId): string
    {
        $filename = "original_{$conversionId}_" . Str::random(8) . '.pdf';
        $path = "{$this->storagePath}/originals/{$filename}";
        
        Storage::putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return $path;
    }

    /**
     * Executa a conversão PDF/A usando Ghostscript
     */
    protected function executeConversion(string $originalPath, int $conversionId): string
    {
        $inputPath = Storage::path($originalPath);
        $outputFilename = "converted_{$conversionId}_" . Str::random(8) . '.pdf';
        $outputPath = "{$this->storagePath}/converted/{$outputFilename}";
        $fullOutputPath = Storage::path($outputPath);

        // Criar diretório se não existir
        Storage::makeDirectory(dirname($outputPath));

        // Comando Ghostscript para conversão PDF/A
        $command = sprintf(
            'gs -dPDFA=1 -dBATCH -dNOPAUSE -sColorConversionStrategy=RGB ' .
            '-sDEVICE=pdfwrite -dPDFACompatibilityPolicy=1 ' .
            '-sOutputFile=%s %s 2>&1',
            escapeshellarg($fullOutputPath),
            escapeshellarg($inputPath)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($fullOutputPath)) {
            throw new Exception('Falha na conversão PDF/A: ' . implode("\n", $output));
        }

        return $outputPath;
    }

    /**
     * Extrai metadados do PDF
     */
    protected function extractPdfMetadata(string $pdfPath): array
    {
        try {
            $fullPath = Storage::path($pdfPath);
            $command = sprintf('pdfinfo %s 2>&1', escapeshellarg($fullPath));
            
            $output = [];
            exec($command, $output);

            $metadata = [];
            foreach ($output as $line) {
                if (strpos($line, ':') !== false) {
                    [$key, $value] = explode(':', $line, 2);
                    $metadata[trim($key)] = trim($value);
                }
            }

            return $metadata;
        } catch (Exception $e) {
            return ['error' => 'Não foi possível extrair metadados'];
        }
    }

    /**
     * Atualiza contador de uso diário
     */
    protected function updateDailyUsage(string $ipAddress): void
    {
        $usage = DailyUsage::getForIpToday($ipAddress);
        $usage->incrementConversions();
    }

    /**
     * Obtém arquivo convertido para download
     */
    public function getConvertedFile(int $conversionId, string $ipAddress): array
    {
        $conversion = PdfConversion::where('id', $conversionId)
            ->where('ip_address', $ipAddress)
            ->where('status', 'completed')
            ->firstOrFail();

        if (!Storage::exists($conversion->converted_path)) {
            throw new Exception('Arquivo convertido não encontrado.');
        }

        return [
            'path' => Storage::path($conversion->converted_path),
            'filename' => $conversion->converted_filename,
            'size' => $conversion->converted_size,
        ];
    }

    /**
     * Remove arquivos antigos (cleanup)
     */
    public function cleanupOldFiles(int $daysOld = 7): int
    {
        $cutoffDate = now()->subDays($daysOld);
        $conversions = PdfConversion::where('created_at', '<', $cutoffDate)->get();
        
        $deletedCount = 0;
        foreach ($conversions as $conversion) {
            try {
                if ($conversion->original_path && Storage::exists($conversion->original_path)) {
                    Storage::delete($conversion->original_path);
                }
                if ($conversion->converted_path && Storage::exists($conversion->converted_path)) {
                    Storage::delete($conversion->converted_path);
                }
                $conversion->delete();
                $deletedCount++;
            } catch (Exception $e) {
                // Log error but continue
            }
        }

        return $deletedCount;
    }

    /**
     * Validar PDF para conversão
     */
    public function validatePdfForConversion(UploadedFile $file): array
    {
        $tempPath = $file->store('temp');
        $fullPath = storage_path('app/' . $tempPath);
        
        try {
            $validation = [
                'is_valid' => true,
                'is_pdf_a' => false,
                'pdf_version' => null,
                'issues' => [],
                'recommendations' => [],
                'can_convert' => true,
                'estimated_processing_time' => null
            ];

            // Verificar se é um PDF válido
            $command = "pdfinfo \"$fullPath\" 2>&1";
            $output = shell_exec($command);
            
            if (strpos($output, 'PDF document') === false) {
                $validation['is_valid'] = false;
                $validation['can_convert'] = false;
                $validation['issues'][] = 'Arquivo não é um PDF válido';
                return $validation;
            }

            // Extrair versão do PDF
            if (preg_match('/PDF version:\s+(\d+\.\d+)/', $output, $matches)) {
                $validation['pdf_version'] = $matches[1];
            }

            // Verificar se já é PDF/A
            if (strpos($output, 'PDF/A') !== false) {
                $validation['is_pdf_a'] = true;
                $validation['recommendations'][] = 'Este arquivo já é compatível com PDF/A';
            }

            // Estimar tempo de processamento
            $fileSize = $file->getSize();
            $estimatedTime = max(1000, ($fileSize / 1024) * 100); // ~100ms por KB
            $validation['estimated_processing_time'] = round($estimatedTime) . 'ms';

            return $validation;

        } finally {
            Storage::delete($tempPath);
        }
    }

    /**
     * Verificar conformidade com PDF/A
     */
    public function checkPdfACompliance(UploadedFile $file): array
    {
        $tempPath = $file->store('temp');
        $fullPath = storage_path('app/' . $tempPath);
        
        try {
            $compliance = [
                'is_pdf_a' => false,
                'pdf_a_level' => null,
                'compliance_score' => 0,
                'compliance_details' => [],
                'non_compliant_elements' => [],
                'recommendations' => []
            ];

            // Verificar metadados PDF/A
            $pdfinfoCommand = "pdfinfo \"$fullPath\" 2>&1";
            $pdfinfoOutput = shell_exec($pdfinfoCommand);

            if (strpos($pdfinfoOutput, 'PDF/A') !== false) {
                $compliance['is_pdf_a'] = true;
                $compliance['compliance_score'] = 95;
                
                // Determinar nível PDF/A
                if (strpos($pdfinfoOutput, 'PDF/A-1') !== false) {
                    $compliance['pdf_a_level'] = 'PDF/A-1b';
                } elseif (strpos($pdfinfoOutput, 'PDF/A-2') !== false) {
                    $compliance['pdf_a_level'] = 'PDF/A-2b';
                } elseif (strpos($pdfinfoOutput, 'PDF/A-3') !== false) {
                    $compliance['pdf_a_level'] = 'PDF/A-3b';
                }
                
                $compliance['compliance_details'][] = 'Documento está em conformidade com PDF/A';
            } else {
                $compliance['compliance_score'] = 30;
                $compliance['non_compliant_elements'][] = 'Não possui metadados PDF/A';
                $compliance['recommendations'][] = 'Converter para PDF/A para conformidade de arquivo';
            }

            $compliance['compliance_score'] = max(0, min(100, $compliance['compliance_score']));

            return $compliance;

        } finally {
            Storage::delete($tempPath);
        }
    }

    /**
     * Estimar tempo de processamento
     */
    public function estimateProcessingTime(UploadedFile $file): array
    {
        $fileSize = $file->getSize();
        $baseTime = 500; // tempo base em ms
        $sizeMultiplier = ($fileSize / 1024) * 50; // 50ms por KB
        
        $estimate = [
            'estimated_time' => round($baseTime + $sizeMultiplier),
            'estimated_time_human' => '',
            'complexity_score' => 'medium',
            'factors' => []
        ];

        // Determinar complexidade baseada no tamanho
        if ($fileSize < 512 * 1024) { // < 512KB
            $estimate['complexity_score'] = 'low';
            $estimate['factors'][] = 'Arquivo pequeno';
        } elseif ($fileSize > 5 * 1024 * 1024) { // > 5MB
            $estimate['complexity_score'] = 'high';
            $estimate['factors'][] = 'Arquivo grande';
            $estimate['estimated_time'] *= 1.5;
        }

        // Verificar se tem muitas páginas (estimativa baseada no tamanho)
        $estimatedPages = max(1, $fileSize / (50 * 1024)); // ~50KB por página
        if ($estimatedPages > 50) {
            $estimate['factors'][] = 'Muitas páginas estimadas (~' . round($estimatedPages) . ')';
            $estimate['estimated_time'] *= 1.2;
        }

        $estimate['factors'][] = 'Tamanho do arquivo: ' . $this->formatBytes($fileSize);

        // Formatar tempo legível
        $time = $estimate['estimated_time'];
        if ($time < 1000) {
            $estimate['estimated_time_human'] = $time . 'ms';
        } elseif ($time < 60000) {
            $estimate['estimated_time_human'] = round($time / 1000, 1) . 's';
        } else {
            $estimate['estimated_time_human'] = round($time / 60000, 1) . 'min';
        }

        $estimate['estimated_time'] = round($estimate['estimated_time']);

        return $estimate;
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
     * Verificar limite de uso
     */
    public function checkUsageLimit(string $ipAddress, int $filesCount): array
    {
        $usage = DailyUsage::getForIpToday($ipAddress);
        $remainingConversions = $usage->getRemainingConversions();
        
        if ($filesCount > $remainingConversions) {
            return [
                'can_convert' => false,
                'message' => "Limite insuficiente. Você tem {$remainingConversions} conversões restantes hoje.",
                'usage_info' => [
                    'remaining_conversions' => $remainingConversions,
                    'daily_limit' => $usage->daily_limit,
                    'used_today' => $usage->conversions_count
                ]
            ];
        }

        return [
            'can_convert' => true,
            'message' => 'Limite disponível'
        ];
    }

    /**
     * Obter informações de uso
     */
    public function getUsageInfo(string $ipAddress): array
    {
        $usage = DailyUsage::getForIpToday($ipAddress);
        
        return [
            'conversions_count' => $usage->conversions_count,
            'daily_limit' => $usage->daily_limit,
            'remaining_conversions' => $usage->getRemainingConversions(),
            'is_expanded' => $usage->is_expanded
        ];
    }

    /**
     * Obter status de uma conversão
     */
    public function getConversionStatus(int $conversionId, string $ipAddress): array
    {
        $conversion = PdfConversion::where('id', $conversionId)
            ->where('ip_address', $ipAddress)
            ->firstOrFail();

        return [
            'id' => $conversion->id,
            'status' => $conversion->status,
            'original_filename' => $conversion->original_filename,
            'converted_filename' => $conversion->converted_filename,
            'original_size' => $conversion->formatted_original_size,
            'converted_size' => $conversion->formatted_converted_size,
            'processing_time' => $conversion->processing_time ? $conversion->processing_time . 'ms' : null,
            'error_message' => $conversion->error_message,
            'created_at' => $conversion->created_at->toISOString(),
            'download_url' => $conversion->status === 'completed' ? route('api.pdf.download', $conversion->id) : null
        ];
    }

    /**
     * Obter histórico de conversões
     */
    public function getConversionHistory(string $ipAddress, int $page, int $perPage): array
    {
        $conversions = PdfConversion::byIpAddress($ipAddress)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'conversions' => $conversions->items(),
            'pagination' => [
                'current_page' => $conversions->currentPage(),
                'last_page' => $conversions->lastPage(),
                'per_page' => $conversions->perPage(),
                'total' => $conversions->total()
            ]
        ];
    }

    /**
     * Obter estatísticas do usuário
     */
    public function getUserStats(string $ipAddress): array
    {
        $usage = DailyUsage::getForIpToday($ipAddress);
        $totalConversions = PdfConversion::byIpAddress($ipAddress)->completed()->count();
        $todayConversions = PdfConversion::byIpAddress($ipAddress)->completed()->today()->count();

        return [
            'daily_usage' => [
                'conversions_used_today' => $usage->conversions_count,
                'daily_limit' => $usage->daily_limit,
                'remaining_conversions' => $usage->getRemainingConversions(),
                'is_expanded' => $usage->is_expanded
            ],
            'total_stats' => [
                'total_conversions' => $totalConversions,
                'today_conversions' => $todayConversions
            ]
        ];
    }
}
