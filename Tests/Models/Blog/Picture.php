<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * @ORM\ParseObject(collection="blog_picture")
 */
class Picture
{
    use \Redking\ParseBundle\ObjectTrait;

    /**
     * @ORM\Field(type="string")
     */
    private $file;

    /**
     * @ORM\Field(type="geopoint")
     */
    private $location;

    /**
     * @ORM\Field(type="object")
     */
    private $exif;

    /**
     * @ORM\Field(type="file")
     */
    private $media;

    public function getId()
    {
        return $this->id;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function setLocation($location)
    {
        $this->location = $location;

        return $this;
    }

    public function setExif($exif)
    {
        $this->exif = $exif;

        return $this;
    }

    public function getExif()
    {
        return $this->exif;
    }

    public function setMedia($media)
    {
        $this->media = $media;

        return $this;
    }

    public function getMedia()
    {
        return $this->media;
    }
}
