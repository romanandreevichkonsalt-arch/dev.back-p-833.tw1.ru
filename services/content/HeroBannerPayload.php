<?php

namespace app\services\content;

class HeroBannerPayload
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        if (isset($data['label']) && !isset($data['collectionLabel'])) {
            $data['collectionLabel'] = $data['label'];
        }
        if (isset($data['brandTitle']) && !isset($data['collectionTitle'])) {
            $data['collectionTitle'] = $data['brandTitle'];
        }
        if (isset($data['title']) && !isset($data['collectionTitle'])) {
            $data['collectionTitle'] = $data['title'];
        }
        if (isset($data['subtitle']) && !isset($data['tagline'])) {
            $data['tagline'] = $data['subtitle'];
        }

        $imageDesktop = self::imageFromData($data, 'imageDesktop', 'image');
        $imageMobile = self::imageFromData($data, 'imageMobile', null);

        $result = array_filter([
            'collectionLabel' => trim((string)($data['collectionLabel'] ?? '')),
            'collectionTitle' => trim((string)($data['collectionTitle'] ?? '')),
            'tagline' => trim((string)($data['tagline'] ?? '')),
            'year' => trim((string)($data['year'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        if ($imageDesktop !== null) {
            $result['imageDesktop'] = $imageDesktop;
        }
        if ($imageMobile !== null) {
            $result['imageMobile'] = $imageMobile;
        }

        if (isset($result['collectionLabel'])) {
            $result['label'] = $result['collectionLabel'];
        }
        if (isset($result['collectionTitle'])) {
            $result['title'] = $result['collectionTitle'];
            $result['brandTitle'] = $result['collectionTitle'];
        }
        if (isset($result['tagline'])) {
            $result['subtitle'] = $result['tagline'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function fromPost(array $post): array
    {
        return self::normalize([
            'collectionLabel' => $post['collection_label'] ?? '',
            'collectionTitle' => $post['collection_title'] ?? '',
            'tagline' => $post['tagline'] ?? '',
            'year' => $post['year'] ?? '',
            'imageDesktop' => [
                'src' => $post['image_desktop_src'] ?? '',
                'alt' => $post['image_desktop_alt'] ?? '',
            ],
            'imageMobile' => [
                'src' => $post['image_mobile_src'] ?? '',
                'alt' => $post['image_mobile_alt'] ?? '',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function toForm(array $data): array
    {
        $data = self::normalize($data);
        $desktop = is_array($data['imageDesktop'] ?? null) ? $data['imageDesktop'] : [];
        $mobile = is_array($data['imageMobile'] ?? null) ? $data['imageMobile'] : [];

        return [
            'collection_label' => $data['collectionLabel'] ?? '',
            'collection_title' => $data['collectionTitle'] ?? '',
            'tagline' => $data['tagline'] ?? '',
            'year' => $data['year'] ?? '',
            'image_desktop_src' => $desktop['src'] ?? '',
            'image_desktop_alt' => $desktop['alt'] ?? '',
            'image_mobile_src' => $mobile['src'] ?? '',
            'image_mobile_alt' => $mobile['alt'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{src: string, alt: string}|null
     */
    private static function imageFromData(array $data, string $primaryKey, ?string $fallbackKey): ?array
    {
        $primary = $data[$primaryKey] ?? null;
        if (is_array($primary)) {
            $src = trim((string)($primary['src'] ?? ''));
            if ($src !== '') {
                return [
                    'src' => $src,
                    'alt' => trim((string)($primary['alt'] ?? '')),
                ];
            }
        }

        if ($fallbackKey !== null) {
            $fallback = $data[$fallbackKey] ?? null;
            if (is_array($fallback)) {
                $src = trim((string)($fallback['src'] ?? ''));
                if ($src !== '') {
                    return [
                        'src' => $src,
                        'alt' => trim((string)($fallback['alt'] ?? '')),
                    ];
                }
            }
        }

        return null;
    }
}
