<?php
declare(strict_types=1);

namespace Cornelsen\Renderlets\Provider;

use Cornelsen\Renderlets\Provider\Exception\InvalidRenderletId;
use Cornelsen\Renderlets\Provider\Exception\MissingRenderletParameter;
use Cornelsen\Renderlets\Provider\Exception\UnknownRenderletParameters;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Middleware implements MiddlewareInterface
{

    private Renderer $renderer;
    private Context $securityContext;

    private const URI_PATH_PREFIX = '__renderlet';

    public function __construct(Renderer $renderer, Context $securityContext)
    {
        $this->renderer = $renderer;
        $this->securityContext = $securityContext;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $uriPathParts = explode('/', ltrim($request->getUri()->getPath(), '\/'));
        if ($uriPathParts[0] !== self::URI_PATH_PREFIX) {
            return $next->handle($request);
        }
        if (!isset($uriPathParts[1])) {
            return new Response(406, [], sprintf('Missing "id" path segment. Expected URI format: /%s/:renderlet-id', self::URI_PATH_PREFIX));
        }
        try {
            $renderletId = RenderletId::fromString($uriPathParts[1]);
        } catch (\InvalidArgumentException $e) {
            return new Response(406, [], sprintf('Invalid "id" path segment: %s', $e->getMessage()));
        }
        parse_str($request->getUri()->getQuery(), $queryParameters);
        $fakeActionRequest = ActionRequest::fromHttpRequest($request);
        $this->securityContext->setRequest($fakeActionRequest);
        try {
            $renderlet = $this->renderer->render($request, $renderletId, $queryParameters);
        } catch (InvalidRenderletId $exception) {
            return new Response(404, [], 'Unknown renderlet id');
        } catch (MissingRenderletParameter | UnknownRenderletParameters $exception) {
            return new Response(400, [], $exception->getMessage());
        }
        if ($request->hasHeader('If-None-Match') && $request->getHeaderLine('If-None-Match') === $renderlet->cacheId->toString()) {
            return new Response(304);
        }
        return new Response(200, ['Content-Type' => $renderlet->contentType, 'ETag' => $renderlet->cacheId->toString()], $renderlet->content);
    }
}
