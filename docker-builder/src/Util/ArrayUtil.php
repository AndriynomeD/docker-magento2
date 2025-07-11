<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Util;

final class ArrayUtil
{
    /**
     * array_merge_recursive_distinct
     * Recursively merge arrays with distinct values (later values override earlier ones)
     *
     * @param array ...$arrays
     * @return array
     */
    public static function arrayMergeRecursiveDistinct(array ...$arrays): array
    {
        if (empty($arrays)) {
            return [];
        }

        $base = array_shift($arrays);

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                    $base[$key] = self::arrayMergeRecursiveDistinct($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }
}
