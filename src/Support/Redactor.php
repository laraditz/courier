<?php

namespace Laraditz\Courier\Support;

class Redactor
{
    public static function redact(mixed $value, array $redactKeys): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                return ['_raw' => $value];
            }

            $value = $decoded;
        }

        if (! is_array($value)) {
            return $value;
        }

        return self::redactArray($value, array_map('strtolower', $redactKeys));
    }

    private static function redactArray(array $data, array $redactKeys): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $redactKeys, true)) {
                $data[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactArray($value, $redactKeys);
            }
        }

        return $data;
    }
}
