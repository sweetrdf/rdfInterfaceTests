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

use UnexpectedValueException;
use rdfHelpers\GenericQuadIterator;
use rdfInterface\LiteralInterface;
use rdfInterface\QuadInterface;
use rdfInterface\DatasetInterface;
use rdfInterface\QuadCompareInterface;
use rdfInterface\TermInterface;
use rdfInterface\TermCompareInterface;
use rdfInterface\MultipleQuadsMatchedException;

/**
 * Description of DatasetInterfaceTest
 *
 * @author zozlak
 */
abstract class DatasetInterfaceTest extends \PHPUnit\Framework\TestCase {

    use TestBaseTrait;

    abstract public static function getDataset(): DatasetInterface;

    abstract public static function getForeignDataset(): DatasetInterface; // foreign \rdfInterface\Dataset implementation

    abstract public static function getQuadTemplate(TermCompareInterface | TermInterface | null $subject = null,
                                                    TermCompareInterface | TermInterface | null $predicate = null,
                                                    TermCompareInterface | TermInterface | null $object = null,
                                                    TermCompareInterface | TermInterface | null $graph = null): QuadCompareInterface;

    public function testAddQuads(): void {
        $d = static::getDataset();
        for ($i = 0; $i < 3; $i++) {
            $d->add(self::$quads[$i]);
        }
        $this->assertCount(3, $d);

        $d->add(self::$quads);
        $this->assertCount(4, $d);
    }

    public function testAddIterator(): void {
        $d = static::getDataset();
        $d->add(self::$quads);
        foreach ($d as $k => $v) {
            $this->assertNotNull($v);
            $this->assertTrue($v->equals(self::$quads[$k]));
        }
    }

    public function testGetIterator(): void {
        $de = self::getDataset();
        $d  = static::getDataset();
        $d->add(self::$quads);

        // all
        $n      = 0;
        $counts = [0, 0, 0, 0];
        foreach ($d as $q) {
            $n++;
            foreach (self::$quads as $i => $j) {
                $counts[$i] += (int) $j->equals($q);
            }
        }
        $this->assertEquals(4, $n);
        $this->assertEquals([1, 1, 1, 1], $counts);

        // match - QuadCompareInterface
        $qt     = static::getQuadTemplate(self::$quads[0]->getSubject());
        $n      = 0;
        $counts = [0, 0, 0, 0];
        foreach ($d->getIterator($qt) as $q) {
            $n++;
            foreach (self::$quads as $i => $j) {
                $counts[$i] += (int) $j->equals($q);
            }
        }
        $this->assertEquals(2, $n);
        $this->assertEquals([1, 0, 0, 1], $counts);

        // match - callable
        $fn     = fn($x) => !self::$quads[1]->getPredicate()->equals($x->getPredicate());
        $n      = 0;
        $counts = [0, 0, 0, 0];
        foreach ($d->getIterator($fn) as $q) {
            $n++;
            foreach (self::$quads as $i => $j) {
                $counts[$i] += (int) $j->equals($q);
            }
        }
        $this->assertEquals(3, $n);
        $this->assertEquals([1, 0, 1, 1], $counts);

        // match - QuadIteratorInterface|QuadIteratorAggregateInterface
        $dd     = static::getDataset();
        $dd->add([self::$quads[1], self::$quads[3]]);
        $n      = 0;
        $counts = [0, 0, 0, 0];
        foreach ($dd as $q) {
            $n++;
            foreach (self::$quads as $i => $j) {
                $counts[$i] += (int) $j->equals($q);
            }
        }
        $this->assertEquals(2, $n);
        $this->assertEquals([0, 1, 0, 1], $counts);

        // no match - QuadCompareInterface
        $qt = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        foreach ($d->getIterator($qt) as $q) {
            $this->assertTrue(false);
        }

        // no match - callable
        $fn = fn() => false;
        foreach ($d->getIterator($fn) as $q) {
            $this->assertTrue(false);
        }

        // no match - QuadIteratorInterface|QuadIteratorAggregateInterface
        $dd = static::getDataset();
        $nn = self::$df->namedNode('noMatch');
        $dd->add(self::$df->quad($nn, $nn, $nn));
        foreach ($d->getIterator($dd) as $q) {
            $this->assertTrue(false);
        }

        // no match - empty QuadIteratorInterface|QuadIteratorAggregateInterface
        $dd = static::getDataset();
        foreach ($d->getIterator($dd) as $q) {
            $this->assertTrue(false);
        }

        // empty
        foreach ($de as $q) {
            $this->assertTrue(false);
        }
        $qt = static::getQuadTemplate(self::$quads[0]->getSubject());
        foreach ($de->getIterator($qt) as $q) {
            $this->assertTrue(false);
        }
        foreach ($de->getIterator(fn() => true) as $q) {
            $this->assertTrue(false);
        }
        foreach ($de->getIterator($d) as $q) {
            $this->assertTrue(false);
        }
    }

