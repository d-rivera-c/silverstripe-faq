<?php

namespace SilverStripe\Faq\Tests;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Faq\Model\Faq;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Control\Controller;
use SilverStripe\Faq\PageTypes\FAQPage;

/**
 * FAQ Module Unit Tests
 */
class FAQTest extends SapphireTest
{
    /**
     * Link() functionality, returns a link to view the detail page for FAQ
     *
     * @see FAQ::getLink
     */
    public function testLink()
    {
        // no controller or object created, shouldn't get a link
        $faq = new FAQ();
        $this->assertEquals('', $faq->getLink());

        //TODO Fix the unit tests
        $this->markTestSkipped();

        // object created, should get a link
        $faq1 = new FAQ();
        $faq1->Question = 'question 1';
        $faq1->Answer = 'Milkyway chocolate bar';
        $faq1->write();
        $this->assertNotEquals('', $faq1->getLink());
    }

    /**
     * Should always get a root category
     *
     * @see FAQ::getRootCategory
     */
    public function testGetRootCategory()
    {
        // get root we assume is set by config
        $root = FAQ::getRootCategory();
        $this->assertTrue($root->exists());
        $this->assertEquals(TaxonomyTerm::class, $root->ClassName);

        // change config to something we know is not in the taxonomy table
        Config::inst()->update(FAQ::class, 'taxonomy_name', 'lolipopRANDOMCategory');
        $root = FAQ::getRootCategory();
        $this->assertTrue($root->exists());
        $this->assertEquals(TaxonomyTerm::class, $root->ClassName);
    }
}
