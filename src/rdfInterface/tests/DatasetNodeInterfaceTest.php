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

use OutOfBoundsException;
use rdfHelpers\GenericQuadIterator;
use rdfInterface\DatasetInterface;
use rdfInterface\DatasetNodeInterface;
use rdfInterface\LiteralInterface;
use rdfInterface\QuadCompareInterface;
use rdfInterface\TermInterface;
use rdfInterface\TermCompareInterface;
use rdfInterface\QuadInterface;

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

    public function testGetValue(): void {
        $d = static::getDatasetNode(self::$quads[0]);
        $this->assertTrue(self::$quads[0]->equals($d->getValue()));
    }

    public function testWithGetNode(): void {
        $dn1 = static::getDatasetNode(self::$quads[0]);
        $dn1->add(new GenericQuadIterator(self::$quads));
        $dn2 = $dn1->withNode(self::$quads[1]);

        $this->assertFalse($dn1->equals($dn2));
        $this->assertTrue($dn1->getDataset()->equals($dn2->getDataset()));
        $this->assertTrue(self::$quads[0]->equals($dn1->getNode()));
        $this->assertTrue(self::$quads[1]->equals($dn2->getNode()));
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

        // DatasetNodeInterface::factory() isolates from the initial dataset
        $d1->add(self::$quads[2]);
        $this->assertContains(self::$quads[2], $d1);
        $this->assertNotContains(self::$quads[2], $dn1->getDataset());
        $this->assertNotContains(self::$quads[2], $d2);

        // withDataset() uses provided dataset directly
        $dn2 = $dn1->withDataset($d2);
        $this->assertFalse($dn1->equals($dn2));
        $this->assertContains(self::$quads[0], $d1);
        $this->assertContains(self::$quads[0], $dn1);
        $this->assertContains(self::$quads[0], $dn1->getDataset());
        $this->assertContains(self::$quads[1], $d2);
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

    public function testIterator(): void {
        $d = static::getDatasetNode(self::$quads[0]->getSubject());
        $d->add(new GenericQuadIterator(self::$quads));
        foreach ($d as $v) {
            $this->assertNotNull($v);
            $this->assertTrue($v->equals(self::$quads[0]) || $v->equals(self::$quads[3]));
        }
    }

    public function testOffsetExistsGet(): void {
        $node   = self::$quads[0]->getSubject();
        $d      = static::getDatasetNode($node);
        $d->add(new GenericQuadIterator(self::$quads));
        $triple = self::$df::quad(self::$df::namedNode('foo'), self::$df::namedNode('bar'), self::$df::literal('baz', 'de'));

        // by Quad
        foreach (self::$quads as $i) {
            if ($node->equals($i->getSubject())) {
                $this->assertTrue(isset($d[$i]));
                $this->assertTrue($i->equals($d[$i]));
            } else {
                $this->assertFalse(isset($d[$i]));
            }
        }
        $this->assertFalse(isset($d[$triple]));
        try {
            $x = $d[$triple];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }

        // by QuadTemplate
        $tmpl = static::getQuadTemplate(subject: $node, graph: self::$quads[3]->getGraph());
        $this->assertTrue(self::$quads[3]->equals($d[$tmpl]));
        try {
            $tmpl = static::getQuadTemplate(null, self::$df::namedNode('bar'));
            $x    = $d[$tmpl];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }

        // by callback
        $fn = function (QuadInterface $q, DatasetInterface $d) {
            $obj = $q->getObject();
            return $obj instanceof LiteralInterface && $obj->getLang() === 'en';
        };
        $this->assertTrue(self::$quads[3]->equals($d[$fn]));
        try {
            $fn = function (QuadInterface $q, DatasetInterface $d) {
                return $q->getPredicate()->getValue() === 'bar';
            };
            $x = $d[$fn];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }

        // by integer
        $this->assertTrue(isset($d[0]));
        $quad = $d[0];
        $this->assertEquals(1, array_sum(array_map(fn($x) => $x->equals($quad), self::$quads)));
        $this->assertFalse(isset($d[1])); // isset() internally suppresses exceptions
        try {
            $quad = $d[1];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }
        $d = static::getDatasetNode($node);
        $this->assertFalse(isset($d[0]));
        try {
            $quad = $d[0];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }
        $this->assertEquals("fallback", $d[0] ?? "fallback");
    }

    public function testOffsetSet(): void {
        $node = self::$quads[0]->getSubject();
        $d    = static::getDatasetNode($node);
        $d[]  = self::$quads[0];
        $this->assertCount(1, $d);
        $this->assertContains(self::$quads[0], $d);

        $d[] = self::$quads[1];
        $d[] = self::$quads[3];
        $this->assertCount(2, $d);
        $this->assertNotContains(self::$quads[1], $d);
        $this->assertNotContains(self::$quads[2], $d);
        $this->assertContains(self::$quads[3], $d);

        // by Quad
        // 0 foo bar "baz"
        // 1 baz foo bar
        // 3 foo bar "baz"@en graph
        $d[self::$quads[0]] = self::$quads[3];
        $this->assertCount(1, $d);
        $this->assertContains(self::$quads[3], $d);
        $this->assertNotContains(self::$quads[0], $d);
        $this->assertNotContains(self::$quads[1], $d);
        $this->assertNotContains(self::$quads[2], $d);
        try {
            $d[self::$quads[1]] = self::$quads[2];
            $this->assertTrue(false);
        } catch (OutOfBoundsException $ex) {
            $this->assertTrue(true);
        }

        // by QuadTemplate
        // 0 foo bar "baz"
        // 1 baz foo bar
        // 2 bar baz foo
        // 3 foo bar "baz"@en graph
        $d        = static::getDatasetNode($node);
        $d->add(new GenericQuadIterator(self::$quads));
        $tmpl     = static::getQuadTemplate($node, self::$df::namedNode('bar'), self::$df::literal('baz', 'en'));
        $d[$tmpl] = self::$quads[0];
        $this->assertCount(1, $d);
        $this->assertContains(self::$quads[0], $d);
        $this->assertNotContains(self::$quads[1], $d);
        $this->assertNotContains(self::$quads[2], $d);
        $this->assertNotContains(self::$quads[3], $d);
        $d[]      = self::$quads[3];
        try {
            // two quads match
            $d[static::getQuadTemplate($node)] = self::$quads[0];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }
        try {
            // no quad matches
            $d[static::getQuadTemplate($node, self::$df::namedNode('foo'))] = self::$quads[0];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }
        try {
            // no quad matches
            $d[static::getQuadTemplate(self::$df::namedNode('aaa'))] = self::$quads[0];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }

        // by callback
        // 0 foo bar "baz"
        // 1 baz foo bar
        // 2 bar baz foo
        // 3 foo bar "baz"@en graph
        $d  = static::getDatasetNode($node);
        $d->add(new GenericQuadIterator(self::$quads));
        $fn = function (QuadInterface $q, DatasetInterface $d) {
            return $q->getGraph()->getValue() === 'graph';
        };
        $d[$fn] = self::$quads[2];
        $this->assertCount(1, $d);
        $this->assertContains(self::$quads[0], $d);
        $this->assertNotContains(self::$quads[1], $d);
        $this->assertNotContains(self::$quads[2], $d);
        $this->assertNotContains(self::$quads[3], $d);
        $d[]    = self::$quads[3];
        try {
            // many matches
            $fn = function (QuadInterface $q, DatasetInterface $d) {
                return $q->getSubject()->getValue() === 'foo';
            };
            $d[$fn] = self::$quads[1];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }
        try {
            // no match
            $fn = function (QuadInterface $q, DatasetInterface $d) {
                return $q->getSubject()->getValue() === 'aaa';
            };
            $d[$fn] = self::$quads[1];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }
    }

    public function testOffsetUnSet(): void {
        $node = self::$quads[0]->getSubject();
        $d    = static::getDatasetNode($node);
        $d->add(new GenericQuadIterator(self::$quads));
        $this->assertCount(2, $d);
        // many matches
        try {
            unset($d[static::getQuadTemplate($node)]);
            $this->assertTrue(false);
        } catch (OutOfBoundsException $ex) {
            $this->assertTrue(true);
        }
        // by Quad
        unset($d[self::$quads[0]]);
        $this->assertCount(1, $d);
        $this->assertNotContains(self::$quads[0], $d);
        $this->assertContains(self::$quads[3], $d);
        // by QuadTemplate
        unset($d[static::getQuadTemplate($node)]);
        $this->assertCount(0, $d);
        $this->assertNotContains(self::$quads[0], $d);
        $this->assertNotContains(self::$quads[3], $d);
        // by callable
        $d->add(new GenericQuadIterator(self::$quads));
        $fn = function (QuadInterface $x) {
            return $x->getGraph()->getValue() === 'graph';
        };
        unset($d[$fn]);
        $this->assertCount(1, $d);
        $this->assertContains(self::$quads[0], $d);
        $this->assertNotContains(self::$quads[3], $d);
        // unset non-existent
        unset($d[self::$quads[3]]);
        $this->assertCount(1, $d);
        $this->assertContains(self::$quads[0], $d);
        $this->assertNotContains(self::$quads[3], $d);
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

    public function testGetIterator(): void {
        $d   = static::getDatasetNode(self::$quads[0]->getSubject());
        $d[] = self::$quads[0];
        $d[] = self::$quads[1];
        $d[] = self::$quads[3];
        $n   = 0;
        foreach ($d->getIterator(self::$quads[0]) as $i) {
            $this->assertTrue(self::$quads[0]->equals($i));
            $n++;
        }
        $this->assertEquals(1, $n);
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
