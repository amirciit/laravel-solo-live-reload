<?php

$root = dirname(__DIR__);
$input = $root . DIRECTORY_SEPARATOR . 'README.md';
$output = $root . DIRECTORY_SEPARATOR . 'Laravel-Solo-Live-Reload-README.pdf';

if (! is_file($input)) {
    fwrite(STDERR, "README.md was not found.\n");
    exit(1);
}

$markdown = file($input, FILE_IGNORE_NEW_LINES);
$lines = [];
$inCode = false;

foreach ($markdown as $line) {
    $trimmed = trim($line);

    if (strpos($trimmed, '```') === 0) {
        $inCode = ! $inCode;
        $lines[] = '';
        continue;
    }

    if (! $inCode) {
        if (preg_match('/^#{1,6}\s+(.*)$/', $line, $matches)) {
            $lines[] = '';
            $lines[] = strtoupper($matches[1]);
            $lines[] = str_repeat('-', min(72, strlen($matches[1])));
            continue;
        }

        $line = preg_replace('/\*\*(.*?)\*\*/', '$1', $line);
        $line = preg_replace('/`([^`]+)`/', '$1', $line);
        $line = str_replace('| --- | --- |', '|-----|---------|', $line);
    }

    foreach (wrapLine($line, $inCode ? 84 : 92) as $wrapped) {
        $lines[] = $wrapped;
    }
}

$pages = [];
$current = [];
$maxLines = 54;

foreach ($lines as $line) {
    if (count($current) >= $maxLines) {
        $pages[] = $current;
        $current = [];
    }

    $current[] = $line;
}

if (count($current) > 0) {
    $pages[] = $current;
}

writePdf($output, $pages);

echo "Created: {$output}\n";

function wrapLine($line, $width)
{
    if ($line === '') {
        return [''];
    }

    $wrapped = [];

    while (strlen($line) > $width) {
        $break = strrpos(substr($line, 0, $width + 1), ' ');

        if ($break === false || $break < 20) {
            $break = $width;
        }

        $wrapped[] = rtrim(substr($line, 0, $break));
        $line = ltrim(substr($line, $break));
    }

    $wrapped[] = $line;

    return $wrapped;
}

function writePdf($path, array $pages)
{
    $objects = [];
    $pageObjectNumbers = [];
    $fontObjectNumber = 3;
    $nextObjectNumber = 4;

    foreach ($pages as $pageLines) {
        $contentObjectNumber = $nextObjectNumber++;
        $pageObjectNumber = $nextObjectNumber++;
        $pageObjectNumbers[] = $pageObjectNumber;

        $stream = buildPageStream($pageLines);
        $objects[$contentObjectNumber] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        $objects[$pageObjectNumber] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObjectNumber} 0 R >> >> /Contents {$contentObjectNumber} 0 R >>";
    }

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(function ($number) {
        return $number . ' 0 R';
    }, $pageObjectNumbers)) . '] /Count ' . count($pageObjectNumbers) . ' >>';
    $objects[$fontObjectNumber] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $number => $object) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= max(array_keys($objects)); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", isset($offsets[$i]) ? $offsets[$i] : 0);
    }

    $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

    file_put_contents($path, $pdf);
}

function buildPageStream(array $lines)
{
    $stream = "BT\n/F1 10 Tf\n50 800 Td\n14 TL\n";

    foreach ($lines as $line) {
        $stream .= '(' . escapePdfText($line) . ") Tj\nT*\n";
    }

    $stream .= "ET";

    return $stream;
}

function escapePdfText($text)
{
    $text = str_replace(["\\", "(", ")", "\t"], ["\\\\", "\\(", "\\)", "    "], $text);

    return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text);
}
