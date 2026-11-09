<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

class PdfQrAnchorService
{
    public const MARKER = '[[QR_CODE]]';

    public const QR_SIZE = 30.0;

    /** @return array{page: int, x: float, y: float, size: float} */
    public function locate(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Unggah dokumen surat dalam format PDF.');
        }

        $process = new Process([
            config('documents.pdftotext_binary', 'pdftotext'),
            '-bbox-layout', '-cropbox', '-enc', 'UTF-8', $path, '-',
        ]);
        $process->setTimeout(30);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            throw new RuntimeException('PDF tidak dapat dibaca. Pastikan PDF tidak terkunci dan Poppler pdftotext sudah dikonfigurasi melalui PDFTOTEXT_BINARY.', 0, $e);
        }

        $anchor = $this->parse($process->getOutput());

        // Validate with the same importer used when the headmaster approves.
        $pdf = new Fpdi;
        try {
            $pageCount = $pdf->setSourceFile($path);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $pdf->importPage($pageNumber);
            }
            $id = $pdf->importPage($anchor['page']);
            $size = $pdf->getTemplateSize($id);
            if (! is_array($size)) {
                throw new RuntimeException('Ukuran halaman PDF tidak dapat dibaca.');
            }
            if (abs($size['width'] - $anchor['page_width']) > 0.5 || abs($size['height'] - $anchor['page_height']) > 0.5) {
                throw new RuntimeException('Ukuran halaman PDF tidak cocok dengan koordinat penanda. Ekspor ulang PDF tanpa rotasi atau pemotongan halaman.');
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('PDF tidak dapat diproses. Ekspor ulang sebagai PDF biasa yang tidak terkunci.', 0, $e);
        } finally {
            $pdf->cleanUp();
        }

        return ['page' => $anchor['page'], 'x' => $anchor['x'], 'y' => $anchor['y'], 'size' => $anchor['size']];
    }

    /** @return array{page: int, x: float, y: float, size: float, page_width: float, page_height: float} */
    public function parse(string $xml): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            throw new RuntimeException('Koordinat teks PDF tidak dapat dibaca.');
        }

        $xpath = new DOMXPath($document);
        $pages = $this->elements($xpath, '//*[local-name()="page"]');
        $matches = [];
        $occurrences = 0;
        foreach ($pages as $pageIndex => $page) {
            foreach ($this->elements($xpath, './/*[local-name()="line"]', $page) as $line) {
                $words = $this->elements($xpath, './/*[local-name()="word"]', $line);
                $text = implode('', array_map(fn ($word) => trim($word->textContent), $words));
                $occurrences += substr_count($text, self::MARKER);
                if ($text === self::MARKER) {
                    $matches[] = compact('pageIndex', 'page', 'words');
                }
            }
        }
        if ($occurrences !== 1 || count($matches) !== 1) {
            throw new RuntimeException('PDF harus memuat tepat satu penanda [[QR_CODE]] pada baris tersendiri. Gunakan PDF hasil ekspor Word dengan teks yang dapat diseleksi, bukan hasil scan.');
        }

        ['pageIndex' => $pageIndex, 'page' => $page, 'words' => $words] = $matches[0];
        if ($words === []) {
            throw new RuntimeException('Penanda PDF tidak dapat dibaca.');
        }
        $mm = 25.4 / 72;
        $left = min(array_map(fn ($word) => $this->coordinate($word, 'xMin'), $words)) * $mm;
        $top = min(array_map(fn ($word) => $this->coordinate($word, 'yMin'), $words)) * $mm;
        $right = max(array_map(fn ($word) => $this->coordinate($word, 'xMax'), $words)) * $mm;
        $bottom = max(array_map(fn ($word) => $this->coordinate($word, 'yMax'), $words)) * $mm;
        $width = $this->coordinate($page, 'width') * $mm;
        $height = $this->coordinate($page, 'height') * $mm;
        $x = $left - 1;
        $y = $top - 1;
        $size = self::QR_SIZE;
        if ($x < 0 || $y < 0 || $x + $size > $width || $y + $size > $height || $right > $x + $size - 1 || $bottom > $y + $size - 1) {
            throw new RuntimeException('Sediakan ruang 30 × 30 mm dari penanda ke kanan dan bawah, di dalam halaman. Gunakan ukuran teks penanda 10–12 pt.');
        }

        foreach ($this->elements($xpath, './/*[local-name()="word"]', $page) as $word) {
            if (count(array_filter($words, fn ($markerWord) => $markerWord->isSameNode($word))) > 0) {
                continue;
            }
            if ($this->coordinate($word, 'xMin') * $mm < $x + $size && $this->coordinate($word, 'xMax') * $mm > $x
                && $this->coordinate($word, 'yMin') * $mm < $y + $size && $this->coordinate($word, 'yMax') * $mm > $y) {
                throw new RuntimeException('Area QR bertumpuk dengan teks surat. Kosongkan area 30 × 30 mm dari penanda ke kanan dan bawah.');
            }
        }

        return ['page' => $pageIndex + 1, 'x' => $x, 'y' => $y, 'size' => $size, 'page_width' => $width, 'page_height' => $height];
    }

    /** @return list<DOMElement> */
    private function elements(DOMXPath $xpath, string $expression, ?DOMNode $context = null): array
    {
        $nodes = $xpath->query($expression, $context);
        if ($nodes === false) {
            throw new RuntimeException('Struktur koordinat PDF tidak valid.');
        }
        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function coordinate(DOMElement $element, string $name): float
    {
        $value = $element->getAttribute($name);
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new RuntimeException('Koordinat teks PDF tidak valid.');
        }

        return (float) $value;
    }
}
