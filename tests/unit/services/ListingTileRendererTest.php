<?php

namespace tests\unit\services;

use app\services\media\ListingFrameData;
use app\services\media\ListingTileConfig;
use app\services\media\ListingTileRenderer;
use Codeception\Test\Unit;

class ListingTileRendererTest extends Unit
{
    public function testRenderFileCreatesExpectedDimensions(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $sourcePath = sys_get_temp_dir() . '/listing-tile-src-' . uniqid('', true) . '.png';
        $targetPath = sys_get_temp_dir() . '/listing-tile-out-' . uniqid('', true) . '.webp';

        $source = imagecreatetruecolor(800, 600);
        $red = imagecolorallocate($source, 220, 40, 40);
        imagefilledrectangle($source, 0, 0, 800, 600, $red);
        imagepng($source, $sourcePath);
        imagedestroy($source);

        $config = new ListingTileConfig(width: 458, height: 347, backgroundHex: '#fcfbf2');
        $renderer = new ListingTileRenderer($config);
        $renderer->renderFile($sourcePath, ListingFrameData::defaults(), $targetPath);

        verify(is_file($targetPath))->true();
        $info = getimagesize($targetPath);
        verify($info)->notNull();
        verify($info[0])->equals(458);
        verify($info[1])->equals(347);

        @unlink($sourcePath);
        @unlink($targetPath);
    }
}
