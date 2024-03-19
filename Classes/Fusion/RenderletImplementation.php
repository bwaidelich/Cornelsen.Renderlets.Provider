<?php
declare(strict_types=1);

namespace Wwwision\Renderlets\Provider\Fusion;

use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Core\Cache\ContentCache;
use Wwwision\Renderlets\Provider\Exception\MissingRenderletParameter;
use Wwwision\Renderlets\Provider\Exception\UnknownRenderletParameters;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Wwwision\Renderlets\Provider\Model\CacheId;
use Wwwision\Renderlets\Provider\Model\Renderlet;

final class RenderletImplementation extends AbstractFusionObject
{

    /**
     * @Flow\Inject
     * @var ContentCache
     */
    protected $contentCache;

    private function cacheId(): CacheId
    {
        return CacheId::fromString($this->fusionValue('cacheId'));
    }

    private function parameters(): array
    {
        return $this->fusionValue('parameters');
    }

    private function httpHeaders(): array
    {
        return $this->fusionValue('httpHeaders');
    }

    public function evaluate(): string
    {
        $context = $this->runtime->getCurrentContext();
        $parameters = $context['parameters'];
        foreach ($this->parameters() as $parameterName => $required) {
            if ($required && empty($context['parameters'][$parameterName])) {
                throw new MissingRenderletParameter(sprintf('Missing/empty parameter "%s"', $parameterName), 1637760230);
            }
            unset($parameters[$parameterName]);
        }
        if ($parameters !== []) {
            throw new UnknownRenderletParameters(sprintf('Unknown parameter(s) "%s"', implode('", "', array_keys($parameters))), 1637831451);
        }
        $this->runtime->pushContextArray($context);
        $content = $this->runtime->render($this->path . '/renderer');
        $this->runtime->popContext();
        return Renderlet::fromContentCacheIdAndHttpHeaders(
            $this->contentCache->processCacheSegments($content, false),
            $this->cacheId(),
            $this->httpHeaders()
        )->toJson();
    }
}
