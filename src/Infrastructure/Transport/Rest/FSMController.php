<?php

declare(strict_types=1);

namespace FSM\Infrastructure\Transport\Rest;

use FSM\Application\Command\CreateFSMCommand;
use FSM\Application\Command\ExecuteFSMCommand;
use FSM\Application\Query\GetFSMStateQuery;
use FSM\Application\Handler\CreateFSMHandler;
use FSM\Application\Handler\ExecuteFSMHandler;
use FSM\Application\Handler\GetFSMStateHandler;
use FSM\Application\Exception\FSMNotFoundException;
use FSM\Core\Exception\InvalidAutomatonException;
use FSM\Core\Exception\InvalidInputException;
use FSM\Core\Exception\InvalidTransitionException;
use FSM\Application\Exception\ConcurrencyException;

/**
 * REST controller for FSM operations
 * Thin adapter that delegates to application layer
 */
final class FSMController
{
    public function __construct(
        private readonly CreateFSMHandler $createHandler,
        private readonly ExecuteFSMHandler $executeHandler,
        private readonly GetFSMStateHandler $getStateHandler
    ) {
    }
    
    /**
     * POST /api/fsm
     * Create a new FSM instance
     */
    public function createFSM(array $requestData): array
    {
        try {
            $this->validateCreateRequest($requestData);
            
            $command = new CreateFSMCommand(
                states: $requestData['states'],
                alphabet: $requestData['alphabet'],
                initialState: $requestData['initial_state'],
                finalStates: $requestData['final_states'],
                transitions: $this->parseTransitions($requestData['transitions']),
                name: $requestData['name'] ?? null,
                description: $requestData['description'] ?? null
            );
            
            $result = $this->createHandler->handle($command);
            
            return [
                'status' => 'success',
                'data' => [
                    'fsm_id' => $result->fsmId,
                    'metadata' => [
                        'name' => $result->metadata->name,
                        'description' => $result->metadata->description,
                        'created_at' => $result->metadata->createdAt
                    ]
                ]
            ];
            
        } catch (InvalidAutomatonException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    /**
     * POST /api/fsm/{id}/execute
     * Execute input on an FSM
     */
    public function execute(string $fsmId, array $requestData): array
    {
        try {
            $this->validateExecuteRequest($requestData);
            
            $command = new ExecuteFSMCommand(
                fsmId: $fsmId,
                input: $requestData['input'],
                recordHistory: $requestData['record_history'] ?? false
            );
            
            $result = $this->executeHandler->handle($command);
            
            return [
                'status' => 'success',
                'data' => [
                    'final_state' => $result->finalState,
                    'is_accepted' => $result->isAccepted,
                    'transitions' => $result->transitions,
                    'execution_time_ms' => $result->executionTimeMs
                ]
            ];
            
        } catch (FSMNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (InvalidInputException | InvalidTransitionException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (ConcurrencyException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (\Exception $e) {
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    /**
     * GET /api/fsm/{id}/state
     * Get current FSM state
     */
    public function getState(string $fsmId, array $queryParams = []): array
    {
        try {
            $historyLimit = isset($queryParams['history_limit']) 
                ? (int)$queryParams['history_limit'] 
                : 0;
            
            $query = new GetFSMStateQuery($fsmId, $historyLimit);
            $result = $this->getStateHandler->handle($query);
            
            return [
                'status' => 'success',
                'data' => [
                    'current_state' => $result->currentState,
                    'is_final_state' => $result->isFinalState,
                    'history' => $result->history,
                    'metadata' => [
                        'name' => $result->metadata->name,
                        'description' => $result->metadata->description,
                        'created_at' => $result->metadata->createdAt,
                        'execution_count' => $result->metadata->getExecutionCount()
                    ]
                ]
            ];
            
        } catch (FSMNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    /**
     * POST /api/fsm/batch
     * Execute batch of inputs
     */
    public function executeBatch(string $fsmId, array $requestData): array
    {
        try {
            if (!isset($requestData['inputs']) || !is_array($requestData['inputs'])) {
                return $this->errorResponse('Invalid request: inputs array required', 400);
            }
            
            $results = [];
            $totalTime = 0;
            
            foreach ($requestData['inputs'] as $input) {
                $command = new ExecuteFSMCommand(
                    fsmId: $fsmId,
                    input: $input,
                    recordHistory: false
                );
                
                try {
                    $result = $this->executeHandler->handle($command);
                    $results[] = [
                        'input' => $input,
                        'final_state' => $result->finalState,
                        'is_accepted' => $result->isAccepted,
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
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    private function validateCreateRequest(array $data): void
    {
        $required = ['states', 'alphabet', 'initial_state', 'final_states', 'transitions'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        
        if (!is_array($data['states']) || empty($data['states'])) {
            throw new \InvalidArgumentException('States must be a non-empty array');
        }
        
        if (!is_array($data['alphabet']) || empty($data['alphabet'])) {
            throw new \InvalidArgumentException('Alphabet must be a non-empty array');
        }
    }
    
    private function validateExecuteRequest(array $data): void
    {
        if (!isset($data['input'])) {
            throw new \InvalidArgumentException('Missing required field: input');
        }
        
        if (!is_string($data['input'])) {
            throw new \InvalidArgumentException('Input must be a string');
        }
    }
    
    private function parseTransitions($transitions): array
    {
        if (!is_array($transitions)) {
            throw new \InvalidArgumentException('Transitions must be an array');
        }
        
        // Handle both formats: {"from:symbol": "to"} or [{"from": "A", "symbol": "0", "to": "B"}]
        $result = [];
        
        foreach ($transitions as $key => $value) {
            if (is_string($key) && is_string($value)) {
                // Format: "from:symbol" => "to"
                $result[$key] = $value;
            } elseif (is_array($value)) {
                // Format: {"from": "A", "symbol": "0", "to": "B"}
                if (!isset($value['from'], $value['symbol'], $value['to'])) {
                    throw new \InvalidArgumentException('Invalid transition format');
                }
                $key = $value['from'] . ':' . $value['symbol'];
                $result[$key] = $value['to'];
            }
        }
        
        return $result;
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