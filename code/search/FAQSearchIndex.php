<?php
/**
 * Custom solr search index. Extends {@see CwpSearchIndex}
 * and adds customization capabilities to change solr configuration (.solr folder) only for this index.
 * Uses a loose search.
 */

use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use SilverStripe\Core\Config\Config;
use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;

class FAQSearchIndex extends SolrIndex
{
    private static $include_suggestions = true;

    /**
     * Adds FAQ fields to the index
     */
    public function init()
    {
        // Add classes
        $this->addClass('FAQ');

        // Add fields
        $this->addFulltextField('Question');
        $this->addFulltextField('Answer');
        $this->addFulltextField('Keywords');

        // category filter
        $this->addFilterField('Category.ID');

        // Add field boosting
        $this->setFieldBoosting('FAQ_Question', FAQ::config()->question_boost);
        $this->setFieldBoosting('FAQ_Answer', FAQ::config()->answer_boost);
        $this->setFieldBoosting('FAQ_Keywords', FAQ::config()->keywords_boost);

        $this->extend('updateFAQInit');
    }

    /**
     * Overload
     */
    public function search(SearchQuery $query, $offset = -1, $limit = -1, $params = array())
    {
        // escape query
        $queryInternals = array_pop($query->search);
        $queryInternals['text'] = self::escapeQuery($queryInternals['text']);
        $query->search[] = $queryInternals;

        $result = parent::search($query, $offset, $limit, $params);

        // Replace the paginated list of results so that we can add tracking code to the URL of pagination links
        $matches = $result->getField('Matches');
        $trackedMatches = new FAQSearchIndex_PaginatedList($matches->getList());
        $trackedMatches->setLimitItems($matches->getLimitItems());
        $trackedMatches->setTotalItems($matches->getTotalItems());
        $trackedMatches->setPageStart($matches->getPageStart());
        $trackedMatches->setPageLength($matches->getPageLength());
        $result->setField('Matches', $trackedMatches);

        if ($this->stat('include_suggestions')) {
            // unescape suggestions
            $unescapedSuggestions = self::unescapeQuery(
                array(
                    $result->Suggestion,
                    $result->SuggestionNice,
                    $result->SuggestionQueryString,
                )
            );
            $result->Suggestion = $unescapedSuggestions[0];
            $result->SuggestionNice = $unescapedSuggestions[1];
            $result->SuggestionQueryString = $unescapedSuggestions[2];
        } else {
            $result->Suggestion = '';
            $result->SuggestionNice = '';
            $result->SuggestionQueryString = '';
        }

        return $result;
    }

    /**
     * escapes characters that may break Solr search
     */
    public static function escapeQuery($keywords)
    {
        $searchKeywords = preg_replace('/([\+\-!\(\)\{\}\[\]\^"~\*\?:\/\|&]|AND|OR|NOT)/', '\\\${1}', $keywords);
        return $searchKeywords;
    }

    /**
     * unescapes characters previously escaped to stop Solr breaking
     */
    public static function unescapeQuery($keywords)
    {
        $searchKeywords = preg_replace('/\\\([\+\-!\(\)\{\}\[\]\^"~\*\?:\/\|&]|AND|OR|NOT)/', '${1}', $keywords);
        return $searchKeywords;
    }

    /**
     * Overwrite extra paths functions to only use the path defined on the yaml file
     * We can create/overwrite new .txt templates for only this index
     *
     * @see SolrIndex::getExtrasPath
     */
    public function getExtrasPath()
    {
        // get options from configuration
        $options = Config::inst()->get('FAQSearchIndex', 'options');

        $globalOptions = Solr::solr_options();
        if (isset($options['extraspath']) && file_exists($options['extraspath'])) {
            $globalOptions['extraspath'] = $options['extraspath'];
        }
        return $this->extrasPath ? $this->extrasPath : $globalOptions['extraspath'];
    }

    /**
     * Overwrite template paths to only use the path defined on the yaml file
     *
     * @see SolrIndex::getTemplatesPath
     */
    public function getTemplatesPath()
    {
        $options = Config::inst()->get('FAQSearchIndex', 'options');

        $globalOptions = Solr::solr_options();
        if (isset($options['templatespath']) && file_exists($options['templatespath'])) {
            $globalOptions['templatespath'] = $options['templatespath'];
        }
        return $this->templatesPath ? $this->templatesPath : $globalOptions['templatespath'];
    }

