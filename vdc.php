<?php
function encodeToUtf8(string $input): string {
    return mb_convert_encoding($input, 'UTF-8', 'auto');
}



    // Recursive function to traverse the DOM tree and detect variables
    function traverseDOM($node, &$elements, &$parentChildMap, $parentId = null) {
        static $idCounter = 0;

        $currentId = $idCounter++;
        $parentChildMap[$currentId] = ['parent' => $parentId, 'children' => []];

        if ($parentId !== null) {
            $parentChildMap[$parentId]['children'][] = $currentId;
        }

        $elements[$currentId] = [
            'node' => $node,
            'variables' => [],
            'text' => '',
            'child_elements' => []
        ];

        if ($node->nodeType === XML_ELEMENT_NODE || $node->nodeType === XML_TEXT_NODE) {
            $textContent = $node->textContent;

            if (preg_match_all('/\{\{\s*(.*?)\s*\}\}/', $textContent, $matches)) {
                $variables = $matches[1];
                $cleanedText = trim(preg_replace('/\{\{\s*.*?\s*\}\}/', '', $textContent));
                $elements[$currentId]['variables'] = $variables;
                $elements[$currentId]['text'] = $cleanedText;
            }
        }

        foreach ($node->childNodes as $childNode) {
            traverseDOM($childNode, $elements, $parentChildMap, $currentId);
            $childId = array_key_last($elements);
            $elements[$currentId]['child_elements'][] = $childId;
        }
    }

    function findMeaningfulText($id, &$elements, &$parentChildMap) {
        $currentId = $id;
        while ($currentId !== null) {
            if (isset($elements[$currentId]['text']) && trim($elements[$currentId]['text']) !== '') {
                return $elements[$currentId]['text'];
            }
            $currentId = $parentChildMap[$currentId]['parent'] ?? null;
        }
        return null;
    }
function vdc($inputPath) {
    // Load the raw HTML content
    $htmlContent = file_get_contents($inputPath);
    if ($htmlContent === false) {
        throw new Exception("Failed to read the file at: $inputPath");
    }

    // Add a UTF-8 meta charset declaration if not already present
    if (!preg_match('/<meta[^>]*charset[^>]*>/i', $htmlContent)) {
        $htmlContent = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $htmlContent . '</body></html>';
    }

    // Load content into DOMDocument
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $processedContent = $dom->saveHTML();

    $elements = [];
    $parentChildMap = [];

    traverseDOM($dom->documentElement, $elements, $parentChildMap);

    $childMostElements = [];
    foreach ($elements as $id => $element) {
        $isChildMost = true;
        foreach ($parentChildMap[$id]['children'] as $childId) {
            if (isset($elements[$childId])) {
                $isChildMost = false;
                break;
            }
        }

        if ($isChildMost) {
            $description = findMeaningfulText($id, $elements, $parentChildMap);
            if ($description) {
                $element['text'] = $description;
                $childMostElements[$id] = $element;
            }
        }
    }

    $htmlOutput = "";
    foreach ($childMostElements as $element) {
        foreach ($element['variables'] as $variable) {
            $description = '';

            foreach ($element['child_elements'] as $childId) {
                if (isset($elements[$childId]['text']) && trim($elements[$childId]['text']) !== '') {
                    $childText = trim($elements[$childId]['text']);
                    if (!preg_match('/\{\{.*?\}\}|\{%\s*.*?%\}/', $childText)) {
                        $description = preg_replace('/\s+/', ' ', $childText);
                        break;
                    }
                }
            }

            if (!$description && isset($element['text'])) {
                $currentText = trim($element['text']);
                if (!preg_match('/\{\{.*?\}\}|\{%\s*.*?%\}/', $currentText)) {
                    $description = preg_replace('/\s+/', ' ', $currentText);
                }
            }

            if (!$description) {
                foreach ($element['child_elements'] as $childId) {
                    if (isset($elements[$childId]['text']) && trim($elements[$childId]['text']) !== '') {
                        $description = preg_replace('/\s+/', ' ', trim($elements[$childId]['text']));
                        break;
                    }
                }

                if (!$description && isset($element['text'])) {
                    $description = preg_replace('/\s+/', ' ', trim($element['text']));
                }
            }

            $htmlOutput .= "
<b class=\"\"><u>{$variable}</u></b> <br>{$description}<br>";
        }
    }

    return $htmlOutput;
}

function getControllerFiles($path, $exclusions = ['vendor', 'var'], $suffix = 'Controller.php') {
    $controllerFiles = [];
    
    $directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directoryIterator);

    foreach ($iterator as $file) {
        if ($file->isFile() && substr($file->getFilename(), -strlen($suffix)) === $suffix) {
            // Check if the file path contains any of the excluded directories
            $exclude = false;
            foreach ($exclusions as $excluded) {
                if (strpos($file->getPathname(), DIRECTORY_SEPARATOR . $excluded . DIRECTORY_SEPARATOR) !== false) {
                    $exclude = true;
                    break;
                }
            }

            if (!$exclude) {
            	$fn = $file->getPathname();
                $controllerFiles[] = $fn;
                echo "Found : $fn\n";
            }
        }
    }

    return $controllerFiles;
}