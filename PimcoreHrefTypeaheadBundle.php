<?php

namespace PimcoreHrefTypeaheadBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;

class PimcoreHrefTypeaheadBundle extends AbstractPimcoreBundle implements PimcoreBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;
    use PackageVersionTrait;

    protected function getComposerPackageName()
    {
        return 'youwe/pimcore-href-typeahead';
    } 

    public function getJsPaths(): array
    {
        return [
            '/bundles/pimcorehreftypeahead/js/models/HrefObject.js',
            '/bundles/pimcorehreftypeahead/js/pimcore/object/tags/hrefTypeahead.js',
            '/bundles/pimcorehreftypeahead/js/pimcore/object/classes/data/hrefTypeahead.js'
        ];
    }

    public function getCssPaths(): array
    {
        return [
            '/bundles/pimcorehreftypeahead/css/style.css'
        ];
    }
}
