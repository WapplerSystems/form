<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Service;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders a set of submissions into a downloadable export. CSV is always
 * available (no dependency). XLSX requires phpoffice/phpspreadsheet and PDF
 * requires mpdf/mpdf — both are optional; when missing, the caller receives a
 * plain-text response explaining which package to install instead of a fatal.
 *
 * @internal
 */
final class SubmissionExportService
{
    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    /**
     * @param array<string, string> $columns identifier => header label
     * @param list<array{uid: int, crdate: int, language_uid: int, cells: array<string, string>}> $rows
     */
    public function export(string $format, string $formIdentifier, array $columns, array $rows, ServerRequestInterface $request): ResponseInterface
    {
        $filenameBase = 'submissions-' . ($formIdentifier !== '' ? $formIdentifier : 'export') . '-' . date('Ymd-His');

        return match (strtolower($format)) {
            'xlsx' => $this->exportXlsx($filenameBase, $columns, $rows),
            'pdf' => $this->exportPdf($filenameBase, $formIdentifier, $columns, $rows, $request),
            default => $this->exportCsv($filenameBase, $columns, $rows),
        };
    }

    /**
     * @param array<string, string> $columns
     * @param list<array<string, mixed>> $rows
     */
    private function exportCsv(string $filenameBase, array $columns, array $rows): ResponseInterface
    {
        $handle = fopen('php://temp', 'r+');
        // UTF-8 BOM so Excel opens special characters correctly.
        fwrite($handle, "\xEF\xBB\xBF");

        $header = ['#', 'date'];
        foreach ($columns as $label) {
            $header[] = $label;
        }
        fputcsv($handle, $header, ',', '"', '\\');

        foreach ($rows as $row) {
            $line = [(string)$row['uid'], date('Y-m-d H:i', (int)$row['crdate'])];
            foreach (array_keys($columns) as $key) {
                $line[] = $row['cells'][$key] ?? '';
            }
            fputcsv($handle, $line, ',', '"', '\\');
        }

        rewind($handle);
        $body = (string)stream_get_contents($handle);
        fclose($handle);

        return $this->fileResponse($body, $filenameBase . '.csv', 'text/csv; charset=utf-8');
    }

    /**
     * @param array<string, string> $columns
     * @param list<array<string, mixed>> $rows
     */
    private function exportXlsx(string $filenameBase, array $columns, array $rows): ResponseInterface
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return $this->missingDependencyResponse('XLSX export requires the "phpoffice/phpspreadsheet" package. Install it with: composer require phpoffice/phpspreadsheet');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $columnIndex = 1;
        $sheet->setCellValue([$columnIndex++, 1], '#');
        $sheet->setCellValue([$columnIndex++, 1], 'date');
        foreach ($columns as $label) {
            $sheet->setCellValue([$columnIndex++, 1], $label);
        }

        $rowIndex = 2;
        foreach ($rows as $row) {
            $columnIndex = 1;
            $sheet->setCellValueExplicit([$columnIndex++, $rowIndex], (string)$row['uid'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([$columnIndex++, $rowIndex], date('Y-m-d H:i', (int)$row['crdate']));
            foreach (array_keys($columns) as $key) {
                $sheet->setCellValueExplicit([$columnIndex++, $rowIndex], $row['cells'][$key] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $rowIndex++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $handle = fopen('php://temp', 'r+');
        $writer->save($handle);
        rewind($handle);
        $body = (string)stream_get_contents($handle);
        fclose($handle);

        return $this->fileResponse(
            $body,
            $filenameBase . '.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /**
     * @param array<string, string> $columns
     * @param list<array<string, mixed>> $rows
     */
    private function exportPdf(string $filenameBase, string $formIdentifier, array $columns, array $rows, ServerRequestInterface $request): ResponseInterface
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return $this->missingDependencyResponse('PDF export requires the "mpdf/mpdf" package. Install it with: composer require mpdf/mpdf');
        }

        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: ['EXT:form/Resources/Private/Templates/'],
            partialRootPaths: ['EXT:form/Resources/Private/Partials/'],
            layoutRootPaths: ['EXT:form/Resources/Private/Layouts/'],
            request: $request,
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $view->assignMultiple([
            'formIdentifier' => $formIdentifier,
            'columns' => $columns,
            'rows' => $rows,
        ]);
        $html = $view->render('Backend/FormSubmission/ExportPdf');

        $tempDir = GeneralUtility::tempnam('mpdf_');
        @unlink($tempDir);
        @mkdir($tempDir);
        $mpdf = new \Mpdf\Mpdf(['tempDir' => $tempDir, 'orientation' => 'L']);
        $mpdf->WriteHTML($html);
        $body = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        GeneralUtility::rmdir($tempDir, true);

        return $this->fileResponse($body, $filenameBase . '.pdf', 'application/pdf');
    }

    private function fileResponse(string $body, string $filename, string $contentType): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write($body);
        return $response
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string)strlen($body));
    }

    private function missingDependencyResponse(string $message): ResponseInterface
    {
        $response = new Response(null, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        $response->getBody()->write($message);
        return $response;
    }
}
