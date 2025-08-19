<?php

declare(strict_types=1);

namespace FSM\Infrastructure\Transport\Rest;

use FSM\Examples\ModuloThree\ModuloThreeService;

/**
 * REST controller for Modulo-3 calculations
 */
final class ModuloThreeController
{
    private ModuloThreeService $service;
    
    public function __construct()
    {
        $this->service = new ModuloThreeService();
    }
    
    /**
     * POST /api/modulo-three
     * Calculate n mod 3 for binary input
     */
    public function calculate(array $requestData): array
    {
        try {
            if (!isset($requestData['binary_input'])) {
                return $this->errorResponse('Missing required field: binary_input', 400);
            }
            
            $binaryInput = $requestData['binary_input'];
            $returnTransitions = $requestData['return_transitions'] ?? false;
            
            if (!is_string($binaryInput)) {
                return $this->errorResponse('Binary input must be a string', 400);
            }
            
            if (!preg_match('/^[01]+$/', $binaryInput)) {
                return $this->errorResponse('Input must be a binary string (only 0 and 1)', 400);
            }
            
            $result = $this->service->calculate($binaryInput, $returnTransitions);
            
            return [
                'status' => 'success',
                'data' => [
                    'binary_input' => $binaryInput,
                    'decimal_value' => $result->decimalValue,
                    'modulo_result' => $result->modulo,
                    'final_state' => $result->finalState,
                    'transitions' => $returnTransitions ? $result->transitions : null,
                    'execution_time_ms' => $result->executionTimeMs
                ]
            ];
            
        } catch (\Exception $e) {
            return $this->errorResponse('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/modulo-three/batch
     * Calculate modulo-3 for multiple binary inputs
     */
    public function calculateBatch(array $requestData): array
    {
        try {
            if (!isset($requestData['inputs']) || !is_array($requestData['inputs'])) {
                return $this->errorResponse('Missing required field: inputs (array)', 400);
            }
            
            $results = [];
            $totalTime = 0;
            
            foreach ($requestData['inputs'] as $input) {
                if (!is_string($input) || !preg_match('/^[01]+$/', $input)) {
                    $results[] = [
                        'input' => $input,
                        'error' => 'Invalid binary string'
                    ];
                    continue;
                }
                
                try {
                    $result = $this->service->calculate($input, false);
                    $results[] = [
                        'input' => $input,
                        'decimal_value' => $result->decimalValue,
                        'modulo_result' => $result->modulo,
                        'execution_time_ms' => $result->executionTimeMs
                    ];
                    $totalTime += $result->executionTimeMs;
                } catch (\Exception $e) {
                    $results[] = [
                        'input' => $input,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return [
                'status' => 'success',
                'data' => [
                    'results' => $results,
                    'total_execution_time_ms' => $totalTime,
                    'inputs_processed' => count($results)
                ]
            ];
            
        } catch (\Exception $e) {
            return $this->errorResponse('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    private function errorResponse(string $message, int $code): array
    {
        return [
            'status' => 'error',
            'error' => [
                'message' => $message,
                'code' => $code
            ]
        ];
    }
}