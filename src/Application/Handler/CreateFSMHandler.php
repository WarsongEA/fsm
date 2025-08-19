<?php

declare(strict_types=1);

namespace FSM\Application\Handler;

use FSM\Application\Command\CreateFSMCommand;
use FSM\Application\DTO\CreateFSMResult;
use FSM\Application\Event\FSMCreatedEvent;
use FSM\Application\Port\EventDispatcher;
use FSM\Application\Port\FSMRepository;
use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\Model\FSMInstance;
use FSM\Core\Model\FSMMetadata;

/**
 * Handler for creating FSM instances
 * Thin orchestration layer - no business logic
 */
final class CreateFSMHandler
{
    public function __construct(
        private readonly FSMRepository $repository,
        private readonly EventDispatcher $eventDispatcher
    ) {
    }
    
    public function handle(CreateFSMCommand $command): CreateFSMResult
    {
        // Build the automaton using the builder
        $automaton = AutomatonBuilder::create()
            ->withStates(...$command->states)
            ->withAlphabet(...$command->alphabet)
            ->withInitialState($command->initialState)
            ->withFinalStates(...$command->finalStates)
            ->withTransitions($command->transitions)
            ->build();
        
        // Generate unique ID
        $id = $this->generateId();
        
        // Create the FSM instance
        $instance = new FSMInstance(
            id: $id,
            automaton: $automaton,
            metadata: new FSMMetadata(
                name: $command->name,
                description: $command->description
            )
        );
        
        // Persist
        $this->repository->save($instance);
        
        // Dispatch event
        $this->eventDispatcher->dispatch(FSMCreatedEvent::fromInstance($instance));
        
        return new CreateFSMResult(
            fsmId: $instance->getId(),
            metadata: $instance->getMetadata()
        );
    }
    
    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}