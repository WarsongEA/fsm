<?php

declare(strict_types=1);

namespace FSM\Examples\ModuloThree;

use FSM\Core\Model\FiniteAutomaton;
use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;
use InvalidArgumentException;
use LogicException;

final class ModuloThreeAutomaton
{
    private static ?FiniteAutomaton $instance = null;
    
    public static function getInstance(): FiniteAutomaton
    {
        if (self::$instance === null) {
            self::$instance = AutomatonBuilder::create()
                ->withStates('S0', 'S1', 'S2')
                ->withAlphabet('0', '1')
                ->withInitialState('S0')
                ->withFinalStates('S0', 'S1', 'S2')
                ->withTransitions([
                    'S0:0' => 'S0',  // 0 * 2 = 0 (mod 3)
                    'S0:1' => 'S1',  // 0 * 2 + 1 = 1 (mod 3)
                    'S1:0' => 'S2',  // 1 * 2 = 2 (mod 3)
                    'S1:1' => 'S0',  // 1 * 2 + 1 = 3 ≡ 0 (mod 3)
                    'S2:0' => 'S1',  // 2 * 2 = 4 ≡ 1 (mod 3)
                    'S2:1' => 'S2',  // 2 * 2 + 1 = 5 ≡ 2 (mod 3)
                ])
                ->build();
        }
        
        return self::$instance;
    }
    
    public static function calculate(string $binaryString): int
    {
        if (!preg_match('/^[01]+$/', $binaryString)) {
            throw new InvalidArgumentException('Input must be a binary string');
        }
        
        $automaton = self::getInstance();
        $result = $automaton->execute(new InputString($binaryString));
        
        return match((string)$result->finalState) {
            'S0' => 0,
            'S1' => 1,
            'S2' => 2,
            default => throw new LogicException('Invalid final state')
        };
    }
}