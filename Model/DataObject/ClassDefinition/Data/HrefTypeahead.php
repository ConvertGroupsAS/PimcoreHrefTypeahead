<?php

namespace PimcoreHrefTypeaheadBundle\Model\DataObject\ClassDefinition\Data;

use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToOneRelation;

class HrefTypeahead extends ManyToOneRelation
{
    /**
     * Allow show trigger
     *
     * @var boolean
     */
    public $showTrigger;

    /**
     * @return bool
     */
    public function getShowTrigger()
    {
        return $this->showTrigger;
    }

    /**
     * @param bool $showTrigger
     *
     * @return $this
     */
    public function setShowTrigger($showTrigger)
    {
        $this->showTrigger = $showTrigger;
        return $this;
    }

    public function getFieldType(): string
    {
        return 'hrefTypeahead';
    }
}
