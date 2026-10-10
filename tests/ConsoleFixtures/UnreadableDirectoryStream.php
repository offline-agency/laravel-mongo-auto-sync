<?php

namespace Tests\ConsoleFixtures;

/**
 * Stream wrapper whose paths are directories that cannot be opened, like a directory without read
 * permission (chmod has no effect when the suite runs as root).
 */
class UnreadableDirectoryStream
{
    public const PROTOCOL = 'unreadable-dir';

    /** @var resource|null */
    public $context;

    public static function register(): void
    {
        if (! in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }
    }

    public static function unregister(): void
    {
        if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PROTOCOL);
        }
    }

    /**
     * @return array<string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        return ['mode' => 0040755];
    }

    public function dir_opendir(string $path, int $options): bool
    {
        return false;
    }
}
