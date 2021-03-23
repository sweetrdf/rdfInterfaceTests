# RdfInterface compliance tests

A set of [PHPUnit](https://phpunit.de/) tests for checking compliance of your classes with the [rdfInterface](https://github.com/sweetrdf/rdfInterface/).

## Usage

I assume you are using the [composer](https://getcomposer.org/) to manage your code dependencies.\
If not, it's definitely a good idea to start using it.

* Declare `sweetrdf/rdf-interface-tests` as development dependency of your package
  ```bash
  composer require --dev sweetrdf/rdf-interface-tests:*
  ```
* Prepare your own test classes inheriting from classes provided by this package.
    * Available test classes are in the [src/rdfInterface/tests](https://github.com/sweetrdf/rdfInterfaceTests/tree/master/src/rdfInterface/tests) directory of this repository.
    * A sample implementation is provided below.
    * You are free to extend your classes with additional tests.
* Run PHPUnit
  ```
  vendor/bin/phpunit --bootstrap vendor/autoload.php directoryWithYourTestClasses
  ```

## Sample implementation

Let's assume I developed a `myOwnRdf\MyOwnNamedNode` class implementing the `rdfInterface\NamedNode` interface.

The class providing compliance tests for the `rdfInterface\NamedNode` is `\rdfInterface\tests\TermsTest`. See [here](https://github.com/sweetrdf/rdfInterfaceTests/blob/master/src/rdfInterface/tests/TermsTest.php).

The `\rdfInterface\tests\TermsTest` class declares two abstract methods (trough the [TestBaseTrait](https://github.com/sweetrdf/rdfInterfaceTests/blob/master/src/rdfInterface/tests/TestBaseTrait.php)) which I have to implement:

* `abstract public static function getDataFactory(): \rdfInterface\DataFactory` 
  This one's pretty obvious. The test class has to know what should be tested and I must implement a method which will give it access to my implementation of the `rdfInterface\NamedNode` interface. 
  As in the rdfInterface terms (named/blank nodes, literals, quads, default graphs) are expected to be created by a static factory class implementing the `rdfInterface\DataFactory` I must provide a method returning instance of such a class (by the way yes, it means that if a given term can be created by the `rdfInterface\DataFactory`, you can't implement it alone but must provide also a `rdfInterface\DataFactory` implementation; at least when you want it to be testable). So let's assume here I also developed `myOwnRdf\MyOwnDataFactory` implementing the `rdfInterface\DataFactory`.
* `abstract public static function getForeignDataFactory(): DataFactory` 
  This one is not so obvious. The important thing about using a common iterface is to assure different implementations can work with each other (are interoperable), e.g. that `myOwnRdf\MyOwnNamedNode::equals(rdfInterface\Term $term)` returns correct results not only with `$term` being `myOwnRdf\MyOwnNamedNode` but coming from any other implementation.
  To check that the test class must be able to generate terms coming from another implementation.
  As we already know terms are to be generated trough a rdfInterface\DataFactory` implementation.
  This method is supposed to return such a "foreign terms factory".
    * If I don't want to perform interoperability tests, I can just implement this method as returning my implementation of the `rdfInterface\DataFactory` (here `myOwnRdf\MyOwnDataFactory`).
    * I decided to use `simpleRdf\DataFactory` from the [simpleRdf](https://github.com/sweetrdf/simpleRdf/) (all in all it's meant exactly for that).

Knowing all of that I can prepare my own test class inheriting from `\rdfInterface\tests\TermsTest`. Just:

* My own test class name must end with `Test` so the PHPUnit recognizes it properly. Let's make it `myOwnRdf\MyOwnNamedNodeTest`.
* `\rdfInterface\tests\TermsTest` contains a lot of tests for other kind of terms. I must mask them (see code sample below).
* I will also add my own test checking some unique features of my implementation.

```php
<?php
namespace myOwnRdf;

class MyOwnNamedNodeTest extends \rdfInterface\tests\DataFactoryTest {

    public static function getDataFactory(): \rdfInterface\DataFactory {
        return new MyDataFactoryClass();
    }

    public static function getForeignDataFactory(): \rdfInterface\DataFactory {
        return new \simpleRdf\DataFactory();
    }

    // override unwanted \rdfInterface\tests\DataFactoryTest methods
    public function testBlankNode(): void {
        $this->assertTrue(true);
    }
    public function testLiteralFactory(): void {
        $this->assertTrue(true);
    }
    (...etc., there is a lot of methods to skip in this scenario...)

    // provide my own test

    public function testMyFeature(): void {
       (...perform some tests...)
    }
}
```

You can find more exhaustive examples of reusing tests provided by this package in [simpleRdf](https://github.com/sweetrdf/simpleRdf/tree/master/tests) library tests and [quickRdf](https://github.com/sweetrdf/quickRdf/tree/master/tests) library tests.
