<?php
/**
 * Representing individual searches for the search log.
 */

use SilverStripe\ORM\DataObject;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\ORM\Search\SearchContext;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\Filters\LessThanFilter;
use SilverStripe\ORM\Filters\GreaterThanOrEqualFilter;
use SilverStripe\Security\Permission;

class FAQSearch extends DataObject implements PermissionProvider
{
    private static $singular_name = 'Search';

    private static $db = array(
        'Term' => 'Varchar(255)',
        'SessionID' => 'Varchar(255)',
        'TotalResults' => 'Int',
        'Archived' => 'Boolean',
        'ReferrerID' => 'Int',
        'ReferrerType' => 'Varchar(255)',
        'ReferrerURL' => 'Varchar(255)'
    );

    private static $summary_fields = array(
        'Term' => 'Term',
        'Created.Nice' => 'Date',
        'TotalResults' => 'TotalResults'
    );

    private static $searchable_fields = array(
        'Term' => 'Term'
    );

    private static $has_many = array(
        'Results' => 'FAQResults',
        'Articles' => 'FAQResults_Article'
    );

    private static $default_sort = '"Created" DESC';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('SessionID');
        $fields->removeFieldsFromTab('Root', array(
            'Results',
            'Articles',
            'ReferrerID',
            'ReferrerType',
            'ReferrerURL'
        ));

        $fields->addFieldsToTab('Root.Main', array(
            ReadonlyField::create('Term', 'Search term'),
            ReadonlyField::create('TotalResults', 'Total results found'),
            GridField::create(
                'Results',
                'Search results pages viewed',
                $this->Results(),
                GridFieldConfig_RecordEditor::create()
            ),
            GridField::create(
                'Articles',
                'Articles viewed',
                $this->Articles(),
                $config = GridFieldConfig::create()
            )
        ));

        $sort = new GridFieldSortableHeader();
        $sort->setThrowExceptionOnBadDataType(false);

        $config->addComponents(
            new GridFieldButtonRow('before'),
            new GridFieldToolbarHeader(),
            $sort,
            new GridFieldDataColumns(),
            new FAQResults_Article_EditButton(),
            new FAQResults_Article_DetailForm(),
            new GridFieldFooter()
        );

        return $fields;
    }

    /**
     * Creates a custom FAQSearch search object, can override to prevent the field removals
     *
     * @return FAQSearch_SearchContext
     */
    public function getDefaultSearchContext()
    {
        $fields = $this->scaffoldSearchFields();
        $filters = $this->defaultSearchFilters();

        return new FAQSearch_SearchContext(
            $this->class,
            $fields,
            $filters
        );
    }

    public function onBeforeWrite()
    {
        if ($this->isChanged('Archived')) {
            $this->archiveResults($this->Archived);
        }
        parent::onBeforeWrite();
    }

    /**
     * Archives FAQSearch children
     */
    protected function archiveResults($archive = true)
    {
        $results = $this->Results()->filter('Archived', !$archive);
        $articles = $this->Articles()->filter('Archived', !$archive);

        foreach ($results as $result) {
            $result->Archived = $archive;
            $result->write();
        }

        foreach ($articles as $article) {
            $article->Archived = $archive;
            $article->write();
        }
    }

    public function getTitle()
    {
        return "Search '$this->Term'";
    }

    public function canView($member = false)
    {
        return Permission::check('FAQ_VIEW_SEARCH_LOGS');
    }

    public function canEdit($member = false)
    {
        return Permission::check('FAQ_EDIT_SEARCH_LOGS');
    }

    public function canDelete($member = false)
    {
        return Permission::check('FAQ_DELETE_SEARCH_LOGS');
    }

    public function canCreate($member = false, $context = [])
    {
        return false;
    }

    public function providePermissions()
    {
        return array(
            'FAQ_VIEW_SEARCH_LOGS' => array(
                'name' => _t(
                    'Faq.ViewSearchLogsLabel',
                    'View FAQ search logs'
                ),
                'category' => _t(
                    'Faq.Category',
                    'FAQ'
                ),
            ),
            'FAQ_EDIT_SEARCH_LOGS' => array(
                'name' => _t(
                    'Faq.EditSearchLogsLabel',
                    'Edit FAQ search logs'
                ),
                'category' => _t(
                    'Faq.Category',
                    'FAQ'
                ),
            ),
            'FAQ_DELETE_SEARCH_LOGS' => array(
                'name' => _t(
                    'Faq.DeleteSearchLogsLabel',
                    'Delete FAQ search logs'
                ),
                'category' => _t(
                    'Faq.Category',
                    'FAQ'
                ),
            ),
            'FAQ_IGNORE_SEARCH_LOGS' => array(
                'name' => _t(
                    'Faq.IgnoreSearchLogsLabel',
                    'Ignore search logs for this user'
                ),
                'category' => _t(
                    'Faq.Category',
                    'FAQ'
                ),
            ),
        );
    }
}

