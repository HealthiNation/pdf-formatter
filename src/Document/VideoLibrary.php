<?php
    require_once("video-library-pdf.php");

    class VideoLibraryDocument {

        protected $content = '';

        protected $doc = null;

        function __construct() {}

        public function formatPDF() {
            $data = $this->asArray();
            $template = new VideoLibraryPdf();

            foreach ($data as $item) {
                switch ($item->meta->tagName) {
                    case 'Category':
                        if ($item->meta->nodeLevel === 1 && !empty($item->meta->immediateChildTagCount['Item'])) {
                            $template->printSectionTitle($item->attributes->name);
                        } else if ($item->meta->nodeLevel > 1 && !empty($item->meta->immediateChildTagCount['Item'])) {
                            $template->printSectionSubTitle($item->attributes->name);
                        }
                        break;
                    case 'Item':
                        $template->printItem($item->attributes);
                        break;
                }
            }

            $this->content = $template->Output('S');

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
            // Sort out all unique categories
            $cats = [];
            foreach ($records as $record) {
                foreach ($record->cats as $cat) {
                    if (!isset($cats[$cat->id])) {
                        $cats[$cat->id] = $cat;
                    }
                }
            }

            $sortOption = isset($options['sort']) ? $options['sort'] : [];

            $buildCategoryDocument = function($cats) use ($sortOption) {
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

                $buildNestedDocument();

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

                $qSortTree($root);

                $doc = $dom->saveXML();

//            echo $doc;
//            exit;

                return $doc;
            };
            //$buildCategoryDocument($cats);

            $buildCategoryTreeNode = function &($cat, &$tree, $cats) use (&$buildCategoryTreeNode) {
                if (!isset($cats[$cat->parent_id])) {   // or say $cat->parent_id == '0'
                    if (!isset($tree[$cat->parent_id])) {
                        //var_dump('create the root out of the parent id');
                        $tree[$cat->parent_id] = [
                            'data' => null,
                            'children' => [],
                        ];
                    }

                    if (!isset($tree[$cat->parent_id]['children'][$cat->id])) {
                        //var_dump('create new parent id: ' . $cat->id . ' under the root);
                        $tree[$cat->parent_id]['children'][$cat->id] = [
                            'data' => $cat,
                            'children' => [],
                        ];
                    }

                    return $tree[$cat->parent_id]['children'][$cat->id];

                } else {
                    $parent = &$buildCategoryTreeNode($cats[$cat->parent_id], $tree, $cats);

                    if (!isset($parent['children'][$cat->id])) {
                        //var_dump('pushing '. $cat->id. ' to parent ' . $parent['data']->id);
                        $parent['children'][$cat->id] = [
                            'data' => $cat,
                            'children' => [],
                        ];
                    }

                    return $parent['children'][$cat->id];
                }
            };

            /**
             * Build a tree using only the given categories
             *
             * @param $cats     Associative array of category objects. Key is the cat id and value is the category object
             * @return array
             */
            $buildCategoryTree = function($cats) use ($buildCategoryTreeNode) {
                $tree = [];

                foreach ($cats as $cat) {
                    $buildCategoryTreeNode($cat, $tree, $cats);
                }

                return $tree;
            };

            $findLeaves = function($node) use (&$findLeaves) {
                if (empty($node[key($node)]['children'])) {
                    return $node;
                }

                $leaves = [];
                foreach ($node[key($node)]['children'] as $k => $v) {
                    $leaf = $findLeaves([ $k => $v]);

                    foreach($leaf as $k => $v) {
                        $leaves[$k] = $v;
                    }
                }

                return $leaves;
            };

            $buildDataDocument = function($records) use ($buildCategoryTree, $findLeaves, $buildCategoryDocument, $cats) {
                $categoryDoc = $buildCategoryDocument($cats);
                //echo $categoryDoc;

                $dom = new DOMDocument('1.0', 'utf-8');
                $dom->loadXML($categoryDoc);    // loading the doc with xml:id intact will cause a DOMDocument: xml:id is not a NCName in Entity
                $dom->formatOutput = true;
                $xpath = new DOMXPath($dom);

                // Mark the id attribute. This must happen after loadXML loaded the document into a DOM
                // @link https://stackoverflow.com/questions/23269067/using-domdocument-is-it-possible-to-get-all-elements-that-exists-within-a-certa
                foreach ($dom->getElementsByTagName('*') as $node) {
                    if ($id = $node->getAttribute('id')) {
                        // Internally mark the id as 'xml:id' for getElementById to work. Adding xml:id manually to the tag will cause loadXML to throw an error DOMDocument: xml:id is not a NCName in Entity
                        $node->setIdAttribute('id', true);
                    }
                };

                foreach ($records as $record) {
                    //$record = $records[10];
                    // Sort out the categories being assigned to this record
                    $cats = [];
                    foreach ($record->cats as $cat) {
                        $cats[$cat->id] = $cat;
                    }

                    // Find the significant categories along each category path
                    $categoryTree = $buildCategoryTree($cats);
                    $significantCategories = $findLeaves($categoryTree);

                    foreach ($significantCategories as $cat) {
                        // The function to create one <Item> node
                        $createItemNode = function ($data) use ($dom) {
                            $node = $dom->createElement("Item");
                            $node->setAttribute('id', $data->id);
                            $node->setAttribute('title', $data->title);
                            $node->setAttribute('duration', $data->duration);

                            if (!$data->published_date) {
                                $published_date = '';
                            } else {
                                // Given: ISO 8601 "2020-06-24T08:28:10Z" Convert to: "06-24-20"
                                $date = new DateTime($data->published_date);
                                $published_date = $date->format("m-d-y");
                            }
                            $node->setAttribute('published_date', $published_date);

                            // Internally mark the id as 'xml:id' for getElementById to work. Adding xml:id manually to the tag will cause loadXML to throw an error DOMDocument: xml:id is not a NCName in Entity
                            $node->setIdAttribute('id', true);

                            return $node;
                        };

                        $parent = $dom->getElementById($cat['data']->id);
                        $child = null;
                        if ($parent) {
                            $categories = $xpath->query(".//Category", $parent);
                            if ($categories->length > 0) {
                                // insert the <Item> tag before the first category
                                $parent->insertBefore($createItemNode($record), $categories->item(0));
                            } else {
                                // insert the <Item> tag to the end
                                $child = $parent->appendChild($createItemNode($record));
                            }
                        }
                    }
                }

                $doc = $dom->saveXML();

//            echo $doc;
//            exit;

                return $doc;
            };

            $dataDoc = $buildDataDocument($records);

            $this->doc = $dataDoc;

            return $this;
        }

    }