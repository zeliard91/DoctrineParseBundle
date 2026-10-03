<?php

namespace Redking\ParseBundle\Form;

use Parse\ParseFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Links a ParseFile built from a form upload to the UploadedFile it comes from, so that the
 * ParseFile validators can check the uploaded file before it is sent to Parse Server.
 *
 * Entries are held in a WeakMap: they vanish along with their ParseFile, nothing leaks from one
 * request (or one message, in a long-running worker) to the next.
 *
 * @internal
 */
final class UploadedParseFileRegistry
{
    private static ?\WeakMap $map = null;

    public static function attach(ParseFile $parseFile, UploadedFile $uploadedFile): void
    {
        self::$map ??= new \WeakMap();
        self::$map[$parseFile] = $uploadedFile;
    }

    public static function get(ParseFile $parseFile): ?UploadedFile
    {
        return self::$map[$parseFile] ?? null;
    }
}
