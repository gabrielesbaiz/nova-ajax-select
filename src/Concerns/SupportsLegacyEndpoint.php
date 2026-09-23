<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Concerns;

use Closure;
use Gabrielesbaiz\NovaAjaxSelect\Sources\EndpointSource;

/**
 * The 1.x URL mode, kept so existing call sites upgrade without edits.
 *
 * The browser fetches the application's own route and accepts both the legacy
 * `{value, display}` payload and Nova's `{value, label}`.
 */
trait SupportsLegacyEndpoint
{
    /**
     * Fetch options from an application route.
     *
     * The URL may contain `{resource-name}`, `{resource-id}` and one token per
     * parent attribute, e.g. `/api/cities/{province_id}`.
     *
     * @deprecated 2.0 Use options() or optionsFromModel() instead; removed in 3.0.
     */
    public function get(string $endpoint): static
    {
        return $this->endpoint($endpoint);
    }

    /**
     * Fetch options from an application route.
     *
     * @param  (Closure(mixed): mixed)|null  $transform
     */
    public function endpoint(string $url, ?Closure $transform = null): static
    {
        return $this->withOptionSource(new EndpointSource($url, $transform));
    }

    public function usesEndpoint(): bool
    {
        return $this->optionSource() instanceof EndpointSource;
    }

    public function endpointUrl(): ?string
    {
        $source = $this->optionSource();

        return $source instanceof EndpointSource ? $source->url : null;
    }
}
