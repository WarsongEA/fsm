<?php

declare(strict_types=1);

namespace FSM\Examples\BinaryAdder;

use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\Model\FiniteAutomaton;
use FSM\Core\ValueObject\InputString;

/**
 * Example: Binary addition FSM with carry
 * Demonstrates a more complex automaton for adding two binary numbers
 * 
 * This FSM processes pairs of bits and maintains carry state
 * Input format: pairs like "00", "01", "10", "11" representing two binary digits
 */
final class BinaryAdderAutomaton
{
    private static ?FiniteAutomaton $instance = null;
    
    /**
     * Create a binary adder automaton
     * States represent carry state: NoCarry or Carry
     */
    public static function create(): FiniteAutomaton
    {
        if (self::$instance === null) {
            self::$instance = AutomatonBuilder::create()
                ->withStates('NoCarry', 'Carry')
                ->withAlphabet('00', '01', '10', '11')  // Input pairs
                ->withInitialState('NoCarry')
                ->withFinalStates('NoCarry')  // Valid if no carry at end
                ->withTransitions([
                    // From NoCarry state
                    'NoCarry:00' => 'NoCarry',  // 0+0=0, no carry
                    'NoCarry:01' => 'NoCarry',  // 0+1=1, no carry
                    'NoCarry:10' => 'NoCarry',  // 1+0=1, no carry
                    'NoCarry:11' => 'Carry',    // 1+1=0, carry 1
                    
                    // From Carry state
                    'Carry:00' => 'NoCarry',    // 0+0+1=1, no carry
                    'Carry:01' => 'Carry',      // 0+1+1=0, carry 1
                    'Carry:10' => 'Carry',      // 1+0+1=0, carry 1
                    'Carry:11' => 'Carry',      // 1+1+1=1, carry 1
                ])
                ->build();
        }
        
        return self::$instance;
    }
    
    /**
     * Add two binary numbers represented as strings
     * Returns the sum and whether there's an overflow
     */
    public static function add(string $binary1, string $binary2): BinaryAdditionResult
    {
        // Pad to same length
        $maxLen = max(strlen($binary1), strlen($binary2));
        $binary1 = str_pad($binary1, $maxLen, '0', STR_PAD_LEFT);
        $binary2 = str_pad($binary2, $maxLen, '0', STR_PAD_LEFT);
        
        // Process from right to left (LSB to MSB)
        $pairs = [];
        for ($i = $maxLen - 1; $i >= 0; $i--) {
            $pairs[] = $binary1[$i] . $binary2[$i];
        }
        
        $automaton = self::create();
        $inputString = implode('', $pairs);
        
        // Create input string with pairs as symbols
        $inputSymbols = [];
        for ($i = 0; $i < strlen($inputString); $i += 2) {
            $inputSymbols[] = substr($inputString, $i, 2);
        }
        
        // Build a proper input string
        $processedInput = new InputString($inputSymbols);
        
        $result = $automaton->execute($processedInput);
        
        // Calculate the actual sum
        $sum = self::calculateSum($pairs, $result->transitions);
        
        return new BinaryAdditionResult(
            operand1: $binary1,
            operand2: $binary2,
            sum: $sum,
            hasOverflow: !$result->isAccepted,
            transitions: $result->transitions
        );
    }
    
    private static function calculateSum(array $pairs, array $transitions): string
    {
        $result = '';
        $carry = 0;
        
        foreach ($pairs as $i => $pair) {
            $bit1 = (int)$pair[0];
            $bit2 = (int)$pair[1];
            $sum = $bit1 + $bit2 + $carry;
            $result = ($sum % 2) . $result;
            $carry = $sum >= 2 ? 1 : 0;
        }
        
        if ($carry) {
            $result = '1' . $result;
        }
        
        return $result ?: '0';
    }
}

/**
 * Result of binary addition
 */
final class BinaryAdditionResult
{
    public function __construct(
        public readonly string $operand1,
        public readonly string $operand2,
        public readonly string $sum,
        public readonly bool $hasOverflow,
        public readonly array $transitions
    ) {
    }
}