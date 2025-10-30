<?php

namespace App\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Routes http.telemetry records to per-source rotating files under storage/logs/http/{source}-YYYY-MM-DD.log
 * Source is resolved from context key 'http.source' or nested ['http']['source'].
 */
class HttpSourceRouterHandler extends AbstractProcessingHandler
{
    /** @var array<string, RotatingFileHandler> */
    protected array $handlers = [];

    /** @var array<int, \Monolog\Handler\HandlerInterface> */
    protected array $mirrorHandlers = [];

    /** @var array<string, bool> */
    protected array $mirrorAllowlist = [];

    protected ?Level $mirrorMinLevel = null;

    protected string $dir;
    protected int $days;

    /**
     * @param array<string,mixed> $mirror Mirror options: [
     *   'sources' => string[] allowlisted http.source values,
     *   'min_level' => string|int|null Monolog level name or value,
     *   'slack' => ['enabled'=>bool,'url'=>string|null],
     *   'papertrail' => ['enabled'=>bool,'host'=>string|null,'port'=>int|null],
     *   'discord' => ['enabled'=>bool,'url'=>string|null]
     * ]
     */
    public function __construct(string $dir, int $days = 14, int|string|Level $level = Level::Info, bool $bubble = true, array $mirror = [])
    {
        parent::__construct($level, $bubble);

        $this->dir = rtrim($dir, '/');
        $this->days = $days;

        if (! is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }

        $this->configureMirrors($mirror);
    }

    protected function write(LogRecord $record): void
    {
        $source = $this->extractSource($record);
        $handler = $this->forSource($source, $record->level);

        // Forward the record to the per-source rotating handler
        $handler->handle($record);

        // Conditional mirroring
        if ($this->shouldMirror($source, $record->level)) {
            foreach ($this->mirrorHandlers as $mh) {
                $mh->handle($record);
            }
        }
    }

    protected function extractSource(LogRecord $record): string
    {
        $ctx = $record->context ?? [];
        $source = $ctx['http.source'] ?? ($ctx['http']['source'] ?? 'default');
        $source = is_string($source) ? $source : 'default';
        // Slugify to safe filename
        $source = strtolower($source);
        $source = preg_replace('/[^a-z0-9_\-.]/i', '_', $source) ?? 'default';
        return $source === '' ? 'default' : $source;
    }

    protected function forSource(string $source, Level $level): RotatingFileHandler
    {
        if (! isset($this->handlers[$source])) {
            $filename = $this->dir . '/' . $source . '.log';
            $handler = new RotatingFileHandler($filename, $this->days, $level, true);

            // Use the same formatter if one is set on this router
            $formatter = $this->getFormatter();
            if ($formatter instanceof FormatterInterface) {
                $handler->setFormatter($formatter);
            }

            $this->handlers[$source] = $handler;
        }

        return $this->handlers[$source];
    }

    /**
     * @param array<string,mixed> $mirror
     */
    protected function configureMirrors(array $mirror): void
    {
        // Allowlist
        $sources = [];
        if (isset($mirror['sources'])) {
            if (is_string($mirror['sources'])) {
                $sources = array_filter(array_map('trim', explode(',', $mirror['sources'])));
            } elseif (is_array($mirror['sources'])) {
                $sources = $mirror['sources'];
            }
        }
        foreach ($sources as $s) {
            $this->mirrorAllowlist[strtolower((string) $s)] = true;
        }

        // Min level
        $min = $mirror['min_level'] ?? null;
        if ($min !== null) {
            $this->mirrorMinLevel = Level::fromName(is_string($min) ? strtoupper($min) : $min);
        }

        // Slack mirror
        $slack = $mirror['slack'] ?? [];
        $slackEnabled = (bool) ($slack['enabled'] ?? false);
        $slackUrl = $slack['url'] ?? null;
        if ($slackEnabled && is_string($slackUrl) && $slackUrl !== '') {
            $this->mirrorHandlers[] = new \Monolog\Handler\SlackWebhookHandler($slackUrl, level: $this->mirrorMinLevel ?? Level::Warning, bubble: true);
        }

    // Papertrail mirror
        $pt = $mirror['papertrail'] ?? [];
        $ptEnabled = (bool) ($pt['enabled'] ?? false);
        $ptHost = $pt['host'] ?? null;
        $ptPort = isset($pt['port']) ? (int) $pt['port'] : null;
        if ($ptEnabled && is_string($ptHost) && $ptHost !== '' && is_int($ptPort) && $ptPort > 0) {
            $this->mirrorHandlers[] = new \Monolog\Handler\SyslogUdpHandler($ptHost, $ptPort, level: $this->mirrorMinLevel ?? Level::Warning, bubble: true);
        }

        // Discord mirror
        $dc = $mirror['discord'] ?? [];
        $dcEnabled = (bool) ($dc['enabled'] ?? false);
        $dcUrl = $dc['url'] ?? null;
        if ($dcEnabled && is_string($dcUrl) && $dcUrl !== '') {
            $this->mirrorHandlers[] = new \App\Logging\DiscordWebhookHandler($dcUrl, level: $this->mirrorMinLevel ?? Level::Warning, bubble: true);
        }
    }

    protected function shouldMirror(string $source, Level $level): bool
    {
        if ($this->mirrorHandlers === []) {
            return false;
        }
        $src = strtolower($source);
        if ($this->mirrorAllowlist !== [] && ! isset($this->mirrorAllowlist[$src])) {
            return false;
        }
        if ($this->mirrorMinLevel instanceof Level && $level->value < $this->mirrorMinLevel->value) {
            return false;
        }
        return true;
    }
}
