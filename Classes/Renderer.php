<?php
declare(strict_types=1);

namespace Wwwision\Renderlets\Provider;

use Neos\Fusion\Core\Cache\ContentCache;
use Wwwision\Renderlets\Provider\Exception\FailedToRenderRenderlet;
use Wwwision\Renderlets\Provider\Exception\InvalidRenderletId;
use Wwwision\Renderlets\Provider\Exception\MissingRenderletParameter;
use Wwwision\Renderlets\Provider\Exception\UnknownRenderletParameters;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Fusion\Exception\MissingFusionObjectException;
use Neos\Fusion\Exception\RuntimeException as FusionRuntimeException;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\FusionService;
use Psr\Http\Message\ServerRequestInterface;
use Wwwision\Renderlets\Provider\Model\Renderlet;
use Wwwision\Renderlets\Provider\Model\RenderletId;

/**
 * @Flow\Scope("singleton")
 */
final class Renderer
{

    private ContextFactoryInterface $contextFactory;
    private FusionService $fusionService;
    private ContentCache $contentCache;

    public function __construct(ContextFactoryInterface $contextFactory, FusionService $fusionService, ContentCache $contentCache)
    {
        $this->contextFactory = $contextFactory;
        $this->fusionService = $fusionService;
        $this->contentCache = $contentCache;
    }

    /**
     * @throws MissingFusionObjectException | MissingRenderletParameter | FailedToRenderRenderlet
     **/
    public function render(ServerRequestInterface $request, RenderletId $id, array $parameters): Renderlet
    {
        $fakeActionRequest = ActionRequest::fromHttpRequest($request);
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($fakeActionRequest);
        $controllerContext = new ControllerContext($fakeActionRequest, new ActionResponse(), new Arguments(), $uriBuilder);

        $siteNode = $this->getSiteNode($request);
        $fusionPath = 'renderlets/' . $id->toString();

        $fusionRuntime = $this->fusionService->createRuntime($siteNode, $controllerContext);
        $fusionRuntime->setEnableContentCache(true);
        $fusionRuntime->pushContextArray([
            'node' => $siteNode,
            'documentNode' => $siteNode,
            'site' => $siteNode,
            'parameters' => $parameters,
        ]);
        try {
            try {
                $result = $fusionRuntime->render($fusionPath);
            } catch (FusionRuntimeException $exception) {
                throw $exception->getPrevious();
            }
            $result = $this->contentCache->processCacheSegments($result, false);
            $renderlet = Renderlet::fromJson($result);
        } catch (MissingFusionObjectException $exception) {
            throw new InvalidRenderletId(sprintf('Failed to render fusion path "%s" for node "%s" (exception)', $fusionPath, $siteNode->getIdentifier()), 1637756438, $exception);
        } catch (MissingRenderletParameter | UnknownRenderletParameters $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new FailedToRenderRenderlet(sprintf('Failed to render fusion path "%s" for node "%s" (exception)', $fusionPath, $siteNode->getIdentifier()), 1637225684, $exception);
        }
        $fusionRuntime->popContext();
        return $renderlet;
    }

    private function getSiteNode(ServerRequestInterface $request): TraversableNodeInterface
    {
        // TODO fetch current site node from $request
        /** @var ContentContext $context */
        $context = $this->contextFactory->create();
        $node = $context->getCurrentSiteNode();
        if (!$node instanceof TraversableNodeInterface) {
            throw new \RuntimeException('Failed to determine current site node', 1637225619);
        }
        return $node;
    }

}
