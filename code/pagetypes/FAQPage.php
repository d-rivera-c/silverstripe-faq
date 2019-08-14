<?php
/**
 * FAQ pagetype, displays Q & A related to the page.
 * Has a custom search index to add search capabilities to the page.
 * Can live in any part of the SiteTree
 */

use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\ORM\ArrayList;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;

class FAQPage extends Page
{

    private static $db = array(
        'SinglePageLimit' => 'Int',
        'CategoriesSelectAllText' => 'Varchar(124)',
        'SearchFieldPlaceholder' => 'Varchar(124)',
        'SearchResultsSummary' => 'Varchar(255)',
        'SearchResultsTitle' => 'Varchar(255)',
        'SearchButtonText' => 'Varchar(124)',
        'NoResultsMessage' => 'Varchar(255)',
        'SearchNotAvailable' => 'Varchar(255)',
        'MoreLinkText' => 'Varchar(124)'
    );

    private static $defaults = array(
        'SinglePageLimit' => 0,
        'CategoriesSelectAllText' => 'All categories',
        'SearchFieldPlaceholder' => 'Ask us a question',
        'SearchResultsSummary' => 'Displaying %CurrentPage% of %TotalPages% pages for "%Query%"',
        'SearchResultsTitle' => 'FAQ Results',
        'SearchButtonText' => 'Search',
        'NoResultsMessage' => 'We couldn\'t find an answer to your question. Maybe try asking it in a different way, or check your spelling.',
        'SearchNotAvailable' => 'We are currently unable to search the website for you. Please try again later.',
        'MoreLinkText' => 'Read more'
    );

    private static $many_many = array(
        'FeaturedFAQs' => 'FAQ',
        'Categories' => 'SilverStripe\Taxonomy\TaxonomyTerm'
    );

    private static $many_many_extraFields = array(
        'FeaturedFAQs' => array(
            'SortOrder' => 'Int'
        )
    );

    private static $singular_name = 'FAQ Page';

    private static $description = 'FAQ search page';


    /**
     *
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // categories
        $treedropdown = new TreeMultiselectField(
            'Categories',
            'Categories to show and search for',
            'SilverStripe\Taxonomy\TaxonomyTerm'
        );
        $treedropdown->setDescription(
            'Displays FAQs with selected categories filtered. '.
            'Don\'t select any if you want to show all FAQs regardless of categories'
        );
        $treedropdown->setTreeBaseID(FAQ::getRootCategory()->ID);
        $fields->addFieldToTab(
            'Root.Main',
            $treedropdown,
            'Content'
        );

        $settings = new Tab('Settings', 'FAQ Settings');
        $fields->insertBefore($settings, 'PublishingSchedule');
        $fields->addFieldsToTab(
            'Root.Settings',
            array(
                TextField::create('SinglePageLimit')
                ->setDescription(
                    'If set higher than 0, limits results to that many and removes pagination.'
                ),
                TextField::create('CategoriesSelectAllText')
                ->setDescription('Text to appear in on the "empty" first option in the categories selector'),
                TextField::create('SearchFieldPlaceholder')
                ->setDescription('Text to appear in the search field before the user enters their question'),
                TextField::create('SearchFieldPlaceholder')
                ->setDescription('Text to appear in the search field before the user enters their question'),
                TextField::create('SearchButtonText')
                ->setDescription('Text for the search button'),
                TextField::create('SearchResultsTitle')
                ->setDescription('Title for the FAQ search results'),
                TextareaField::create('NoResultsMessage')
                ->setDescription('Text to appear when no search results are found'),
                TextareaField::create('SearchNotAvailable')
                ->setDescription('Text to appear when search functionality is not available'),
                TextField::create('MoreLinkText')
                ->setDescription('Text for the "Read more" link below each search result'),
                TextareaField::create('SearchResultsSummary')
                ->setDescription('
                    Search summary string. Replacement keys:
                    <ul>
                        <li>
                            <strong>%CurrentPage%</strong>: Current page number
                        </li>
                        <li>
                            <strong>%TotalPages%</strong>: Total page count
                        </li>
                        <li>
                            <strong>%Query%</strong>: Current search query
                        </li>
                    </ul>
                ')
            )
        );

        // Featured FAQs tab
        $FeaturedFAQsTab = new Tab('FeaturedFAQs', _t('FAQPage.FeaturedFAQs', 'Featured FAQs'));
        $fields->insertBefore($FeaturedFAQsTab, 'PublishingSchedule');

        $components = GridFieldConfig_RelationEditor::create();
        $components->removeComponentsByType('GridFieldAddNewButton');
        $components->removeComponentsByType('GridFieldEditButton');
        $components->removeComponentsByType('GridFieldFilterHeader');
        // $components->addComponent(new GridFieldSortableRows('SortOrder'));

        $dataColumns = $components->getComponentByType('GridFieldDataColumns');
        if ($dataColumns) {
            $dataColumns->setDisplayFields(array(
                'Title' => _t('FAQPage.ColumnQuestion', 'Ref.'),
                'Question' => _t('FAQPage.ColumnQuestion', 'Question'),
                'Answer.Summary' => _t('FAQPage.ColumnPageType', 'Answer'),
                'Category.Name' => _t('FAQPage.ColumnPageType', 'Category'),
            ));
        }

        /*$components->getComponentByType('GridFieldAddExistingAutocompleter')
            ->setResultsFormat('$Question');*/

