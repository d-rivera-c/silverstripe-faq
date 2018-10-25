<?php

namespace SilverStripe\Faq\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Faq\Model\FAQ;

/**
 * Model Admin for FAQs search module.
 * Allows a content author to publish and edit questions and answers.
 *
 * @see FAQ for FAQ DataObject.
 */
class FAQAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $url_segment = 'faq';

    /**
     * @var array
     */
    private static $managed_models = [
        FAQ::class,
    ];

    /**
     * @var string
     */
    private static $menu_title = 'FAQs';

    /**
     * @var array
     */
    private static $model_importers = [
        'FAQ' => FAQCsvBulkLoader::class,
    ];

    /**
     * Overload ModelAdmin->getExportFields() so that we can export keywords.
     *
     * @see ModelAdmin::getExportFields
     * @return array
     */
    public function getExportFields()
    {
        return [
            'Question'      => 'Question',
            'Answer'        => 'Answer',
            'Keywords'      => 'Keywords',
            'Category.Name' => 'Category',
        ];
    }
}
