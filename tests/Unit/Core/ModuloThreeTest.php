<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;
use FSM\Examples\ModuloThree\ModuloThreeService;
use InvalidArgumentException;

final class ModuloThreeTest extends TestCase
{
    /**
     * @dataProvider binaryNumberProvider
     */
    public function testModuloThreeCorrectness(string $binary, int $decimal, int $expectedModulo): void
    {
        $result = ModuloThreeAutomaton::calculate($binary);
        $this->assertEquals($expectedModulo, $result);
        $this->assertEquals($decimal % 3, $result);
    }
    
    public function binaryNumberProvider(): array
    {
        return [
            ['0', 0, 0],
            ['1', 1, 1],
            ['10', 2, 2],
            ['11', 3, 0],
            ['100', 4, 1],
            ['101', 5, 2],
            ['110', 6, 0],
            ['111', 7, 1],
            ['1000', 8, 2],
            ['1001', 9, 0],
            ['1010', 10, 1],
            ['1011', 11, 2],
            ['1100', 12, 0],
            ['11111111', 255, 0],
            ['100000000', 256, 1],
            ['111111111', 511, 1],
        ];
    }
    
    public function testInvalidBinaryStringThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ModuloThreeAutomaton::calculate('102');
    }
    
    public function testEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ModuloThreeAutomaton::calculate('');
    }
    
    public function testServiceWithTransitions(): void
    {
        $service = new ModuloThreeService();
        $result = $service->calculate('1011', true);
        
        $this->assertEquals(2, $result->modulo);
        $this->assertEquals('S2', $result->finalState);
        $this->assertEquals('11', $result->decimalValue);
        $this->assertCount(4, $result->transitions);
        
        $expected = [
            ['from' => 'S0', 'input' => '1', 'to' => 'S1'],
            ['from' => 'S1', 'input' => '0', 'to' => 'S2'],
            ['from' => 'S2', 'input' => '1', 'to' => 'S2'],
            ['from' => 'S2', 'input' => '1', 'to' => 'S2'],
        ];
        
        $this->assertEquals($expected, $result->transitions);
    }
    
    public function testServiceWithoutTransitions(): void
    {
        $service = new ModuloThreeService();
        $result = $service->calculate('1011', false);
        
        $this->assertEquals(2, $result->modulo);
        $this->assertEmpty($result->transitions);
    }
    
    public function testLargeBinaryNumber(): void
    {
        $binary = str_repeat('10', 50);
        $service = new ModuloThreeService();
        $result = $service->calculate($binary, false);
        
        $this->assertIsInt($result->modulo);
        $this->assertGreaterThanOrEqual(0, $result->modulo);
        $this->assertLessThanOrEqual(2, $result->modulo);
    }
    
    public function testPerformanceMetrics(): void
    {
        $service = new ModuloThreeService();
        $result = $service->calculate('11010101', false);
        
        $this->assertIsFloat($result->executionTimeMs);
        $this->assertGreaterThan(0, $result->executionTimeMs);
    }
}