        // warning for categories filtering on featured FAQs
        $differentCategories = 0;
        if ($this->Categories()->count() > 0) {
            $FAQsWithCategories = $this->FeaturedFAQs()->filter('CategoryID', $this->Categories()->column('ID'))->count();
            $totalFeaturedFAQs = $this->FeaturedFAQs()->count();
            $differentCategories = $totalFeaturedFAQs - $FAQsWithCategories;
        }

        $FeaturedFAQsCategoryNotice = '<p class="message %s">Only featured FAQs with selected categories will '.
                                      'be displayed on the site. If you have not selected a category, all of the '.
                                      'featured FAQs will be displayed.</p>';
        if ($differentCategories) {
            $FeaturedFAQsCategoryNotice = sprintf(
                '<p class="message %s">You have %d FAQs with different categories than the ones you have selected '.
                'to show on this FAQPage. These will not be displayed.</p>',
                $differentCategories ? 'bad' : '',
                $differentCategories
            );
        }

        $fields->addFieldsToTab(
            'Root.FeaturedFAQs',
            array(
                LiteralField::create(
                    'FeaturedFAQsCategoryNotice',
                    $FeaturedFAQsCategoryNotice
                ),
                GridField::create(
                    'FeaturedFAQs',
                    _t('FAQPage.FeaturedFAQs', 'Featured FAQs'),
                    $this->FeaturedFAQs(),
                    $components
                )
            )
        );

        return $fields;
    }

    /**
     * Gets Featured FAQs sorted by order. Used by template
     */
    public function FeaturedFAQs()
    {
        return $this->getManyManyComponents('FeaturedFAQs')->sort('SortOrder');
    }

    /**
     * Remove Featured FAQs that aren't in the categories selected to filter
     */
    public function FilterFeaturedFAQs()
    {
        $featured = $this->FeaturedFAQs()->toArray();
        $categories = $this->Categories()->column('ID');

        // if there's a category selected, filter
        if (count($categories) > 0) {
            foreach ($featured as $i => $feat) {
                if (!in_array($feat->CategoryID, $categories)) {
                    unset($featured[$i]);
                }
            }
        }

        return new ArrayList($featured);
    }
}

/**
 *
 */
class FAQPage_Controller extends PageController
{
    private static $allowed_actions = array(
        'view',
        'RatingForm'
    );

