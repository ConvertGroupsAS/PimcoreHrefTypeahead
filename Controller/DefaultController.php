<?php

namespace PimcoreHrefTypeaheadBundle\Controller;

use PimcoreHrefTypeaheadBundle\Service\SearchBuilder;
use Pimcore\Controller\Traits\JsonHelperTrait;
use Pimcore\Controller\UserAwareController;
use Pimcore\Bundle\AdminBundle\Helper\QueryParams;
use Pimcore\Logger;
use Pimcore\Model\Element;
use Pimcore\Model\Element\AbstractElement;
use Pimcore\Model\DataObject;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DefaultController
 *
 * @Route("/admin/href-typeahead")
 * @package PimcoreHrefTypeaheadBundle\Controller
 */
class DefaultController extends UserAwareController
{
     use JsonHelperTrait;

    /**
     * @param Request $request
     * @param SearchBuilder $searchBuilder
     * @Route("/find")
     */
    public function findAction(Request $request, SearchBuilder $searchBuilder)
    {
        $sourceId = $request->get('sourceId');
        $sourceClassName = $request->get('className');
        $valueIds = $request->get('valueIds');
        $formatterClass = $request->get('formatterClass');
        $className = $request->get('class');
        $fieldName = $request->get('fieldName'); // fieldName used to find field definition if needed
        if ($request->get('context')) {
            $context = $this->decodeJson($request->get('context'));
        } else {
            $context = [];
        }

        $source = null;
        $sourceClass = null;

        // Get a sourceClass if given a sourceClassName
        if ($sourceClassName) {
            $classFullName = "\\Pimcore\\Model\\DataObject\\$sourceClassName";

            if (Tool::classExists($classFullName)) {
                $sourceClass = new $classFullName();
            }
        }

        // If we have a sourceId, grab source through the id. Otherwise, set source to be our sourceClass (which is null if it doesn't exist)
        $source = $sourceId ? DataObject\Concrete::getById($sourceId) : $sourceClass;

        // Don`t do anything without valid source object
        if (!$source) {
            return $this->adminJson(['data' => [], 'success' => false, 'total' => 0]);
        }

        // If there is a sourceClass, fieldName, and we are still missing a className, then grab the className from the allowedClasses in definition
        if ($sourceClass && $fieldName && !$className) {
            $allowedClasses = $sourceClass->getClass()->getFieldDefinition($fieldName)->getClasses();

            if (count($allowedClasses) > 0 && isset($allowedClasses[0]['classes'])) {
                $className = $allowedClasses[0]['classes'];
            }
        }

        // This is a special case when the field is loaded for the first time or they are loaded from
        if ($valueIds) {
            $valueObjs = [];
            foreach (explode_and_trim(',', $valueIds) as $valueId) {
                $valueObjs[] = DataObject\Concrete::getById($valueId);
            }

            if (!$valueObjs) {
                return $this->adminJson(['data' => [], 'success' => false, 'total' => 0]);
            }

            $elements = [];
            foreach ($valueObjs as $valueObj) {
                $label = $this->getNicePath($valueObj, $source, $context);
                $elements[] = $this->formatElement($valueObj, $label);
            }

            return $this->adminJson(['data' => $elements, 'success' => true, 'total' => count($elements)]);
        }
        // This means that we have passed the values ids
        // but the field is empty this is common when the field is empty
        // We don't need to continue looping
        elseif (!$valueIds && $request->get('valueIds')) {
            return $this->adminJson(['data' => [], 'success' => false, 'total' => 0]);
        }

        $filter = $request->get('filter') ? \Zend_Json::decode($request->get('filter')) : null;
        $considerChildTags = $request->get('considerChildTags') === 'true';
        $sortingSettings = QueryParams::extractSortingSettings($request->request->all());
        $searchService = $searchBuilder
            ->withUser($this->getPimcoreUser())
            ->withTypes(['object'])
            ->withSubTypes(['object'])
            ->withClassNames([$className])
            ->withQuery( $request->get('query'))
            ->withStart((int) $request->get('start'))
            ->withLimit((int) $request->get('limit'))
            ->withFields( $request->get('fields'))
            ->withFilter($filter)
            ->withSourceObject($source)
            ->withTagIds( $request->get('tagIds'))
            ->withConsiderChildTags($considerChildTags)
            ->withSortSettings($sortingSettings)
            ->build();

        $searcherList = $searchService->getListingObject();

        /** @var \Pimcore\Model\Search\Backend\Data[] $hits */
        $hits = $searcherList->load();
        $elements = [];

        foreach ($hits as $hit) {
            /** @var AbstractElement $element */
            $element = Element\Service::getElementById($hit->getId()->getType(), $hit->getId()->getId());
            if ($element->isAllowed('list')) {
                if ($element->getType() === 'object') {
                    $label = $this->getNicePath($valueObj, $source, $context);
                } else {
                    $label = (string) $element;
                }
                $elements[] = $this->formatElement($element, $label);
            }
        }

        // only get the real total-count when the limit parameter is given otherwise use the default limit
        if ($request->get('limit')) {
            $totalMatches = $searcherList->getTotalCount();
        } else {
            $totalMatches = count($elements);
        }

        return $this->jsonResponse(['data' => $elements, 'success' => true, 'total' => $totalMatches]);
    }

