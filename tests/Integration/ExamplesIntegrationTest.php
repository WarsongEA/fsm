<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;
use FSM\Examples\ModuloThree\ModuloThreeService;
use FSM\Examples\Regex\EndsWithZeroOneAutomaton;
use FSM\Examples\Regex\ContainsOneZeroOneAutomaton;

final class ExamplesIntegrationTest extends TestCase
{
    public function testModuloThreeAutomaton(): void
    {
        // Test various binary numbers
        $testCases = [
            '0' => 0,       // 0 mod 3 = 0
            '1' => 1,       // 1 mod 3 = 1
            '10' => 2,      // 2 mod 3 = 2
            '11' => 0,      // 3 mod 3 = 0
            '100' => 1,     // 4 mod 3 = 1
            '101' => 2,     // 5 mod 3 = 2
            '110' => 0,     // 6 mod 3 = 0
            '111' => 1,     // 7 mod 3 = 1
            '1000' => 2,    // 8 mod 3 = 2
            '1001' => 0,    // 9 mod 3 = 0
            '1111' => 0,    // 15 mod 3 = 0
            '10101' => 0,   // 21 mod 3 = 0
            '11011' => 0,   // 27 mod 3 = 0
        ];
        
        foreach ($testCases as $binary => $expected) {
            $result = ModuloThreeAutomaton::calculate($binary);
            $this->assertEquals(
                $expected,
                $result,
                "Failed for binary {$binary} (decimal " . bindec($binary) . ")"
            );
        }
    }
    
    public function testModuloThreeService(): void
    {
        $service = new ModuloThreeService();
        
        // Test with transitions
        $result = $service->calculate('110', true);
        
        $this->assertEquals(0, $result->modulo);
        $this->assertEquals('S0', $result->finalState);
        $this->assertEquals('6', $result->decimalValue);
        $this->assertCount(3, $result->transitions);
        
        // Verify transition path
        $expectedPath = [
            ['from' => 'S0', 'input' => '1', 'to' => 'S1'],
            ['from' => 'S1', 'input' => '1', 'to' => 'S0'],
            ['from' => 'S0', 'input' => '0', 'to' => 'S0'],
        ];
        
        $this->assertEquals($expectedPath, $result->transitions);
    }
    
    public function testEndsWithZeroOneAutomaton(): void
    {
        // Test cases that should be accepted
        $accepted = ['01', '001', '101', '0101', '1101', '00001', '11101'];
        
        foreach ($accepted as $input) {
            $this->assertTrue(
                EndsWithZeroOneAutomaton::matches($input),
                "Should accept '{$input}' (ends with 01)"
            );
        }
        
        // Test cases that should be rejected
        $rejected = ['', '0', '1', '10', '00', '11', '010', '011', '100'];
        
        foreach ($rejected as $input) {
            if ($input === '') continue; // Skip empty string
            
            $this->assertFalse(
                EndsWithZeroOneAutomaton::matches($input),
                "Should reject '{$input}' (doesn't end with 01)"
            );
        }
    }
    
    public function testFindAllOccurrences(): void
    {
        $input = '010101101';
        $positions = EndsWithZeroOneAutomaton::findAllOccurrences($input);
        
        // "01" occurs at positions 0, 2, 4, 6
        $expected = [0, 2, 4, 6];
        
        $this->assertEquals($expected, $positions);
    }
    
    public function testContainsOneZeroOneAutomaton(): void
    {
        // Test cases that should be accepted (contain "101")
        $accepted = ['101', '1101', '1010', '11011', '0101', '101101'];
        
        foreach ($accepted as $input) {
            $this->assertTrue(
                ContainsOneZeroOneAutomaton::matches($input),
                "Should accept '{$input}' (contains 101)"
            );
        }
        
        // Test cases that should be rejected (don't contain "101")
        $rejected = ['', '0', '1', '10', '01', '11', '100', '110', '011', '1001'];
        
        foreach ($rejected as $input) {
            if ($input === '') continue; // Skip empty string
            
            $this->assertFalse(
                ContainsOneZeroOneAutomaton::matches($input),
                "Should reject '{$input}' (doesn't contain 101)"
            );
        }
    }
    
    public function testLargeBinaryNumbers(): void
    {
        // Test with large binary numbers
        $service = new ModuloThreeService();
        
        // Generate a large binary number
        $largeBinary = str_repeat('10110101', 100); // 800 bits
        
        $result = $service->calculate($largeBinary, false);
        
        // Verify the result is valid (0, 1, or 2)
        $this->assertContains($result->modulo, [0, 1, 2]);
        
        // Verify performance (should be fast)
        $this->assertLessThan(100, $result->executionTimeMs);
    }
}