<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

final class AwardCertificateLayoutApplier
{
    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    public function apply(array $layout, AwardCertificateBlueprint $blueprint): array
    {
        $elements = is_array($layout['elements'] ?? null) ? $layout['elements'] : [];

        foreach ($elements as $index => $element) {
            if (! is_array($element)) {
                continue;
            }

            $id = isset($element['id']) && is_string($element['id']) ? $element['id'] : null;

            if ($id === 'certifying_line') {
                $elements[$index]['text'] = $blueprint->certifyingLine;
                $elements[$index]['visible'] = $blueprint->certifyingLine !== '';
            }

            if ($id === 'completed_line') {
                $elements[$index]['text'] = $blueprint->completedLine;
                $elements[$index]['visible'] = $blueprint->completedLine !== '';
            }

            if ($id === 'description') {
                $elements[$index]['text'] = $blueprint->description;
                $elements[$index]['visible'] = $blueprint->description !== '';
            }

            if ($id === 'course_name') {
                $elements[$index]['visible'] = $blueprint->includeCourseName;
            }
        }

        $layout['elements'] = array_values($elements);

        return $layout;
    }
}
