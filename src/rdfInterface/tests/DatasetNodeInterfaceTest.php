<?php

/*
 * The MIT License
 *
 * Copyright 2023 zozlak.
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
use rdfInterface\DatasetInterface;
use rdfInterface\DatasetNodeInterface;
use rdfInterface\LiteralInterface;
use rdfInterface\QuadCompareInterface;
use rdfInterface\TermInterface;
use rdfInterface\TermCompareInterface;
use rdfInterface\QuadInterface;
use rdfInterface\MultipleQuadsMatchedException;

/**
 * Description of NodeInterfaceTest
 *
 * @author zozlak
 */
abstract class DatasetNodeInterfaceTest extends \PHPUnit\Framework\TestCase {

    use TestBaseTrait;

    abstract public static function getDataset(): DatasetInterface;

    abstract public static function getForeignDataset(): DatasetInterface; // foreign \rdfInterface\Dataset implementation

    abstract public static function getDatasetNode(TermInterface $node,
                                                   DatasetInterface | null $dataset = null): DatasetNodeInterface;

    abstract public static function getForeignDatasetNode(TermInterface $node,
                                                          DatasetInterface | null $dataset = null): DatasetNodeInterface; // foreign \rdfInterface\DatasetNode implementation

    abstract public static function getQuadTemplate(TermCompareInterface | TermInterface | null $subject = null,
                                                    TermCompareInterface | TermInterface | null $predicate = null,
                                                    TermCompareInterface | TermInterface | null $object = null,
                                                    TermCompareInterface | TermInterface | null $graph = null): QuadCompareInterface;

    public function testGetNode(): void {
        $n = self::$quads[0]->getSubject();
        $d = static::getDatasetNode($n);
        $this->assertTrue($n->equals($d->getNode()));
    }

    /**
     * withNode() changes the node but preserves the dataset
     */
    public function testWithNode(): void {
        $n1 = self::$quads[0]->getSubject();
        $n2 = self::$quads[1]->getSubject();
        $this->assertFalse($n1->equals($n2));
        $d1 = static::getDatasetNode($n1);

        // named node node
        $d2 = $d1->withNode($n2);
        $this->assertTrue($n2->equals($d2->getNode()));
        $this->assertCount(0, $d2);
        $this->assertEquals(spl_object_id($d1->getDataset()), spl_object_id($d2->getDataset()));

        // quad node
        $n3 = self::$quads[2];
        $d3 = $d2->withNode($n3);
        $this->assertTrue($n3->equals($d3->getNode()));
        $this->assertCount(0, $d3);
        $this->assertEquals(spl_object_id($d2->getDataset()), spl_object_id($d3->getDataset()));
    }

    /**
     * DatasetNodeInterface::getValue() is a equivalent of getNode()->getValue()
     */
    public function testGetValue(): void {
        $n = self::$quads[0]->getSubject();
        $d = static::getDatasetNode($n);
        $this->assertEquals($n->getValue(), $d->getValue());

        $d = static::getDatasetNode(self::$quads[0]);
        try {
            $d->getValue();
            $this->assertTrue(false);
        } catch (\BadMethodCallException $ex) {
            $this->assertTrue(true);
        }
    }

    public function testGetDataset(): void {
        $dn = static::getDatasetNode(self::$quads[0]->getSubject());
        $dn->add(self::$quads[0]);
        $dn->add(self::$quads[1]);
        $this->assertCount(1, $dn);

        $d = $dn->getDataset();
        $this->assertCount(2, $d);

        $d[] = self::$quads[2];
        $d[] = self::$quads[3];
        $this->assertCount(4, $d);
        $this->assertCount(2, $dn);
    }

    public function testWithDataset(): void {
        $d1 = static::getDataset();
        $d1->add(self::$quads[0]);
        $d2 = static::getDataset();
        $d2->add(self::$quads[1]);

        $dn1 = static::getDatasetNode(self::$quads[0]->getSubject(), $d1);

        // DatasetNodeInterface::factory() creates a copy of the dataset
        $d1->add(self::$quads[2]);
        $this->assertContains(self::$quads[2], $d1);
        $this->assertNotContains(self::$quads[2], $dn1);
        $this->assertNotContains(self::$quads[2], $dn1->getDataset());
        $this->assertNotContains(self::$quads[2], $d2);

        // withDataset() keeps the provided dataset
        $dn2 = $dn1->withDataset($d2);
        $this->assertFalse($dn1->equals($dn2));
        $this->assertContains(self::$quads[0], $d1);
        $this->assertContains(self::$quads[0], $dn1);
        $this->assertContains(self::$quads[0], $dn1->getDataset());
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[0], $dn2);
        $this->assertNotContains(self::$quads[0], $dn2->getDataset());
        $this->assertContains(self::$quads[1], $d2);
        $this->assertNotContains(self::$quads[1], $dn2);
        $this->assertContains(self::$quads[1], $dn2->getDataset());

