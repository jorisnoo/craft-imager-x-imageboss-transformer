<?php

use craft\base\Fs;
use craft\elements\Asset;
use craft\models\Volume;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use spacecatninja\imagebosstransformer\ImageBossTransformer;
use spacecatninja\imagebosstransformer\models\ImageBossTransformedImageModel;
use spacecatninja\imagebosstransformer\transformers\ImageBoss;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\services\ImagerService;

abstract class CloudFilesystemFixture extends Fs
{
    public string $subfolder = '';
}

final class TransformerTest extends TestCase
{
    private Asset $asset;
    private Volume $volume;
    private CloudFilesystemFixture $fs;

    protected function setUp(): void
    {
        $plugin = (new ReflectionClass(ImageBossTransformer::class))->newInstanceWithoutConstructor();
        ImageBossTransformer::$plugin = $plugin;
        $plugin->setSettings([
            'baseUrl' => 'https://images.example.test/prefix/',
            'profiles' => [
                'main' => ['sourceName' => 'original'],
                'cloud' => ['sourceName' => 'media', 'useCloudSourcePath' => true, 'signToken' => 'test-signing-key'],
            ],
            'defaultProfile' => 'main',
            'volumeProfiles' => ['uploads' => 'cloud'],
        ]);
        // Use Imager's real defaults and getSetting() without booting a Craft site.
        ImagerService::$transformConfig = (new ReflectionClass(ConfigModel::class))->newInstanceWithoutConstructor();
        $this->fs = $this->createStub(CloudFilesystemFixture::class);
        $this->volume = $this->createStub(Volume::class);
        $this->volume->handle = 'uploads';
        $this->volume->method('getFs')->willReturn($this->fs);
        $this->asset = $this->createStub(Asset::class);
        $this->asset->method('getVolume')->willReturn($this->volume);
    }

    protected function tearDown(): void
    {
        ImagerService::$transformConfig = null;
    }

    private function transform(array $transform): ImageBossTransformedImageModel
    {
        return (new ImageBoss())->transform($this->asset, [$transform])[0];
    }

    public function testSignsExactlyTheEncodedRequestPathIncludingTheBasePrefix(): void
    {
        $this->fs->subfolder = '/cloud folder/';
        $this->volume->method('getSubpath')->willReturn('/médias/nested/');
        $this->asset->method('getPath')->willReturn('images/Eugène #1?100%.PNG');
        $image = $this->transform(['format' => 'JPG', 'jpegQuality' => 75, 'pngCompressionLevel' => 9]);
        $path = '/prefix/media/cdn/quality:75,format:jpg/cloud%20folder/m%C3%A9dias/nested/images/Eug%C3%A8ne%20%231%3F100%25.PNG';
        self::assertSame('https://images.example.test'.$path.'?bossToken='.hash_hmac('sha256', $path, 'test-signing-key'), $image->getUrl());
    }

    public function testProfilePrecedenceAndPassthroughEffect(): void
    {
        $this->asset->method('getPath')->willReturn('photo.jpg');
        $transform = ['effects' => ['blur' => true]];
        self::assertStringContainsString('/media/cdn/quality:80,blur:2/', $this->transform($transform)->url);
        self::assertStringContainsString('/original/cdn/', $this->transform($transform + ['transformerParams' => ['profile' => 'main']])->url);
        $this->volume->handle = 'unmapped';
        self::assertStringContainsString('/original/cdn/', $this->transform($transform)->url);
    }

    public static function emptyCloudFolders(): array
    {
        return [[''], ['/'], ['$UNSET_TRANSFORMER_TEST_SUBFOLDER']];
    }

    #[DataProvider('emptyCloudFolders')]
    public function testEmptyParsedFoldersDoNotIntroduceEmptySegments(string $folder): void
    {
        $this->fs->subfolder = $folder;
        $this->volume->method('getSubpath')->willReturn('/');
        $this->asset->method('getPath')->willReturn('folder/image.jpg');
        self::assertSame('/prefix/media/width/400/quality:80/folder/image.jpg', parse_url($this->transform(['width' => 400])->url, PHP_URL_PATH));
    }

    public static function qualities(): array
    {
        return [
            ['PNG', ['pngCompressionLevel' => 9], 10],
            ['PNG', ['format' => 'jpg', 'pngCompressionLevel' => 9], 80],
            ['jpg', ['format' => 'WEBP', 'webpQuality' => 72], 72],
            ['AVIF', ['avifQuality' => 44], 44],
            ['png', ['jpegQuality' => 60], 60], // normalized Imager `quality`
            ['webp', ['quality' => 61], 61],
        ];
    }

    #[DataProvider('qualities')]
    public function testOutputQuality(string $extension, array $transform, int $quality): void
    {
        $this->asset->method('getExtension')->willReturn($extension);
        self::assertStringContainsString('/cdn/quality:'.$quality, $this->transform($transform)->url);
    }

    public static function dimensions(): array
    {
        return [
            [null, null, [], [0, 0]],
            [100, null, [], [100, 0]],
            [null, null, ['width' => 400], [400, 0]],
            [100, 0, ['height' => 200], [0, 200]],
            [null, null, ['width' => 400, 'height' => 300, 'mode' => 'fit'], [0, 0]],
            [null, null, ['width' => 400, 'height' => 300], [400, 300]],
            [800, 600, ['width' => 400], [400, 300]],
            [800, 600, ['height' => 150], [200, 150]],
            [800, 600, ['width' => 200, 'height' => 200, 'mode' => 'fit'], [200, 150]],
            [100, 80, ['width' => 200, 'height' => 200, 'mode' => 'fit'], [100, 80]],
        ];
    }

    #[DataProvider('dimensions')]
    public function testReportsOnlyKnownDimensions(?int $width, ?int $height, array $transform, array $expected): void
    {
        $this->asset->method('getWidth')->willReturn($width);
        $this->asset->method('getHeight')->willReturn($height);
        $image = $this->transform($transform);
        self::assertSame($expected, [$image->getWidth(), $image->getHeight()]);
    }

    public static function crops(): array
    {
        return [
            [['position' => '33.33 66.67'], '/cover/80x60/fp-x:0.3333,fp-y:0.6667'],
            [['position' => '0'], '/cover/80x60/fp-x:0,fp-y:0.5'],
            [['position' => '130 -10'], '/cover/80x60/fp-x:1,fp-y:0'],
            [['mode' => 'letterbox', 'letterbox' => ['opacity' => 0.5]], '/cover:contain/80x60/fill-color:000000,quality:'],
            [['mode' => 'letterbox', 'letterbox' => ['color' => '']], '/cover:contain/80x60/quality:'],
        ];
    }

    #[DataProvider('crops')]
    public function testCropOptions(array $params, string $fragment): void
    {
        self::assertStringContainsString($fragment, $this->transform(['width' => 80, 'height' => 60] + $params)->url);
    }

    public static function unsupportedModes(): array
    {
        return [['stretch'], ['cropOnly']];
    }

    #[DataProvider('unsupportedModes')]
    public function testUnsupportedModesFailBeforeReturningAnIncorrectSize(string $mode): void
    {
        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('does not support resize mode');
        $this->transform(['width' => 400, 'height' => 300, 'mode' => $mode]);
    }

    public function testStringFallbackHasAnActionableError(): void
    {
        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('configure mockImage and fallbackImage as asset IDs');
        (new ImageBoss())->transform('example.jpg', [['width' => 100]]);
    }
}