/**
 * Admin area for search log.
 */
class FAQSearch_Admin extends ModelAdmin
{
    private static $url_segment = 'faqsearch';

    private static $managed_models = array(
        'FAQSearch'
    );

    private static $menu_title = 'Search Log';
}

/**
 * Custom Search Context for FAQSearch, with different filters to the ones provided by scaffolding
 */
class FAQSearch_SearchContext extends SearchContext
{

    public function __construct($modelClass, $fields = null, $filters = null)
    {
        parent::__construct($modelClass, $fields, $filters);

        // add before filter
        $date = new DateField('CreatedBefore', 'Created before');
        $date->setRightTitle('e.g. ' . date('Y-m-d'));
        $date->setAttribute('placeholder', 'yyyy-mm-dd');

        $dateFilter = new LessThanFilter('CreatedBefore');
        $dateFilter->setName('Created');

        $this->addField($date);
        $this->addFilter($dateFilter);

        // add after filter
        $date = new DateField('CreatedAfter', 'Created after (inclusive)');
        $date->setRightTitle('e.g. ' . date('Y-m-d'));
        $date->setAttribute('placeholder', 'yyyy-mm-dd');

        $dateFilter = new GreaterThanOrEqualFilter('CreatedAfter');
        $dateFilter->setName('Created');

        $this->addField($date);
        $this->addFilter($dateFilter);

        // filter based on what articles were rated
        $usefulOptions = array('Y' => 'Yes', 'N' => 'No', 'U' => 'Unrated');
        $useful = new DropdownField('Useful', 'How articles were rated in search', $usefulOptions);
        $useful->setEmptyString('Any');

        $this->addField($useful);

        // filter for rating comments
        $this->addField(
            DropdownField::create('RatingComment', 'Whether articles were commented on', array(
                'WithComment' => 'Has comments'
            ))->setEmptyString('Any')
        );

        // filter if any results were returned
        $results = new DropdownField('HasResults', 'Has results', array('results' => 'With results', 'noresults' => 'Without results'));
        $results->setEmptyString('Any');

        $this->addField($results);

        // filter for whether the search log was archived or not
        $archived = new DropdownField('IsArchived', 'Show archived searches', array('archived' => 'Archived', 'notarchived' => 'Not Archived'));
        $archived->setEmptyString('Any');

        $this->addField($archived);
    }

    public function getResults($params, $sort = false, $limit = false)
    {
        $list = parent::getResults($params, $sort = false, $limit = false);

        if (isset($params['Useful']) && $params['Useful']) {
            $useful = Convert::raw2sql($params['Useful']);

            $list = $list->filter('Articles.Useful:ExactMatch', $useful);
        }

        if (isset($params['HasResults']) && $params['HasResults']) {
            $filter = 'TotalResults' . (($params['HasResults'] == 'results') ? ':GreaterThanOrEqual' : ':LessThan');
            $list = $list->filter($filter, 1);
        }

        // default not archived, so will cater for that
        if (isset($params['IsArchived']) && $params['IsArchived']) {
            $archived = (isset($params['IsArchived']) && $params['IsArchived'] == 'archived');
            $list = $list->filter('Archived', $archived);
        }

        if (isset($params['RatingComment']) && $params['RatingComment']) {
            // Need to include the filter to ensure the table is joined
            $list = $list->filter('Articles.ID:GreaterThan', 0)->where("\"FAQResults_Article\".\"Comment\" IS NOT NULL");
        }

        return $list;
    }
}
