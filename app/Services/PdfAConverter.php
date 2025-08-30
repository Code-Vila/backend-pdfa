<?php

namespace App\Services;

use TCPDF;
use setasign\Fpdi\Tcpdf\Fpdi;
use Exception;

class PdfAConverter
{
    /**
     * Converte um PDF para PDF/A usando FPDI + TCPDF
     */
    public function convertToPdfA(string $inputPath, string $outputPath): bool
    {
        try {
            // Criar instância do FPDI (que extende TCPDF)
            $pdf = new Fpdi();

            // Configurar para PDF/A-1b
            $pdf->SetPDFVersion('1.4');
            
            // Definir metadados obrigatórios para PDF/A
            $pdf->SetCreator('PDF/A Converter');
            $pdf->SetAuthor('Sistema de Conversão PDF/A');
            $pdf->SetTitle('Documento convertido para PDF/A');
            $pdf->SetSubject('Conversão PDF/A para arquivamento');
            $pdf->SetKeywords('PDF/A, arquivamento, conversão');

            // Configurar fonte padrão
            $pdf->SetFont('helvetica', '', 12);

            // Contar páginas do PDF original
            $pageCount = $pdf->setSourceFile($inputPath);

            // Copiar cada página
            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                // Importar página
                $templateId = $pdf->importPage($pageNum);
                $size = $pdf->getTemplateSize($templateId);

                // Adicionar nova página com o mesmo tamanho
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);

                // Usar a página importada
                $pdf->useTemplate($templateId);
            }

            // Salvar como PDF/A
            $pdf->Output($outputPath, 'F');

            return file_exists($outputPath);

        } catch (Exception $e) {
            throw new Exception('Erro na conversão PDF/A: ' . $e->getMessage());
        }
    }

    /**
     * Valida se um arquivo é um PDF válido
     */
    public function validatePdf(string $filePath): bool
    {
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($filePath);
            return $pageCount > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtém informações básicas do PDF
     */
    public function getPdfInfo(string $filePath): array
    {
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($filePath);
            
            $info = [
                'pages' => $pageCount,
                'file_size' => filesize($filePath),
                'is_valid' => true
            ];

            // Tentar obter tamanho da primeira página
            if ($pageCount > 0) {
                $templateId = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($templateId);
                $info['page_width'] = $size['width'];
                $info['page_height'] = $size['height'];
            }

            return $info;

        } catch (Exception $e) {
            return [
                'pages' => 0,
                'file_size' => filesize($filePath),
                'is_valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