    /**
     * Overloaded to remove compulsory matching on all words
     *
     * @see SolrIndex::getQueryComponent
     */
    protected function getQueryComponent(SearchQuery $searchQuery, &$hlq = array())
    {
        $q = array();
        foreach ($searchQuery->search as $search) {
            $text = $search['text'];
            preg_match_all('/"[^"]*"|\S+/', $text, $parts);

            $fuzzy = $search['fuzzy'] ? '~' : '';

            foreach ($parts[0] as $part) {
                $fields = (isset($search['fields'])) ? $search['fields'] : array();
                if (isset($search['boost'])) {
                    $fields = array_merge($fields, array_keys($search['boost']));
                }
                if ($fields) {
                    $searchq = array();
                    foreach ($fields as $field) {
                        $boost = (isset($search['boost'][$field])) ? '^' . $search['boost'][$field] : '';
                        $searchq[] = "{$field}:".$part.$fuzzy.$boost;
                    }
                    $q[] = '+('.implode(' OR ', $searchq).')';
                } else {
                    $q[] = $part.$fuzzy;
                }
                $hlq[] = $part;
            }
        }
        return $q;
    }

    /**
     * Upload config for this index to the given store
     *
     * @param SolrConfigStore $store
     */
    public function uploadConfig($store)
    {
        parent::uploadConfig($store);

        $this->extend('updateConfig', $store);
    }

    public function getFieldDefinitions() {
        $xml = parent::getFieldDefinitions();

        $this->extend('updateFieldDefinitions', $xml);

        return $xml;
    }
}

/**
 * Subclass for added ability of injecting the URL to be used as the base for links. There is a lot of copy/paste
 * here but we couldn't find a better option.
 */
class FAQSearchIndex_PaginatedList extends PaginatedList
{
    protected $trackingURL = null;

    /**
     * Set the tracking URL
     * @param SS_HTTPRequest $request    Usually the current request
     * @param int            $trackingID The tracking ID to append to the URL
     */
    public function setTrackingURL(HTTPRequest $request, $trackingID)
    {
        $this->trackingURL = Director::makeRelative(Controller::join_links($request->getURL(true), '?t=' . $trackingID));
        return $this;
    }

    public function getTrackingURL()
    {
        return $this->trackingURL;
    }

    public function Pages($max = null)
    {
        $result = new ArrayList();

        if ($max) {
            $start = ($this->CurrentPage() - floor($max / 2)) - 1;
            $end   = $this->CurrentPage() + floor($max / 2);

            if ($start < 0) {
                $start = 0;
                $end   = $max;
            }

            if ($end > $this->TotalPages()) {
                $end   = $this->TotalPages();
                $start = max(0, $end - $max);
            }
        } else {
            $start = 0;
            $end   = $this->TotalPages();
        }

        for ($i = $start; $i < $end; $i++) {
            $result->push(new ArrayData(array(
                'PageNum'     => $i + 1,
                'Link'        => HTTP::setGetVar($this->getPaginationGetVar(), $i * $this->getPageLength(), $this->getTrackingURL()),
                'CurrentBool' => $this->CurrentPage() == ($i + 1)
            )));
        }

        return $result;
    }

    public function PaginationSummary($context = 4)
    {
        $result  = new ArrayList();
        $current = $this->CurrentPage();
        $total   = $this->TotalPages();

        // Make the number even for offset calculations.
        if ($context % 2) {
            $context--;
        }

        // If the first or last page is current, then show all context on one
        // side of it - otherwise show half on both sides.
        if ($current == 1 || $current == $total) {
            $offset = $context;
        } else {
            $offset = floor($context / 2);
        }

        $left  = max($current - $offset, 1);
        $range = range($current - $offset, $current + $offset);

        if ($left + $context > $total) {
            $left = $total - $context;
        }

        for ($i = 0; $i < $total; $i++) {
            $link    = HTTP::setGetVar($this->getPaginationGetVar(), $i * $this->getPageLength(), $this->getTrackingURL());
            $num     = $i + 1;

            $emptyRange = $num != 1 && $num != $total && (
                $num == $left - 1 || $num == $left + $context + 1
            );

            if ($emptyRange) {
                $result->push(new ArrayData(array(
                    'PageNum'     => null,
                    'Link'        => null,
                    'CurrentBool' => false
                )));
            } elseif ($num == 1 || $num == $total || in_array($num, $range)) {
                $result->push(new ArrayData(array(
                    'PageNum'     => $num,
                    'Link'        => $link,
                    'CurrentBool' => $current == $num
                )));
            }
        }

        return $result;
    }

    public function FirstLink()
    {
        return HTTP::setGetVar(
            $this->getPaginationGetVar(),
            0,
            $this->getTrackingURL()
        );
    }

    public function LastLink()
    {
        return HTTP::setGetVar(
            $this->getPaginationGetVar(),
            ($this->TotalPages() - 1) * $this->getPageLength(),
            $this->getTrackingURL()
        );
    }

    public function NextLink()
    {
        if ($this->NotLastPage()) {
            return HTTP::setGetVar(
                $this->getPaginationGetVar(),
                $this->getPageStart() + $this->getPageLength(),
                $this->getTrackingURL()
            );
        }
    }

    public function PrevLink()
    {
        if ($this->NotFirstPage()) {
            return HTTP::setGetVar(
                $this->getPaginationGetVar(),
                $this->getPageStart() - $this->getPageLength(),
                $this->getTrackingURL()
            );
        }
    }
}
