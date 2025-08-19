<?php

declare(strict_types=1);

namespace FSM\Examples\ModuloThree;

use FSM\Core\ValueObject\InputString;
use InvalidArgumentException;

final class ModuloThreeService
{
    public function calculate(string $binaryInput, bool $returnTransitions = false): ModuloThreeResult
    {
        $startTime = microtime(true);
        
        if (!preg_match('/^[01]+$/', $binaryInput)) {
            throw new InvalidArgumentException('Input must be a binary string');
        }
        
        $automaton = ModuloThreeAutomaton::getInstance();
        $result = $automaton->execute(new InputString($binaryInput));
        
        $decimalValue = gmp_strval(gmp_init($binaryInput, 2), 10);
        
        $modulo = match((string)$result->finalState) {
            'S0' => 0,
            'S1' => 1,
            'S2' => 2,
        };
        
        return new ModuloThreeResult(
            modulo: $modulo,
            finalState: (string)$result->finalState,
            decimalValue: $decimalValue,
            transitions: $returnTransitions ? $result->getPath() : [],
            executionTimeMs: (microtime(true) - $startTime) * 1000
        );
    }
}