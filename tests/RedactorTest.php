<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Support\Redactor;

class RedactorTest extends TestCase
{
    public function test_redacts_matching_keys_case_insensitively(): void
    {
        $result = Redactor::redact(
            ['Secret' => 'x', 'client_secret' => 'y', 'other' => 'z'],
            ['secret']
        );

        $this->assertSame('[REDACTED]', $result['Secret']);
        $this->assertSame('y', $result['client_secret']);
        $this->assertSame('z', $result['other']);
    }

    public function test_redacts_recursively_through_nested_arrays(): void
    {
        $result = Redactor::redact(
            ['data' => ['token' => 'x', 'nested' => ['password' => 'y']]],
            ['token', 'password']
        );

        $this->assertSame('[REDACTED]', $result['data']['token']);
        $this->assertSame('[REDACTED]', $result['data']['nested']['password']);
    }

    public function test_wraps_non_array_non_json_input_as_raw(): void
    {
        $result = Redactor::redact('<xml>not json</xml>', ['secret']);

        $this->assertSame(['_raw' => '<xml>not json</xml>'], $result);
    }

    public function test_decodes_json_string_and_redacts_it(): void
    {
        $result = Redactor::redact('{"secret":"x","other":"z"}', ['secret']);

        $this->assertSame('[REDACTED]', $result['secret']);
        $this->assertSame('z', $result['other']);
    }

    public function test_null_input_returns_null(): void
    {
        $this->assertNull(Redactor::redact(null, ['secret']));
    }
}
