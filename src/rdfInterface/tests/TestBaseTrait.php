<?php

/*
 * The MIT License
 *
 * Copyright 2021 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace rdfInterface\tests;

use rdfInterface\DataFactoryInterface;
use rdfInterface\DatasetInterface;

/**
 * Description of TestBaseTrait
 *
 * @author zozlak
 */
trait TestBaseTrait {

    abstract public static function getDataFactory(): DataFactoryInterface;

    abstract public static function getForeignDataFactory(): DataFactoryInterface;

    protected static DataFactoryInterface $df;
    protected static DataFactoryInterface $fdf; // foreign \rdfInterface\DataFactoryInterface implementation

    /**
     *
     * @var array<\rdfInterface\QuadInterface>
     */
    protected static array $quads;

    public static function setUpBeforeClass(): void {
        self::$df    = static::getDataFactory();
        self::$fdf   = static::getForeignDataFactory();
        self::$quads = [
            self::$df::quad(self::$df::namedNode('foo'), self::$df::namedNode('bar'), self::$df::literal('baz')),
            self::$df::quad(self::$df::namedNode('baz'), self::$df::namedNode('foo'), self::$df::namedNode('bar')),
            self::$df::quad(self::$df::namedNode('bar'), self::$df::namedNode('baz'), self::$df::namedNode('foo')),
            self::$df::quad(self::$df::namedNode('foo'), self::$df::namedNode('bar'), self::$df::literal('baz', 'en'), self::$df::namedNode('graph')),
        ];
    }

    /**
     * 
     * @param DatasetInterface $d
     * @return array<int>
     */
    private function getQuadsCount(DatasetInterface $d): array {
        $n      = 0;
        $counts = [0, 0, 0, 0];
        foreach ($d as $q) {
            $n++;
            foreach (self::$quads as $i => $j) {
                $counts[$i] += (int) $j->equals($q);
            }
        }
        return array_merge([$n], $counts);
    }
}
