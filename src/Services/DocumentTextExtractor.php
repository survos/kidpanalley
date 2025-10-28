<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\AbstractContainer;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Service for extracting plain text from Word documents
 * This can be extended to handle XML music formats in the future
 */
class DocumentTextExtractor
{
    /**
     * Extract text from legacy .doc file using catdoc
     *
     * @param string $filePath
     * @return string
     * @throws \Exception
     */
    public function extractFromDoc(string $filePath): string
    {
        if (!$this->isCatdocAvailable()) {
            throw new \Exception('catdoc is not available. Install with: apt-get install catdoc');
        }

        $process = new Process(['catdoc', $filePath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Extract text from modern .docx file using PHPWord
     *
     * @param string $filePath
     * @return string
     * @throws \Exception
     */
    public function extractFromDocx(string $filePath): string
    {
        try {
            $reader = IOFactory::createReader('Word2007');
            $phpWord = $reader->load($filePath);
        } catch (\Exception $e) {
            throw new \Exception("Failed to read docx file: " . $e->getMessage(), 0, $e);
        }

        $sections = $phpWord->getSections();
        return $this->extractTextFromSections($sections);
    }

    /**
     * Extract text from PHPWord sections recursively
     *
     * @param array $sections
     * @return string
     */
    private function extractTextFromSections(array $sections): string
    {
        $text = '';

        foreach ($sections as $section) {
            $elements = $section->getElements();
            $text .= $this->extractTextFromElements($elements);
        }

        return $text;
    }

    /**
     * Extract text from PHPWord elements recursively
     *
     * @param array $elements
     * @return string
     */
    private function extractTextFromElements(array $elements): string
    {
        $text = '';

        foreach ($elements as $element) {
            $elementClass = get_class($element);

            // Handle different element types
            if (method_exists($element, 'getText')) {
                $text .= $element->getText();
            } elseif (method_exists($element, 'getElements')) {
                // Recursive for containers (tables, textboxes, etc.)
                $text .= $this->extractTextFromElements($element->getElements());
            } elseif ($elementClass === 'PhpOffice\PhpWord\Element\TextRun') {
                foreach ($element->getElements() as $textElement) {
                    if (method_exists($textElement, 'getText')) {
                        $text .= $textElement->getText();
                    }
                }
            }

            // Add line break after block elements
            if (in_array($elementClass, [
                'PhpOffice\PhpWord\Element\TextRun',
                'PhpOffice\PhpWord\Element\Text',
                'PhpOffice\PhpWord\Element\Table',
            ])) {
                $text .= "\n";
            }
        }

        return $text;
    }

    /**
     * Check if catdoc is available on the system
     *
     * @return bool
     */
    private function isCatdocAvailable(): bool
    {
        $process = new Process(['which', 'catdoc']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Future method: Extract structured data from MusicXML
     * This will be implemented when you're ready to move to the XML music standard
     *
     * @param string $filePath
     * @return array Structured song data with verses, refrains, bridges, etc.
     */
    public function extractFromMusicXml(string $filePath): array
    {
        // TODO: Implement MusicXML parsing
        // This will return structured data like:
        // [
        //     'title' => 'Song Title',
        //     'composer' => 'Composer Name',
        //     'parts' => [
        //         ['type' => 'verse', 'number' => 1, 'lyrics' => '...'],
        //         ['type' => 'refrain', 'lyrics' => '...'],
        //         ['type' => 'bridge', 'lyrics' => '...'],
        //     ]
        // ]

        throw new \Exception('MusicXML extraction not yet implemented');
    }

    /**
     * Future method: Convert Word document to MusicXML structure
     * This will parse indentation to determine verse/refrain/bridge structure
     *
     * @param string $filePath
     * @return array Structured song data
     */
    public function convertToStructuredFormat(string $filePath): array
    {
        // TODO: Implement structured parsing based on indentation
        // This will analyze indentation patterns to identify:
        // - Verses (standard indentation)
        // - Refrains/Chorus (different indentation)
        // - Bridges (another indentation level)

        throw new \Exception('Structured format conversion not yet implemented');
    }
}
