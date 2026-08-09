<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\Concerns\SerializesResourceAttributes;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MediaAsset
 */
class MediaAssetResource extends JsonResource
{
    use SerializesResourceAttributes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disk' => $this->disk,
            'path' => $this->path,
            'url' => $this->url,
            'title' => $this->title,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'visibility' => $this->enumValue(data_get($this->resource, 'visibility')),
            'metadata' => $this->metadata,
        ];
    }
}
