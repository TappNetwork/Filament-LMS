<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

use Illuminate\Support\Facades\Log;

/**
 * Extracts HTML content from Articulate Storyline slide JS files (html5/data/js/{slideId}.js).
 * Parses the JSON slide data and builds HTML from textLib/vartext blocks (paragraphs and lists).
 * When nodeExtractorScriptPath is set, runs a Node script to extract slide JSON (avoids PHP string/JSON edge cases).
 */
final class ArticulateSlideContentExtractor
{
    public function __construct(
        private readonly ?string $nodeExtractorScriptPath = null,
    ) {}

    /**
     * Load and decode slide JSON from a slide's JS file. Returns null if file missing or invalid.
     * Tries Node script first when configured and available; falls back to PHP extraction.
     *
     * @return array<string, mixed>|null
     */
    public function getSlideData(string $extractedPath, string $slideId): ?array
    {
        $path = mb_rtrim($extractedPath, '/').'/html5/data/js/'.basename($slideId).'.js';
        if (! is_file($path)) {
            return null;
        }

        if ($this->nodeExtractorScriptPath !== null && is_file($this->nodeExtractorScriptPath)) {
            $data = $this->getSlideDataViaNode($path, $slideId);
            if (is_array($data)) {
                return $data;
            }
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        $content = $this->stripUtf8Bom($content);

        $json = $this->extractJsonFromProvideData($content);
        if ($json === null) {
            Log::channel('single')->info('CC import: slide JSON extraction returned null', [
                'context' => 'cc-import',
                'slide_id' => $slideId,
                'slide_js_path' => $path,
            ]);

            return null;
        }

        $data = $this->decodeSlideJson($json, $slideId, false);
        if (is_array($data)) {
            return $data;
        }

        $json = $this->sanitizeJsonForDecode($json);
        $data = $this->decodeSlideJson($json, $slideId, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Run the Node extractor script and return decoded slide data, or null on failure.
     *
     * @return array<string, mixed>|null
     */
    private function getSlideDataViaNode(string $slideJsPath, string $slideId): ?array
    {
        $nodeBinary = $this->resolveNodeBinary();
        $env = $this->getNodeProcessEnv();
        $pipes = [];
        $proc = @proc_open(
            [$nodeBinary, $this->nodeExtractorScriptPath, $slideJsPath],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            null,
            $env,
        );
        if (! is_resource($proc)) {
            Log::channel('single')->warning('CC import: Node extractor proc_open failed', [
                'context' => 'cc-import',
                'slide_id' => $slideId,
                'slide_js_path' => $slideJsPath,
            ]);

            return null;
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        if ($exitCode !== 0) {
            Log::channel('single')->warning('CC import: Node extractor exited non-zero', [
                'context' => 'cc-import',
                'slide_id' => $slideId,
                'exit_code' => $exitCode,
                'stderr' => $stderr !== false && $stderr !== '' ? mb_substr($stderr, 0, 500) : null,
            ]);

            return null;
        }
        if ($stdout === false || $stdout === '') {
            Log::channel('single')->warning('CC import: Node extractor returned empty stdout', [
                'context' => 'cc-import',
                'slide_id' => $slideId,
            ]);

            return null;
        }
        $data = json_decode($stdout, true);
        if (! is_array($data)) {
            Log::channel('single')->warning('CC import: Node extractor stdout was not valid JSON', [
                'context' => 'cc-import',
                'slide_id' => $slideId,
                'json_error' => json_last_error_msg(),
                'stdout_length' => strlen($stdout),
            ]);

            return null;
        }

        return $data;
    }

    /**
     * Resolve the node binary path so the subprocess can find it (web server often has minimal PATH).
     */
    private function resolveNodeBinary(): string
    {
        if (function_exists('config')) {
            $configured = config('filament-lms.node_binary');
            if (is_string($configured) && $configured !== '' && is_executable($configured)) {
                return $configured;
            }
        }
        $candidates = ['/opt/homebrew/bin/node', '/usr/local/bin/node'];
        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'node';
    }

    /**
     * Environment for the node subprocess: current env with PATH extended so node is findable.
     *
     * @return array<string, string>
     */
    private function getNodeProcessEnv(): array
    {
        $env = getenv() ?: [];
        $extraPaths = ['/opt/homebrew/bin', '/usr/local/bin'];
        $currentPath = $env['PATH'] ?? '';
        $env['PATH'] = implode(':', array_merge($extraPaths, array_filter([$currentPath])));

        return $env;
    }

    /**
     * Decode JSON with UTF-8 substitution and log on failure for investigation.
     *
     * @return array<string, mixed>|null
     */
    private function decodeSlideJson(string $json, string $slideId, bool $afterSanitize): ?array
    {
        $flags = JSON_INVALID_UTF8_SUBSTITUTE;
        $data = json_decode($json, true, 512, $flags);

        if (is_array($data)) {
            return $data;
        }

        $error = json_last_error_msg();
        $excerptStart = mb_substr($json, 0, 200);
        $excerptEnd = mb_strlen($json) > 250 ? mb_substr($json, -200) : '';

        Log::channel('single')->warning('CC import: slide JSON decode failed', [
            'context' => 'cc-import',
            'slide_id' => $slideId,
            'json_error' => $error,
            'json_error_code' => json_last_error(),
            'json_length' => strlen($json),
            'after_sanitize' => $afterSanitize,
            'excerpt_start' => $excerptStart,
            'excerpt_end' => $excerptEnd,
        ]);

        return null;
    }

    /**
     * Extract HTML from a slide's JS file. Returns null if file missing or invalid.
     */
    public function extract(string $extractedPath, string $slideId): ?string
    {
        $data = $this->getSlideData($extractedPath, $slideId);
        if ($data === null) {
            return null;
        }

        return $this->extractFromSlideData($data);
    }

    /**
     * Build HTML from already-decoded slide data (e.g. from getSlideData). Use for assessment detection + text without double file read.
     *
     * @param  array<string, mixed>  $data
     */
    public function extractFromSlideData(array $data): string
    {
        return $this->buildHtmlFromSlideData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function getSlideTitle(array $data): ?string
    {
        $title = $data['title'] ?? null;

        return is_string($title) && $title !== '' ? $title : null;
    }

    private function extractJsonFromProvideData(string $content): ?string
    {
        $jsonStr = $this->extractJsonWithDoubleQuotePattern($content);
        if ($jsonStr !== null) {
            return $jsonStr;
        }

        return $this->extractJsonWithSingleQuotePattern($content);
    }

    /**
     * Try pattern: globalProvideData("slide", "{...}")
     */
    private function extractJsonWithDoubleQuotePattern(string $content): ?string
    {
        $needle = '("slide", "';
        $start = strpos($content, $needle);
        if ($start === false) {
            return null;
        }
        $start += strlen($needle);
        $len = strlen($content);
        $end = null;
        for ($i = $start; $i < $len; $i++) {
            if ($content[$i] === '"') {
                $backslashes = 0;
                $p = $i - 1;
                while ($p >= $start && $content[$p] === '\\') {
                    $backslashes++;
                    $p--;
                }
                if ($backslashes % 2 === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        if ($end === null) {
            return null;
        }
        $jsonStr = substr($content, $start, $end - $start);
        if ($jsonStr === '') {
            return null;
        }
        $placeholder = "\x00";
        $jsonStr = str_replace('\\\\\"', $placeholder, $jsonStr);
        $jsonStr = str_replace('\\"', '"', $jsonStr);
        $jsonStr = str_replace($placeholder, '\\\\\"', $jsonStr);

        return $jsonStr;
    }

    /**
     * Try pattern: globalProvideData('slide', '{...}')
     */
    private function extractJsonWithSingleQuotePattern(string $content): ?string
    {
        $needle = "('slide', '";
        $start = strpos($content, $needle);
        if ($start === false) {
            return null;
        }
        $start += strlen($needle);
        $len = strlen($content);
        $end = null;
        for ($i = $start; $i < $len; $i++) {
            if ($content[$i] === "'") {
                $backslashes = 0;
                $p = $i - 1;
                while ($p >= $start && $content[$p] === '\\') {
                    $backslashes++;
                    $p--;
                }
                if ($backslashes % 2 === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        if ($end === null) {
            return null;
        }
        $jsonStr = substr($content, $start, $end - $start);
        if ($jsonStr === '') {
            return null;
        }
        $placeholder = "\x00";
        $jsonStr = str_replace("\\\\'", $placeholder, $jsonStr);
        $jsonStr = str_replace("\\'", "'", $jsonStr);
        $jsonStr = str_replace($placeholder, "\\\\'", $jsonStr);

        return $jsonStr;
    }

    private function stripUtf8Bom(string $content): string
    {
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            return substr($content, 3);
        }

        return $content;
    }

    /**
     * Try to fix common issues that cause json_decode to fail.
     */
    private function sanitizeJsonForDecode(string $json): string
    {
        $sanitized = mb_convert_encoding($json, 'UTF-8', 'UTF-8');
        if ($sanitized !== false) {
            $json = $sanitized;
        }
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;
        $json = $this->escapeLiteralNewlinesInsideJsonStrings($json);

        return $json;
    }

    /**
     * Replace literal newline/carriage return inside JSON double-quoted strings with \n and \r
     * so that json_decode can succeed (literal U+000A/U+000D inside strings are invalid in JSON).
     */
    private function escapeLiteralNewlinesInsideJsonStrings(string $json): string
    {
        $len = strlen($json);
        $result = '';
        $inString = false;
        $i = 0;
        while ($i < $len) {
            $ch = $json[$i];
            if (! $inString) {
                $result .= $ch;
                if ($ch === '"') {
                    $inString = true;
                }
                $i++;

                continue;
            }
            if ($ch === '\\' && $i + 1 < $len) {
                $result .= $ch.$json[$i + 1];
                $i += 2;

                continue;
            }
            if ($ch === '"') {
                $result .= $ch;
                $inString = false;
                $i++;

                continue;
            }
            if ($ch === "\n") {
                $result .= '\\n';
                $i++;

                continue;
            }
            if ($ch === "\r") {
                $result .= '\\r';
                $i++;

                continue;
            }
            $result .= $ch;
            $i++;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildHtmlFromSlideData(array $data): string
    {
        $parts = [];
        $slideLayers = $data['slideLayers'] ?? [];
        foreach ($slideLayers as $layer) {
            $objects = $layer['objects'] ?? [];
            foreach ($objects as $obj) {
                $textLib = $obj['textLib'] ?? null;
                if (! is_array($textLib)) {
                    continue;
                }
                foreach ($textLib as $textData) {
                    $vartext = $textData['vartext'] ?? null;
                    if (! is_array($vartext)) {
                        continue;
                    }
                    $blocks = $vartext['blocks'] ?? [];
                    $listItems = [];
                    foreach ($blocks as $block) {
                        $text = $this->getBlockText($block);
                        if ($text === '') {
                            continue;
                        }
                        $listStyle = $block['style']['listStyle'] ?? null;
                        $isBullet = is_array($listStyle) && ($listStyle['listType'] ?? '') === 'bullet';
                        $escaped = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
                        if ($isBullet) {
                            $listItems[] = '<li>'.$escaped.'</li>';
                        } else {
                            if ($listItems !== []) {
                                $parts[] = '<ul>'.implode('', $listItems).'</ul>';
                                $listItems = [];
                            }
                            $parts[] = '<p>'.$escaped.'</p>';
                        }
                    }
                    if ($listItems !== []) {
                        $parts[] = '<ul>'.implode('', $listItems).'</ul>';
                    }
                }
            }
        }

        return mb_trim(implode("\n", $parts));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function getBlockText(array $block): string
    {
        $spans = $block['spans'] ?? [];
        $texts = [];
        foreach ($spans as $span) {
            $t = $span['text'] ?? '';
            if (is_string($t)) {
                $texts[] = $t;
            }
        }

        return mb_trim(implode('', $texts));
    }

    /**
     * For Assessment slides: return only intro/directions HTML (exclude question, UI labels, feedback).
     *
     * @param  array<string, mixed>  $data
     */
    public function extractAssessmentIntroFromSlideData(array $data): string
    {
        $blocks = $this->collectBlocksFromSlideData($data);
        $uiBlacklist = [
            'assessment',
            'session 1:', 'session 2:', 'session 3:', 'session 4:', 'session 5:', 'session 6:', 'session 7:', 'session 8:',
            'question 1 of 4:', 'question 2 of 4:', 'question 3 of 4:', 'question 4 of 4:',
            'question 1 of 3:', 'question 2 of 3:', 'question 3 of 3:',
            'submit', 'try again', 'incorrect', 'continue', 'correct',
            'that is incorrect. please try again.', 'you did not select the correct response.',
            "that's right! you selected the correct response.",
        ];
        $parts = [];
        $listItems = [];
        foreach ($blocks as [$text, $isBullet]) {
            $normalized = mb_strtolower(mb_trim($text));
            if ($normalized === '') {
                continue;
            }
            foreach ($uiBlacklist as $black) {
                if ($normalized === $black || mb_strpos($normalized, $black) === 0) {
                    continue 2;
                }
            }
            if (mb_substr(mb_trim($text), -1) === '?') {
                break;
            }
            $escaped = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
            if ($isBullet) {
                $listItems[] = '<li>'.$escaped.'</li>';
            } else {
                if ($listItems !== []) {
                    $parts[] = '<ul>'.implode('', $listItems).'</ul>';
                    $listItems = [];
                }
                $parts[] = '<p>'.$escaped.'</p>';
            }
        }
        if ($listItems !== []) {
            $parts[] = '<ul>'.implode('', $listItems).'</ul>';
        }

        return mb_trim(implode("\n", $parts));
    }

    /**
     * For Assessment slides: return first question text and its option labels (for form name and RADIO field).
     *
     * @param  array<string, mixed>  $data
     * @return array{question: string, options: array<int, string>}|null
     */
    public function getAssessmentFirstQuestionAndOptions(array $data): ?array
    {
        $blocks = $this->collectBlocksFromSlideData($data);
        $question = null;
        $options = [];
        $foundQuestion = false;
        foreach ($blocks as [$text, $isBullet]) {
            $t = mb_trim($text);
            if ($t === '') {
                continue;
            }
            if (! $foundQuestion) {
                if (mb_substr($t, -1) === '?') {
                    $question = $t;
                    $foundQuestion = true;
                }

                continue;
            }
            if ($isBullet) {
                $options[] = $t;
            } else {
                break;
            }
        }
        if ($question === null || $question === '') {
            return null;
        }

        return ['question' => $question, 'options' => $options];
    }

    /**
     * Collect all text blocks from slide data as [text, isBullet].
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{0: string, 1: bool}>
     */
    private function collectBlocksFromSlideData(array $data): array
    {
        $out = [];
        $slideLayers = $data['slideLayers'] ?? [];
        foreach ($slideLayers as $layer) {
            $objects = $layer['objects'] ?? [];
            foreach ($objects as $obj) {
                $textLib = $obj['textLib'] ?? null;
                if (! is_array($textLib)) {
                    continue;
                }
                foreach ($textLib as $textData) {
                    $vartext = $textData['vartext'] ?? null;
                    if (! is_array($vartext)) {
                        continue;
                    }
                    $blocks = $vartext['blocks'] ?? [];
                    foreach ($blocks as $block) {
                        $text = $this->getBlockText($block);
                        if ($text === '') {
                            continue;
                        }
                        $listStyle = $block['style']['listStyle'] ?? null;
                        $isBullet = is_array($listStyle) && ($listStyle['listType'] ?? '') === 'bullet';
                        $out[] = [$text, $isBullet];
                    }
                }
            }
        }

        return $out;
    }
}