    public function testOffsetExists(): void {
        $de = self::getDataset();
        $d  = static::getDataset();
        $d->add(self::$quads);

        // single match - QuadCompareInterface
        $this->assertTrue(isset($d[self::$quads[1]]));
        // single match - callable
        $this->assertTrue(isset($d[fn($x) => self::$quads[2]->equals($x)]));
        // single match - 0
        $this->assertTrue(isset($d[0]));

        // no match - QuadCompareInterface
        $qt = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        $this->assertFalse(isset($d[$qt]));
        // no match - callable
        $this->assertFalse(isset($d[fn() => false]));
        // no match - trash
        foreach ([100, -10, 'aaa', new \stdClass()] as $i) {
            try {
                isset($d[$i]);
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertTrue(true);
            }
        }

        // no match - empty dataset - QuadCompareInterface
        $qt = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        $this->assertFalse(isset($de[$qt]));
        // no match - empty dataset - callable
        $this->assertFalse(isset($de[fn() => false]));
        // no match - empty dataset - 0
        $this->assertFalse(isset($de[0]));
        // no match - empty dataset - trash
        foreach ([100, -10, 'aaa', new \stdClass()] as $i) {
            try {
                isset($de[$i]);
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertTrue(true);
            }
        }

        // multiple matches - QuadCompareInterface, callabel
        $qt     = static::getQuadTemplate(self::$quads[0]->getSubject());
        $toFail = [$qt, fn() => true];
        foreach ($toFail as $i) {
            try {
                isset($d[$i]);
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (MultipleQuadsMatchedException $ex) {
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));
            }
        }
    }

    public function testOffsetUnset(): void {
        $de = self::getDataset();
        $this->assertCount(0, $de);
        $d  = static::getDataset();
        $d->add(self::$quads);
        $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));

        // single match - QuadCompareInterface
        unset($d[self::$quads[0]]);
        $this->assertEquals([3, 0, 1, 1, 1], $this->getQuadsCount($d));
        // single match - callable
        unset($d[fn($x) => self::$quads[2]->equals($x)]);
        $this->assertEquals([2, 0, 1, 0, 1], $this->getQuadsCount($d));

        // no match - QuadCompareInterface
        $qt = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        unset($d[$qt]);
        $this->assertEquals([2, 0, 1, 0, 1], $this->getQuadsCount($d));
        // no match - callable
        unset($d[fn() => false]);
        $this->assertEquals([2, 0, 1, 0, 1], $this->getQuadsCount($d));
        // no match - trash
        foreach ([0, 100, -10, 'aaa', new \stdClass()] as $i) {
            try {
                unset($d[$i]);
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertTrue(true);
            }
        }
        $this->assertEquals([2, 0, 1, 0, 1], $this->getQuadsCount($d));

        // no match - empty dataset - QuadCompareInterface
        $qt = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        unset($de[$qt]);
        $this->assertCount(0, $de);
        // no match - empty dataset - callable
        unset($de[fn() => false]);
        $this->assertCount(0, $de);
        // no match - empty dataset - trash
        foreach ([0, 100, -10, 'aaa', new \stdClass()] as $i) {
            try {
                unset($de[$i]);
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($de));
            }
        }

        // multiple matches - QuadCompareInterface, callable
        $d->add(self::$quads);
        $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));
        $qt     = static::getQuadTemplate(self::$quads[0]->getSubject());
        $toFail = [$qt, fn() => true];
        foreach ($toFail as $i) {
            try {
                unset($d[$i]);
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (MultipleQuadsMatchedException $ex) {
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));
            }
        }
    }

    public function testOffsetGet(): void {
        $de = self::getDataset();
        $d  = static::getDataset();
        $d->add(self::$quads);

        // single match - QuadCompareInterface
        $this->assertTrue(self::$quads[1]->equals($d[self::$quads[1]]));
        // single match - callable
        $this->assertTrue(self::$quads[2]->equals($d[fn($x) => self::$quads[2]->equals($x)]));
        // single match - 0
        $q = $d[0];
        $this->assertTrue(self::$quads[0]->equals($q) || self::$quads[1]->equals($q) || self::$quads[2]->equals($q) || self::$quads[3]->equals($q));

        // no match - QuadCompareInterface, callable, trash
        $qt     = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        $toFail = [$qt, fn() => false, 100, -10, 'aaa', new \stdClass()];
        foreach ($toFail as $i) {
            try {
                $d[$i];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));
            }
        }

        // no match - empty dataset - QuadCompareInterface, callable, 0, trash
        $qt     = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        $toFail = [$qt, fn() => false, 0, 100, -10, 'aaa', new \stdClass()];
        foreach ($toFail as $i) {
            try {
                $de[$i];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($de));
            }
        }

        // multiple matches - QuadCompareInterface, callable
        $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));
        $qt     = static::getQuadTemplate(self::$quads[0]->getSubject());
        $toFail = [$qt, fn() => true];
        foreach ($toFail as $i) {
            try {
                $d[$i];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (MultipleQuadsMatchedException $ex) {
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));
            }
        }
    }

    public function testOffsetSet(): void {
        $de = static::getDataset();
        $d  = static::getDataset();

        // null
        $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($d));
        $d[] = self::$quads[0];
        $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d));
        $d[] = self::$quads[3];
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
        $d[] = self::$quads[3];
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));

        // single match - QuadCompareInterface
        $nq                                       = self::$quads[3]->withSubject(self::$df->namedNode('other'));
        $d[self::$quads[3]]                       = $nq;
        $this->assertEquals([2, 1, 0, 0, 0], $this->getQuadsCount($d));
        $d[$nq]                                   = self::$quads[0];
        $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d));
        // single match - callable
        $d[]                                      = self::$quads[3];
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
        $d[fn($x) => self::$quads[3]->equals($x)] = $nq;
        $this->assertEquals([2, 1, 0, 0, 0], $this->getQuadsCount($d));
        $d[fn($x) => $nq->equals($x)]             = self::$quads[0];
        $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d));

        // no match - QuadCompareInterface, callable, trash
        $qt = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        foreach ([$qt, fn() => false, 100, -10, 'aaa', new \stdClass()] as $i) {
            try {
                $d[$i] = self::$quads[0];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d));
            }
        }

        // no match - empty dataset - QuadCompareInterface, callable, trash
        $qt     = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        $toFail = [$qt, fn() => false, 0, 100, -10, 'aaa', new \stdClass()];
        foreach ($toFail as $i) {
            try {
                $de[$i] = self::$quads[0];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($de));
            }
        }

        // multiple matches - QuadCompareInterface, callable
        $d      = static::getDataset();
        $d->add(self::$quads);
        $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));
        $qt     = static::getQuadTemplate(self::$quads[0]->getSubject());
        $toFail = [$qt, fn() => true];
        foreach ($toFail as $i) {
            try {
                $d[$i] = self::$quads[0];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (MultipleQuadsMatchedException $ex) {
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d));
            }
        }
    }

    public function testToString(): void {
        $d   = static::getDataset();
        $d->add(self::$quads[0]);
        $d->add(self::$quads[1]);
        $ref = self::$quads[0] . "\n" . self::$quads[1] . "\n";
        $this->assertEquals($ref, (string) $d);
    }

    public function testEquals(): void {
        $d1 = static::getDataset();
        $d2 = static::getDataset();

        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[1];
        $this->assertTrue($d1->equals($d2));

        $d2[] = self::$quads[2];
        $this->assertFalse($d1->equals($d2));

        unset($d2[self::$quads[2]]);
        $this->assertTrue($d1->equals($d2));

        unset($d2[self::$quads[1]]);
        $this->assertFalse($d1->equals($d2));

        // blank nodes don't count
        $d2[] = self::$quads[1];
        $d1[] = self::$df::quad(self::$df::blankNode(), self::$df::namedNode('foo'), self::$df::literal('bar'));
        $this->assertTrue($d1->equals($d2));
        $d2[] = self::$df::quad(self::$df::blankNode(), self::$df::namedNode('bar'), self::$df::literal('baz'));
        $this->assertTrue($d1->equals($d2));
    }

    public function testCopy(): void {
        $d1 = static::getDataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // simple
        $d2 = $d1->copy();
        $this->assertTrue($d1->equals($d2));
        unset($d2[self::$quads[0]]);
        $this->assertCount(4, $d1);
        $this->assertCount(3, $d2);
        $this->assertFalse($d1->equals($d2));

        // Quad
        $d2 = $d1->copy(self::$quads[0]);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(1, $d2);
        $this->assertTrue(isset($d2[self::$quads[0]]));
        $this->assertFalse(isset($d2[self::$quads[1]]));

        // QuadTemplate
        $d2   = $d1->copy(static::getQuadTemplate(self::$df::namedNode('foo')));
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(2, $d2);
        $d2[] = self::$quads[1];
        $d2[] = self::$quads[2];
        $this->assertTrue($d1->equals($d2));

        // QuadIterator
        $d2 = $d1->copy($d1);
        $this->assertTrue($d1->equals($d2));

        // callable
        $fn = function (QuadInterface $x): bool {
            return false;
        };
        $d2 = $d1->copy($fn);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);

        // empty
        $de = static::getDataset();
        $de = $de->copy();
        $this->assertCount(0, $de);
    }

    public function testCopyExcept(): void {
        $d1 = static::getDataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $d2   = $d1->copyExcept(self::$quads[0]);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(3, $d2);
        $d2[] = self::$quads[0];
        $this->assertTrue($d1->equals($d2));

        // QuadTemplate
        $d2   = $d1->copyExcept(static::getQuadTemplate(self::$df::namedNode('foo')));
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(2, $d2);
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[3];
        $this->assertTrue($d1->equals($d2));

        // QuadIterator
        $d2 = $d1->copyExcept($d1);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);

        // callable
        $fn = function (QuadInterface $x): bool {
            return true;
        };
        $d2 = $d1->copyExcept($fn);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);

        $d1 = static::getDataset();
        $d1->add(new GenericQuadIterator(self::$quads));
        $d2 = $d1->copyExcept(static::getQuadTemplate(self::$quads[0]->getSubject()));
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(2, $d2);
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[3], $d2);
    }

    public function testDelete(): void {
        $d1 = static::getDataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $d2 = $d1->copy();
        $d2->delete(self::$quads[0]->withSubject(self::$df::blankNode()));
        $this->assertCount(4, $d2);
        $this->assertTrue($d2->equals($d1));

        $d2->delete(self::$quads[0]);
        $this->assertCount(3, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertNotContains(self::$quads[0], $d2);

        // QuadTemplate
        $d2 = $d1->copy();
        $d2->delete(static::getQuadTemplate(self::$df::namedNode('foo')));
        $this->assertCount(2, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[3], $d2);

        // QuadIterator
        $d2 = $d1->copy();
        $d2->delete($d1);
        $this->assertCount(0, $d2);
        $this->assertFalse($d2->equals($d1));

        // callable
        $fn = function (QuadInterface $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $d2 = $d1->copy();
        $d2->delete($fn);
        $this->assertCount(2, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[3], $d2);
    }

    public function testDeleteExcept(): void {
        $d1 = static::getDataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $d2      = $d1->copy();
        $deleted = $d2->deleteExcept(self::$quads[0]->withSubject(self::$df::blankNode()));
        $this->assertCount(0, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertTrue($deleted->equals($d1));

        $d2 = $d1->copy();
        $d2->deleteExcept(self::$quads[0]);
        $this->assertCount(1, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertTrue(isset($d2[self::$quads[0]]));
        $this->assertFalse(isset($d2[self::$quads[1]]));

        // QuadTemplate
        $d2 = $d1->copy();
        $d2->deleteExcept(static::getQuadTemplate(self::$df::namedNode('foo')));
        $this->assertCount(2, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertTrue(isset($d2[self::$quads[0]]));
        $this->assertFalse(isset($d2[self::$quads[1]]));
        $this->assertTrue(isset($d2[self::$quads[3]]));

        // QuadIterator
        $d2 = $d1->copy();
        $d2->deleteExcept($d1);
        $this->assertCount(4, $d2);
        $this->assertTrue($d2->equals($d1));

        // callable
        $fn = function (QuadInterface $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $d2 = $d1->copy();
        $d2->deleteExcept($fn);
        $this->assertCount(2, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertTrue(isset($d2[self::$quads[0]]));
        $this->assertFalse(isset($d2[self::$quads[1]]));
        $this->assertTrue(isset($d2[self::$quads[3]]));
    }

    public function testUnion(): void {
        $d1   = static::getDataset();
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
        $d2   = static::getDataset();
        $d2[] = self::$quads[1];
        $d2[] = self::$quads[2];

        $d11 = $d1->copy();
        $d22 = $d2->copy();
        $d3  = $d1->union($d2);
        $this->assertCount(2, $d1);
        $this->assertCount(2, $d2);
        $this->assertCount(3, $d3);
        $this->assertTrue($d11->equals($d1));
        $this->assertTrue($d22->equals($d2));
        $this->assertFalse($d3->equals($d1));
        $this->assertFalse($d3->equals($d2));
    }

    public function testXor(): void {
        $d1   = static::getDataset();
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
        $d2   = static::getDataset();
        $d2[] = self::$quads[1];
        $d2[] = self::$quads[2];

        $d3 = $d1->xor($d2);
        $this->assertCount(2, $d1);
        $this->assertCount(2, $d2);
        $this->assertCount(2, $d3);
        $this->assertFalse($d3->equals($d1));
        $this->assertFalse($d3->equals($d2));
        $this->assertContains(self::$quads[0], $d3);
        $this->assertContains(self::$quads[2], $d3);
        $this->assertNotContains(self::$quads[1], $d3);
    }

    public function testForEach(): void {
        $d   = static::getDataset();
        $d[] = self::$df::quad(self::$df::namedNode('foo'), self::$df::namedNode('baz'), self::$df::literal(1));
        $d[] = self::$df::quad(self::$df::namedNode('bar'), self::$df::namedNode('baz'), self::$df::literal(5));
        $d->forEach(function (QuadInterface $x): QuadInterface {
            $obj = $x->getObject();
            return $obj instanceof LiteralInterface ? $x->withObject($obj->withValue((float) $obj->getValue() * 2)) : $x;
        });
        $this->assertEquals(2, (int) $d[static::getQuadTemplate(self::$df::namedNode('foo'))]->getObject()->getValue());
        $this->assertEquals(10, (int) $d[static::getQuadTemplate(self::$df::namedNode('bar'))]->getObject()->getValue());

        $d->forEach(function (QuadInterface $x): ?QuadInterface {
            $obj = $x->getObject();
            return $obj instanceof LiteralInterface && $obj->getValue() < 5 ? $x : null;
        });
        $this->assertCount(1, $d);
        $this->assertEquals(2, (int) $d[static::getQuadTemplate(self::$df::namedNode('foo'))]->getObject()->getValue());

        $d2 = $d->copy();
        $d->forEach(function (QuadInterface $x): ?QuadInterface {
            throw new \RuntimeException();
        }, static::getQuadTemplate(self::$df::namedNode('foobar')));
        $this->assertTrue($d2->equals($d));
    }

    public function testForeignTerms(): void {
        $nn = self::$df::namedNode('foo');
        $bn = self::$df::blankNode('bar');
        $l  = self::$df::literal('baz');
        $dg = self::$df::defaultGraph();
        $q  = self::$df::quad($nn, $nn, $nn);
        $q2 = self::$df::quad($nn, $nn, $bn);
        $q3 = self::$df::quad($nn, $nn, $l, $dg);

        $fnn = self::$fdf::namedNode('foo');
        $fbn = self::$fdf::blankNode('bar');
        $fl  = self::$fdf::literal('baz');
        $fdg = self::$fdf::defaultGraph();
        $fq  = self::$fdf::quad($fnn, $fnn, $fnn);
        $fq2 = self::$fdf::quad($fnn, $fnn, $fbn);
        $fq3 = self::$fdf::quad($fnn, $fnn, $fl, $fdg);
        $fqt = static::getQuadTemplate($fnn);
        $fqi = new GenericQuadIterator($fq);

        // add
        $d  = static::getDataset();
        $d->add(new GenericQuadIterator([$q, $q2, $q3]));
        $fd = static::getForeignDataset();
        $fd->add(new GenericQuadIterator([$fq, $fq2, $fq3]));
        $this->assertTrue($d->equals($fd));
        $this->assertTrue(isset($d[$fq]));
        $this->assertTrue(isset($d[$fq2]));
        $this->assertTrue(isset($d[$fq3]));

        $d->add($fq);
        $this->assertEquals(3, count($d));
        $this->assertTrue(isset($d[$fq]));
        $d->add($fqi);
        $this->assertEquals(3, count($d));
        $this->assertTrue(isset($d[$fq]));

        // base for other tests
        $d  = static::getDataset();
        $d->add($q);
        $fd = static::getDataset();
        $fd->add($q);

        // offsetSet
        $d[$fq]  = $fq;
        $this->assertEquals(1, count($d));
        $this->assertTrue(isset($d[$q]));
        $d[$fqt] = $q;
        $this->assertEquals(1, count($d));
        $this->assertTrue(isset($d[$fq]));

        // offsetSet as add
        $d   = static::getDataset();
        $d[] = $fq;
        $this->assertEquals(1, count($d));
        $this->assertTrue(isset($d[$q]));
        $d[] = $q;
        $this->assertEquals(1, count($d));
        $this->assertTrue(isset($d[$q]));

        // copy
        $d = static::getDataset();
        $d->add($q);
        foreach ([$fq, $fqt, $fqi] as $i) {
            $d2 = $d->copy($i);
            $this->assertEquals(1, count($d2), "Tested class " . $fq::class);
            $this->assertTrue(isset($d2[$q]), "Tested class " . $fq::class);
            $this->assertTrue($d2->equals($fd), "Tested class " . $fq::class);

            $d2->deleteExcept($i);
            $this->assertEquals(1, count($d2), "Tested class " . $fq::class);
            $this->assertTrue(isset($d2[$q]), "Tested class " . $fq::class);
            $this->assertTrue($d2->equals($fd), "Tested class " . $fq::class);

            $r = $d2->delete($i);
            $this->assertEquals(0, count($d2), "Tested class " . $fq::class);
            $this->assertTrue(isset($r[$q]), "Tested class " . $fq::class);

            $d3 = $d->copyExcept($i);
            $this->assertEquals(0, count($d3), "Tested class " . $fq::class);
        }

        // union / xor
        $d = static::getDataset();
        $d->add($q);

        $d2 = $d->union($fqi);
        $this->assertEquals(1, count($d2));
        $this->assertTrue(isset($d2[$q]));
        $this->assertTrue(isset($d2[$fqt]));
        $this->assertTrue($d2->equals($fd), "Tested class " . $fq::class);

        $d3 = $d->xor($fqi);
        $this->assertEquals(0, count($d3));

        // any / every / none
        $fc = function ($x) use ($fqt) {
            return $fqt->equals($x);
        };
        $d = static::getDataset();
        $d->add($q);
        $this->assertTrue(isset($d[$fq]));
        foreach ([$fq, $fqt, $fc] as $i) { // add callable
            $this->assertTrue($d->any($i), "Tested class " . $fq::class);
            $this->assertTrue($d->every($i), "Tested class " . $fq::class);
            $this->assertFalse($d->none($i), "Tested class " . $fq::class);
        }
    }

    public function testAnyNone(): void {
        $d1 = static::getDataset();
        $d1->add(new GenericQuadIterator(self::$quads));
        $de = static::getDataset();

        // Quad
        $this->assertTrue($d1->any(self::$quads[0]));
        $this->assertFalse($d1->none(self::$quads[0]));
        $this->assertFalse($d1->any(self::$quads[0]->withSubject(self::$df::namedNode('aaa'))));
        $this->assertTrue($d1->none(self::$quads[0]->withSubject(self::$df::namedNode('aaa'))));
        $this->assertFalse($de->any(self::$quads[0]));
        $this->assertTrue($de->none(self::$quads[0]));

        // QuadTemplate
        $this->assertTrue($d1->any(static::getQuadTemplate(self::$df::namedNode('foo'))));
        $this->assertFalse($d1->none(static::getQuadTemplate(self::$df::namedNode('foo'))));
        $this->assertFalse($d1->any(static::getQuadTemplate(self::$df::namedNode('aaa'))));
        $this->assertTrue($d1->none(static::getQuadTemplate(self::$df::namedNode('aaa'))));
        $this->assertFalse($de->any(static::getQuadTemplate(self::$df::namedNode('foo'))));
        $this->assertTrue($de->none(static::getQuadTemplate(self::$df::namedNode('foo'))));

        // QuadIterator
        $d2   = static::getDataset();
        $d2[] = self::$quads[0];
        $this->assertTrue($d1->any($d2));
        $this->assertFalse($d1->none($d2));
        $this->assertFalse($de->any($d2));
        $this->assertTrue($de->none($d2));

        $d2   = static::getDataset();
        $d2[] = self::$quads[0]->withSubject(self::$df::namedNode('aaa'));
        $this->assertFalse($d1->any($d2));
        $this->assertTrue($d1->none($d2));
        $this->assertFalse($de->any($de));
        $this->assertTrue($de->none($de));

        // callable
        $fn = function (QuadInterface $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $this->assertTrue($d1->any($fn));
        $this->assertFalse($d1->none($fn));
        $this->assertFalse($de->any($fn));
        $this->assertTrue($de->none($fn));

        $fn = function (QuadInterface $x): bool {
            return $x->getSubject()->getValue() === 'aaa';
        };
        $this->assertFalse($d1->any($fn));
        $this->assertTrue($d1->none($fn));
        $this->assertFalse($de->any($fn));
        $this->assertTrue($de->none($fn));
    }

    public function testEvery(): void {
        // Quad
        $d1   = static::getDataset();
        $d1[] = self::$quads[0];
        $this->assertTrue($d1->every(self::$quads[0]));
        $d1[] = self::$quads[1];
        $this->assertFalse($d1->every(self::$quads[0]));
        $this->assertFalse($d1->every(self::$quads[0]->withSubject(self::$df::namedNode('aaa'))));

        // QuadTemplate
        $d1   = static::getDataset();
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[3];
        $this->assertTrue($d1->every(static::getQuadTemplate(self::$df::namedNode('foo'))));
        $this->assertFalse($d1->none(static::getQuadTemplate(null, null, self::$df::literal('baz', 'en'))));

        // callable
        $d1   = static::getDataset();
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[3];
        $fn   = function (QuadInterface $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $this->assertTrue($d1->every($fn));
        $fn = function (QuadInterface $x): bool {
            $obj = $x->getObject();
            return $obj instanceof LiteralInterface ? $obj->getLang() === 'en' : false;
        };
        $this->assertFalse($d1->every($fn));
    }

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

    public function testMap(): void {
        $d1   = static::getDataset();
        $d1[] = self::$df::quad(self::$df::namedNode('foo'), self::$df::namedNode('baz'), self::$df::literal(1));
        $d1[] = self::$df::quad(self::$df::namedNode('bar'), self::$df::namedNode('baz'), self::$df::literal(5));
        $d2   = $d1->map(function (QuadInterface $x) {
            $obj = $x->getObject();
            return $obj instanceof LiteralInterface ? $x->withObject($obj->withValue((float) (string) $obj->getValue() * 2)) : $x;
        });
        $this->assertCount(2, $d1);
        $this->assertEquals(1, (int) (string) $d1[static::getQuadTemplate(self::$df::namedNode('foo'))]->getObject()->getValue());
        $this->assertEquals(5, (int) (string) $d1[static::getQuadTemplate(self::$df::namedNode('bar'))]->getObject()->getValue());
        $this->assertCount(2, $d2);
        $this->assertEquals(2, (int) (string) $d2[static::getQuadTemplate(self::$df::namedNode('foo'))]->getObject()->getValue());
        $this->assertEquals(10, (int) (string) $d2[static::getQuadTemplate(self::$df::namedNode('bar'))]->getObject()->getValue());

        $d3 = $d2->map(function (QuadInterface $x) {
            throw new \RuntimeException();
        }, static::getQuadTemplate(self::$df::namedNode('foobar')));
        $this->assertEquals(0, count($d3));

        // empty dataset
        $de = static::getDataset()->map(fn(QuadInterface $x) => $x);
        $this->assertEquals(0, count($de));
    }

    public function testReduce(): void {
        $d1   = static::getDataset();
        $d1[] = self::$df::quad(self::$df::namedNode('foo'), self::$df::namedNode('baz'), self::$df::literal(1));
        $d1[] = self::$df::quad(self::$df::namedNode('bar'), self::$df::namedNode('baz'), self::$df::literal(5));
        $sum  = $d1->reduce(function (float $sum, QuadInterface $x) {
            return $sum + (float) (string) $x->getObject()->getValue();
        }, 0);
        $this->assertEquals(6, $sum);
        $this->assertCount(2, $d1);
        $this->assertEquals(1, (int) (string) $d1[static::getQuadTemplate(self::$df::namedNode('foo'))]->getObject()->getValue());
        $this->assertEquals(5, (int) (string) $d1[static::getQuadTemplate(self::$df::namedNode('bar'))]->getObject()->getValue());

        $sum = $d1->reduce(function (QuadInterface $x) {
            throw new \RuntimeException();
        }, -5, static::getQuadTemplate(self::$df::namedNode('foobar')));
        $this->assertEquals(-5, $sum);

        // empty dataset
        $sum = static::getDataset()->reduce(fn(QuadInterface $x) => 1, -10);
        $this->assertEquals(-10, $sum);
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
