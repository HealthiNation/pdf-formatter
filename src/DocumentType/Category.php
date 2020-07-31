<?php
    namespace PDFformatter\Document;

    use \DOMNode;
    use \DOMDocument;
    use \stdClass;
    
    class Category {

        protected $content = '';

        protected $doc = null;

        protected $options = [];

        function __construct() {}

        public function formatArray() {
            $this->content = $this->asArray();
    
            return $this;
        }
    
        public function formatXML() {
            $this->content = $this->doc;
    
            return $this;      
        }

        public function getContent() {
            return $this->content;
        }

        protected function asArray() {
            if (!$this->doc) {
                return [];
            }
    
            $flattenDocument = function (DOMDocument $dom) {
                $dataArray = [];
    
                foreach ($dom->getElementsByTagName('*') as $node) {
    
                    if ($node->hasAttributes()) {
                        $data = new stdClass;
    
                        $data->meta = new stdClass;
                        $data->meta->tagName = $node->tagName;
                        $data->meta->nodePath = $node->getNodePath();
                        $data->meta->nodeLevel = substr_count($node->getNodePath(), '/') - 1;
    
                        // get the tag type and count of all immediate children
                        $data->meta->immediateChildTagCount = [];
                        foreach ($node->childNodes as $child) {
                            // #text node does not have a tag name
                            if ($child->tagName) {
                                if (!isset($data->meta->immediateChildTagCount[$child->tagName])) {
                                    $data->meta->immediateChildTagCount[$child->tagName] = 1;
                                } else {
                                    $data->meta->immediateChildTagCount[$child->tagName] += 1;
                                }
                            }
                        }
    
                        $data->attributes = new stdClass;
                        foreach ($node->attributes as $attr) {
                            $data->attributes->{$attr->nodeName} = $attr->nodeValue;
                        }
    
                        // additional data depending on tag name
    
    
                        $dataArray[] = $data;
                    }
                };
    
                return $dataArray;
            };
    
            $dom = new DOMDocument('1.0', 'utf-8');
            $dom->loadXML($this->doc);
            $dataArray = $flattenDocument($dom);
            //        var_dump($dataArray);
            //        exit;
    
            return $dataArray;
        }
        
        public function writeToFile($path) {
            $length = file_put_contents($path, $this->content);
    
            return $this;
        }

        public function buildDoc($records, $options = []) {
            $this->options = $options;
    
            $cats = [];
            // convert data to assoc array where id is the key
            foreach ($records as $record) {
                if (!isset($cats[$record->id])) {
                    $cats[$record->id] = $record;
                }
            }
    
            $buildCategoryDocument = function($cats) use ($options) {
                // Todo: need to work on the option model
                $structure = !empty($options['structure']) ? $options['structure'] : '';
                $sortOption = !empty($options['sort']) ? $options['sort'] : [];
    
                $xmlstr = '<?xml version="1.0" standalone="yes"?><Categories></Categories>';
    
                $dom = new DOMDocument('1.0', 'utf-8');
                $dom->loadXML($xmlstr);
                $dom->formatOutput = true;
    
                $root = $dom->getElementsByTagName('Categories')[0];
    
                // The function to create one <Category> node
                $createCategoryNode = function ($data) use ($dom) {
                    $node = $dom->createElement("Category");
                    $node->setAttribute('id', $data->id);
                    $node->setAttribute('name', $data->name);
                    $node->setAttribute('parent_id', $data->parent_id);
    
                    // Internally mark the id as 'xml:id' for getElementById to work. Adding xml:id manually to the tag will cause loadXML to throw an error DOMDocument: xml:id is not a NCName in Entity
                    $node->setIdAttribute('id', true);
    
                    return $node;
                };
    
                // build nested doc
                $buildNestedDocument = function() use ($dom, $root, $cats, $createCategoryNode) {
                    foreach ($cats as $cat) {
                        $parent = $dom->getElementById($cat->parent_id);
                        if (!$parent && isset($cats[$cat->parent_id])) {
                            $parent = $createCategoryNode($cats[$cat->parent_id]);
                            $root->appendChild($parent);
                        }
    
                        $child = $dom->getElementById($cat->id);
                        if (!$child && isset($cats[$cat->id])) {
                            if ($parent) {
                                $child = $parent->appendChild($createCategoryNode($cats[$cat->id]));
                            } else {
                                $child = $root->appendChild($createCategoryNode($cats[$cat->id]));
                            }
                        } else {
                            // The case for child created before the parent. Need to nest the child back to the parent
                            if ($parent) {
                                $parent->appendChild($child);
                            }
                        }
                    }
                };
    
                // build flat doc
                $buildFlatDocument = function() use ($dom, $root, $cats, $createCategoryNode) {
                    foreach ($cats as $cat) {
                        $node = $dom->getElementById($cat->id);
                        if (!$node) {
                            $root->appendChild($createCategoryNode($cats[$cat->id]));
                        }
                    }
                };
    
                if ($structure) {
                    $buildNestedDocument();
                } else {
                    $buildFlatDocument();
                }
    
                /**
                 * Swap the actual elements in the DOM
                 *
                 * @param DOMNode $a
                 * @param DOMNode $b
                 */
                $swap = function(DOMNode $a, DOMNode $b) {
                    // Because nodes are swapped by reference, if 2 nodes are the same, then there is no need to swap
                    if ($a->isSameNode($b)) {
                        return;
                    }
    
                    $new = $a->cloneNode(true);
                    $old = $b->parentNode->replaceChild($new, $b);
                    $a->parentNode->replaceChild($old, $a);
                };
    
                /**
                 * Custom function that can take a sort option. Default sort by id in ASC order.
                 *
                 * @param DOMNode $a
                 * @param DOMNode $b
                 * @return bool
                 */
                $compare = function(DOMNode $a, DOMNode $b) use ($sortOption) {
                    // ASC (< 0)    DESC ( > 0)
                    $attr = !empty($sortOption['attribute']) ? $sortOption['attribute'] : 'id';
                    $order = !empty($sortOption['order']) ? $sortOption['order'] : 'ASC';
    
                    if (strtolower($order) === 'desc') {
                        return strcmp($a->getAttribute($attr), $b->getAttribute($attr)) > 0;
                    } else {
                        return strcmp($a->getAttribute($attr), $b->getAttribute($attr)) < 0;
                    }
                };
    
                $printPartitionNames = function(DOMNode $firstNode, DOMNode $lastNode) {
                    $names = [];
    
                    $node = $firstNode;
                    while($node && !$node->isSameNode($lastNode)) {
                        $names[] = '"' . $node->getAttribute('name') . '"';
                        $node = $node->nextSibling;
                    }
                    $names[] = '"' . $lastNode->getAttribute('name') . '"';
    
                    echo implode(', ', $names);
                    var_dump("\n");
                };
    
                $getNodeInfo = function(DOMNode $node) {
                    return 'Name: "' . $node->getAttribute('name') . '" Path: ' . $node->getNodePath();
                };
    
    
                $partition = function(DOMNode $root, $low, $high) use ($compare, $swap) {
                    $pivot = $root->childNodes[$high];
    
                    $i = $low - 1;
    
                    for ($j = $low; $j <= $high- 1; $j++)
                    {
                        if ($compare($root->childNodes[$j], $pivot))
                        {
                            $i++;    // group matched element to the left side
                            $swap($root->childNodes[$i],  $root->childNodes[$j]);
                        }
                    }
    
                    $swap($root->childNodes[$i + 1], $root->childNodes[$high]);
    
                    return ($i + 1);
                };
    
    
                $qSort = function(DOMNode $root, $low, $high) use (&$qSort, $partition) {
                    if ($low < $high) {
                        $pIndex = $partition($root, $low, $high);
    
                        $qSort($root, $low, $pIndex-1);
                        $qSort($root, $pIndex+1, $high);
                    }
                };
    
    
                $qSortTree = function(DOMNode $root) use (&$qSortTree, $qSort) {
                    $childNodeCount = $root->childNodes->length;
    
                    if ($childNodeCount == 0) {
                        return;
                    }
    
                    for ($i = 0; $i < $childNodeCount; $i++) {
                        $qSortTree($root->childNodes[$i]);
                    }
    
                    $qSort($root, 0, $childNodeCount-1);
                };
    
                // Note: qSortTree generates the same outcome as a nested document is built with presorted data, where
                // the data is sorted by parentid followed by the field to be sorted 
                if ($sortOption) {
                    $qSortTree($root);
                }
    
                $doc = $dom->saveXML();
    
            //    echo $doc;
            //    exit;
    
                return $doc;
            };
    
            $dataDoc = $buildCategoryDocument($cats);
    
            $this->doc = $dataDoc;
    
            return $this;
        }

    }