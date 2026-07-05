<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Output;

/**
 * Renders the standalone HTML report: template + inlined vis-network
 * bundle + embedded analysis JSON, joined by plain string replacement
 * at generation time. No build step, and the resulting file opens with
 * zero network dependency.
 *
 * The JSON is encoded with JSON_HEX_TAG (& friends) so no "</script>"
 * sequence can ever appear inside the embedded document.
 */
final class HtmlReporter
{
    public function __construct(
        private readonly string $templatePath = __DIR__ . '/templates/report.html',
        private readonly string $visBundlePath = __DIR__ . '/../../resources/vendor/vis-network.min.js',
    ) {
    }

    public function render(AnalysisResult $result): string
    {
        $template = @file_get_contents($this->templatePath);

        if ($template === false) {
            throw new \RuntimeException("HTML template not found: {$this->templatePath}");
        }

        $visBundle = @file_get_contents($this->visBundlePath);

        if ($visBundle === false) {
            throw new \RuntimeException(
                "vis-network bundle not found: {$this->visBundlePath} — see resources/vendor/README.md",
            );
        }

        $json = json_encode(
            (new JsonReporter())->toArray($result),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );

        // Order matters: the bundle goes in first so the JSON (which
        // could theoretically contain a placeholder-looking string from
        // analyzed code) is inserted last and never re-scanned.
        $html = str_replace('__VIS_NETWORK_JS__', $visBundle, $template);

        return str_replace('__ANALYSIS_JSON__', $json, $html);
    }
}
