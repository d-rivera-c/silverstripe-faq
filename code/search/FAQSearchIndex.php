<?php
/**
 * Custom solr search index. Extends {@see CwpSearchIndex}
 * and adds customization capabilities to change solr configuration (.solr folder) only for this index.
 * Uses a loose search.
 */
class FAQSearchIndex extends SolrIndex
{
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
}
