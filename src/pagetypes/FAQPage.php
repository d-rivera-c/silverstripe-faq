<?php

namespace SilverStripe\Faq\PageTypes;

use Page;
use SilverStripe\Faq\Model\Faq;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Taxonomy\TaxonomyTerm;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use SilverStripe\Faq\Controllers\FAQPageController;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldEditButton;

/**
 * FAQ pagetype, displays Q & A related to the page.
 * Has a custom search index to add search capabilities to the page.
 * Can live in any part of the SiteTree
 */
class FAQPage extends Page
{
    /**
     * @var array
     */
    private static $db = [
        'SinglePageLimit'         => 'Int',
        'CategoriesSelectAllText' => 'Varchar(124)',
        'SearchFieldPlaceholder'  => 'Varchar(124)',
        'SearchResultsSummary'    => 'Varchar(255)',
        'SearchResultsTitle'      => 'Varchar(255)',
        'SearchButtonText'        => 'Varchar(124)',
        'NoResultsMessage'        => 'Varchar(255)',
        'SearchNotAvailable'      => 'Varchar(255)',
        'MoreLinkText'            => 'Varchar(124)',
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'SinglePageLimit'         => 0,
        'CategoriesSelectAllText' => 'All categories',
        'SearchFieldPlaceholder'  => 'Ask us a question',
        'SearchResultsSummary'    => 'Displaying %CurrentPage% of %TotalPages% pages for "%Query%"',
        'SearchResultsTitle'      => 'FAQ Results',
        'SearchButtonText'        => 'Search',
        'NoResultsMessage'        => 'We couldn\'t find an answer to your question. Maybe try asking it in a different way, or check your spelling.',
        'SearchNotAvailable'      => 'We are currently unable to search the website for you. Please try again later.',
        'MoreLinkText'            => 'Read more',
    ];

    /**
     * @var array
     */
    private static $many_many = [
        'FeaturedFAQs' => FAQ::class,
        'Categories'   => TaxonomyTerm::class,
    ];

    /**
     * @var array
     */
    private static $many_many_extraFields = [
        'FeaturedFAQs' => [
            'SortOrder' => 'Int',
        ],
    ];

    /**
     * @var string
     */
    private static $singular_name = 'FAQ Page';

    /**
     * @var string
     */
    private static $description = 'FAQ search page';

    /**
     * @var string
     */
    private static $table_name = "FAQPage";

    /**
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // categories
        $treedropdown = new TreeMultiselectField(
            'Categories',
            'Categories to show and search for',
            TaxonomyTerm::class
        );
        $treedropdown->setDescription('Displays FAQs with selected categories filtered. ' .
            'Don\'t select any if you want to show all FAQs regardless of categories');
        $treedropdown->setTreeBaseID(FAQ::getRootCategory()->ID);
        $fields->addFieldToTab(
            'Root.Main',
            $treedropdown,
            'Content'
        );

        $settings = new Tab('Settings', 'FAQ Settings');

        $fields->insertBefore('PublishingSchedule', $settings);

        $fields->addFieldsToTab('Root.Settings', [
            TextField::create('SinglePageLimit')
                ->setDescription('
                    If set higher than 0, limits results to that many and removes pagination.
                '),
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
                '),
        ]);

        // Featured FAQs tab
        $FeaturedFAQsTab = new Tab('FeaturedFAQs', _t(__CLASS__ .'.FeaturedFAQs', 'Featured FAQs'));
        $fields->insertBefore($FeaturedFAQsTab, 'PublishingSchedule');

        $components = GridFieldConfig_RelationEditor::create();
        $components->removeComponentsByType(GridFieldAddNewButton::class);
        $components->removeComponentsByType(GridFieldEditButton::class);
        $components->removeComponentsByType(GridFieldFilterHeader::class);
        $components->addComponent(new GridFieldSortableRows('SortOrder'));

        $dataColumns = $components->getComponentByType(GridFieldDataColumns::class);

        $dataColumns->setDisplayFields([
            'Title'          => _t(__CLASS__ .'.ColumnQuestion', 'Ref.'),
            'Question'       => _t(__CLASS__ .'.ColumnQuestion', 'Question'),
            'Answer.Summary' => _t(__CLASS__ .'.ColumnPageType', 'Answer'),
            'Category.Name'  => _t(__CLASS__ .'.ColumnPageType', 'Category'),
        ]);

        $components->getComponentByType(GridFieldAddExistingAutocompleter::class)
            ->setResultsFormat('$Question');

        // warning for categories filtering on featured FAQs
        $differentCategories = 0;
        if ($this->Categories()->count() > 0) {
            $FAQsWithCategories = $this->FeaturedFAQs()
                ->filter('CategoryID', $this->Categories()->column('ID'))
                ->count();
            $totalFeaturedFAQs = $this->FeaturedFAQs()->count();
            $differentCategories = $totalFeaturedFAQs - $FAQsWithCategories;
        }

        $FeaturedFAQsCategoryNotice = '<p class="message %s">Only featured FAQs with selected categories will '
            . 'be displayed on the site. If you have not selected a category, all of the '
            . 'featured FAQs will be displayed.</p>';
        if ($differentCategories) {
            $FeaturedFAQsCategoryNotice = sprintf(
                '<p class="message %s">You have %d FAQs with different categories than the ones you have selected '
                . 'to show on this FAQPage. These will not be displayed.</p>',
                $differentCategories ? 'bad' : '',
                $differentCategories
            );
        }

        $fields->addFieldsToTab(
            'Root.FeaturedFAQs',
            [
                LiteralField::create(
                    'FeaturedFAQsCategoryNotice',
                    $FeaturedFAQsCategoryNotice
                ),
                GridField::create(
                    'FeaturedFAQs',
                    _t(__CLASS__ .'.FeaturedFAQs', 'Featured FAQs'),
                    $this->FeaturedFAQs(),
                    $components
                ),
            ]
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
     *
     * @return ArrayList
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


    /**
     * @return string
     */
    public function getControllerName()
    {
        return FAQPageController::class;
    }
}
