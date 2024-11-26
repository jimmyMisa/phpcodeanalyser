<?php
require_once("classparser.php");
require_once("vdc.php");

function renderFile($pathFile, $outputDir, $basePath) {
    $result = parseFile($pathFile);
    $pathFile = str_replace("\\", "/", $pathFile);
    $outputDir = str_replace("\\", "/", $outputDir);
    $basePath = str_replace("\\", "/", $basePath);

    // Determine the relative path and create subdirectory
    $relativePath = str_replace($basePath, '', $pathFile);
    $relativeDir = dirname($relativePath);
    $relativeDir = str_replace("/", "-", $relativeDir);
    $targetDir = $outputDir . '/' . $relativeDir;

    // Generate the HTML content
    ob_start(); // Start output buffering
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    	<meta charset="utf-8">
    	<meta name="viewport" content="width=device-width, initial-scale=1">
    	<title>
    		
    Documentation Technique : <?= htmlentities($result["class"]); ?>
    	</title>

	    <style>
	        table {
	            border-collapse: collapse;
	            width: 100%;
	        }
	        th, td {
	            border: 1px solid #ddd;
	            padding: 8px;
	            text-align: left;
	            vertical-align: top;
	        }
	        th {
	            background-color: #f4f4f4;
	        }
	        tr:nth-child(even) {
	            background-color: #f9f9f9;
	        }
	        tr:hover {
	            background-color: #f1f1f1;
	        }
	        .td{
	        	display: inline-block;
	        	word-break: break-all;
	        	white-space: pre-wrap;
	        }
	        .label{
	        	display: inline-block;
	        	width: 200px;
	        }
	    </style>
    </head>
    <body>
    
    <h1>Documentation Technique : <?= htmlentities($result["class"]); ?></h1>
    <table>
        <thead>
            <tr>
                <th>Route</th>
                <th>Uses Classes</th>
                <th>Twig Templates</th>
                <th>Responses</th>
                <th>Repository Calls</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($result['methods'] as $method): 
                $route = $method['route'] ?? [];
                $route['path'] = $route['path'] ?? '';
                $route['routename'] = $route['routename'] ?? '';
                
                $parameters = implode(', ', array_map(function ($param) {
                    return "{$param['type']} \${$param['name']}";
                }, $method['parameters'] ?? []));
                
                $usesClasses = implode(', ', array_map(function ($use) {
                    return "{$use['class']}::{$use['method']}";
                }, $method['uses'] ?? []));
                
                $twigTemplates = implode("\n", array_map(function ($twig) {
                    $r = $twig['template'];
                    $r = str_replace("'", "", $r);
                    if (is_file(Provider::$datas["views"] . $r)) {
                        $r .= vdc(Provider::$datas["views"] . $r);
                    }
                    return $r;
                }, $method['twig_calls'] ?? []));
                
                $jsonResponses = implode(";\n\n", array_map(function ($response) {
                    return htmlspecialchars($response['parameters']);
                }, $method['json_responses'] ?? []));
                
                $responses = implode(";\n\n", array_map(function ($response) {
                    return htmlspecialchars($response['parameters']);
                }, $method['responses'] ?? []));
                
                $repositoryCalls = implode(";\n\n", array_map(function ($call) {
                    return "{$call['entity']}::{$call['method']}(" . htmlspecialchars($call['parameters']) . ")";
                }, $method['repository_calls'] ?? []));
            ?>
            <tr>
                <td><b>Method</b>: <?= $method['name']; ?><br/>
                    <b>Parameters</b>: <?= $parameters; ?><br/>
                    <b>Route Path</b>: <?= $route['path']; ?><br/>
                    <b>Route Name</b>: <?= $route['routename']; ?>
                </td>
                <td><?= $usesClasses; ?></td>
                <td><?= $twigTemplates; ?></td>
                <td><?= $jsonResponses; ?><br/><?= $responses; ?></td>
                <td><?= $repositoryCalls; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </body>
    </html>
    <?php
    $htmlContent = ob_get_clean(); // Get buffered content

    // Write content to an HTML file
    $outputFile = $targetDir . '-' . basename($pathFile, '.php') . '.html';
	echo "File $outputFile\n";
    file_put_contents($outputFile, $htmlContent);
}


// Configuration and execution
class Provider {
    static $datas = [];
}
require_once("config.php");

$outputDir = './output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true); // Create output directory if it doesn't exist
}

// Get controller files (assuming `getControllerFiles` is defined)
$basePath = Provider::$datas['base'];
$excludedDirectories = ['vendor', 'var', 'node_modules'];
$controllers = getControllerFiles($basePath, $excludedDirectories);

// Generate reports for each controller
foreach ($controllers as $controller) {
	echo "Treat $controller\n";
    renderFile($controller, $outputDir, Provider::$datas['base']);
}

echo "Reports generated in the 'output' folder.";
