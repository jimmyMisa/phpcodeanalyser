<?php
header('Content-Type: application/json');

// Directory containing HTML files
$directory = './output';

// Get the search term from the query parameter
$searchTerm = isset($_GET['term']) ? $_GET['term'] : '';

// Get all HTML files in the directory
$htmlFiles = array_filter(scandir($directory), function ($file) use ($directory) {
    return is_file($directory . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'html';
});
$d = [];
foreach($htmlFiles as $htmlFile){
	$d[] = $htmlFile;
}
$htmlFiles = $d;

if ($searchTerm !== '') {
    // Filter files based on the search term
    $matchingFiles = [];
    foreach ($htmlFiles as $file) {
        $filePath = $directory . '/' . $file;
        $content = file_get_contents($filePath);
        if (stripos($content, $searchTerm) !== false) { // Case-insensitive search
            $matchingFiles[] = $file;
        }
    }

    echo json_encode([
        'status' => 'success',
        'files' => $matchingFiles,
    ]);
} else {
    // Return all files if no search term is provided
    echo json_encode([
        'status' => 'success',
        'files' => $htmlFiles,
    ]);
}
