<?php

namespace SilverStripe\Faq\Controllers;

use PageController;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Faq\PageTypes\FAQPage;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Faq\Search\FAQSearchIndex;
use SilverStripe\Faq\Model\FAQ;
use SilverStripe\View\ArrayData;

/**
 *
 */
class FAQPageController extends PageController
{
    /**
     * How many search results should be shown per-page?
     *
     * @var int
     */
    public static $results_per_page = 10;
    /**
     * This is the string used for the url search term variable.
     * E.g. "searchterm" in "http://mysite/faq?searchterm=this+is+a+search"
     *
     * @var string
     */
    public static $search_term_key = 'q';

    /**
     * @var string
     */
    public static $search_category_key = 'c';

    /**
     * @var string
     */
    public static $search_results_summary_current_page_key = '%CurrentPage%';

    // We replace these keys with real data in the SearchResultsSummary before adding to the template.
    /**
     * @var string
     */
    public static $search_results_summary_total_pages_key = '%TotalPages%';

    /**
     * @var string
     */
    public static $search_results_summary_query_key = '%Query%';

    /**
     * @var array
     */
    private static $allowed_actions = ['view'];

    /**
     * Renders the base search page if no search term is present.
     * Otherwise runs a search and renders the search results page.
     * Search action taken from FAQPage.php and modified.
     */
    public function index()
    {
        if ($this->request->getVar(self::$search_term_key) || $this->request->getVar(self::$search_category_key)) {
            return $this->renderSearch($this->search());
        }

        return $this->render();
    }

    /**
     * Sets a template and displays data
     */
    protected function renderSearch($renderData)
    {
        $templates = ['FAQPage_results', 'Page'];
        if ($this->request->getVar('format') == 'rss') {
            array_unshift($templates, 'Page_results_rss');
        }
        if ($this->request->getVar('format') == 'atom') {
            array_unshift($templates, 'Page_results_atom');
        }

        return $this->customise($renderData)->renderWith($templates);
    }

    /**
     * Search function. Called from index() if we have a search term.
     *
     * @return array search results template.
     */
    public function search()
    {
        // limit if required by cms config
        $limit = $this->config()->results_per_page;
        if ($this->data()->SinglePageLimit != '0') {
            $setlimit = intval($this->data()->SinglePageLimit);
            if ($setlimit != 0 && is_int($setlimit)) {
                $limit = $setlimit;
            }
        }

        $start = $this->request->getVar('start') or 0;
        $suggestionData = null;
        $keywords = $this->request->getVar(self::$search_term_key) or '';

        // get search query
        $query = $this->getSearchQuery($keywords);

        try {
            $searchResult = $this->doSearch($query, $start, $limit);
            $results = $searchResult->Matches;

            // if the suggested query has a trailing '?' then hide the hardcoded one from 'Did you mean <Suggestion>?'
            $showTrailingQuestionmark = !preg_match('/\?$/', $searchResult->Suggestion);

            $suggestionData = [
                'ShowQuestionmark'      => $showTrailingQuestionmark,
                'Suggestion'            => $searchResult->Suggestion,
                'SuggestionNice'        => $searchResult->SuggestionNice,
                'SuggestionQueryString' => $this->makeQueryLink($searchResult->SuggestionQueryString),
            ];
            $renderData = $this->parseSearchResults($results, $suggestionData, $keywords);
        } catch (\Exception $e) {
            $renderData = ['SearchError' => true];
            Injector::inst()->get(LoggerInterface::class)->warning($e);
        }

        return $renderData;
    }

    /**
     * Builds a search query from a give search term.
     * @param array $keywords
     *
     * @return SearchQuery
     */
    protected function getSearchQuery($keywords)
    {
        $categoryFilterID = $this->request->requestVar(self::$search_category_key);

        $categories = $this->data()->Categories(); 
        if ($categories->count() == 0) {
            $categories = FAQ::getRootCategory()->Children();
        }

        $filterCategory = $categories->filter('ID', $categoryFilterID)->first();

        if ($filterCategory && $filterCategory->exists()) {
            $categoryIDs = $this->getSelectedIDs([$filterCategory]);
        } else {
            $categoryIDs = $this->data()->Categories()->column('ID');
        }

        $query = SearchQuery::create()
            ->addSearchTerm($keywords);

        if (count($categoryIDs) > 0) {
            $query->addFilter('SilverStripe\\\Faq\\\Model\\\FAQ_Category_ID', array_filter($categoryIDs, 'intval'));
        }

        // Artificially lower the amount of results to prevent too high resource usage.
        // on subsequent canView check loop.
        $query->setLimit(100);

        return $query;
    }

