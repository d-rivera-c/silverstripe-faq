<?php

/**
 * Common fields and functionality for FAQ result sets and article views.
 */
class FAQResults_Extension extends Extension
{
    private static $singular_name = 'View';

    private static $db = array(
        'Useful' => "Enum('Y,N,U','U')", // Yes, No, Unset
        'Comment' => 'Varchar(255)'
    );

    private static $has_one = array(
        'Search' => 'FAQSearch'
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
}

/**
 * Represents a result set resulting from a search.
 */
class FAQResults extends DataObject
{
    private static $singular_name = 'Result Set';

    private static $db = array(
        'ArticleSet' => 'Varchar(255)',
        'SetSize' => 'Int'
    );

    private static $has_many = array(
        'ArticlesViewed' => 'FAQResults_Article'
    );

    private static $summary_fields = array(
        'ArticleSet',
        'SetSize',
        'Useful',
        'Comment'
    );
}

/**
 * Represents views of individual articles from a search result set.
 */
class FAQResults_Article extends DataObject
{
    private static $singular_name = 'Article';

    private static $has_one = array(
        'FAQ' => 'FAQ',
        'ResultSet' => 'FAQResults'
    );

    private static $summary_fields = array(
        'FAQ.ID' => 'Article ID',
        'FAQ.Question' => 'Article',
        'Useful' => 'Useful',
        'Comment' => 'Comment'
    );
}


