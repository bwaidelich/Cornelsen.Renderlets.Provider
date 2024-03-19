<?php
declare(strict_types=1);

namespace Wwwision\Renderlets\Provider\Fusion;

use Neos\Fusion\FusionObjects\AbstractFusionObject;

/**
 * This is a hack to replace the JSON encoded Content Cache Markers with their original ASCII value
 * @see https://neos-project.slack.com/archives/C050C8FEK/p1631719290265300?thread_ts=1554993047.233100&cid=C050C8FEK
 */
final class ReplaceJsonCacheMarkerImplementation extends AbstractFusionObject
{

    /**
     * @return mixed
     */
    public function evaluate()
    {
        $value = $this->fusionValue('value');
        if (!is_string($value) || $value === '') {
            return $value;
        }
        $jsonCacheMarkers = ["\u0002", "\u0003", "\u001f"];
        $asciiCacheMarkers = ["\x02", "\x03", "\x1f"];
        return str_replace($jsonCacheMarkers, $asciiCacheMarkers, $value);
    }
}