    /**
     * Deep recursion of a category taxonomy term and its children. Builds array of categoriy IDs for searching.
     *
     * @param  array $categoryTerms
     *
     * @return array
     */
    public function getSelectedIDs($categoryTerms)
    {
        $IDsAccumulator = [];
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
     * Performs a search against the configured Solr index from a given query, start and limit.
     * Returns $result and $suggestionData - both of which are passed by reference.
     *
     * @param $query
     * @param $start
     * @param $limit
     *
     * @return FAQSearchIndex
     */
    public function doSearch($query, $start, $limit)
    {
        $result = FAQSearchIndex::singleton()->search($query,
            $start,
            $limit,
            [
                'defType'            => 'edismax',
                'hl'                 => 'true',
                'spellcheck'         => 'true',
                'spellcheck.collate' => 'true',
            ]
        );

        return $result;
    }

    /**
     * Makes a query link for the current page from a search term
     * Returns a URL with an empty search term if no query is passed
     * @param string $query
     *
     * @return string  The URL for this search query
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
     * Renders the search template from a given Solr search result, suggestion and search term.
     *
     * @param $results
     * @param $suggestion
     * @param $keywords
     *
     * @return array search results template.
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
            $searchSummary = _t(FAQ::class . '.SearchResultsSummary', $this->SearchResultsSummary);
            $keys = [
                self::$search_results_summary_current_page_key,
                self::$search_results_summary_total_pages_key,
                self::$search_results_summary_query_key,
            ];
            $values = [
                $results->CurrentPage(),
                $results->TotalPages(),
                $keywords,
            ];
            $searchSummary = str_replace($keys, $values, $searchSummary);
        }

        $renderData = [
            'SearchResults'    => $results,
            'SearchSummary'    => $searchSummary,
            'SearchSuggestion' => $suggestion,
            'Query'            => DBField::create_field('Text', $keywords),
            'SearchLink'       => DBField::create_field('Text', $searchURL),
            'RSSLink'          => DBField::create_field('Text', $rssUrl),
        ];

        // remove pagination if required by cms config
        if ($this->data()->SinglePageLimit != '0') {
            $setlimit = intval($this->data()->SinglePageLimit);
            $renderData['SearchResults']->setTotalItems($setlimit);
        }

        return $renderData;
    }

    /**
     * Render individual view for FAQ
     *
     * @return array|HTTPResponse 404 error if faq not found
     */
    public function view()
    {
        $faq = FAQ::get()->filter('ID', $this->request->param('ID'))->first();

        if ($faq === null) {
            $this->httpError(404);
        }

        $templates = ['FAQPage_view', 'Page'];

        return $this->customise(['FAQ' => $faq])->renderWith($templates);
    }

    /**
     * Expose variables to the template.
     *
     * @return ArrayList
     */
    public function getSelectorCategories()
    {
        $baseCategories = [FAQ::getRootCategory()];
        $categories = $this->getCategoriesForTemplate($baseCategories);

        return $categories;
    }

    /**
     * Deep recursion of category taxonomy terms. Builds array of categories for template.
     *
     * @param  array $categoryTerms
     * @param  int $depth
     *
     * @return ArrayList
     */
    protected function getCategoriesForTemplate($categoryTerms, $depth = 0)
    {
        $categoriesAccumulator = new ArrayList([]);
        // id of current filter category
        $categoryFilterID = $this->request->requestVar(self::$search_category_key);
        $FAQRootTag = FAQ::getRootCategory()->ID;

        foreach ($categoryTerms as $category) {
            $isNotBaseCategory = $category->ID !== $FAQRootTag;
            $hasNoCategories = $this->data()->Categories()->count() === '0';
            $existsOnPage = $this->data()->Categories()->filter('ID', $category->ID)->exists();
            // don't increment the tree depth if the parent isn't being added to this page
            $depthIncrement = $existsOnPage || $hasNoCategories ? 1 : 0;

            if ($isNotBaseCategory && ($existsOnPage || $hasNoCategories)) {
                // generate the name, along with correct spacing for this depth and bullets
                $namePrefix = $category->Name;
                $namePrefix = ($depth === 0) ? $namePrefix : ('&bull;&nbsp;' . $namePrefix);
                $namePrefix = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth) . $namePrefix;

                $formattedCategoryArray = [
                    'Name'     => $namePrefix,
                    'ID'       => $category->ID,
                    'Selected' => (String)$categoryFilterID === (String)$category->ID,
                ];

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
     * @return string
     */
    public function SearchTermKey()
    {
        return self::$search_term_key;
    }

    /**
     * @return string
     */
    public function SearchCategoryKey()
    {
        return self::$search_category_key;
    }

    /**
     * Translators
     *
     * @return string
     */
    public function CategoriesSelectAllText()
    {
        return _t(FAQ::class . '.CategoriesSelectAllText', $this->data()->CategoriesSelectAllText);
    }

    public function SearchFieldPlaceholder()
    {
        return _t(FAQ::class . '.SearchFieldPlaceholder', $this->data()->SearchFieldPlaceholder);
    }

    public function SearchButtonText()
    {
        return _t(FAQ::class . '.SearchButtonText', $this->data()->SearchButtonText);
    }

    public function NoResultsMessage()
    {
        return _t(FAQ::class . '.NoResultsMessage', $this->data()->NoResultsMessage);
    }

    public function SearchResultsTitle()
    {
        return _t(FAQ::class . '.SearchResultsTitle', $this->data()->SearchResultsTitle);
    }

    public function SearchResultMoreLink()
    {
        return _t(FAQ::class . '.SearchResultMoreLink', $this->data()->MoreLinkText);
    }

}
