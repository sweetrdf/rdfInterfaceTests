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

use rdfInterface\DatasetListQuadPartsInterface as DatasetListQuadParts;
use rdfInterface\TermInterface as Term;
use rdfInterface\TermCompareInterface as TermCompare;
use rdfInterface\QuadCompareInterface as QuadCompare;
use rdfHelpers\GenericQuadIterator;

/**
 * Description of DatasetListQuadPartsTest
 *
 * @author zozlak
 */
abstract class DatasetListQuadPartsInterfaceTest extends \PHPUnit\Framework\TestCase {

    use TestBaseTrait;

    abstract public static function getDataset(): DatasetListQuadParts;

    abstract public static function getQuadTemplate(TermCompare | Term | null $subject = null,
                                                    TermCompare | Term | null $predicate = null,
                                                    TermCompare | Term | null $object = null,
                                                    TermCompare | Term | null $graph = null): QuadCompare;

    public function testList(): void {
        //0 <foo> <bar> "baz"
        //1 <baz> <foo> <bar>
        //2 <bar> <baz> <foo>
        //3 <foo> <bar> "baz"@en <graph>
        $d = static::getDataset();
        $d->add(new GenericQuadIterator(self::$quads));

        $list = iterator_to_array($d->listSubjects());
        $this->assertEquals(3, count($list));
        $list = iterator_to_array($d->listSubjects($this->getQuadTemplate(null, static::$df::namedNode('bar'))));
        $this->assertEquals(1, count($list));
        $this->assertTrue(static::$df::namedNode('foo')->equals($list[0]));
        $list = iterator_to_array($d->listSubjects($this->getQuadTemplate(static::$df::namedNode('foobar'))));
        $this->assertEquals(0, count($list));

        $list = iterator_to_array($d->listPredicates());
        $this->assertEquals(3, count($list));
        $list = iterator_to_array($d->listPredicates($this->getQuadTemplate(static::$df::namedNode('foo'))));
        $this->assertEquals(1, count($list));
        $this->assertTrue(static::$df::namedNode('bar')->equals($list[0]));
        $list = iterator_to_array($d->listPredicates($this->getQuadTemplate(static::$df::namedNode('foobar'))));
        $this->assertEquals(0, count($list));

        $list = iterator_to_array($d->listObjects());
        $this->assertEquals(4, count($list));
        $list = iterator_to_array($d->listObjects($this->getQuadTemplate(static::$df::namedNode('bar'))));
        $this->assertEquals(1, count($list));
        $this->assertTrue(static::$df::namedNode('foo')->equals($list[0]));
        $list = iterator_to_array($d->listObjects($this->getQuadTemplate(static::$df::namedNode('foobar'))));
        $this->assertEquals(0, count($list));

        $list = iterator_to_array($d->listGraphs());
        $this->assertEquals(2, count($list));
        $list = iterator_to_array($d->listGraphs($this->getQuadTemplate(null, static::$df::namedNode('bar'))));
        $this->assertEquals(2, count($list));
        $list = iterator_to_array($d->listGraphs($this->getQuadTemplate(null, null, static::$df::literal('baz', 'en'))));
        $this->assertEquals(1, count($list));
        $this->assertTrue(static::$df::namedNode('graph')->equals($list[0]));
        $list = iterator_to_array($d->listGraphs($this->getQuadTemplate(static::$df::namedNode('foobar'))));
        $this->assertEquals(0, count($list));
    }

    public function testNested(): void {
        //0 <foo> <bar> "baz"
        //1 <baz> <foo> <bar>
        //2 <bar> <baz> <foo>
        //3 <foo> <bar> "baz"@en <graph>
        $d = static::getDataset();
        $d->add(new GenericQuadIterator(self::$quads));
        $d->add(self::$quads[3]->withPredicate(self::$quads[2]->getPredicate()));

        $counts = ['foo' => 2, 'bar' => 1, 'baz' => 1];
        foreach ($d->listSubjects() as $sbj) {
            $n  = 0;
            $d1 = $d->copy($this->getQuadTemplate($sbj));
            foreach ($d1->listPredicates() as $pred) {
                $n++;
                $d2 = $d1->copy($this->getQuadTemplate(null, $pred));
            }
            $this->assertEquals($counts[$sbj->getValue()], $n, $sbj->getValue());
        }
    }
}