        $d2->add(self::$quads[3]);
        $this->assertNotContains(self::$quads[3], $d1);
        $this->assertNotContains(self::$quads[3], $dn1);
        $this->assertNotContains(self::$quads[3], $dn1->getDataset());
        $this->assertContains(self::$quads[3], $d2);
        $this->assertContains(self::$quads[3], $dn2);
        $this->assertContains(self::$quads[3], $dn2->getDataset());
    }

    public function testAddQuads(): void {
        $d = static::getDatasetNode(self::$quads[0]->getSubject());
        for ($i = 0; $i < 3; $i++) {
            $d->add(self::$quads[$i]);
        }
        $this->assertCount(1, $d);
        $this->assertCount(3, $d->getDataset());

        $d->add(new GenericQuadIterator(self::$quads));
        $this->assertEquals(2, count($d));
        $this->assertCount(4, $d->getDataset());
    }

    public function testAddQuadsNoSubject(): void {
        $d   = static::getDatasetNode(self::$quads[1]->getSubject());
        $qns = self::$df->quadNoSubject(self::$df->namedNode('foo'), self::$df->literal('bar'));
        $d->add($qns);
        $this->assertCount(1, $d);
        $q   = self::$df->quad($d->getNode(), $qns->getPredicate(), $qns->getObject(), $qns->getGraph());
        $this->assertContains($q, $d);
    }

    public function testGetIterator(): void {
        $de = static::getDatasetNode(self::$quads[0]->getSubject());
        $d  = static::getDatasetNode(self::$quads[0]->getSubject());
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
        $this->assertEquals(2, $n);
        $this->assertEquals([1, 0, 0, 1], $counts);

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
        $this->assertEquals(2, $n);
        $this->assertEquals([1, 0, 0, 1], $counts);

        // match - QuadIteratorInterface|QuadIteratorAggregateInterface
        $dd     = static::getDatasetNode(self::$quads[0]->getSubject());
        $dd->add([self::$quads[1], self::$quads[3]]);
        $n      = 0;
        $counts = [0, 0, 0, 0];
        foreach ($dd as $q) {
            $n++;
            foreach (self::$quads as $i => $j) {
                $counts[$i] += (int) $j->equals($q);
            }
        }
        $this->assertEquals(1, $n);
        $this->assertEquals([0, 0, 0, 1], $counts);

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
        $dd = static::getDatasetNode(self::$quads[0]->getSubject());
        $nn = self::$df->namedNode('noMatch');
        $dd->add(self::$df->quad($nn, $nn, $nn));
        foreach ($d->getIterator($dd) as $q) {
            $this->assertTrue(false);
        }

        // no match - empty QuadIteratorInterface|QuadIteratorAggregateInterface
        $dd = static::getDatasetNode(self::$quads[0]->getSubject());
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
        $de = static::getDatasetNode(self::$quads[0]->getSubject());
        $d  = static::getDatasetNode(self::$quads[0]->getSubject());
        $d->add(self::$quads);

        // single match - QuadCompareInterface
        $this->assertTrue(isset($d[self::$quads[0]]));
        // single match - callable
        $this->assertTrue(isset($d[fn($x) => self::$quads[3]->equals($x)]));
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
                $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
            }
        }
    }

    public function testOffsetUnset(): void {
        $de = static::getDatasetNode(self::$quads[0]->getSubject());
        $this->assertCount(0, $de);
        $d  = static::getDatasetNode(self::$quads[0]->getSubject());
        $d->add(self::$quads);
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
        $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));

        // single match - QuadCompareInterface
        unset($d[self::$quads[0]]);
        $this->assertEquals([1, 0, 0, 0, 1], $this->getQuadsCount($d));
        $this->assertEquals([3, 0, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
        // single match - callable
        unset($d[fn($x) => self::$quads[3]->equals($x)]);
        $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($d));
        $this->assertEquals([2, 0, 1, 1, 0], $this->getQuadsCount($d->getDataset()));

        // no match - QuadCompareInterface
        $qt = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        unset($d[$qt]);
        $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($d));
        $this->assertEquals([2, 0, 1, 1, 0], $this->getQuadsCount($d->getDataset()));
        // no match - callable
        unset($d[fn() => false]);
        $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($d));
        $this->assertEquals([2, 0, 1, 1, 0], $this->getQuadsCount($d->getDataset()));
        // no match - trash
        foreach ([0, 100, -10, 'aaa', new \stdClass()] as $i) {
            try {
                unset($d[$i]);
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertTrue(true);
            }
        }
        $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($d));
        $this->assertEquals([2, 0, 1, 1, 0], $this->getQuadsCount($d->getDataset()));

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
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
        $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
        $qt     = static::getQuadTemplate(self::$quads[0]->getSubject());
        $toFail = [$qt, fn() => true];
        foreach ($toFail as $i) {
            try {
                unset($d[$i]);
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (MultipleQuadsMatchedException $ex) {
                $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
            }
        }
    }

    public function testOffsetGet(): void {
        $de = static::getDatasetNode(self::$quads[0]->getSubject());
        $d  = static::getDatasetNode(self::$quads[0]->getSubject());
        $d->add(self::$quads);

        // single match - QuadCompareInterface
        $this->assertTrue(self::$quads[0]->equals($d[self::$quads[0]]));
        // single match - callable
        $this->assertTrue(self::$quads[3]->equals($d[fn($x) => self::$quads[3]->equals($x)]));
        // single match - 0
        $q = $d[0];
        $this->assertTrue(self::$quads[0]->equals($q) || self::$quads[3]->equals($q));

        // no match - QuadCompareInterface, callable, trash
        $qt     = static::getQuadTemplate(self::$df->namedNode('noMatch'));
        $toFail = [$qt, fn() => false, 100, -10, 'aaa', new \stdClass()];
        foreach ($toFail as $i) {
            try {
                $d[$i];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (UnexpectedValueException $ex) {
                $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
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
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
        $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
        $qt     = static::getQuadTemplate(self::$quads[0]->getSubject());
        $toFail = [$qt, fn() => true];
        foreach ($toFail as $i) {
            try {
                $d[$i];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (MultipleQuadsMatchedException $ex) {
                $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
            }
        }
    }

    public function testOffsetSet(): void {
        $de = static::getDatasetNode(self::$quads[0]->getSubject());
        $d  = static::getDatasetNode(self::$quads[0]->getSubject());

        // null
        $this->assertEquals([0, 0, 0, 0, 0], $this->getQuadsCount($d));
        $d[] = self::$quads[0];
        $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d));
        $d[] = self::$quads[3];
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
        $d[] = self::$quads[3];
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));

        // single match - QuadCompareInterface
        $nq                                       = self::$quads[3]->withPredicate(self::$df->namedNode('other'));
        $d[self::$quads[3]]                       = $nq;
        $this->assertEquals([2, 1, 0, 0, 0], $this->getQuadsCount($d));
        $this->assertEquals([2, 1, 0, 0, 0], $this->getQuadsCount($d->getDataset()));
        $d[$nq]                                   = self::$quads[0];
        $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d));
        $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d->getDataset()));
        // single match - callable
        $d[]                                      = self::$quads[3];
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d->getDataset()));
        $d[fn($x) => self::$quads[3]->equals($x)] = $nq;
        $this->assertEquals([2, 1, 0, 0, 0], $this->getQuadsCount($d));
        $this->assertEquals([2, 1, 0, 0, 0], $this->getQuadsCount($d->getDataset()));
        $d[fn($x) => $nq->equals($x)]             = self::$quads[0];
        $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d));
        $this->assertEquals([1, 1, 0, 0, 0], $this->getQuadsCount($d->getDataset()));

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
        $d      = static::getDatasetNode(self::$quads[0]->getSubject());
        $d->add(self::$quads);
        $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
        $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
        $qt     = static::getQuadTemplate(self::$quads[0]->getSubject());
        $toFail = [$qt, fn() => true];
        foreach ($toFail as $i) {
            try {
                $d[$i] = self::$quads[0];
                $this->assertTrue(false, "No exception for " . (is_object($i) ? get_class($i) : $i));
            } catch (MultipleQuadsMatchedException $ex) {
                $this->assertEquals([2, 1, 0, 0, 1], $this->getQuadsCount($d));
                $this->assertEquals([4, 1, 1, 1, 1], $this->getQuadsCount($d->getDataset()));
            }
        }
    }

    public function testToString(): void {
        $d   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d->add(self::$quads[0]);
        $d->add(self::$quads[1]);
        $ref = self::$quads[0] . "\n";
        $this->assertEquals($ref, (string) $d);
    }

    public function testEquals(): void {
        // DatasetNode
        $d1   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d2   = static::getDatasetNode(self::$quads[0]->getSubject());
        // equal - triples with different subject don't count
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[3];
        $d1[] = self::$quads[1];
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[3];
        $d2[] = self::$quads[2];
        $this->assertTrue($d1->equals($d2));
        // not equal
        unset($d2[self::$quads[3]]);
        $this->assertFalse($d1->equals($d2));
        // equal again
        unset($d1[self::$quads[3]]);
        $this->assertTrue($d1->equals($d2));

        // Dataset
        $d1   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d2   = static::getDataset();
        // equal - triples with different subject don't count
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[3];
        $d1[] = self::$quads[1];
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[3];
        $d2[] = self::$quads[2];
        $this->assertTrue($d1->equals($d2));
        // not equal
        unset($d2[self::$quads[3]]);
        $this->assertFalse($d1->equals($d2));
        // equal again
        unset($d1[self::$quads[3]]);
        $this->assertTrue($d1->equals($d2));

        // other terms
        $nodeUri = $d1->getNode()->getValue();
        $this->assertTrue($d1->equals(self::$df->namedNode($nodeUri)));
        $this->assertFalse($d1->equals(self::$quads[0]));
    }

    public function testCopy(): void {
        $d1 = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1->add(new GenericQuadIterator(self::$quads));

        // simple
        $d2 = $d1->copy();
        $this->assertTrue($d1->equals($d2));
        unset($d2[self::$quads[0]]);
        $this->assertCount(4, $d1->getDataset());
        $this->assertCount(2, $d1);
        $this->assertCount(3, $d2->getDataset());
        $this->assertCount(1, $d2);
        $this->assertFalse($d1->equals($d2));

        // Quad
        $d2 = $d1->copy(self::$quads[0]);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(1, $d2);
        $this->assertCount(3, $d2->getDataset());
        $this->assertTrue(isset($d2[self::$quads[0]]));
        $this->assertFalse(isset($d2[self::$quads[1]]));
        $this->assertTrue(isset($d2->getDataset()[self::$quads[1]]));

        // Quad not matching the node
        $d2 = $d1->copy(self::$quads[1]);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);
        $this->assertCount(2, $d2->getDataset());
        $this->assertTrue(isset($d2->getDataset()[self::$quads[1]]));
        $this->assertFalse(isset($d2[self::$quads[1]]));

        // QuadTemplate
        $d2 = $d1->copy(static::getQuadTemplate(self::$df::namedNode('foo')));
        $this->assertTrue($d1->equals($d2));
        $this->assertTrue($d1->getDataset()->equals($d2->getDataset()));
        $this->assertCount(2, $d2);
        $this->assertCount(4, $d2->getDataset());

        // QuadTemplate not matching the node
        $d2   = $d1->copy(static::getQuadTemplate(self::$df::namedNode('noSuchNode')));
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);
        $this->assertCount(2, $d2->getDataset());
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[3];
        $this->assertTrue($d1->equals($d2));
        $this->assertTrue($d1->getDataset()->equals($d2->getDataset()));

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
        $this->assertCount(2, $d2->getDataset());
        $this->assertTrue(isset($d2->getDataset()[self::$quads[1]]));
        $this->assertTrue(isset($d2->getDataset()[self::$quads[2]]));
    }

    public function testCopyExcept(): void {
        $d1 = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $d2   = $d1->copyExcept(self::$quads[0]);
        $this->assertFalse($d1->equals($d2));
        $this->assertFalse($d1->getDataset()->equals($d2->getDataset()));
        $this->assertCount(1, $d2);
        $this->assertCount(3, $d2->getDataset());
        $d2[] = self::$quads[0];
        $this->assertTrue($d1->equals($d2));
        $this->assertTrue($d1->getDataset()->equals($d2->getDataset()));

        // Quad not matching the node
        $d2 = $d1->copyExcept(self::$quads[1]);
        $this->assertTrue($d1->equals($d2));
        $this->assertTrue($d1->getDataset()->equals($d2->getDataset()));
        $this->assertCount(2, $d2);
        $this->assertCount(4, $d2->getDataset());

        // QuadTemplate
        $d2   = $d1->copyExcept(static::getQuadTemplate(self::$df::namedNode('foo')));
        $this->assertFalse($d1->equals($d2));
        $this->assertFalse($d1->getDataset()->equals($d2->getDataset()));
        $this->assertCount(0, $d2);
        $this->assertCount(2, $d2->getDataset());
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[3];
        $this->assertTrue($d1->equals($d2));
        $this->assertTrue($d1->getDataset()->equals($d2->getDataset()));

        // QuadIterator
        $d2 = $d1->copyExcept($d1);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);
        $this->assertCount(2, $d2->getDataset());

        // callable
        $fn = function (QuadInterface $x): bool {
            return true;
        };
        $d2 = $d1->copyExcept($fn);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);
        $this->assertCount(2, $d2->getDataset());
    }

    public function testDelete(): void {
        $d1 = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $d2      = $d1->copy();
        $deleted = $d2->delete(self::$quads[0]->withSubject(self::$df::blankNode()));
        $this->assertCount(0, $deleted);
        $this->assertCount(2, $d2);
        $this->assertCount(4, $d2->getDataset());
        $this->assertTrue($d2->equals($d1));
        $this->assertTrue($d2->getDataset()->equals($d1->getDataset()));

        $deleted = $d2->delete(self::$quads[0]);
        $this->assertCount(1, $deleted);
        $this->assertCount(1, $d2);
        $this->assertCount(3, $d2->getDataset());
        $this->assertFalse($d2->equals($d1));
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertContains(self::$quads[0], $deleted);

        // QuadTemplate
        $d2 = $d1->copy();
        $d2->delete(static::getQuadTemplate(self::$df::namedNode('foo')));
        $this->assertCount(0, $d2);
        $this->assertCount(2, $d2->getDataset());
        $this->assertFalse($d2->equals($d1));
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[3], $d2);

        $d2 = $d1->copy();
        $d2->delete(static::getQuadTemplate(self::$df::namedNode('bar')));
        $this->assertCount(2, $d2);
        $this->assertCount(4, $d2->getDataset());
        $this->assertTrue($d2->equals($d1));
        $this->assertTrue($d2->getDataset()->equals($d1->getDataset()));

        // QuadIterator
        $d2 = $d1->copy();
        $d2->delete($d1);
        $this->assertCount(0, $d2);
        $this->assertCount(2, $d2->getDataset());
        $this->assertFalse($d2->equals($d1));

        // callable
        $fn = function (QuadInterface $x): bool {
            return $x->getGraph()->getValue() === 'graph';
        };
        $d2 = $d1->copy();
        $d2->delete($fn);
        $this->assertCount(1, $d2);
        $this->assertCount(3, $d2->getDataset());
        $this->assertFalse($d2->equals($d1));
        $this->assertContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[3], $d2);
    }

    public function testDeleteExcept(): void {
        $d1 = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $d2 = $d1->copy();
        $d2->deleteExcept(self::$quads[0]->withSubject(self::$df::blankNode()));
        $this->assertCount(0, $d2);
        $this->assertCount(2, $d2->getDataset());
        $this->assertFalse($d2->equals($d1));
        $this->assertFalse($d2->getDataset()->equals($d1->getDataset()));

        $d2      = $d1->copy();
        $deleted = $d2->deleteExcept(self::$quads[0]);
        $this->assertCount(1, $deleted);
        $this->assertCount(1, $d2);
        $this->assertCount(3, $d2->getDataset());
        $this->assertFalse($d2->equals($d1));
        $this->assertFalse($d2->getDataset()->equals($d1->getDataset()));
        $this->assertTrue(isset($d2[self::$quads[0]]));
        $this->assertFalse(isset($d2[self::$quads[3]]));
        $this->assertTrue(isset($deleted[self::$quads[3]]));

        // QuadTemplate
        $d2 = $d1->copy();
        $d2->deleteExcept(static::getQuadTemplate(self::$df::namedNode('foo')));
        $this->assertTrue($d2->equals($d1));
        $this->assertTrue($d2->getDataset()->equals($d1->getDataset()));

        // QuadIterator
        $d2      = $d1->copy();
        $deleted = $d2->deleteExcept($d1);
        $this->assertCount(0, $deleted);
        $this->assertCount(2, $d2);
        $this->assertCount(4, $d2->getDataset());
        $this->assertTrue($d2->equals($d1));

        // callable
        $fn = function (QuadInterface $x): bool {
            return $x->getGraph()->getValue() === 'graph';
        };
        $d2      = $d1->copy();
        $deleted = $d2->deleteExcept($fn);
        $this->assertCount(1, $deleted);
        $this->assertCount(1, $d2);
        $this->assertCount(3, $d2->getDataset());
        $this->assertFalse($d2->equals($d1));
        $this->assertTrue(isset($d2[self::$quads[3]]));
        $this->assertFalse(isset($d2[self::$quads[0]]));
    }

    public function testUnion(): void {
        $d1   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[2];
        $d2   = static::getDataset();
        $d2[] = self::$quads[3];
        $d2[] = self::$quads[1];

        $d3 = $d1->union($d2);
        $d4 = $d2->union($d1);
        $this->assertCount(1, $d1);
        $this->assertCount(2, $d1->getDataset());
        $this->assertCount(2, $d2);
        $this->assertCount(2, $d3);
        $this->assertCount(3, $d3->getDataset());
        $this->assertCount(3, $d4);
        $this->assertNotContains(self::$quads[2], $d3);

        $d1   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[2];
        $d2   = static::getDataset();
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[1];

        $d3 = $d1->union($d2);
        $d4 = $d2->union($d1);
        $this->assertCount(1, $d1);
        $this->assertCount(2, $d1->getDataset());
        $this->assertCount(2, $d2);
        $this->assertCount(1, $d3);
        $this->assertCount(2, $d3->getDataset());
        $this->assertCount(2, $d4);
        $this->assertNotContains(self::$quads[2], $d3);
    }

    public function testXor(): void {
        $d1   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
        $d2   = static::getDataset();
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[2];
        $d2[] = self::$quads[3];

        $d3 = $d1->xor($d2);
        $d4 = $d2->xor($d1);
        $this->assertCount(1, $d1);
        $this->assertCount(2, $d1->getDataset());
        $this->assertCount(3, $d2);
        $this->assertCount(1, $d3);
        $this->assertCount(2, $d3->getDataset());
        $this->assertCount(2, $d4);
        $this->assertContains(self::$quads[3], $d3);
        $this->assertContains(self::$quads[1], $d3->getDataset());
        $this->assertNotContains(self::$quads[0], $d3);
        $this->assertContains(self::$quads[2], $d4);
        $this->assertContains(self::$quads[3], $d4);
        $this->assertNotContains(self::$quads[0], $d4);
    }

    public function testForEach(): void {
        $d   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d[] = self::$df::quad(self::$df::namedNode('foo'), self::$df::namedNode('bar'), self::$df::literal(1));
        $d[] = self::$df::quad(self::$df::namedNode('foo'), self::$df::namedNode('baz'), self::$df::literal(5));
        $d[] = self::$df::quad(self::$df::namedNode('bar'), self::$df::namedNode('foo'), self::$df::literal(3));
        $d->forEach(function (QuadInterface $x): QuadInterface {
            $obj = $x->getObject();
            return $obj instanceof LiteralInterface ? $x->withObject($obj->withValue((float) $obj->getValue() * 2)) : $x;
        });
        $this->assertEquals(2, (int) $d[static::getQuadTemplate(predicate: self::$df::namedNode('bar'))]->getObject()->getValue());
        $this->assertEquals(10, (int) $d[static::getQuadTemplate(predicate: self::$df::namedNode('baz'))]->getObject()->getValue());
        $this->assertEquals(3, (int) $d->getDataset()[static::getQuadTemplate(self::$df::namedNode('bar'))]->getObject()->getValue());

        $d->forEach(function (QuadInterface $x): ?QuadInterface {
            $obj = $x->getObject();
            return $obj instanceof LiteralInterface && $obj->getValue() < 5 ? $x : null;
        });
        $this->assertCount(1, $d);
        $this->assertCount(2, $d->getDataset());
        $this->assertEquals(2, (int) $d[static::getQuadTemplate(self::$df::namedNode('foo'))]->getObject()->getValue());

        $d2 = $d->copy();
        $d->forEach(function (QuadInterface $x): ?QuadInterface {
            throw new \RuntimeException();
        }, static::getQuadTemplate(self::$df::namedNode('foobar')));
        $this->assertTrue($d2->equals($d));
        $this->assertTrue($d2->getDataset()->equals($d->getDataset()));
    }

    public function testAnyNone(): void {
        $d1 = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $this->assertTrue($d1->any(self::$quads[0]));
        $this->assertFalse($d1->none(self::$quads[0]));
        $this->assertFalse($d1->any(self::$quads[1]));
        $this->assertTrue($d1->none(self::$quads[1]));

        // QuadTemplate
        $this->assertTrue($d1->any(static::getQuadTemplate(self::$df::namedNode('foo'))));
        $this->assertFalse($d1->none(static::getQuadTemplate(self::$df::namedNode('foo'))));
        $this->assertFalse($d1->any(static::getQuadTemplate(self::$df::namedNode('bar'))));
        $this->assertTrue($d1->none(static::getQuadTemplate(self::$df::namedNode('bar'))));

        // QuadIterator
        $d2   = static::getDataset();
        $d2[] = self::$quads[0];
        $d3   = static::getDataset();
        $d3[] = self::$quads[1];
        $this->assertTrue($d1->any($d2));
        $this->assertFalse($d1->none($d2));
        $this->assertFalse($d1->any($d3));
        $this->assertTrue($d1->none($d3));

        // callable
        $fn = function (QuadInterface $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $this->assertTrue($d1->any($fn));
        $this->assertFalse($d1->none($fn));
        $fn = function (QuadInterface $x): bool {
            return $x->getSubject()->getValue() === 'bar';
        };
        $this->assertFalse($d1->any($fn));
        $this->assertTrue($d1->none($fn));
    }

    public function testEvery(): void {
        // Quad
        $d1   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1[] = self::$quads[0];
        $this->assertTrue($d1->every(self::$quads[0]));
        $d1[] = self::$quads[1];
        $this->assertTrue($d1->every(self::$quads[0]));
        $d1[] = self::$quads[3];
        $this->assertFalse($d1->every(self::$quads[0]));

        // QuadTemplate
        $d1   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
        $d1[] = self::$quads[3];
        $this->assertTrue($d1->every(static::getQuadTemplate(self::$df::namedNode('foo'))));
        $this->assertFalse($d1->none(static::getQuadTemplate(null, null, self::$df::literal('baz', 'en'))));

        // callable
        $d1   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
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
        $d = static::getDatasetNode(self::$quads[0]->getSubject());
        $d->add(new GenericQuadIterator(self::$quads));

        $list = iterator_to_array($d->listSubjects());
        $this->assertEquals(1, count($list));
        $list = iterator_to_array($d->listSubjects($this->getQuadTemplate(null, static::$df::namedNode('bar'))));
        $this->assertEquals(1, count($list));
        $this->assertTrue(static::$df::namedNode('foo')->equals($list[0]));
        $list = iterator_to_array($d->listSubjects($this->getQuadTemplate(static::$df::namedNode('foobar'))));
        $this->assertEquals(0, count($list));

        $list = iterator_to_array($d->listPredicates($this->getQuadTemplate(static::$df::namedNode('foo'))));
        $this->assertEquals(1, count($list));
        $this->assertTrue(static::$df::namedNode('bar')->equals($list[0]));
        $list = iterator_to_array($d->listPredicates($this->getQuadTemplate(static::$df::namedNode('foobar'))));
        $this->assertEquals(0, count($list));

        $list = iterator_to_array($d->listObjects());
        $this->assertEquals(2, count($list));
        $list = iterator_to_array($d->listObjects($this->getQuadTemplate(object: static::$df::literal('baz', 'en'))));
        $this->assertEquals(1, count($list));
        $this->assertTrue(static::$df::literal('baz', 'en')->equals($list[0]));
        $list = iterator_to_array($d->listObjects($this->getQuadTemplate(static::$df::namedNode('foobar'))));
        $this->assertEquals(0, count($list));

        $list = iterator_to_array($d->listGraphs());
        $this->assertEquals(2, count($list));
        $list = iterator_to_array($d->listGraphs($this->getQuadTemplate(null, null, static::$df::literal('baz', 'en'))));
        $this->assertEquals(1, count($list));
        $this->assertTrue(static::$df::namedNode('graph')->equals($list[0]));
        $list = iterator_to_array($d->listGraphs($this->getQuadTemplate(static::$df::namedNode('foobar'))));
        $this->assertEquals(0, count($list));
    }

    public function testNested(): void {
        $d   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d->add(self::$quads[1]);
        $nn1 = self::$df->namedNode('foo');
        $nn2 = self::$df->namedNode('bar');
        $nn3 = self::$df->namedNode('baz');
        $d->add(self::$df->quad($d->getNode(), $nn1, $nn1));
        $d->add(self::$df->quad($d->getNode(), $nn1, $nn2));
        $d->add(self::$df->quad($d->getNode(), $nn2, $nn2));
        $d->add(self::$df->quad($d->getNode(), $nn3, $nn3));

        $counts = ['foo' => 2, 'bar' => 1, 'baz' => 1];
        foreach ($d->listPredicates() as $pred) {
            $n  = 0;
            $d1 = $d->copy($this->getQuadTemplate(predicate: $pred));
            foreach ($d1->listObjects() as $value) {
                $n++;
                $d2 = $d1->copy($this->getQuadTemplate(object: $value));
            }
            $this->assertEquals($counts[$pred->getValue()], $n, $pred->getValue());
        }
    }

    public function testMap(): void {
        $t1   = self::$df::namedNode('foo');
        $d1   = static::getDatasetNode($t1);
        $d1[] = self::$df::quad($t1, self::$df::namedNode('baz'), self::$df::literal(1));
        $d1[] = self::$df::quad(self::$df::namedNode('bar'), self::$df::namedNode('baz'), self::$df::literal(5));
        $d2   = $d1->map(function (QuadInterface $x) {
            $obj = $x->getObject();
            return $obj instanceof LiteralInterface ? $x->withObject($obj->withValue((float) (string) $obj->getValue() * 2)) : $x;
        });
        $this->assertCount(2, $d1->getDataset());
        $this->assertCount(1, $d1);
        $this->assertEquals(1, (int) (string) $d1[0]->getObject()->getValue());
        $this->assertCount(1, $d2);
        $this->assertEquals(2, (int) (string) $d2[0]->getObject()->getValue());

        $d3 = $d2->map(function (QuadInterface $x) {
            throw new \RuntimeException();
        }, static::getQuadTemplate(self::$df::namedNode('foobar')));
        $this->assertEquals(0, count($d3));
    }

    public function testReduce(): void {
        $t1   = self::$df::namedNode('foo');
        $d1   = static::getDatasetNode($t1);
        $d1[] = self::$df::quad($t1, self::$df::namedNode('baz'), self::$df::literal(1));
        $d1[] = self::$df::quad($t1, self::$df::namedNode('bar'), self::$df::literal(2));
        $d1[] = self::$df::quad(self::$df::namedNode('bar'), self::$df::namedNode('baz'), self::$df::literal(5));
        $sum  = $d1->reduce(function (float $sum, QuadInterface $x) {
            return $sum + (float) (string) $x->getObject()->getValue();
        }, 0);
        $this->assertCount(3, $d1->getDataset());
        $this->assertEquals(3, $sum);
        $this->assertCount(2, $d1);
        $this->assertEquals(1, (int) (string) $d1[static::getQuadTemplate($t1, self::$df::namedNode('baz'))]->getObject()->getValue());
        $this->assertEquals(2, (int) (string) $d1[static::getQuadTemplate($t1, self::$df::namedNode('bar'))]->getObject()->getValue());

        $sum = $d1->reduce(function (QuadInterface $x) {
            throw new \RuntimeException();
        }, -5, static::getQuadTemplate(self::$df::namedNode('foobar')));
        $this->assertEquals(-5, $sum);
    }
}
