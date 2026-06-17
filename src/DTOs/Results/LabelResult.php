<?php

namespace Laraditz\Courier\DTOs\Results;

readonly class LabelResult
{
    /**
     * format: 'pdf' | 'zpl' | 'url'
     * 'pdf'/'zpl' → content is base64-encoded binary
     * 'url'       → content is a direct download URL
     * Base64 encoding is the Mapper's responsibility.
     */
    public function __construct(
        public string $waybillNumber,
        public string $format,
        public string $content,
        private array $meta = [],
    ) {}

    public function meta(): array
    {
        return $this->meta;
    }
}
