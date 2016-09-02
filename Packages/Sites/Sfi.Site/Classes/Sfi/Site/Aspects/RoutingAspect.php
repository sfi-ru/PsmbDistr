<?php
namespace Sfi\Site\Aspects;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Neos\Domain\Service\NodeSearchServiceInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class RoutingAspect {

	/**
	 * @Flow\Inject
	 * @var NodeTypeManager
	 */
	protected $nodeTypeManager;

	/**
	 * @Flow\Inject
	 * @var NodeSearchServiceInterface
	 */
	protected $nodeSearchService;

	/**
	 * Hardcoded performace optimization for /a/*.html links
	 * TODO: remove when https://github.com/neos/neos-development-collection/pull/672 is firstUriPartExploded
	 *
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint
	 * @Flow\Around("method(TYPO3\Neos\Routing\FrontendNodeRoutePartHandler->getRelativeNodePathByUriPathSegmentProperties())")
	 * @return void
	 */
	public function speedupRouting(JoinPointInterface $joinPoint) {
		$relativeRequestPath = $joinPoint->getMethodArgument('relativeRequestPath');

		// If within /a/path
		if (strlen($relativeRequestPath) > 3 && substr($relativeRequestPath, 0, strlen('a/')) === 'a/') {
			/** @var NodeInterface $siteNode */
			$siteNode = $joinPoint->getMethodArgument('siteNode');
			$documentNodeType = $this->nodeTypeManager->getNodeType('TYPO3.Neos:Document');
			$context = $siteNode->getContext();

			$baseNode = $siteNode->getNode('a');
			$pathSegments = explode('/', $relativeRequestPath);
			$pathSegment = end($pathSegments);
			$nodes = $this->nodeSearchService->findByProperties(['uriPathSegment' => $pathSegment], [$documentNodeType], $context, $baseNode);
			$filteredNodes = array_filter($nodes, function ($currentNode) use ($baseNode) {
				// Only consider direct children
				return $currentNode->getParent()->getIdentifier() === $baseNode->getIdentifier();
			});
			if ($filteredNodes) {
				$node = reset($filteredNodes);
				$nodeName = $node->getName();
			} else {
				return false;
			}
			return 'a/' . $nodeName;
		} else {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}
	}
}
