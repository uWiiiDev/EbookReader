<?php
declare(strict_types=1);

class EpubParser
{
    private string $filePath;
    private ?ZipArchive $zip = null;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public static function isValidEpub(string $filePath): bool
    {
        if (!file_exists($filePath) || filesize($filePath) < 58) {
            return false;
        }

        // Check ZIP magic bytes (PK\x03\x04)
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }
        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic !== "PK\x03\x04") {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return false;
        }

        $hasContainer = $zip->locateName('META-INF/container.xml') !== false;
        $zip->close();

        return $hasContainer;
    }

    public function parse(): array
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($this->filePath) !== true) {
            throw new RuntimeException("Konnte EPUB-Datei nicht als ZIP öffnen.");
        }

        try {
            $opfPath = $this->getOpfPath();
            if (!$opfPath) {
                throw new RuntimeException("Kein OPF-Rootfile in META-INF/container.xml gefunden.");
            }

            $opfContent = $this->zip->getFromName($opfPath);
            if (!$opfContent) {
                throw new RuntimeException("OPF-Datei konnte im EPUB-Archiv nicht gelesen werden.");
            }

            $opfDir = dirname($opfPath);
            if ($opfDir === '.' || $opfDir === '/') {
                $opfDir = '';
            }

            return $this->extractMetadata($opfContent, $opfDir);
        } finally {
            $this->zip->close();
            $this->zip = null;
        }
    }

    private function getOpfPath(): ?string
    {
        $containerXml = $this->zip->getFromName('META-INF/container.xml');
        if (!$containerXml) {
            // Fallback: look for common OPF paths
            for ($i = 0; $i < $this->zip->numFiles; $i++) {
                $name = $this->zip->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.opf')) {
                    return $name;
                }
            }
            return null;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($containerXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            libxml_clear_errors();
            return null;
        }
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');

        $nodes = $xpath->query('//c:rootfiles/c:rootfile[@full-path]');
        if ($nodes && $nodes->length > 0) {
            /** @var DOMElement $node */
            $node = $nodes->item(0);
            return $node->getAttribute('full-path');
        }

        // Try without namespace
        $nodes = $xpath->query('//*[@full-path]');
        if ($nodes && $nodes->length > 0) {
            /** @var DOMElement $node */
            $node = $nodes->item(0);
            return $node->getAttribute('full-path');
        }

        return null;
    }

    private function extractMetadata(string $opfXml, string $opfDir): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($opfXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        if (!$loaded) {
            return [
                'title' => pathinfo($this->filePath, PATHINFO_FILENAME),
                'author' => 'Unbekannter Autor',
                'cover_data' => null,
                'cover_mime' => null
            ];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('opf', 'http://www.idpf.org/2007/opf');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        // 1. Title
        $title = '';
        $titleNodes = $xpath->query('//dc:title');
        if ($titleNodes && $titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
        }
        if ($title === '') {
            $title = pathinfo($this->filePath, PATHINFO_FILENAME);
        }

        // 2. Author / Creator
        $authors = [];
        $creatorNodes = $xpath->query('//dc:creator');
        if ($creatorNodes) {
            foreach ($creatorNodes as $node) {
                $val = trim($node->textContent);
                if ($val !== '') {
                    $authors[] = $val;
                }
            }
        }
        $author = !empty($authors) ? implode(', ', $authors) : 'Unbekannter Autor';

        // 3. Cover Image extraction
        $coverData = null;
        $coverMime = null;
        $coverHref = $this->findCoverHref($xpath);

        if ($coverHref) {
            $zipCoverPath = $this->resolvePath($opfDir, $coverHref);
            $rawCover = $this->zip->getFromName($zipCoverPath);

            // If not found directly, try case-insensitive / normalized lookup
            if ($rawCover === false) {
                $rawCover = $this->findFileCaseInsensitive($zipCoverPath);
            }

            if ($rawCover !== false && strlen($rawCover) > 0) {
                $coverData = $rawCover;
                // Determine mime type
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $coverMime = $finfo->buffer($rawCover) ?: 'image/jpeg';
            }
        }

        return [
            'title' => $title,
            'author' => $author,
            'cover_data' => $coverData,
            'cover_mime' => $coverMime
        ];
    }

    private function findCoverHref(DOMXPath $xpath): ?string
    {
        // Strategy A: EPUB 3 manifest item with properties="cover-image"
        $nodes = $xpath->query('//opf:manifest/opf:item[contains(concat(" ", normalize-space(@properties), " "), " cover-image ")]');
        if ($nodes && $nodes->length > 0) {
            /** @var DOMElement $item */
            $item = $nodes->item(0);
            return urldecode($item->getAttribute('href'));
        }

        // Strategy B: EPUB 2 meta name="cover" pointing to manifest item ID
        $metaCover = $xpath->query('//opf:metadata/opf:meta[@name="cover"]/@content');
        if ($metaCover && $metaCover->length > 0) {
            $coverId = $metaCover->item(0)->nodeValue;
            if ($coverId) {
                $items = $xpath->query('//opf:manifest/opf:item[@id="' . $coverId . '"]/@href');
                if ($items && $items->length > 0) {
                    return urldecode($items->item(0)->nodeValue);
                }
            }
        }

        // Strategy C: Manifest item with id="cover" or id="cover-image"
        $nodes = $xpath->query('//opf:manifest/opf:item[@id="cover" or @id="cover-image"]/@href');
        if ($nodes && $nodes->length > 0) {
            return urldecode($nodes->item(0)->nodeValue);
        }

        // Strategy D: Manifest item where href or id contains "cover" and media-type starts with "image/"
        $nodes = $xpath->query('//opf:manifest/opf:item[starts-with(@media-type, "image/") and (contains(@href, "cover") or contains(@id, "cover"))]/@href');
        if ($nodes && $nodes->length > 0) {
            return urldecode($nodes->item(0)->nodeValue);
        }

        // Strategy E: First image in manifest as a last resort
        $nodes = $xpath->query('//opf:manifest/opf:item[starts-with(@media-type, "image/")]/@href');
        if ($nodes && $nodes->length > 0) {
            return urldecode($nodes->item(0)->nodeValue);
        }

        return null;
    }

    private function resolvePath(string $baseDir, string $relativeHref): string
    {
        // Strip query string or anchors if any
        $cleanHref = explode('?', $relativeHref)[0];
        $cleanHref = explode('#', $cleanHref)[0];

        if ($baseDir === '') {
            $fullPath = $cleanHref;
        } else {
            $fullPath = $baseDir . '/' . $cleanHref;
        }

        // Normalize '../' and './'
        $parts = explode('/', str_replace('\\', '/', $fullPath));
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }
        return implode('/', $resolved);
    }

    private function findFileCaseInsensitive(string $targetPath): string|false
    {
        $targetLower = strtolower($targetPath);
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = $this->zip->getNameIndex($i);
            if (strtolower($name) === $targetLower) {
                return $this->zip->getFromIndex($i);
            }
        }
        return false;
    }
}
