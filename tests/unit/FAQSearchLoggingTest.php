<?php
/**
 * Tests basic functionality of the FAQ search log.
 */
use SilverStripe\View\ArrayData;

class FAQSearchLoggingTest extends FunctionalTest
{
    protected static $fixture_file = 'FAQSearchLoggingTest.yml';

    public function setUp()
    {
        parent::setUp();

        $this->admin = $this->objFromFixture('Member', 'admin');

        $this->loginAs($this->admin);
        $this->faqPage = $this->objFromFixture('FAQPage', 'faq');
        $this->faqPage->doPublish();
        $this->logOut();
    }

    /**
     * Session is started when the FAQ landing page is hit
     */
    public function testSessionStart()
    {
        $this->assertNull(Session::get('FAQPage'));
        $this->get(Director::makeRelative($this->faqPage->Link()));
        $this->assertTrue(Session::get('FAQPage'));
    }

    /**
     * Tracking IDs are separated correctly.
     */
    public function testTrackingIDs()
    {
        $controller = FAQPage_Controller::create();

        $ids = $controller->getTrackingIDs('5_32');
        $this->assertEquals($ids, array(
            'trackingSearchID' => 5,
            'trackingResultsID' => 32
        ));

        $ids = $controller->getTrackingIDs('5_');
        $this->assertEquals($ids, array(
            'trackingSearchID' => 5,
            'trackingResultsID' => null
        ));

        $ids = $controller->getTrackingIDs('_32');
        $this->assertEquals($ids, array(
            'trackingSearchID' => null,
            'trackingResultsID' => 32
        ));
    }

    /**
     * Search and results set logs generated for session when search is performed.
     */
    public function testSearchTracking()
    {
        Phockito::include_hamcrest();

        // Need to set session ID explicitly for running tests in CLI
        $sessID = uniqid();
        session_id($sessID);

        $mockResponse = array(
            'Matches' => FAQSearchIndex_PaginatedList::create(ArrayList::create(array(
                $this->objFromFixture('FAQ', 'one')
            ))),
            'Suggestion' => 'suggestion text'
        );

        $spy = Phockito::spy('FAQPage_Controller');
        Phockito::when($spy)->getSearchQuery(anything())->return(new SearchQuery());
        Phockito::when($spy)->doSearch(anything(), anything(), anything())->return(new ArrayData($mockResponse));

        $response = $spy->search();
        $searches = FAQSearch::get()->sort('"Created" ASC');

        $this->assertEquals($searches->count(), 2);
        // Session IDs are a little tricky in the CLI environment so we just check that they are set
        $this->assertTrue($searches->last()->SessionID != null);

        $results = FAQResults::get();
        $this->assertEquals($results->count(), 2);
        $this->assertEquals($results->last()->SearchID, $searches->last()->ID);
        $this->assertTrue($searches->last()->SessionID != null);
    }

    /**
     * Search logs are reused for exactly the same search for the same user within the same search window.
     */
    public function testSearchTrackingDuplicates()
    {
        Phockito::include_hamcrest();

        // Need to set session ID explicitly for running tests in CLI
        $sessID = uniqid();
        session_id($sessID);

        $mockRequest = new SS_HTTPRequest('GET', '/', array(
            FAQPage_Controller::$search_term_key => 'test terms'
        ));
        $mockResponse = array(
            'Matches' => FAQSearchIndex_PaginatedList::create(ArrayList::create(array(
                $this->objFromFixture('FAQ', 'one')
            ))),
            'Suggestion' => 'suggestion text'
        );
        $spy = Phockito::spy('FAQPage_Controller');
        $spy->setRequest($mockRequest);
        Phockito::when($spy)->getSearchQuery(anything())->return(new SearchQuery());
        Phockito::when($spy)->doSearch(anything(), anything(), anything())->return(new ArrayData($mockResponse));

        $response = $spy->search();
        $searches = FAQSearch::get();

        $this->assertEquals($searches->count(), 2);

        $response = $spy->search();
        $searches = FAQSearch::get();

        // Still only 2 search logs generated as the same search for 'test terms' performed twice in same session
        $this->assertEquals($searches->count(), 2);

        session_id(uniqid());

        $response = $spy->search();
        $searches = FAQSearch::get();

        // Same search for 'test terms' performed twice in different sessions
        $this->assertEquals($searches->count(), 3);
    }

    /**
     * New article view log is created and linked to the search and results set log items with the same session.
     */
    public function testViewTracking()
    {
        // Need to set session ID explicitly for running tests in CLI
        $search = FAQSearch::get()->first();
        session_id($search->SessionID);

        $faq = $this->objFromFixture('FAQ', 'one');
        $link = Director::makeRelative($this->faqPage->Link('view') . '/' . $faq->ID);

        $request = new SS_HTTPRequest('GET', $link, array(
            't' => '1_1'
        ));
        // Need to set params in the request explicitly apparently
        $request->setRouteParams(array(
            'ID' => 1
        ));
        $controller = new FAQPage_Controller();

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 0);