    /**
     * How many search results should be shown per-page?
     *
     * @var int
     */
    public static $results_per_page = 10;

    /**
     * This is the string used for the url search term variable.
     * E.g. "searchterm" in "http://mysite/faq?searchterm=this+is+a+search"
     */
    public static $search_term_key = 'q';
    public static $search_category_key = 'c';

    /**
     * We replace these keys with real data in the SearchResultsSummary before adding to the template.
     */
    public static $search_results_summary_current_page_key = '%CurrentPage%';
    public static $search_results_summary_total_pages_key = '%TotalPages%';
    public static $search_results_summary_query_key = '%Query%';

    /**
     * Solr configuration
     */
    public static $search_index_class = 'FAQSearchIndex';
    public static $classes_to_search = array(
        array(
            'class' => 'FAQ',
            'includeSubclasses' => true
        )
    );

    public function init()
    {
        parent::init();
        Requirements::javascript(FAQ_DIR .'/javascript/faq.js');
    }

    /**
     * Start a session by setting a dummy property. Sessions are essential for linking behaviour to a user.
     */
    public function startSession()
    {
        $sess = $this->getRequest()->getSession();
        if (!$sess->get('FAQPage')) {
            // Ensure that session is started for tracking behaviour, essential for linking behaviour to a user
            $sess->set('FAQPage', true);
            $sess->save($this->getRequest());
        }
    }

    /**
     * Renders the base search page if no search term is present.
     * Otherwise runs a search and renders the search results page.
     * Search action taken from FAQPage.php and modified.
     */
    public function index()
    {
        $this->startSession();
        if ($this->request->getVar(self::$search_term_key) || $this->request->getVar(self::$search_category_key)) {
            return $this->renderSearch($this->search());
        }
        return $this->render();
    }

    /**
     * Render individual view for FAQ, record the view if tracking ID is passed
     * from a search result set for example.
     *
     * @return array|SS_HTTPResponse FAQ content or 404 error if FAQ not found
     */
    public function view(HTTPRequest $request)
    {
        $this->startSession();
        $faq = FAQ::get()->filter('ID', $request->param('ID'))->first();

        if ($faq === null) {
            $this->httpError(404);
        }

        // Record the view of an article, linked to search query and results
        $sessID = session_id();
        $ratingForm = null;
        $query = null;

        if ($sessID && $request->getVar('t') && Permission::check('FAQ_IGNORE_SEARCH_LOGS') == false) {
            $trackingIDs = $this->getTrackingIDs($request->getVar('t'));

            // If there is an article log for the same article attached to the search and results set logs, reuse it
            $articleLog = FAQResults_Article::get()->filter(array(
                'SearchID' => $trackingIDs['trackingSearchID'],
                'ResultSetID' => $trackingIDs['trackingResultsID'],
                'SessionID' => $sessID,
                'FAQID' => $faq->ID
            ))->first();

            if (!$articleLog || !$articleLog->exists()) {
                // Check that the session matches before writing a new log for an article view
                $searchLog = FAQSearch::get()->filter(array(
                    'ID' => $trackingIDs['trackingSearchID'],
                    'SessionID' => $sessID
                ))->first();

                if ($searchLog && $searchLog->exists()) {
                    $articleLog = FAQResults_Article::create(array(
                        'SearchID' => $trackingIDs['trackingSearchID'],
                        'ResultSetID' => $trackingIDs['trackingResultsID'],
                        'FAQID' => $faq->ID,
                        'SessionID' => $sessID,
                    ));
                    $articleLog->write();
                }
            } else {
                $searchLog = $articleLog->Search();
            }

            // Only generate the rating form if article log exists
            if ($articleLog && $articleLog->exists()) {
                $ratingForm = $this->RatingForm();
                $ratingForm->loadDataFrom($articleLog);
            }
            if ($searchLog && $searchLog->exists()) {
                $query = $searchLog->Term;
            }
        }

        return array(
            'FAQ' => $faq,
            'FAQRatingForm' => $ratingForm,
            'Query' => $query
        );
    }

