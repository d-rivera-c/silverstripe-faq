<?php
/**
 * Extends {@link TaxonomyTerm} with useful functionality
 */
class FAQTaxonomyTermExtension extends DataExtension
{

    /**
     * Get's a taxonomy by name
  *
     * @param string $name of the taxonomy to search for
     */
    public static function getByName($name)
    {
        $taxonomy = TaxonomyTerm::get()->filter('Name', $name)->first();
        if ($taxonomy && $taxonomy->exists()) {
            return $taxonomy;
        }
        return null;
    }

    /**
     * Finds or creates a taxonomy.
  *
     * @param array $find   params to find a taxonomy
     * @param array $create used if taxonomy could not be found with above params
     */
    public static function getOrCreate($find, $create)
    {
        $taxonomy = TaxonomyTerm::get()->filter($find)->first();
        if (!$taxonomy || !$taxonomy->exists()) {
            $taxonomy = new TaxonomyTerm($create);
            $taxonomy->write();
        }
        return $taxonomy;
    }


    /**
     * Traverses through the whole tree of taxonomies, filtering by $filter.
     * Gets the first taxonomy that matches the filters
     *
     * @param array $filter
     */
    public function getChildDeep(array $filter)
    {
        // check if this matches filter
        $match = true;
        foreach ($filter as $key => $value) {
            if (isset($this->owner->$key) && $this->owner->$key != $value) {
                $match = false;
            }
        }

        if ($match) {
            return $this->owner;
        }

        // if not, loop over children and run this method
        foreach ($this->owner->Children() as $child) {
            $response = $child->getChildDeep($filter);
            if ($response != null) {
                return $response;
            }
        }
        return null;
    }
}
