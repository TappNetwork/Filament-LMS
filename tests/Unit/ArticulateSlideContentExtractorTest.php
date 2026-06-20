<?php

declare(strict_types=1);

use Tapp\FilamentLms\Services\CommonCartridge\ArticulateSlideContentExtractor;

beforeEach(function () {
    $this->extractor = new ArticulateSlideContentExtractor;
});

test('extract returns null when slide js file is missing', function () {
    $tmp = sys_get_temp_dir().'/articulate-test-'.uniqid();
    mkdir($tmp, 0755, true);
    mkdir($tmp.'/html5/data/js', 0755, true);

    $result = $this->extractor->extract($tmp, 'NonExistentSlideId');

    expect($result)->toBeNull();
    @rmdir($tmp.'/html5/data/js');
    @rmdir($tmp.'/html5/data');
    @rmdir($tmp.'/html5');
    @rmdir($tmp);
});

test('extract returns html from slide js with textLib and vartext blocks', function () {
    $tmp = realpath(sys_get_temp_dir()).'/articulate-test-'.uniqid('', true);
    mkdir($tmp, 0755, true);
    mkdir($tmp.'/html5/data/js', 0755, true);

    $slideId = 'TestSlide123';
    $json = [
        'title' => 'What To Expect',
        'slideLayers' => [
            [
                'objects' => [
                    [
                        'textLib' => [
                            [
                                'vartext' => [
                                    'blocks' => [
                                        [
                                            'spans' => [['text' => 'What To Expect']],
                                            'style' => ['listStyle' => ['listType' => 'none']],
                                        ],
                                        [
                                            'spans' => [['text' => 'Cycle of Instruction:']],
                                            'style' => ['listStyle' => ['listType' => 'bullet']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
    $rawJson = json_encode($json);
    $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $rawJson);
    $jsContent = "window.globalProvideData('slide', '".$escaped."');";
    $path = $tmp.'/html5/data/js/'.$slideId.'.js';
    $written = file_put_contents($path, $jsContent);
    expect($written)->toBeGreaterThan(0);
    expect(file_exists($path))->toBeTrue();

    $result = $this->extractor->extract($tmp, $slideId);

    expect($result)->not->toBeNull();
    expect($result)->toContain('What To Expect');
    expect($result)->toContain('Cycle of Instruction');
    expect($result)->toContain('<p>');
    expect($result)->toContain('<ul>');
    expect($result)->toContain('<li>');

    unlink($path);
    @rmdir($tmp.'/html5/data/js');
    @rmdir($tmp.'/html5/data');
    @rmdir($tmp.'/html5');
    @rmdir($tmp);
});

test('getSlideData returns decoded array and getSlideTitle returns title', function () {
    $tmp = sys_get_temp_dir().'/articulate-test-'.uniqid();
    mkdir($tmp, 0755, true);
    mkdir($tmp.'/html5/data/js', 0755, true);

    $slideId = 'AssessmentSlide';
    $json = ['title' => 'Assessment', 'slideLayers' => []];
    $rawJson = json_encode($json);
    $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $rawJson);
    $jsContent = "window.globalProvideData('slide', '".$escaped."');";
    file_put_contents($tmp.'/html5/data/js/'.$slideId.'.js', $jsContent);

    $data = $this->extractor->getSlideData($tmp, $slideId);
    expect($data)->toBeArray();
    expect($this->extractor->getSlideTitle($data ?? []))->toBe('Assessment');

    unlink($tmp.'/html5/data/js/'.$slideId.'.js');
    @rmdir($tmp.'/html5/data/js');
    @rmdir($tmp.'/html5/data');
    @rmdir($tmp.'/html5');
    @rmdir($tmp);
});

test('getSlideData ignores content after double quoted slide payload', function () {
    $tmp = sys_get_temp_dir().'/articulate-test-'.uniqid();
    mkdir($tmp, 0755, true);
    mkdir($tmp.'/html5/data/js', 0755, true);

    $slideId = 'DoubleQuotedSlide';
    $json = ['title' => 'Double quoted', 'slideLayers' => []];
    $rawJson = json_encode($json);
    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $rawJson);
    $jsContent = 'window.globalProvideData("slide", "'.$escaped.'"); console.log("later quoted content");';
    file_put_contents($tmp.'/html5/data/js/'.$slideId.'.js', $jsContent);

    $data = $this->extractor->getSlideData($tmp, $slideId);

    expect($data)->toBeArray();
    expect($this->extractor->getSlideTitle($data ?? []))->toBe('Double quoted');

    unlink($tmp.'/html5/data/js/'.$slideId.'.js');
    @rmdir($tmp.'/html5/data/js');
    @rmdir($tmp.'/html5/data');
    @rmdir($tmp.'/html5');
    @rmdir($tmp);
});

test('getSlideData ignores content after single quoted slide payload', function () {
    $tmp = sys_get_temp_dir().'/articulate-test-'.uniqid();
    mkdir($tmp, 0755, true);
    mkdir($tmp.'/html5/data/js', 0755, true);

    $slideId = 'SingleQuotedSlide';
    $json = ['title' => 'Single quoted', 'slideLayers' => []];
    $rawJson = json_encode($json);
    $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $rawJson);
    $jsContent = "window.globalProvideData('slide', '".$escaped."'); console.log('later quoted content');";
    file_put_contents($tmp.'/html5/data/js/'.$slideId.'.js', $jsContent);

    $data = $this->extractor->getSlideData($tmp, $slideId);

    expect($data)->toBeArray();
    expect($this->extractor->getSlideTitle($data ?? []))->toBe('Single quoted');

    unlink($tmp.'/html5/data/js/'.$slideId.'.js');
    @rmdir($tmp.'/html5/data/js');
    @rmdir($tmp.'/html5/data');
    @rmdir($tmp.'/html5');
    @rmdir($tmp);
});

test('extractFromSlideData builds html from slide data', function () {
    $data = [
        'slideLayers' => [
            [
                'objects' => [
                    [
                        'textLib' => [
                            [
                                'vartext' => [
                                    'blocks' => [
                                        ['spans' => [['text' => 'Intro text']], 'style' => ['listStyle' => ['listType' => 'none']]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $html = $this->extractor->extractFromSlideData($data);

    expect($html)->toContain('Intro text');
    expect($html)->toContain('<p>');
});