    /**
     * Search function. Called from index() if we have a search term. Record the
     * search if session exists.
     *
     * @return array Including search results as a PaginatedList.
     */
    public function search()
    {
        // limit if required by cms config
        $limit = $this->config()->results_per_page;
        if ($this->SinglePageLimit != '0') {
            $setlimit = intval($this->SinglePageLimit);
            if ($setlimit != 0 && is_int($setlimit)) {
                $limit = $setlimit;
            }
        }

        $start = $this->request->getVar('start') or 0;
        $results = new ArrayList();
        $suggestionData = null;
        $keywords = $this->request->getVar(self::$search_term_key) or '';
        $renderData = array();

        // get search query
        $query = $this->getSearchQuery($keywords);

        try {
            $searchResult = $this->doSearch($query, $start, $limit);
            $results = $searchResult->Matches;

            // Log the search query and result set for the query, link them to a session (ensure session is started)
            $sessID = session_id();
            if ($sessID && Permission::check('FAQ_IGNORE_SEARCH_LOGS') == false) {

                $trackingSearchID = $this->findTrackingID($this->request);

                // If the tracking ID is set then use existing search log if the log is for the same session
                if ($trackingSearchID) {
                    $searchLog = FAQSearch::get()->filter(array(
                        'ID' => $trackingSearchID,
                        'SessionID' => $sessID
                    ))->first();
                    $searchLogID = ($searchLog && $searchLog->exists()) ? $searchLog->ID : null;
                } else {

                    $searchLog = FAQSearch::create(array(
                        'SessionID' => $sessID,
                        'Term' => $keywords,
                        'TotalResults' => $results->getTotalItems()
                    ));

                    // Optionally save some referrer data
                    $controller = Controller::curr();
                    if ($controller instanceof ContentController) {
                        $referrer = Controller::curr()->data();
                        if ($referrer) {
                            $searchLog->update(array(
                                'ReferrerID' => $referrer->ID,
                                'ReferrerType' => $referrer->ClassName,
                                'ReferrerURL' => $referrer->Link()
                            ));
                        }
                    }
                    $searchLogID = $searchLog->write();
                }

                // A valid search log ID exists for this session, a new results set log
                // is created, results logs are not re-used, each page of results viewed is a new log entry
                if ($searchLogID) {
                    $resultsLogID = FAQResults::create(array(
                        'SearchID' => $searchLogID,
                        'ArticleSet' => json_encode(array_keys($results->map()->toArray())),
                        'SetSize' => count($results->map()),
                        'SessionID' => $sessID,
                    ))->write();
                }

                // Loop through page of results and append the full tracking code to each article link
                if ($searchLogID && $resultsLogID) {
                    foreach ($results as $result) {
                        $result->trackingID = $searchLogID . '_' . $resultsLogID;
                    }
                }

                // Append partial tracking code to each pagination link
                $results->setTrackingURL($this->request, $searchLogID . '_');
            }

            // if the suggested query has a trailing '?' then hide the hardcoded one from 'Did you mean <Suggestion>?'
            $showTrailingQuestionmark = !preg_match('/\?$/', $searchResult->Suggestion);

            $suggestionData = array(
                'ShowQuestionmark' => $showTrailingQuestionmark,
                'Suggestion' => $searchResult->Suggestion,
                'SuggestionNice' => $searchResult->SuggestionNice,
                'SuggestionQueryString' => $this->makeQueryLink($searchResult->SuggestionQueryString)
            );
            $renderData = $this->parseSearchResults(
                $results,
                $suggestionData,
                $keywords
            );

        } catch (Exception $e) {
            $renderData = array('SearchError' => true);
            Injector::inst()->get(LoggerInterface::class)->warning($e);
        }

        return $renderData;
    }

