<?php
/**
 * Representing individual searches for the search log.
 */
class FAQSearch extends DataObject implements PermissionProvider
{
    private static $singular_name = 'Search';

    private static $db = array(
        'Term' => 'Varchar(255)',
        'SessionID' => 'Varchar(255)',
        'TotalResults' => 'Int'
    );

    // TODO: Summary of result sets and views associated with this search
    private static $summary_fields = array(
        'Term' => 'Term',
        'Created.Nice' => 'Date',
        'TotalResults' => 'TotalResults'
    );

    private static $has_many = array(
        'Results' => 'FAQResults',
        'Articles' => 'FAQResults_Article'
    );

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
        return false;
    }

    public function canCreate($member = false)
    {
        return false;
    }

    public function providePermissions()
    {
        return array(
            'FAQ_VIEW_SEARCH_LOGS' => 'View FAQ search logs',
            'FAQ_EDIT_SEARCH_LOGS' => 'Edit FAQ search logs'
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
