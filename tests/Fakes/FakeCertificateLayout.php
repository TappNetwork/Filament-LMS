<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Fakes;

final class FakeCertificateLayout
{
    /**
     * @return array{width: int, height: int, signature_count: int, elements: list<array<string, mixed>>}
     */
    public static function default(?string $tokenSet = null): array
    {
        return [
            'width' => 1050,
            'height' => 774,
            'signature_count' => 2,
            'elements' => [
                [
                    'id' => 'recipient_name_display',
                    'type' => 'text',
                    'bind' => 'recipient_name',
                    'visible' => true,
                ],
                [
                    'id' => 'recipient_name',
                    'type' => 'text',
                    'bind' => 'recipient_name',
                    'visible' => true,
                ],
                [
                    'id' => 'certifying_line',
                    'type' => 'text',
                    'text' => 'This certifies that',
                    'visible' => true,
                ],
                [
                    'id' => 'completed_line',
                    'type' => 'text',
                    'text' => 'has successfully completed',
                    'visible' => true,
                ],
                [
                    'id' => 'course_name',
                    'type' => 'text',
                    'bind' => 'course_name',
                    'visible' => true,
                ],
                [
                    'id' => 'description',
                    'type' => 'text',
                    'text' => '',
                    'visible' => false,
                ],
            ],
        ];
    }
}
