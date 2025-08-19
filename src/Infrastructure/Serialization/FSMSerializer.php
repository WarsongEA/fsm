<?php

declare(strict_types=1);

namespace FSM\Infrastructure\Serialization;

use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\Model\FSMInstance;
use FSM\Core\Model\FSMMetadata;
use FSM\Core\ValueObject\State;

/**
 * Serializer for FSM instances
 * Uses clean JSON format without PHP serialization
 */
final class FSMSerializer
{
    public function serialize(FSMInstance $instance): string
    {
        $automaton = $instance->getAutomaton();
        
        $data = [
            'id' => $instance->getId(),
            'version' => $instance->getVersion(),
            'automaton' => [
                'states' => $automaton->getStates()->toArray(),
                'alphabet' => $automaton->getAlphabet()->toArray(),
                'initial_state' => (string)$automaton->getInitialState(),
                'final_states' => $automaton->getFinalStates()->toArray(),
                'transitions' => $automaton->getTransitionFunction()->toArray()
            ],
            'metadata' => [
                'name' => $instance->getMetadata()->name,
                'description' => $instance->getMetadata()->description,
                'created_at' => $instance->getMetadata()->createdAt,
                'execution_count' => $instance->getMetadata()->getExecutionCount()
            ],
            'state' => [
                'current' => (string)$instance->getCurrentState(),
                'history' => $instance->getHistory()
            ]
        ];
        
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
    
    public function deserialize(string $json): FSMInstance
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        
        // Rebuild automaton
        $builder = AutomatonBuilder::create()
            ->withStates(...$data['automaton']['states'])
            ->withAlphabet(...$data['automaton']['alphabet'])
            ->withInitialState($data['automaton']['initial_state'])
            ->withFinalStates(...$data['automaton']['final_states']);
        
        // Add transitions
        foreach ($data['automaton']['transitions'] as $transition) {
            $builder->withTransition(
                $transition['from'],
                $transition['input'],
                $transition['to']
            );
        }
        
        $automaton = $builder->build();
        
        // Create metadata
        $metadata = new FSMMetadata(
            name: $data['metadata']['name'],
            description: $data['metadata']['description'],
            createdAt: $data['metadata']['created_at']
        );
        
        // Set execution count
        for ($i = 0; $i < $data['metadata']['execution_count']; $i++) {
            $metadata->incrementExecutionCount();
        }
        
        // Rebuild instance
        return FSMInstance::restore(
            id: $data['id'],
            automaton: $automaton,
            currentState: new State($data['state']['current']),
            history: $data['state']['history'] ?? [],
            version: $data['version'],
            metadata: $metadata
        );
    }
}