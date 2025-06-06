<?php

namespace Tests\Feature;

use Google\ApiCore\PagedListResponse;
use Google\Photos\Library\V1\PhotosLibraryClient;
use Google\Photos\Library\V1\PhotosLibraryResourceFactory;
use Google\Photos\Types\Album;
use Mockery as m;
use Revolution\Google\Photos\PhotosClient;
use Tests\TestCase;

class AlbumsTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_list_albums()
    {
        $res = m::mock(PagedListResponse::class);

        $client = m::mock(PhotosLibraryClient::class);
        $client->shouldReceive('listAlbums')->once()->andReturn($res);

        $photos = new PhotosClient;

        $album = $photos->setService($client)->listAlbums();

        $this->assertSame($res, $album);
    }

    public function test_create_album()
    {
        $res = new Album;
        $res->setTitle('title');

        $client = m::mock(PhotosLibraryClient::class);
        $client->shouldReceive('createAlbum')->once()->andReturn($res);

        $photos = new PhotosClient;

        $album = $photos->setService($client)->createAlbum(PhotosLibraryResourceFactory::album('title'));

        $this->assertSame('title', $album->getTitle());
    }

    public function test_get_album()
    {
        $res = new Album;
        $res->setTitle('title');

        $client = m::mock(PhotosLibraryClient::class);
        $client->shouldReceive('getAlbum')->once()->andReturn($res);

        $photos = new PhotosClient;

        $album = $photos->setService($client)->getAlbum('1');

        $this->assertSame('title', $album->getTitle());
    }
}
