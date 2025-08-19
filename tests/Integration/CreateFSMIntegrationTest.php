<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use FSM\Application\Command\CreateFSMCommand;
use FSM\Application\Command\ExecuteFSMCommand;
use FSM\Application\Handler\CreateFSMHandler;
use FSM\Application\Handler\ExecuteFSMHandler;
use FSM\Application\Service\FSMExecutor;
use FSM\Infrastructure\Persistence\InMemoryFSMRepository;
use FSM\Infrastructure\Event\NullEventDispatcher;

final class CreateFSMIntegrationTest extends TestCase
{
    private CreateFSMHandler $createHandler;
    private ExecuteFSMHandler $executeHandler;
    private InMemoryFSMRepository $repository;
    
    protected function setUp(): void
    {
        $this->repository = new InMemoryFSMRepository();
        $this->createHandler = new CreateFSMHandler(
            $this->repository,
            new NullEventDispatcher()
        );
        $this->executeHandler = new ExecuteFSMHandler(
            $this->repository,
            new FSMExecutor()
        );
    }
    
    public function testCreateAndExecuteFSM(): void
    {
        // Create FSM
        $createCommand = new CreateFSMCommand(
            states: ['S0', 'S1', 'S2'],
            alphabet: ['0', '1'],
            initialState: 'S0',
            finalStates: ['S0', 'S1', 'S2'],
            transitions: [
                'S0:0' => 'S0',
                'S0:1' => 'S1',
                'S1:0' => 'S2',
                'S1:1' => 'S0',
                'S2:0' => 'S1',
                'S2:1' => 'S2',
            ],
            name: 'Modulo-3 FSM',
            description: 'Calculates n mod 3 for binary input'
        );
        
        $createResult = $this->createHandler->handle($createCommand);
        
        // Verify creation
        $this->assertNotEmpty($createResult->fsmId);
        $this->assertEquals('Modulo-3 FSM', $createResult->metadata->name);
        
        // Execute FSM
        $executeCommand = new ExecuteFSMCommand(
            fsmId: $createResult->fsmId,
            input: '110',  // Binary 6, should be 0 mod 3
            recordHistory: true
        );
        
        $executeResult = $this->executeHandler->handle($executeCommand);
        
        // Verify execution
        $this->assertEquals('S0', $executeResult->finalState);
        $this->assertTrue($executeResult->isAccepted);
        $this->assertCount(3, $executeResult->transitions);
        
        // Verify transitions
        $expectedTransitions = [
            ['from' => 'S0', 'input' => '1', 'to' => 'S1'],
            ['from' => 'S1', 'input' => '1', 'to' => 'S0'],
            ['from' => 'S0', 'input' => '0', 'to' => 'S0'],
        ];
        
        $this->assertEquals($expectedTransitions, $executeResult->transitions);
    }
    
    public function testMultipleExecutionsOnSameFSM(): void
    {
        // Create a simple even/odd FSM
        $createCommand = new CreateFSMCommand(
            states: ['Even', 'Odd'],
            alphabet: ['0', '1'],
            initialState: 'Even',
            finalStates: ['Even'],
            transitions: [
                'Even:0' => 'Even',
                'Even:1' => 'Odd',
                'Odd:0' => 'Odd',
                'Odd:1' => 'Even',
            ],
            name: 'Even/Odd FSM'
        );
        
        $createResult = $this->createHandler->handle($createCommand);
        $fsmId = $createResult->fsmId;
        
        // Test multiple inputs
        $testCases = [
            '0' => ['Even', true],      // 0 ones = even
            '1' => ['Odd', false],       // 1 one = odd
            '11' => ['Even', true],      // 2 ones = even
            '111' => ['Odd', false],     // 3 ones = odd
            '1010' => ['Even', true],    // 2 ones = even
            '10101' => ['Odd', false],   // 3 ones = odd
        ];
        
        foreach ($testCases as $input => [$expectedState, $expectedAccepted]) {
            $executeCommand = new ExecuteFSMCommand(
                fsmId: $fsmId,
                input: $input,
                recordHistory: false
            );
            
            $result = $this->executeHandler->handle($executeCommand);
            
            $this->assertEquals(
                $expectedState,
                $result->finalState,
                "Failed for input: {$input}"
            );
            
            $this->assertEquals(
                $expectedAccepted,
                $result->isAccepted,
                "Failed acceptance for input: {$input}"
            );
        }
    }
    
    public function testFSMWithHistory(): void
    {
        // Create FSM
        $createCommand = new CreateFSMCommand(
            states: ['A', 'B'],
            alphabet: ['0', '1'],
            initialState: 'A',
            finalStates: ['B'],
            transitions: [
                'A:0' => 'A',
                'A:1' => 'B',
                'B:0' => 'B',
                'B:1' => 'A',
            ]
        );
        
        $createResult = $this->createHandler->handle($createCommand);
        
        // Execute with history
        $executeCommand = new ExecuteFSMCommand(
            fsmId: $createResult->fsmId,
            input: '0110',
            recordHistory: true
        );
        
        $this->executeHandler->handle($executeCommand);
        
        // Retrieve and check history
        $instance = $this->repository->findById($createResult->fsmId);
        $this->assertNotNull($instance);
        
        $history = $instance->getHistory();
        $this->assertCount(4, $history);
        
        // Verify each transition in history
        $this->assertEquals('A', $history[0]['from']);
        $this->assertEquals('0', $history[0]['input']);
        $this->assertEquals('A', $history[0]['to']);
        
        $this->assertEquals('A', $history[1]['from']);
        $this->assertEquals('1', $history[1]['input']);
        $this->assertEquals('B', $history[1]['to']);
    }
}