    /**
     * Helper to extract tracking IDs from a get param in the format id_id.
     *
     * @param  string $id ID from GET param in format id_id.
     * @return array      Array with the IDs extracted
     */
    public function getTrackingIDs($id)
    {
        $ids = array();
        $parts = explode('_', $id);
        $ids['trackingSearchID'] = (isset($parts[0])) ? $parts[0] : null;
        $ids['trackingResultsID'] = (isset($parts[1])) ? $parts[1] : null;
        return $ids;
    }

    /**
     * Find the tracking ID to use when updating the search log.
     *
     * @param  SS_HTTPRequest $request Current request
     * @return int                     The search log ID for the current search
     */
    public function findTrackingID(HTTPRequest $request) {

        // Use the GET param first
        $trackingIDs = $this->getTrackingIDs($request->getVar('t'));
        if ($trackingIDs['trackingSearchID'] != null) {
            return $trackingIDs['trackingSearchID'];
        }

        // Check if a search within this session exists for the same query and use that tracking ID
        // to prevent multiple search logs when back button used
        $log = FAQSearch::get()->filter(array(
            'SessionID' => session_id(),
            'Term' => $request->getVar(self::$search_term_key)
        ))->first();

        if ($log && $log->exists()) {
            return $log->ID;
        }
    }

    /**
     * Builds a search query from a give search term.
     *
     * @return SearchQuery
     */
    protected function getSearchQuery($keywords)
    {
        $categoryIDs = array();
        $categoryFilterID = $this->request->requestVar(self::$search_category_key);

        $categories = $this->Categories();
        if ($categories->count() == 0) {
            $categories = FAQ::getRootCategory()->Children();
        }

        $filterCategory = $categories->filter('ID', $categoryFilterID)->first();

        $categoryIDs = array();
        if ($filterCategory && $filterCategory->exists()) {
            $categoryIDs = $this->getSelectedIDs(array($filterCategory));
        } else {
            $categoryIDs = $this->Categories()->column('ID');
        }

        $query = new SearchQuery();
        $query->classes = self::$classes_to_search;
        if (count($categoryIDs) > 0) {
            $query->filter('FAQ_Category_ID', array_filter($categoryIDs, 'intval'));
        }
        $query->addSearchTerm($keywords);

        // Artificially lower the amount of results to prevent too high resource usage.
        // on subsequent canView check loop.
        $query->setLimit(100);

        return $query;
    }

    /**
     * Performs a search against the configured Solr index from a given query, start and limit.
     * Returns $result and $suggestionData - both of which are passed by reference.
     */
    public function doSearch($query, $start, $limit)
    {
        $params = array(
            'defType' => 'edismax',
            'hl' => 'true',
            'spellcheck' => 'true',
            'spellcheck.collate' => 'true'
        );

        // add optional dictionary
        if($searchParams = Config::inst()->get('FAQSearchIndex', 'search_params')) {
            $params = array_merge($params, $searchParams);
            //$params["spellcheck.dictionary"] = $dictionary;
        }

        $result = singleton(self::$search_index_class)->search(
            $query,
            $start,
            $limit,
            $params
        );

        return $result;
    }

