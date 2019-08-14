<?php
/**
 * Tests basic functionality of FAQPage
 */

 use SilverStripe\View\ArrayData;

class FAQPageTest extends FunctionalTest
{

    protected static $fixture_file = 'FAQPageTest.yml';

    protected $_page = null;
    protected $_page2 = null;
    protected $faq1 = null;
    protected $faq2 = null;

    /**
     *
     */
    public function setUp()
    {
        parent::setUp();

        // categories
        $Vehicles = $this->objFromFixture('TaxonomyTerm', 'Vehicles')->getTaxonomy();
        $Cars = $this->objFromFixture('TaxonomyTerm', 'Cars')->getTaxonomy();
        $Fords = $this->objFromFixture('TaxonomyTerm', 'Fords')->getTaxonomy();

        $Cars->Children()->add($Fords);
        $Vehicles->Children()->add($Cars);

        $Roads = $this->objFromFixture('TaxonomyTerm', 'Roads')->getTaxonomy();

        // create faq page
        $this->_page = new FAQPage(array(
            'Title' => "FAQ Page 1",
            'SearchNotAvailable' => 'The SearchIndex is not available',
            'SinglePageLimit' => 2
        ));
        $this->_page->write();
        $this->_page->publish('Stage', 'Live');

        // second faq page
        $this->_page2 = new FAQPage(array('Title' => "FAQ Page 2"));
        $this->_page2->write();
        $this->_page2->Categories()->addMany(array(
            $Vehicles,
            $Cars,
            $Fords
        ));
        $this->_page2->publish('Stage', 'Live');

        // faqs
        $this->faq1 = new FAQ(array(
            'Question' => 'question 1',
            'Answer' => 'Milkyway chocolate bar',
            'CategoryID' => $Vehicles->ID
        ));
        $this->faq1->write();
        $this->faq2 = new FAQ(array(
            'Question' => 'No imagination question',
            'Answer' => '42',
            'CategoryID' => $Roads->ID
        ));
        $this->faq2->write();

        // Featured FAQs
        $this->_page->FeaturedFAQs()->add($this->faq1);
        $this->_page->FeaturedFAQs()->add($this->faq2);
        $this->_page2->FeaturedFAQs()->add($this->faq1);
        $this->_page2->FeaturedFAQs()->add($this->faq2);

        $this->_page2_controller = new FAQPage_Controller($this->_page2);

        $this->controller = Injector::inst()->create('FAQPage_Controller');
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
        $this->assertTrue(strpos($response->getBody(), 'id="FAQSearchForm_FAQSearchForm_Search"') !== false);
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

        // Ensure requests retrieve the correct FAQ
        $request = new SS_HTTPRequest('GET', 'faq-page-1/view/1');
        $request->setRouteParams(array('ID' => 1));
        $result = $this->controller->view($request);

        $this->assertEquals($result['FAQ']->Question, 'question 1');
        $this->assertEquals($result['FAQ']->Answer, 'Milkyway chocolate bar');

        $request = new SS_HTTPRequest('GET', 'faq-page-1/view/2');
        $request->setRouteParams(array('ID' => 2));
        $result = $this->controller->view($request);

        $this->assertEquals($result['FAQ']->Question, 'No imagination question');
        $this->assertEquals($result['FAQ']->Answer, '42');
    }

    /**
     * Test search results
     */
    public function testSearch()
    {
        Phockito::include_hamcrest();
        $faq = FAQ::create(array('Question' => 'question 1', 'Answer' => 'answer 1'));
        $result = new ArrayList();
        $result->push($faq);
        $mockResponse = array();
        $mockResponse['Matches'] = new PaginatedList($result);
        $mockResponse['Suggestion'] = 'suggestion text';

        // testing good response, get one search result
        $spy = Phockito::spy('FAQPage_Controller');
        Phockito::when($spy)->getSearchQuery(anything())->return(new SearchQuery());
        Phockito::when($spy)->doSearch(anything(), anything(), anything())->return(new ArrayData($mockResponse));
        $response = $spy->search();
        $this->assertTrue($response['SearchSuggestion']['Suggestion'] === $mockResponse['Suggestion']);

        // testing error with solr
        $spy1 = Phockito::spy('FAQPage_Controller');
        Phockito::when($spy1)->getSearchQuery(anything())->return(new SearchQuery());
        Phockito::when($spy1)->doSearch(anything(), anything(), anything())->throw(new Exception("Some error"));
        $response = $spy1->search();
        $this->assertTrue($response['SearchError'] === true);
    }

    /**
     * When Single Page limit set, should get limit set of results and no pagination
     */
    public function testSinglePageLimit()
    {
        Phockito::include_hamcrest();
        $result = new ArrayList();
        $result->push(FAQ::create(array('Question' => 'question 1', 'Answer' => 'answer 1')));
        $result->push(FAQ::create(array('Question' => 'question 2', 'Answer' => 'answer 2')));
        $result->push(FAQ::create(array('Question' => 'question 3', 'Answer' => 'answer 3')));
        $result->push(FAQ::create(array('Question' => 'question 4', 'Answer' => 'answer 4')));
        $mockResponse = array();
        $mockResponse['Matches'] = new PaginatedList($result);
        $mockResponse['Suggestion'] = 'suggestion text';

        // testing total items are equal to set in _page, and there's no more than one page in pagination
        $spy = Phockito::spy('FAQPage_Controller', $this->_page);
        Phockito::when($spy)->getSearchQuery(anything())->return(new SearchQuery());
        Phockito::when($spy)->doSearch(anything(), anything(), anything())->return(new ArrayData($mockResponse));
        $response = $spy->search();
        $this->assertTrue($response['SearchResults']->getTotalItems() === 2);
        $this->assertFalse($response['SearchResults']->MoreThanOnePage());
    }

    /**
     * Featured FAQs should not display on frontend if not in the selected category
     * If no category selected, display everything
     */
    public function testFilterFeaturedFAQs()
    {
        // no category selected on FAQPage, show every featured FAQ
        $featured = $this->_page->FilterFeaturedFAQs();
        $this->assertTrue(count($featured) == count($this->_page->FeaturedFAQs()));

        // category selected, only display one
        $featured2 = $this->_page2->FilterFeaturedFAQs();
        $this->assertTrue(count($featured2) == 1);
    }

    /**
     * getSelectedIDs should pull all of the ids of the passed category, and any descendants added to the page.
     */
    public function testGetSelectedIDs()
    {
        $CategoryID = $this->objFromFixture('TaxonomyTerm', 'Vehicles')->getTaxonomy()->ID;
        $filterCategory = $this->_page2_controller->Categories()->filter('ID', $CategoryID)->first();
        $selectedChildIDS = $this->_page2_controller->getSelectedIDs($filterCategory);
        $this->assertTrue($selectedChildIDS == array(1, 2, 4));
    }
}
