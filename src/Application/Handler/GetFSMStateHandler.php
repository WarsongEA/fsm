<?php

declare(strict_types=1);

namespace FSM\Application\Handler;

use FSM\Application\Query\GetFSMStateQuery;
use FSM\Application\DTO\GetFSMStateResult;
use FSM\Application\Exception\FSMNotFoundException;
use FSM\Application\Port\FSMRepository;

/**
 * Handler for getting FSM state
 */
final class GetFSMStateHandler
{
    public function __construct(
        private readonly FSMRepository $repository
    ) {
    }
    
    public function handle(GetFSMStateQuery $query): GetFSMStateResult
    {
        $instance = $this->repository->findById($query->fsmId);
        if ($instance === null) {
            throw FSMNotFoundException::withId($query->fsmId);
        }
        
        $currentState = $instance->getCurrentState();
        $finalStates = $instance->getAutomaton()->getFinalStates();
        
        // Get limited history if requested
        $history = $instance->getHistory();
        if ($query->historyLimit > 0) {
            $history = array_slice($history, -$query->historyLimit);
        }
        
        return new GetFSMStateResult(
            currentState: (string)$currentState,
            isFinalState: $finalStates->contains($currentState),
            history: $history,
            metadata: $instance->getMetadata()
        );
    }
}