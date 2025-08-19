<?php

declare(strict_types=1);

namespace FSM\Application\Handler;

use FSM\Application\Command\ExecuteFSMCommand;
use FSM\Application\DTO\ExecuteFSMResult;
use FSM\Application\Exception\FSMNotFoundException;
use FSM\Application\Port\FSMRepository;
use FSM\Application\Service\FSMExecutor;
use FSM\Core\ValueObject\InputString;

/**
 * Handler for executing FSM
 */
final class ExecuteFSMHandler
{
    public function __construct(
        private readonly FSMRepository $repository,
        private readonly FSMExecutor $executor
    ) {
    }
    
    public function handle(ExecuteFSMCommand $command): ExecuteFSMResult
    {
        $instance = $this->repository->findById($command->fsmId);
        if ($instance === null) {
            throw FSMNotFoundException::withId($command->fsmId);
        }
        
        // Execute with optional history recording
        $result = $this->executor->execute(
            $instance,
            new InputString($command->input),
            $command->recordHistory
        );
        
        // Update instance state if stateful execution
        if ($command->recordHistory) {
            $this->repository->save($instance);
        }
        
        // Map transitions to DTO format
        $transitions = array_map(
            fn($t) => [
                'from' => (string)$t->fromState,
                'input' => (string)$t->symbol,
                'to' => (string)$t->toState
            ],
            $result->transitions
        );
        
        return new ExecuteFSMResult(
            finalState: (string)$result->finalState,
            isAccepted: $result->isAccepted,
            transitions: $transitions,
            executionTimeMs: $result->executionTimeMs
        );
    }
}