<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PdfTextExtractorService
{
    /**
     * Extrae el texto legible de un archivo PDF usando descompresión de streams FlateDecode en PHP puro.
     *
     * TODO(security): Para entornos productivos de alto riesgo, integrar escaneo antivirus (ClamAV) y CDR (Content Disarm & Reconstruction) en archivos PDF subidos.
     */
    public function extractText(string $filePath): string
    {
        $realBase = realpath(storage_path('app'));
        $realFile = realpath($filePath);

        if (!$realFile || ($realBase && !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR) && !str_starts_with($realFile, sys_get_temp_dir() . DIRECTORY_SEPARATOR))) {
            Log::warning('Intento de lectura de PDF fuera de directorio permitido.');
            return '';
        }

        if (!is_file($realFile) || !is_readable($realFile)) {
            return '';
        }

        $content = file_get_contents($realFile);
        if ($content === false || !str_starts_with($content, '%PDF-')) {
            return '';
        }

        $extractedChunks = [];

        // 1. Buscar todos los streams del PDF y descomprimirlos si usan FlateDecode
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $streamMatches)) {
            foreach ($streamMatches[1] as $rawStream) {
                $decoded = @gzuncompress($rawStream);
                if ($decoded === false) {
                    $decoded = @gzinflate($rawStream);
                }
                $dataToParse = $decoded !== false ? $decoded : $rawStream;

                $textFromStream = $this->parseTextOperators($dataToParse);
                if (!empty($textFromStream)) {
                    $extractedChunks[] = $textFromStream;
                }
            }
        }

        // 2. También revisar si hay operadores de texto directos fuera de streams comprimidos
        if (empty($extractedChunks)) {
            $directText = $this->parseTextOperators($content);
            if (!empty($directText)) {
                $extractedChunks[] = $directText;
            }
        }

        $result = trim(preg_replace('/[ \t]+/', ' ', implode("\n", $extractedChunks)));

        return mb_convert_encoding($result, 'UTF-8', 'UTF-8');
    }

    protected function parseTextOperators(string $data): string
    {
        $lines = [];

        // Extraer bloques BT ... ET o cadenas entre paréntesis seguidas de Tj / TJ
        if (preg_match_all('/\(([^()\\\\]*(?:\\\\.[^()\\\\]*)*)\)\s*(?:Tj|\'|\")/s', $data, $tjMatches)) {
            foreach ($tjMatches[1] as $str) {
                $lines[] = $this->unescapePdfString($str);
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $data, $arrayMatches)) {
            foreach ($arrayMatches[1] as $arrayContent) {
                if (preg_match_all('/\(([^()\\\\]*(?:\\\\.[^()\\\\]*)*)\)/s', $arrayContent, $subMatches)) {
                    $line = '';
                    foreach ($subMatches[1] as $piece) {
                        $line .= $this->unescapePdfString($piece);
                    }
                    if (trim($line) !== '') {
                        $lines[] = $line;
                    }
                }
            }
        }

        return trim(implode(' ', $lines));
    }

    protected function unescapePdfString(string $str): string
    {
        $str = str_replace(
            ['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'],
            ["\n", "\r", "\t", '(', ')', '\\'],
            $str
        );

        // Octal escapes \ddd
        $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
            return chr(octdec($m[1]));
        }, $str);

        return $str;
    }
}