    /**
     * @param DataObject\Concrete $source
     * @param array $context
     *
     * @return bool|DataObject\ClassDefinition\Data|null
     *
     * @throws \Exception
     */
    protected function getNicePathFormatterFieldDefinition($source, $context)
    {
        $ownerType = $context['containerType'];
        $fieldname = $context['fieldname'];
        $fd = null;

        if ($ownerType == 'object') {
            $subContainerType = isset($context['subContainerType']) ? $context['subContainerType'] : null;
            if ($subContainerType) {
                $subContainerKey = $context['subContainerKey'];
                $subContainer = $source->getClass()->getFieldDefinition($subContainerKey);
                if (method_exists($subContainer, 'getFieldDefinition')) {
                    $fd = $subContainer->getFieldDefinition($fieldname);
                }
            } else {
                $fd = $source->getClass()->getFieldDefinition($fieldname);
            }
        } elseif ($ownerType == 'localizedfield') {
            $localizedfields = $source->getClass()->getFieldDefinition('localizedfields');
            if ($localizedfields instanceof DataObject\ClassDefinition\Data\Localizedfields) {
                $fd = $localizedfields->getFieldDefinition($fieldname);
            }
        } elseif ($ownerType == 'objectbrick') {
            $fdBrick = DataObject\Objectbrick\Definition::getByKey($context['containerKey']);
            $fd = $fdBrick->getFieldDefinition($fieldname);
        } elseif ($ownerType == 'fieldcollection') {
            $containerKey = $context['containerKey'];
            $fdCollection = DataObject\Fieldcollection\Definition::getByKey($containerKey);
            if (($context['subContainerType'] ?? null) === 'localizedfield') {
                /** @var DataObject\ClassDefinition\Data\Localizedfields $fdLocalizedFields */
                $fdLocalizedFields = $fdCollection->getFieldDefinition('localizedfields');
                $fd = $fdLocalizedFields->getFieldDefinition($fieldname);
            } else {
                $fd = $fdCollection->getFieldDefinition($fieldname);
            }
        }

        return $fd;
    }

    /**
     * @param AbstractElement $element
     * @param string $label
     * @return array
     */
    private function formatElement($element, $label)
    {
        return [
            'id' => $element->getId(),
            'fullpath' => $element->getFullPath(),
            'display' => $label,
            'type' => Element\Service::getType($element),
            'subtype' => $element->getType(),
            'nicePathKey' => Element\Service::getType($element) . '_' . $element->getId(),
        ];
    }

    /**
     * @param $fd
     * @param AbstractElement $element
     * @param DataObject\Concrete $source
     * @return array|mixed
     */
    private function getNicePath($element, $source, $context)
    {
        if (!$element) {
            return null;
        }
        
        $fd = $this->getNicePathFormatterFieldDefinition($source, $context);
        $result = []; 
        if ($fd instanceof DataObject\ClassDefinition\PathFormatterAwareInterface) {
            $formatter = $fd->getPathFormatterClass();
            
            if (null !== $formatter) {
                $pathFormatter = DataObject\ClassDefinition\Helper\PathFormatterResolver::resolvePathFormatter(
                    $fd->getPathFormatterClass()
                );

                if ($pathFormatter instanceof DataObject\ClassDefinition\PathFormatterInterface) {
                    $result = $pathFormatter->formatPath($result, $source, [$element], [
                        'fd' => $fd,
                        'context' => $context,
                    ]);
                }
            }
        }
        
        return $result[$element->getId()] ?? '';
    }
}
