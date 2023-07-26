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

use rdfInterface\DatasetInterface;
use rdfInterface\QuadCompareInterface;
use rdfInterface\TermInterface;
use rdfInterface\TermCompareInterface;
use rdfInterface\DatasetNodeInterface;

/**
 * Description of NodeInterfaceTest
 *
 * @author zozlak
 */
abstract class DatasetNodeInterfaceTest extends \PHPUnit\Framework\TestCase {

    use TestBaseTrait;

    abstract public static function getDataset(): DatasetInterface;

    abstract public static function getDatasetNode(DatasetInterface $dataset,
                                                   TermInterface $node): DatasetNodeInterface;

    abstract public static function getQuadTemplate(TermCompareInterface | TermInterface | null $subject = null,
                                                    TermCompareInterface | TermInterface | null $predicate = null,
                                                    TermCompareInterface | TermInterface | null $object = null,
                                                    TermCompareInterface | TermInterface | null $graph = null): QuadCompareInterface;
}
