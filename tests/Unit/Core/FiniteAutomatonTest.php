<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;
use FSM\Core\Exception\InvalidInputException;
use FSM\Core\Exception\InvalidTransitionException;
use FSM\Core\Exception\InvalidAutomatonException;

final class FiniteAutomatonTest extends TestCase
{
    public function testBuilderCreatesValidAutomaton(): void
    {
        $automaton = AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransition('A', '0', 'A')
            ->withTransition('A', '1', 'B')
            ->withTransition('B', '0', 'B')
            ->withTransition('B', '1', 'A')
            ->build();
        
        $this->assertCount(2, $automaton->getStates());
        $this->assertCount(2, $automaton->getAlphabet());
        $this->assertEquals('A', (string)$automaton->getInitialState());
    }
    
    public function testExecutionProducesCorrectResult(): void
    {
        $automaton = $this->createTestAutomaton();
        
        $result = $automaton->execute(new InputString('0110'));
        
        $this->assertEquals('A', (string)$result->finalState);
        $this->assertFalse($result->isAccepted);
        $this->assertCount(4, $result->transitions);
    }
    
    public function testInvalidInputThrowsException(): void
    {
        $automaton = $this->createTestAutomaton();
        
        $this->expectException(InvalidInputException::class);
        $automaton->execute(new InputString('012'));
    }
    
    public function testPartialTransitionFunctionHandled(): void
    {
        $automaton = AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransition('A', '0', 'B')
            ->build();
        
        $this->expectException(InvalidTransitionException::class);
        $automaton->execute(new InputString('1'));
    }
    
    public function testEmptyInputReturnsInitialState(): void
    {
        $automaton = $this->createTestAutomaton();
        
        $result = $automaton->execute(new InputString(''));
        
        $this->assertEquals('A', (string)$result->finalState);
        $this->assertFalse($result->isAccepted);
        $this->assertCount(0, $result->transitions);
    }
    
    public function testInvalidInitialStateThrowsException(): void
    {
        $this->expectException(InvalidAutomatonException::class);
        $this->expectExceptionMessage('Initial state');
        
        AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('C')
            ->withFinalStates('B')
            ->build();
    }
    
    public function testInvalidFinalStateThrowsException(): void
    {
        $this->expectException(InvalidAutomatonException::class);
        $this->expectExceptionMessage('Final state');
        
        AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('C')
            ->build();
    }
    
    public function testBulkTransitionsWork(): void
    {
        $automaton = AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransitions([
                'A:0' => 'A',
                'A:1' => 'B',
                'B:0' => 'B',
                'B:1' => 'A'
            ])
            ->build();
        
        $result = $automaton->execute(new InputString('01'));
        $this->assertEquals('B', (string)$result->finalState);
    }
    
    private function createTestAutomaton()
    {
        return AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransition('A', '0', 'A')
            ->withTransition('A', '1', 'B')
            ->withTransition('B', '0', 'B')
            ->withTransition('B', '1', 'A')
            ->build();
    }
}