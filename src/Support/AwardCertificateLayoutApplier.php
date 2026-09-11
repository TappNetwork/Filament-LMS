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
                $elements[$index]['visible'] = $blueprint->includeCourseName
                    && ($blueprint->header['title_bind'] ?? '') !== 'course_name';
            }

            if ($id === 'recipient_name_display') {
                $elements[$index]['visible'] = false;
            }
        }

        $elements = $this->placeElementsBelowHeader($elements, $blueprint->header);
        $elements = $this->separateStackedText($elements, $blueprint->header);

        if (! $blueprint->includeSignatures) {
            $layout['signature_count'] = 0;
            $elements = array_values(array_filter(
                $elements,
                function (mixed $element): bool {
                    if (! is_array($element)) {
                        return true;
                    }

                    $type = $element['type'] ?? null;
                    $id = $element['id'] ?? null;

                    return $type !== 'signature'
                        && ! (is_string($id) && str_starts_with($id, 'signature_'));
                },
            ));
        }

        $layout['elements'] = array_values($elements);

        if ($blueprint->border !== []) {
            $layout['border'] = $blueprint->border;
        }

        if ($blueprint->header !== []) {
            $layout['header'] = $blueprint->header;
        }

        return $layout;
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @param  array<string, mixed>  $header
     * @return list<array<string, mixed>>
     */
    private function placeElementsBelowHeader(array $elements, array $header): array
    {
        if (! ($header['enabled'] ?? false)) {
            return $elements;
        }

        $headerHeight = (int) ($header['height'] ?? 220);
        $overlappingYs = [];

        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            $id = isset($element['id']) && is_string($element['id']) ? $element['id'] : null;
            $y = (int) ($element['y'] ?? 0);

            if (
                ! ($element['visible'] ?? true)
                || $y >= $headerHeight
                || (is_string($id) && str_starts_with($id, 'logo_'))
            ) {
                continue;
            }

            $overlappingYs[] = $y;
        }

        $minOverlappingY = $overlappingYs === [] ? null : min($overlappingYs);
        $shift = $minOverlappingY === null
            ? 0
            : ($headerHeight + 16) - $minOverlappingY;

        foreach ($elements as $index => $element) {
            if (! is_array($element)) {
                continue;
            }

            $id = isset($element['id']) && is_string($element['id']) ? $element['id'] : null;
            $y = (int) ($element['y'] ?? 0);

            if (is_string($id) && str_starts_with($id, 'logo_') && $y < $headerHeight) {
                $elements[$index]['y'] = 560;

                continue;
            }

            if ($shift > 0 && $minOverlappingY !== null && $y >= $minOverlappingY) {
                $elements[$index]['y'] = $y + $shift;
            }
        }

        return $elements;
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @param  array<string, mixed>  $header
     * @return list<array<string, mixed>>
     */
    private function separateStackedText(array $elements, array $header): array
    {
        if (! ($header['enabled'] ?? false)) {
            return $elements;
        }

        $minGap = 28;
        $indexes = [];

        foreach ($elements as $index => $element) {
            if (! is_array($element) || ! ($element['visible'] ?? true)) {
                continue;
            }

            $id = isset($element['id']) && is_string($element['id']) ? $element['id'] : '';

            if (($element['type'] ?? 'text') === 'image' || str_starts_with($id, 'logo_')) {
                continue;
            }

            $indexes[] = $index;
        }

        usort($indexes, function (int $left, int $right) use ($elements): int {
            $leftY = (int) ($elements[$left]['y'] ?? 0);
            $rightY = (int) ($elements[$right]['y'] ?? 0);

            if ($leftY === $rightY) {
                return $left <=> $right;
            }

            return $leftY <=> $rightY;
        });

        $previousBottom = null;

        foreach ($indexes as $index) {
            $y = (int) ($elements[$index]['y'] ?? 0);
            $h = (int) ($elements[$index]['h'] ?? 30);

            if ($previousBottom !== null && $y < $previousBottom + $minGap) {
                $y = $previousBottom + $minGap;
                $elements[$index]['y'] = $y;
            }

            $previousBottom = $y + $h;
        }

        return $elements;
    }
}
