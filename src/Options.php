<?php
declare(strict_types=1);

namespace QMGateway;

use InvalidArgumentException;
use Uri\Rfc3986\Uri;

/**
 * How a Client behaves. It never changes after it is made: "with" gives you a changed copy (PHP 8.5 clone-with).
 *
 *     $options = (new Options())->with(['timeout' => 10.0, 'maxRetries' => 2]);
 */
final readonly class Options
{
    public const DEFAULT_BASE_URL = 'https://qmgateway.com';

    /**
     * @param string        $baseUrl    Where the API lives. Must be https. Plain http is accepted only for localhost (testing).
     * @param float         $timeout    Seconds to wait for one request, including connecting. A timeout counts as a network error.
     * @param int           $maxRetries Extra tries after the first one, for network errors, 429 and 5xx (0 turns retries off).
     * @param string|null   $caInfo     Path to a CA bundle, only if your PHP has none configured. Certificates are always checked.
     * @param Transport|null $transport Replaces the cURL transport (tests use QMGateway\Testing\FakeTransport).
     * @param \Closure|null $sleeper    fn(float $seconds): void, replaces the real pause between retries (tests use it to run fast).
     * @param string|null   $userAgent  Appended to the SDK's own User-Agent.
     */
    public function __construct(
        public string $baseUrl = self::DEFAULT_BASE_URL,
        public float $timeout = 30.0,
        public int $maxRetries = 4,
        public ?string $caInfo = null,
        public ?Transport $transport = null,
        public ?\Closure $sleeper = null,
        public ?string $userAgent = null,
    ) {
        $this->check();
    }

    /** A copy with some options changed. */
    public function with(array $changes): self
    {
        $copy = clone($this, $changes);
        $copy->check();
        return $copy;
    }

    private function check(): void
    {
        try { $uri = new Uri($this->baseUrl); } catch (\Throwable) { throw new InvalidArgumentException('baseUrl is not a valid web address.'); }
        $scheme = strtolower((string)$uri->getScheme()); $host = strtolower((string)$uri->getHost());
        $local = in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $local)) throw new InvalidArgumentException('baseUrl must use https (plain http is only allowed for localhost).');
        if ($host === '' || $uri->getUserInfo() !== null) throw new InvalidArgumentException('baseUrl must have a host name and no user name or password.');
        if ($this->timeout <= 0 || $this->timeout > 300) throw new InvalidArgumentException('timeout must be between 0 and 300 seconds.');
        if ($this->maxRetries < 0 || $this->maxRetries > 10) throw new InvalidArgumentException('maxRetries must be between 0 and 10.');
        if ($this->userAgent !== null && preg_match('/[\r\n]/', $this->userAgent)) throw new InvalidArgumentException('userAgent must be one line.');
    }

    /** The base URL without a trailing slash. */
    public function root(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
