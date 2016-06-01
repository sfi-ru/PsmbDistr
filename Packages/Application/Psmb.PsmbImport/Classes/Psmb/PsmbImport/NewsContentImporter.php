<?php
namespace Psmb\PsmbImport;

use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeTemplate;
use Ttree\ContentRepositoryImporter\DataType\Slug;

use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Resource\ResourceManager;
use TYPO3\Media\Domain\Model\Image;
use TYPO3\Media\Domain\Model\ImageVariant;
use TYPO3\Media\Domain\Repository\ImageRepository;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;

class NewsContentImporter extends Importer
{
  /**
   * @Flow\Inject
   * @var ObjectManagerInterface
   */
  protected $objectManager;

  /**
   * @Flow\Inject
   * @var ResourceManager
   */
  protected $resourceManager;

  /**
	 * @Flow\Inject
	 * @var ImageRepository
	 */
	protected $imageRepository;

  public function process()
  {
    $nodeTemplate = new NodeTemplate();
    $this->processBatch($nodeTemplate);
  }
  /**
   * @param NodeTemplate $nodeTemplate
   * @param array $data
   * @return NodeInterface
   */
  public function processRecord(NodeTemplate $nodeTemplate, array $data)
  {
    $this->unsetAllNodeTemplateProperties($nodeTemplate);

    $externalIdentifier = $data['__externalIdentifier'];
    if ($this->skipNodeProcessing($externalIdentifier, '123', $this->siteNode, false)) {
      return null;
    }

    $newsRecordMapping = $this->processedNodeService->get('Psmb\PsmbImport\NewsImporter', $data['__externalIdentifier']);
    if ($newsRecordMapping === null) {
        $this->log(sprintf('Skip "%s", missing node', $data['__externalIdentifier']), LOG_ERR);
        return null;
    }
    $newsNode = $this->siteNode->getNode($newsRecordMapping->getNodePath());
    $newsNode->setProperty('credit', $data['credit']);
    $mainCollection = $newsNode->getNode('main');

    if ($data['coverImage']['filename']) {
      $filePath = $this->getFilePath($data['coverImage']['filename']);
      if ($filePath) {
        $image = $this->importImage($filePath);
        $newsNode->setProperty('coverImage', $image);
      }
    }

    foreach ($data['main'] as $contentItem) {
      $nodeTemplate = new NodeTemplate();
      $nodeTemplate->setNodeType($this->nodeTypeManager->getNodeType($contentItem['_type']));
      switch ($contentItem['_type']) {
        case 'Psmb.NodeTypes:Text':
          $nodeTemplate->setProperty('text', $contentItem['text']);
          break;
        case 'Psmb.NodeTypes:Image':
          $filePath = $this->getFilePath($contentItem['filename']);
          if ($filePath) {
            $image = $this->importImage($filePath);
            $nodeTemplate->setProperty('image', $image);
            $nodeTemplate->setProperty('caption', $contentItem['caption']);
          }
          break;
        case 'Sfi.YouTube:YouTube':
          $nodeTemplate->setProperty('videoUrl', $contentItem['videoUrl']);
          $nodeTemplate->setProperty('caption', $contentItem['caption']);
          break;

        default:
          $nodeTemplate->setProperty('text', $contentItem['text']);
          break;
      }
      $mainCollection->createNodeFromTemplate($nodeTemplate);
    }
    $this->registerNodeProcessing($newsNode, $externalIdentifier);
  }

  protected function getFilePath($fileName) {
    if (in_array(pathinfo(strtolower($fileName), PATHINFO_EXTENSION), ["jpg", "jpeg", "gif"])) {
      $filePath = FLOW_PATH_ROOT . '/uploads/' . $fileName;
      if (!file_exists($filePath)) {
        $this->log("Missing file: " . $filePath);
      } else {
        return $filePath;
      }
    } else {
      $this->log("Illegal mediaItem file extension of file: " . $fileName);
      return null;
    }
  }

  protected function importImage($filename) {
		$resource = $this->resourceManager->importResource($filename);

		$image = new Image($resource);
		$this->imageRepository->add($image);

		$processingInstructions = [];
		return $this->objectManager->get('TYPO3\Media\Domain\Model\ImageVariant', $image, $processingInstructions);
	}
}
