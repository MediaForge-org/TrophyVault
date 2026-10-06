<?php

/*
 | PHPUnit bootstrap: switches the test run to the restricted test role BEFORE the application boots.
 |
 | The containers carry the development credentials (DB_USERNAME/DB_PASSWORD). When DB_TEST_USERNAME is configured
 | (docker compose environment, see .env.example) the suite replaces them with the test role, which owns only
 | <DB>_test and has no CONNECT privilege on the development database. Even a completely wrong DB_DATABASE can then not
 | reach development data. Without DB_TEST_USERNAME (e.g. CI, where only a test database exists) nothing changes.
 | The database *name* is forced separately in phpunit.xml and verified in Tests\TestCase.
 */

require __DIR__.'/../vendor/autoload.php';

$read = static function (string $key): string {
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    $server = $_SERVER[$key] ?? '';

    return is_string($server) ? $server : '';
};
$testUser = $read('DB_TEST_USERNAME');
$testPassword = $read('DB_TEST_PASSWORD');

if ($testUser !== '') {
    if ($testPassword === '') {
        fwrite(STDERR, "DB_TEST_USERNAME is set but DB_TEST_PASSWORD is empty; refusing to run the test suite.\n");
        exit(1);
    }

    foreach (['DB_USERNAME' => $testUser, 'DB_PASSWORD' => $testPassword] as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
