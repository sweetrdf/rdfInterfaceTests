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

use rdfHelpers\GenericQuadIterator;
use rdfInterface\DatasetInterface as Dataset;
use rdfInterface\QuadCompareInterface as QuadCompare;
use rdfInterface\TermInterface as Term;
use rdfInterface\TermCompareInterface as TermCompare;
use rdfInterface\DatasetNodeInterface as DatasetNode;

/**
 * Description of NodeInterfaceTest
 *
 * @author zozlak
 */
abstract class DatasetNodeInterfaceTest extends \PHPUnit\Framework\TestCase {

    use TestBaseTrait;

    abstract public static function getDataset(): Dataset;

    abstract public static function getDatasetNode(Dataset $dataset, Term $node): DatasetNode;

    abstract public static function getQuadTemplate(TermCompare | Term | null $subject = null,
                                                    TermCompare | Term | null $predicate = null,
                                                    TermCompare | Term | null $object = null,
                                                    TermCompare | Term | null $graph = null): QuadCompare;

    public function testBasic(): void {
        $dataset = static::getDataset();
        $dataset->add(new GenericQuadIterator(self::$quads));
        $term    = self::$quads[0]->getSubject();
        $node    = static::getDatasetNode($dataset, $term);

        $this->assertTrue($node->getNode()->equals($term));
        $this->assertTrue($node->getDataset()->equals($dataset));
        $this->assertCount(2, $node->getDataset()->copy(static::getQuadTemplate($node->getNode())));

        $emptyDataset = static::getDataset();
        $node         = $node->withDataset($emptyDataset);
        $this->assertTrue($node->getNode()->equals($term));
        $this->assertTrue($node->getDataset()->equals($emptyDataset));
        $this->assertFalse($node->getDataset()->equals($dataset));
        $this->assertCount(0, $node->getDataset()->copy(static::getQuadTemplate($node->getNode())));

        $termOutOfGraph = static::$quads[0];
        $node           = $node->withNode($termOutOfGraph);
        $this->assertTrue($node->getNode()->equals($termOutOfGraph));
        $this->assertFalse($node->getNode()->equals($term));
        $this->assertTrue($node->getDataset()->equals($emptyDataset));
        $this->assertFalse($node->getDataset()->equals($dataset));
    }

    public function testIterator(): void {
        $dataset = static::getDataset();
        $dataset->add(new GenericQuadIterator(self::$quads));
        $term    = self::$quads[0]->getSubject();
        $node    = static::getDatasetNode($dataset, $term);

        $n = 0;
        foreach ($node as $i) {
            $n++;
            $this->assertTrue(self::$quads[0]->equals($i) || self::$quads[3]->equals($i));
        }
        $this->assertEquals(2, $n);

        $n = 0;
        foreach ($node->getIterator(fn($x) => $x->getGraph()->equals(self::$quads[3]->getGraph())) as $i) {
            $n++;
            $this->assertTrue($i->equals(self::$quads[3]));
        }
        $this->assertEquals(1, $n);
    }
}