    /**
     * Renders the search template from a given Solr search result, suggestion and search term.
     *
     * @return array Including search results as a PaginatedList.
     */
    protected function parseSearchResults($results, $suggestion, $keywords)
    {
        $searchSummary = '';

        // Clean up the results.
        foreach ($results as $result) {
            if (!$result->canView()) {
                $results->remove($result);
            }
        }

        // Generate links
        $searchURL = Director::absoluteURL($this->makeQueryLink(urlencode($keywords)));
        $rssUrl = Controller::join_links($searchURL, '?format=rss');
        RSSFeed::linkToFeed($rssUrl, 'Search results for "' . $keywords . '"');

        /**
         * generate the search summary using string replacement
         * to support translation and max configurability
         */
        if ($results->CurrentPage) {
            $searchSummary = _t('FAQPage.SearchResultsSummary', $this->SearchResultsSummary);
            $keys = array(
                self::$search_results_summary_current_page_key,
                self::$search_results_summary_total_pages_key,
                self::$search_results_summary_query_key
            );
            $values = array(
                $results->CurrentPage(),
                $results->TotalPages(),
                $keywords
            );
            $searchSummary = str_replace($keys, $values, $searchSummary);
        }

        $renderData = array(
            'SearchResults' => $results,
            'SearchSummary' => $searchSummary,
            'SearchSuggestion' => $suggestion,
            'Query' => DBField::create_field('Text', $keywords),
            'SearchLink' => DBField::create_field('Text', $searchURL),
            'RSSLink' => DBField::create_field('Text', $rssUrl)
        );

        // remove pagination if required by cms config
        if ($this->SinglePageLimit != '0') {
            $setlimit = intval($this->SinglePageLimit);
            $renderData['SearchResults']->setTotalItems($setlimit);
        }

        return $renderData;
    }

    /**
     * Sets a template and displays data
     */
    protected function renderSearch($renderData)
    {
        $templates = array('FAQPage_results', 'Page');
        if ($this->request->getVar('format') == 'rss') {
            array_unshift($templates, 'Page_results_rss');
        }
        if ($this->request->getVar('format') == 'atom') {
            array_unshift($templates, 'Page_results_atom');
        }

        return $this->customise($renderData)->renderWith($templates);
    }

    /**
     * Makes a query link for the current page from a search term
     * Returns a URL with an empty search term if no query is passed
     *
     * @return String  The URL for this search query
     */
    protected function makeQueryLink($query = null)
    {
        $query = gettype($query) === 'string' ? $query : '';
        return Controller::join_links(
            Director::baseURL(),
            $this->Link(),
            sprintf('?%s=', self::$search_term_key)
        ) . $query;
    }

    /**
     * Deep recursion of a category taxonomy term and its children. Builds array of categoriy IDs for searching.
     */
    public function getSelectedIDs($categoryTerms)
    {
        $IDsAccumulator = array();
        foreach ($categoryTerms as $category) {
            $hasNoCategories = $this->Categories()->count() === '0';
            $existsOnPage = $this->Categories()->filter('ID', $category->ID)->exists();

            // if the category exists on the page, add it to the IDsAccumulator
            if ($existsOnPage || $hasNoCategories) {
                $IDsAccumulator[] = $category->ID;
            }

            // if there are children getSelectedIDs on them as well.
            $children = $category->Children();
            if ($children->count() !== 0) {
                $IDsAccumulator = array_merge($IDsAccumulator, $this->getSelectedIDs($children));
            }
        }
        return $IDsAccumulator;
    }

