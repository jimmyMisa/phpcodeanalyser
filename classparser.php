<?php

function parseFile($pathFile){


    $code = file_get_contents($pathFile);

    $result = [];

    // 1. Define Regular Expressions
    $regex = [
        'namespace' => '/namespace\s+([\w\\\\]+);/',
        'class' => '/class\s+(\w+)\s*(extends\s+\w+)?\s*(implements\s+[\w, ]+)?\s*{/',
        'methods' => '/((?:\/\*\*[\s\S]*?\*\/\s*)?)(public|protected|private)?\s*function\s+(\w+)\s*\(([^)]*)\)\s*{((?:[^{}]*+|{(?-1)})*)}/',
        'routeAnnotation' => '/@Route\("([^"]+)"(?:,\s*name="([^"]+)")?/',
        'useStatements' => '/use\s+([\w\\\\]+);/',
        'functionCalls' => '/\b(\w+)->(\w+)\(/',
        'repositoryCalls' => '/\$this->getDoctrine\(\)->getRepository\([\'"]([\w:\\\\]+)[\'"]\)->(\w+)\(([^\)]*)\)/',
        // Pour les paramètres avec array()
        'twigRender' => '/\$this->render\(\s*[\'"]([\w\/\.]+)[\'"],\s*(array\s*\([^)]*\))\s*\)/',

        // Pour les paramètres avec []
        'twigRenderArrow' => '/\$this->render\(([^,]+),\s*\[([^\]]*)\]/',

        //'twigRender' => '/\$this->render\(\s*[\'"]([\w\/\.]+)[\'"](?:\s*\.\s*\$[\w]+)*[\'"](?:\s*\.\s*\$[\w]+)*[\'"],\s*(array\s*\([^)]*\))\s*\)/',

        'jsonResponse' => '/return\s+new\s+JsonResponse\(([^;]*)\);/',
        'response' => '/return\s+new\s+Response\(([^\)]*)\)/',
        'queryGet' => '/\$([\w]+)\s*=\s*\$request->query->get\([\'"]([^\'"]+)[\'"]\)/',
        'instantiation' => '/\$([\w]+)\s*=\s*new\s+([\w\\\\]+)\(\);/'
    ];

    // 2. Extract Namespace
    $result['namespace'] = null;
    if (preg_match($regex['namespace'], $code, $matches)) {
        $result['namespace'] = $matches[1];
    }

    $classRoute = '/\*\s+@Route\("([^"]+)"(?:,.*)?\)/';

    $routePrefix = null;

    if (preg_match($classRoute, $code, $matches)) {
        $routePrefix = $matches[1]; // Extracts the route
    }

    // 3. Extract Use Statements
    $useStatements = [];
    if (preg_match_all($regex['useStatements'], $code, $matches)) {
        foreach ($matches[1] as $use) {
            $className = substr($use, strrpos($use, '\\') + 1);
            $useStatements[$className] = $use;
        }
    }

    // 4. Extract Class Name
    $result['class'] = null;
    if (preg_match($regex['class'], $code, $matches)) {
        $result['class'] = $matches[1];
    }

    // 5. Extract Methods
    $result['methods'] = [];
    $code = explode("class", $code);
    array_shift($code);
    $code = implode("class", $code);
    if (preg_match_all($regex['methods'], $code, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $methodMatch) {
            $docComment = trim($methodMatch[1]); // Capture annotations (e.g., @Route)
            $visibility = $methodMatch[2] ?: 'public';
            $name = $methodMatch[3];
            $parameters = [];
            $paramMappings = [];

            // Process method parameters
            if (trim($methodMatch[4])) {
                foreach (array_filter(array_map('trim', explode(',', $methodMatch[4]))) as $param) {
                    if (preg_match('/([\w\\\\]+)\s*\$([\w]+)/', $param, $paramMatch)) {
                        $paramMappings[$paramMatch[2]] = $paramMatch[1];
                        $parameters[] = [
                            'type' => $paramMatch[1],
                            'name' => $paramMatch[2]
                        ];
                    }
                }
            }

            // Detect various usages in the method body
            $body = $methodMatch[5];

            // Extract route annotation, if present
            $routeData = [];
            if (preg_match($regex['routeAnnotation'], $docComment, $routeMatch)) {
                $routeData['path'] = $routePrefix.$routeMatch[1];
                $routeData['routename'] = $routeMatch[2] ?? null;
            }



            $functionCalls = [];
            $repositoryCalls = [];
            $twigCalls = [];
            $jsonResponses = [];
            $responses = [];
            $queryParams = [];
            $instantiations = [];

            // Detect standard function calls
            if (preg_match_all($regex['functionCalls'], $body, $callMatches, PREG_SET_ORDER)) {
                foreach ($callMatches as $callMatch) {
                    $instance = $callMatch[1];
                    $method = $callMatch[2];
                    if (isset($paramMappings[$instance]) && isset($useStatements[$paramMappings[$instance]])) {
                        $functionCalls[] = [
                            'class' => $useStatements[$paramMappings[$instance]],
                            'method' => $method
                        ];
                    }
                }
            }

            // Detect repository calls
            if (preg_match_all($regex['repositoryCalls'], $body, $repoMatches, PREG_SET_ORDER)) {
                foreach ($repoMatches as $repoMatch) {
                    $repositoryCalls[] = [
                        'type' => 'repository',
                        'entity' => $repoMatch[1],
                        'method' => $repoMatch[2],
                        'parameters' => trim($repoMatch[3])
                    ];
                }
            }

            // Detect Twig rendering
            if (preg_match_all($regex['twigRender'], $body, $twigMatches, PREG_SET_ORDER)) {
                foreach ($twigMatches as $twigMatch) {
                    $twigCalls[] = [
                        'type' => 'twig',
                        'template' => $twigMatch[1],
                        'parameters' => $twigMatch[2]
                    ];
                }
            }

            // Detect Twig rendering
            if (preg_match_all($regex['twigRenderArrow'], $body, $twigMatches, PREG_SET_ORDER)) {
                foreach ($twigMatches as $twigMatch) {
                    $twigCalls[] = [
                        'type' => 'twig',
                        'template' => $twigMatch[1],
                        'parameters' => $twigMatch[2]
                    ];
                }
            }

            // Detect JSON responses
            if (preg_match_all($regex['jsonResponse'], $body, $jsonMatches, PREG_SET_ORDER)) {
                foreach ($jsonMatches as $jsonMatch) {
                    $jsonResponses[] = [
                        'type' => 'json_response',
                        'parameters' => trim($jsonMatch[1])
                    ];
                }
            }

            // Detect general responses
            if (preg_match_all($regex['response'], $body, $responseMatches, PREG_SET_ORDER)) {
                foreach ($responseMatches as $responseMatch) {
                    $responses[] = [
                        'type' => 'response',
                        'parameters' => trim($responseMatch[1])
                    ];
                }
            }

            // Detect query parameters
            if (preg_match_all($regex['queryGet'], $body, $queryMatches, PREG_SET_ORDER)) {
                foreach ($queryMatches as $queryMatch) {
                    $queryParams[] = [
                        'variable' => $queryMatch[1],
                        'key' => $queryMatch[2]
                    ];
                }
            }

            // Detect entity instantiations
            if (preg_match_all($regex['instantiation'], $body, $entityMatches, PREG_SET_ORDER)) {
                foreach ($entityMatches as $entityMatch) {
                    $instantiations[] = [
                        'variable' => $entityMatch[1],
                        'entity' => $entityMatch[2]
                    ];
                }
            }

            $result['methods'][] = [
                'visibility' => $visibility,
                'name' => $name,
                'parameters' => $parameters,
                'annotations' => $docComment,
                'route' => $routeData,
                'uses' => $functionCalls,
                'repository_calls' => $repositoryCalls,
                'twig_calls' => $twigCalls,
                'json_responses' => $jsonResponses,
                'responses' => $responses,
                'query_params' => $queryParams,
                'instantiations' => $instantiations,
                'body' => $body
            ];
        }
    }
    return $result;
}
