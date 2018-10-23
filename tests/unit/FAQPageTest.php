<?php

namespace SilverStripe\Faq\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Faq\PageTypes\FAQPage;
use SilverStripe\Faq\Model\Faq;
use SilverStripe\Faq\Controllers\FAQPageController;
use SilverStripe\View\ArrayData;
use Phockito;
use Phake;
use SilverStripe\ORM\PaginatedList;

/**
 * Tests basic functionality of FAQPage
 */
class FAQPageTest extends FunctionalTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'FAQPageTest.yml';

    /**
     * @var null
     */
    protected $_page = null;

    /**
     * @var null
     */
    protected $_page2 = null;

    /**
     * @var null
     */
    protected $faq1 = null;

    /**
     * @var null
     */
    protected $faq2 = null;

    public function setUp()
    {
        parent::setUp();

        // categories
        $Vehicles = $this->objFromFixture(TaxonomyTerm::class, 'Vehicles')->getTaxonomy();
        $Cars = $this->objFromFixture(TaxonomyTerm::class, 'Cars')->getTaxonomy();
        $Fords = $this->objFromFixture(TaxonomyTerm::class, 'Fords')->getTaxonomy();

        $Cars->Children()->add($Fords);
        $Vehicles->Children()->add($Cars);

        $Roads = $this->objFromFixture(TaxonomyTerm::class, 'Roads')->getTaxonomy();

        // create faq page
        $this->_page = new FAQPage([
            'Title'              => "FAQ Page 1",
            'SearchNotAvailable' => 'The SearchIndex is not available',
            'SinglePageLimit'    => 2,
        ]);
        $this->_page->write();
        $this->_page->publish('Stage', 'Live');

        // second faq page
        $this->_page2 = new FAQPage(['Title' => "FAQ Page 2"]);
        $this->_page2->write();
        $this->_page2->Categories()->addMany([
            $Vehicles,
            $Cars,
            $Fords,
        ]);
        $this->_page2->publish('Stage', 'Live');

        // faqs
        $this->faq1 = new FAQ([
            'Question'   => 'question 1',
            'Answer'     => 'Milkyway chocolate bar',
            'CategoryID' => $Vehicles->ID,
        ]);
        $this->faq1->write();
        $this->faq2 = new FAQ([
            'Question'   => 'No imagination question',
            'Answer'     => '42',
            'CategoryID' => $Roads->ID,
        ]);
        $this->faq2->write();

        // Featured FAQs
        $this->_page->FeaturedFAQs()->add($this->faq1);
        $this->_page->FeaturedFAQs()->add($this->faq2);
        $this->_page2->FeaturedFAQs()->add($this->faq1);
        $this->_page2->FeaturedFAQs()->add($this->faq2);

        $this->_page2_controller = new FAQPageController($this->_page2);

        $this->controller = Injector::inst()->create(FAQPageController::class);
    }

    /**
     * Tests individual view  for FAQ
     * TODO: change after slug change
     */
    public function testView()
    {
        // test routing
        $page = $this->get('faq-page-1/view/1');
        $this->assertEquals(200, $page->getStatusCode());

        $page = $this->get('faq-page-1/view/665');
        $this->assertEquals(404, $page->getStatusCode());

        // test page body, we have to get the Q and the A
        $response = $this->get('faq-page-1/view/1');
        $this->assertContains('question 1', (string)$response->getBody());
        $this->assertContains('Milkyway chocolate bar', (string)$response->getBody());

        $response = $this->get('faq-page-1/view/2');
        $this->assertContains('No imagination question', (string)$response->getBody());
    }

    /**
     * Basic load page test
     */
    public function testIndex()
    {
        // faq page should load..
        $page = $this->get('faq-page-1/');
        $this->assertEquals(200, $page->getStatusCode());

        // check that page shows form
        $response = $this->get('faq-page-1');
        $this->assertContains('id="FAQSearchForm_FAQSearchForm_Search"', (string)$response->getBody());
    }

    /**
     * Featured FAQs should not display on frontend if not in the selected category
     * If no category selected, display everything
     */
    public function testFilterFeaturedFAQs()
    {
        // no category selected on FAQPage, show every featured FAQ
        $this->assertCount($this->_page->FeaturedFAQs()->count(), $this->_page->FilterFeaturedFAQs());

        // category selected, only display one
        $this->assertCount(1, $this->_page2->FilterFeaturedFAQs());
    }

    /**
     * getSelectedIDs should pull all of the ids of the passed category, and any descendants added to the page.
     */
    public function testGetSelectedIDs()
    {
        $CategoryID = $this->objFromFixture(TaxonomyTerm::class, 'Vehicles')->getTaxonomy()->ID;
        $filterCategory = $this->_page2_controller->Categories()->filter('ID', $CategoryID)->first();
        $selectedChildIDS = $this->_page2_controller->getSelectedIDs($filterCategory);
        $this->assertEquals([1, 2, 4], $selectedChildIDS);
    }

    /**
     * Test search results
     */
    public function testSearch()
    {
        //TODO Fix the unit tests
        $this->markTestSkipped(
            'Mocking not working properlly in SS4.'
        );

        $faq = FAQ::create(['Question' => 'question 1', 'Answer' => 'answer 1']);
        $result = new ArrayList();
        $result->push($faq);
        $mockResponse = [];
        $mockResponse['Matches'] = new PaginatedList($result);
        $mockResponse['Suggestion'] = 'suggestion text';

        // testing good response, get one search result
        $spy = Phockito::spy(FAQPageController::class);
        Phockito::when($spy)->getSearchQuery(anything())->return(new SearchQuery());
        Phake::when($spy)->doSearch(anything(), anything(), anything())->return(new ArrayData($mockResponse));
        $response = $spy->search();
        $this->assertSame($mockResponse['Suggestion'], $response['SearchSuggestion']['Suggestion']);

        // Testing error with solr
        $spy1 = Phockito::spy(FAQPageController::class);
        Phockito::when($spy1)->getSearchQuery($this->anything())->return(new SearchQuery());
        Phockito::when($spy1)->doSearch(anything(), anything(), anything())->throw(new Exception("Some error"));
        $response = $spy1->search();
        $this->assertTrue($response['SearchError']);
    }

    /**
     * When Single Page limit set, should get limit set of results and no pagination
     */
    public function testSinglePageLimit()
    {
        //TODO Fix the unit tests
        $this->markTestSkipped(
            'Mocking not working properlly in SS4.'
        );

        Phockito::include_hamcrest();
        $result = new ArrayList();
        $result->push(FAQ::create(['Question' => 'question 1', 'Answer' => 'answer 1']));
        $result->push(FAQ::create(['Question' => 'question 2', 'Answer' => 'answer 2']));
        $result->push(FAQ::create(['Question' => 'question 3', 'Answer' => 'answer 3']));
        $result->push(FAQ::create(['Question' => 'question 4', 'Answer' => 'answer 4']));
        $mockResponse = [];
        $mockResponse['Matches'] = new PaginatedList($result);
        $mockResponse['Suggestion'] = 'suggestion text';

        // testing total items are equal to set in _page, and there's no more than one page in pagination
        $spy = Phockito::spy(FAQPageController::class);
        Phockito::when($spy)->getSearchQuery($this->anything())->return(new SearchQuery());
        Phockito::when($spy)->doSearch($this->anything(), $this->anything(), $this->anything())->return(new ArrayData($mockResponse));
        $this->assertEquals(2, $response['SearchResults']->getTotalItems());
        $this->assertFalse($response['SearchResults']->MoreThanOnePage());
    }
}
