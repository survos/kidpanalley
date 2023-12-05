<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Testwork\Hook\Scope;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /** @BeforeSuite */
    public static function setup(Scope\BeforeSuiteScope $scope)
    {
    }


    /** @BeforeStep */
    public function beforeStep(\Behat\Behat\Hook\Scope\BeforeStepScope $scope)
    {
        dump($scope->getStep());
    }

    /** @AfterStep */
    public function afterStep(\Behat\Behat\Hook\Scope\AfterStepScope $scope)
    {
        dd($scope->getStep());
    }

    /** @AfterFeature */
    public static function teardownFeature(Scope\AfterFeatureScope $scope)
    {
    }

    /**
     * @Given there is a(n) :arg1, which costs £:arg2
     */
    public function thereIsAWhichCostsPs($arg1, $arg2)
    {
        throw new PendingException();
    }

    /**
     * @Given I am logged in as an visitor
     */
    public function iAmLoggedInAsAnVisitor()
    {
        throw new PendingException();
    }


}