    /**
     * Deep recursion of category taxonomy terms. Builds array of categories for template.
     */
    protected function getCategoriesForTemplate($categoryTerms, $depth = 0)
    {
        $categoriesAccumulator = new ArrayList(array());
        // id of current filter category
        $categoryFilterID = $this->request->requestVar(self::$search_category_key);

        foreach ($categoryTerms as $category) {
            $isNotBaseCategory = $category->ID !== FAQ::getRootCategory()->ID;
            $hasNoCategories = $this->Categories()->count() === '0';
            $existsOnPage = $this->Categories()->filter('ID', $category->ID)->exists();
            // don't increment the tree depth if the parent isn't being added to this page
            $depthIncrement = $existsOnPage || $hasNoCategories ? 1 : 0;

            if ($isNotBaseCategory && ($existsOnPage || $hasNoCategories)) {
                // generate the name, along with correct spacing for this depth and bullets
                $namePrefix = $category->Name;
                $namePrefix = ($depth === 0) ? $namePrefix : ('&bull;&nbsp;' . $namePrefix);
                $namePrefix = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth) . $namePrefix;

                $formattedCategoryArray = array(
                    'Name' => $namePrefix,
                    'ID' => $category->ID,
                    'Selected' => (String)$categoryFilterID === (String)$category->ID
                );

                $categoriesAccumulator->push(new ArrayData($formattedCategoryArray));
            }


            // if there are children getCategoriesForTemplate on them as well. Increment depth.
            $children = $category->Children();
            if ($children->count() !== 0) {
                $categoriesAccumulator->merge($this->getCategoriesForTemplate($children, $depth + $depthIncrement));
            }
        }
        return $categoriesAccumulator;
    }

    /**
     * Expose variables to the template.
     */
    public function SelectorCategories()
    {
        $baseCategories = array(FAQ::getRootCategory());
        $categories = $this->getCategoriesForTemplate($baseCategories);
        return $categories;
    }

    public function SearchTermKey()
    {
        return self::$search_term_key;
    }

    public function SearchCategoryKey()
    {
        return self::$search_category_key;
    }

    /**
     * Translators
     */
    public function CategoriesSelectAllText()
    {
        return _t('FAQPage.CategoriesSelectAllText', $this->CategoriesSelectAllText);
    }

    public function SearchFieldPlaceholder()
    {
        return _t('FAQPage.SearchFieldPlaceholder', $this->SearchFieldPlaceholder);
    }

    public function SearchButtonText()
    {
        return _t('FAQPage.SearchButtonText', $this->SearchButtonText);
    }

    public function NoResultsMessage()
    {
        return _t('FAQPage.NoResultsMessage', $this->NoResultsMessage);
    }

    public function SearchResultsTitle()
    {
        return _t('FAQPage.SearchResultsTitle', $this->SearchResultsTitle);
    }

    public function SearchResultMoreLink()
    {
        return _t('FAQPage.SearchResultMoreLink', $this->MoreLinkText);
    }

    /**
     * Rating form, best to only display this form if a tracking ID can be set e.g: avoid using $RatingForm directly in
     * the template @see view() above for example usage.
     *
     * @return Form The rating form
     */
    public function RatingForm() {

        // Only show the rating form for users whose tracking ID matches session
        $fields = FieldList::create(
            OptionsetField::create('Useful', 'Rating', array(
                'Y' => 'Helpful',
                'N' => 'Unhelpful'
            )),
            TextareaField::create('Comment'),
            LiteralField::create('CharCounter', '<div class="faq__char-counter pull-right"></div>'),
            HiddenField::create('ID', '')
        );
        $actions = FieldList::create(
            FormAction::create('rate', 'Submit')
        );
        $form = Form::create(
            $this,
            'RatingForm',
            $fields,
            $actions
        )->addExtraClass('faq__rating');

        $this->extend('updateRatingForm', $form);

        return $form;
    }

    /**
     * Rate an article, this will find the article log to update and write to it.
     *
     * @param  Array          $data    Submitted form data
     * @param  Form           $form    Submitted form object
     * @param  SS_HTTPRequest $request Request
     * @return HTTPResponse            Redirects back
     */
    public function rate(Array $data, Form $form, SS_HTTPRequest $request) {

        // If the session and matches for the article log, then add rating/comment
        $updated = false;
        $articleLogID = (int) $data['ID'];
        $sessID = session_id();
        if ($sessID && $articleLogID) {
            $articleLog = FAQResults_Article::get()->filter(array(
                'ID' => $articleLogID,
                'SessionID' => $sessID
            ))->first();

            if ($articleLog && $articleLog->exists()) {
                $updated = $articleLog->update(array(
                    'Comment' => $data['Comment'],
                    'Useful' => $data['Useful']
                ))->write();

                if ($updated) {
                    $form->sessionMessage('Thank you for your feedback.', 'good');
                }
            }
        }
        if (!$updated) {
            $form->sessionMessage('Sorry, your feedback could not be submitted.', 'bad');
        }

        if (!Director::is_ajax()) {
            $this->redirectBack();
        } else {
            //TODO: AJAX response here
        }
    }
}