        $controller->view($request);

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 1);

        $log = $articles->last();
        $this->assertEquals($log->SessionID, $search->SessionID);
        $this->assertEquals($log->SearchID, 1);
        $this->assertEquals($log->ResultSetID, 1);
    }

    /**
     * Viewing articles more than once in the session for the same search and results set does not result in
     * multiple view rows for the article.
     */
    public function testSubsequentViewTracking()
    {
        // Need to set session ID explicitly for running tests in CLI
        $search = FAQSearch::get()->first();
        session_id($search->SessionID);

        $faq = $this->objFromFixture('FAQ', 'one');
        $link = Director::makeRelative($this->faqPage->Link('view') . '/' . $faq->ID);

        $request = new SS_HTTPRequest('GET', $link, array(
            't' => '1_1'
        ));
        // Need to set params in the request explicitly apparently
        $request->setRouteParams(array(
            'ID' => 1
        ));
        $controller = new FAQPage_Controller();

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 0);

        $controller->view($request);

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 1);

        $log = $articles->last();
        $this->assertEquals($log->SessionID, $search->SessionID);
        $this->assertEquals($log->SearchID, 1);
        $this->assertEquals($log->ResultSetID, 1);

        // View the article again
        $controller->view($request);

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 1);
    }

    /**
     * Viewing the tracking URL with a different session does not result in article log being generated.
     * Prevents sharing a link with tracking GET param and having logs generated/altered.
     */
    public function testViewTrackingDifferentSession()
    {
        // View with different session ID is not tracked
        $search = FAQSearch::get()->first();
        $sessionID = 6543219;
        $this->assertTrue($search->SessionID != $sessionID);
        session_id($sessionID);

        $faq = $this->objFromFixture('FAQ', 'one');
        $link = Director::makeRelative($this->faqPage->Link('view') . '/' . $faq->ID);

        $request = new SS_HTTPRequest('GET', $link, array(
            't' => '1_1'
        ));
        // Need to set params in the request explicitly apparently
        $request->setRouteParams(array(
            'ID' => 1
        ));
        $controller = new FAQPage_Controller();

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 0);

        $controller->view($request);

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 0);
    }

    /**
     * Pagination through results creates more results set views logs.
     */
    public function testPaginationTracking()
    {
        // Same session, different page of results
        $search = FAQSearch::get()->first();
        session_id($search->SessionID);

        $mockResponse = array(
            'Matches' => FAQSearchIndex_PaginatedList::create(ArrayList::create(array(
                $this->objFromFixture('FAQ', 'one')
            ))),
            'Suggestion' => 'suggestion text'
        );

        $spy = Phockito::spy('FAQPage_Controller');
        Phockito::when($spy)->getSearchQuery(anything())->return(new SearchQuery());
        Phockito::when($spy)->doSearch(anything(), anything(), anything())->return(new ArrayData($mockResponse));
        Phockito::when($spy)->getTrackingIDs(anything())->return(array(
            'trackingSearchID' => 1,
            'trackingResultsID' => null
        ));

        $request = new SS_HTTPRequest('GET', $this->faqPage->Link(), array(
            't' => '1_',
            'start' => 20
        ));
        $spy->setRequest($request); //Does this work? Have mocked getTrackingIDs() anyway

        $results = FAQResults::get();
        $this->assertEquals($results->count(), 1);

        // Search is the action that is eventually hit for pagination
        $spy->search();

        $results = FAQResults::get();
        $this->assertEquals($results->count(), 2);
        $this->assertEquals($results->last()->SearchID, $search->ID);
        $this->assertEquals($results->last()->SessionID, $search->SessionID);
    }

    /**
     * Pagination links for a different session do not trigger logs or update another sessions logs.
     * Prevents sharing a link with tracking GET param and having logs generated/altered.
     */
    public function testPaginationTrackingDifferentSession()
    {
        // Different session, page of results for logged search
        $search = FAQSearch::get()->first();
        $sessionID = 6543219;
        $this->assertTrue($search->SessionID != $sessionID);
        session_id($sessionID);

        $mockResponse = array(
            'Matches' => FAQSearchIndex_PaginatedList::create(ArrayList::create(array(
                $this->objFromFixture('FAQ', 'one')
            ))),
            'Suggestion' => 'suggestion text'
        );

        $spy = Phockito::spy('FAQPage_Controller');
        Phockito::when($spy)->getSearchQuery(anything())->return(new SearchQuery());
        Phockito::when($spy)->doSearch(anything(), anything(), anything())->return(new ArrayData($mockResponse));
        Phockito::when($spy)->getTrackingIDs(anything())->return(array(
            'trackingSearchID' => 1,
            'trackingResultsID' => null
        ));

        $request = new SS_HTTPRequest('GET', $this->faqPage->Link(), array(
            't' => '1_',
            'start' => 20
        ));
        $spy->setRequest($request); //Does this work? Have mocked getTrackingIDs() anyway

        $searches = FAQSearch::get();
        $this->assertEquals($searches->count(), 1);

        $results = FAQResults::get();
        $this->assertEquals($results->count(), 1);

        // Search is the action that is eventually hit for pagination
        $spy->search();

        $searches = FAQSearch::get();
        $this->assertEquals($searches->count(), 1);

        $results = FAQResults::get();
        $this->assertEquals($results->count(), 1);
    }

    /**
     * Different session to the current log does not get the Rating form.
     * Prevents sharing a link with tracking GET param and having logs generated/altered.
     */
    public function testRatingFormDifferentSession()
    {
        $search = FAQSearch::get()->first();
        $sessionID = 6543219;
        $this->assertTrue($search->SessionID != $sessionID);

        $faq = $this->objFromFixture('FAQ', 'one');
        $link = Director::makeRelative($this->faqPage->Link('view') . '/' . $faq->ID);

        $request = new SS_HTTPRequest('GET', $link, array(
            't' => '1_1'
        ));
        // Need to set params in the request explicitly apparently
        $request->setRouteParams(array(
            'ID' => 1
        ));
        $controller = new FAQPage_Controller();

        session_id($search->SessionID);
        $render = $controller->view($request);
        $this->assertEquals(get_class($render['FAQRatingForm']), 'Form');

        session_id($sessionID);
        $render = $controller->view($request);
        $this->assertEquals($render['FAQRatingForm'], null);
    }

    /**
     * Rating or leaving a comment updates the log for an article.
     */
    public function testRating()
    {
        $search = FAQSearch::get()->first();

        $faq = $this->objFromFixture('FAQ', 'one');
        $link = Director::makeRelative($this->faqPage->Link('view') . '/' . $faq->ID);

        $request = new SS_HTTPRequest('GET', $link, array(
            't' => '1_1'
        ));
        // Need to set params in the request explicitly apparently
        $request->setRouteParams(array(
            'ID' => 1
        ));
        $controller = new FAQPage_Controller();

        session_id($search->SessionID);
        $render = $controller->view($request);

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 1);

        $data = array(
            'ID' => $articles->last()->ID,
            'Useful' => 'Y',
            'Comment' => 'Very useful article.',
            'MobilePhones_1' => null
        );
        $form = $render['FAQRatingForm'];
        $request = new SS_HTTPRequest('GET', $this->faqPage->Link('RatingForm'));

        $controller->rate($data, $form, $request);
        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 1);

        $log = $articles->last();
        $this->assertEquals($log->SessionID, $search->SessionID);
        $this->assertEquals($log->SearchID, 1);
        $this->assertEquals($log->ResultSetID, 1);
        $this->assertEquals($log->FAQID, $faq->ID);
        $this->assertEquals($log->Useful, $data['Useful']);
        $this->assertEquals($log->Comment, $data['Comment']);
    }

    /**
     * When session is different there is no change to the article log when rating submitted.
     */
    public function testRatingDifferentSession()
    {
        $search = FAQSearch::get()->first();
        $sessionID = 6543219;
        $this->assertTrue($search->SessionID != $sessionID);

        $faq = $this->objFromFixture('FAQ', 'one');
        $link = Director::makeRelative($this->faqPage->Link('view') . '/' . $faq->ID);

        $request = new SS_HTTPRequest('GET', $link, array(
            't' => '1_1'
        ));
        // Need to set params in the request explicitly apparently
        $request->setRouteParams(array(
            'ID' => 1
        ));
        $controller = new FAQPage_Controller();

        session_id($search->SessionID);
        $render = $controller->view($request);

        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 1);
        $origLog = $articles->last();

        $data = array(
            'ID' => $articles->last()->ID,
            'Useful' => 'Y',
            'Comment' => 'Very useful article.',
            'MobilePhones_1' => null
        );
        $form = $render['FAQRatingForm'];
        $request = new SS_HTTPRequest('GET', $this->faqPage->Link('RatingForm'));

        session_id($sessionID);

        $controller->rate($data, $form, $request);
        $articles = FAQResults_Article::get();
        $this->assertEquals($articles->count(), 1);

        $log = $articles->last();
        $this->assertEquals($log->toMap(), $origLog->toMap());
    }

    /**
     * Log current member out by clearing session
     */
    private function logOut()
    {
        $this->session()->clear('loggedInAs');
    }
}
