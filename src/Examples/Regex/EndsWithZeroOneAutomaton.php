<?php

declare(strict_types=1);

namespace FSM\Examples\Regex;

use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\Model\FiniteAutomaton;
use FSM\Core\ValueObject\InputString;

/**
 * Example: FSM for matching binary strings ending with "01"
 * Shows how to build pattern matching automata
 * 
 * This FSM accepts any binary string that ends with the pattern "01"
 * For example: "01", "101", "0101", "11101" are accepted
 * But "10", "00", "011" are rejected
 */
final class EndsWithZeroOneAutomaton
{
    private static ?FiniteAutomaton $instance = null;
    
    /**
     * Create the pattern matching automaton
     * States track progress towards matching "01" at the end
     */
    public static function create(): FiniteAutomaton
    {
        if (self::$instance === null) {
            self::$instance = AutomatonBuilder::create()
                ->withStates('Start', 'Zero', 'ZeroOne')
                ->withAlphabet('0', '1')
                ->withInitialState('Start')
                ->withFinalStates('ZeroOne')
                ->withTransitions([
                    // From Start state
                    'Start:0' => 'Zero',     // Saw 0, might be start of "01"
                    'Start:1' => 'Start',    // Saw 1, stay at start
                    
                    // From Zero state (saw 0)
                    'Zero:0' => 'Zero',      // Another 0, stay here
                    'Zero:1' => 'ZeroOne',   // Saw 1 after 0, pattern matched!
                    
                    // From ZeroOne state (matched "01")
                    'ZeroOne:0' => 'Zero',   // New 0, might be new pattern
                    'ZeroOne:1' => 'Start',  // Back to start on 1
                ])
                ->build();
        }
        
        return self::$instance;
    }
    
    /**
     * Check if a binary string ends with "01"
     */
    public static function matches(string $input): bool
    {
        if (!preg_match('/^[01]*$/', $input)) {
            throw new \InvalidArgumentException('Input must be a binary string');
        }
        
        if (empty($input)) {
            return false; // Empty string doesn't end with "01"
        }
        
        $automaton = self::create();
        $result = $automaton->execute(new InputString($input));
        return $result->isAccepted;
    }
    
    /**
     * Find all positions where "01" occurs in the string
     */
    public static function findAllOccurrences(string $input): array
    {
        if (!preg_match('/^[01]*$/', $input)) {
            throw new \InvalidArgumentException('Input must be a binary string');
        }
        
        $positions = [];
        $automaton = self::create();
        
        // Check all suffixes
        for ($i = 0; $i < strlen($input) - 1; $i++) {
            $suffix = substr($input, $i);
            $result = $automaton->execute(new InputString($suffix));
            
            // If this suffix is accepted, it means "01" ends at position i+1
            if ($result->isAccepted && strlen($suffix) >= 2) {
                // Check if "01" actually occurs at this position
                if (substr($input, $i, 2) === '01') {
                    $positions[] = $i;
                }
            }
        }
        
        return $positions;
    }
}

/**
 * More complex pattern matcher for strings containing "101"
 */
final class ContainsOneZeroOneAutomaton
{
    private static ?FiniteAutomaton $instance = null;
    
    /**
     * Create an automaton that accepts strings containing "101"
     */
    public static function create(): FiniteAutomaton
    {
        if (self::$instance === null) {
            self::$instance = AutomatonBuilder::create()
                ->withStates('Start', 'One', 'OneZero', 'Accept')
                ->withAlphabet('0', '1')
                ->withInitialState('Start')
                ->withFinalStates('Accept')
                ->withTransitions([
                    // Building towards "101"
                    'Start:0' => 'Start',      // Reset on 0
                    'Start:1' => 'One',        // Start of potential "101"
                    
                    'One:0' => 'OneZero',      // "10" found
                    'One:1' => 'One',          // Stay at "1"
                    
                    'OneZero:0' => 'Start',    // Reset to start
                    'OneZero:1' => 'Accept',   // "101" found!
                    
                    // Once accepted, stay accepted
                    'Accept:0' => 'Accept',
                    'Accept:1' => 'Accept',
                ])
                ->build();
        }
        
        return self::$instance;
    }
    
    public static function matches(string $input): bool
    {
        if (!preg_match('/^[01]*$/', $input)) {
            throw new \InvalidArgumentException('Input must be a binary string');
        }
        
        $automaton = self::create();
        $result = $automaton->execute(new InputString($input));
        return $result->isAccepted;
    }
}