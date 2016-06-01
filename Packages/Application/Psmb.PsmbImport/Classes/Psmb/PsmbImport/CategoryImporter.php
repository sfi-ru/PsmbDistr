<?php
namespace Psmb\PsmbImport;

use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeTemplate;
use Ttree\ContentRepositoryImporter\DataType\Slug;

class CategoryImporter extends Importer
{
  /**
   * Import data from the given data provider
   *
   * @return void
   */
  public function process()
  {
    $targetNodeName = $this->options['targetNode'];
    $this->storageNode = $this->siteNode->getNode($targetNodeName);
    if ($this->storageNode === null) {
      $storageNodeTemplate = new NodeTemplate();
      $storageNodeTemplate->setNodeType($this->nodeTypeManager->getNodeType('TYPO3.Neos:Shortcut'));
      $storageNodeTemplate->setProperty('title', $targetNodeName);
      $storageNodeTemplate->setProperty('uriPathSegment', $targetNodeName);
      $storageNodeTemplate->setName($targetNodeName);
      $this->storageNode = $this->siteNode->createNodeFromTemplate($storageNodeTemplate);
    }

    $nodeTemplate = new NodeTemplate();
    $nodeTemplate->setNodeType($this->nodeTypeManager->getNodeType('Sfi.Site:Tag'));
    $this->processBatch($nodeTemplate);
  }

  /**
   * @param NodeTemplate $nodeTemplate
   * @param array $data
   */
  public function processRecord(NodeTemplate $nodeTemplate, array $data)
  {
    $this->unsetAllNodeTemplateProperties($nodeTemplate);

    $title = $data['title'];
    $externalIdentifier = $data['__externalIdentifier'];
    $desiredNodeName = Slug::create($title)->getValue();
    if ($this->skipNodeProcessing($externalIdentifier, '123', $this->siteNode, false)) {
      return null;
    }

    $nodeTemplate->setProperty('title', $title);
    $nodeTemplate->setProperty('originalIdentifier', $externalIdentifier);
    if (isset($data['replaceVariants'])) {
      $nodeTemplate->setProperty('replaceVariants', $data['replaceVariants']);
    }

    $parentNode = $data['__parentIdentifier'] ? $this->getCategoryByOriginalId($data['__parentIdentifier']) : $this->storageNode;
    if (!$parentNode) {
      throw new \Exception("No parent node found with identifier " . $data['__parentIdentifier'], 1);
    }
    $node = $this->createUniqueNode($parentNode, $nodeTemplate, $desiredNodeName);

    $this->registerNodeProcessing($node, $externalIdentifier);
  }

  protected function getCategoryByOriginalId($id) {
    $q = new FlowQuery(array($this->siteNode));
    return $q->find('[instanceof Sfi.Site:Tag]')->filter('[originalIdentifier = "' . $id  .'"]')->get(0);
  }
}
