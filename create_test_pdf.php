<?php

require_once __DIR__ . '/vendor/autoload.php';

use TCPDF;

// Criar um PDF de teste simples
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 16);
$pdf->Cell(0, 10, 'PDF de Teste para Conversão PDF/A', 0, 1, 'C');
$pdf->Ln(10);
$pdf->SetFont('helvetica', '', 12);
$pdf->MultiCell(0, 10, 'Este é um documento PDF de teste criado para verificar a funcionalidade de conversão para PDF/A usando FPDI + TCPDF em hospedagem compartilhada.');

// Salvar o arquivo
$testDir = __DIR__ . '/storage/app/test';
if (!is_dir($testDir)) {
    mkdir($testDir, 0755, true);
}

$pdf->Output($testDir . '/sample.pdf', 'F');
echo "PDF de teste criado em: " . $testDir . "/sample.pdf\n";
